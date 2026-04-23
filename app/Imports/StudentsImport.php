<?php

namespace App\Imports;

use App\Jobs\GenerateStudentQrJob;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithChunkReading
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => [], 'qr_pending' => 0];

    public function headingRow(): int
    {
        return 1; // row 1 là tiêu đề
    }

    public function chunkSize(): int
    {
        return 200; // xử lý 200 dòng mỗi lần, tránh out of memory, timeout
    }

    public function collection(Collection $rows): void
    {
        // Pre-load all existing data from database (1 query mỗi lần chunk)

        $existingEmails   = User::pluck('email')->flip();     
        // flip trả về: ['admin@test.com' => 0, 'dh52201279@student.edu.vn' => 1, ...]

        $existingCodes    = Student::pluck('student_code')->flip();
        // ['DH52201279' => 0, 'DH52201280' => 1, ...]
        
        $toInsert = [];

        foreach ($rows as $index => $row) {
            $studentCode = trim((string) ($row['student_code'] ?? ''));
            $name        = trim((string) ($row['name']         ?? ''));
            $email       = trim((string) ($row['email']        ?? ''));

            // Skip completely empty rows
            if (empty($studentCode) && empty($name) && empty($email)) {
                continue;
            }

            // Validate required fields
            if (empty($studentCode) || empty($name) || empty($email)) {
                $this->results['errors'][] = [
                    'row'    => $index + 2,
                    'email'  => $email ?: '?',
                    'reason' => 'student_code, name, and email fields are required.',
                ];
                continue;
            }

            // Skip duplicates (check in-memory, no extra queries)
            if ($existingEmails->has($email) || $existingCodes->has($studentCode)) {
                $this->results['skipped']++;
                continue;
            }

            // Tránh duplicate trong cùng 1 file (2 dòng cùng email)
            // nếu file có 2 dòng cùng email, dòng thứ 2 sẽ bị skip ngay tại đây mà không cần thêm DB query.
            $existingEmails[$email]     = true;
            $existingCodes[$studentCode] = true;

            // Parse date of birth
            $dob = null;
            if (!empty($row['date_of_birth'])) {
                if (is_numeric($row['date_of_birth'])) {
                    $dob = Date::excelToDateTimeObject($row['date_of_birth'])->format('Y-m-d');
                } else {
                    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['date_of_birth'])));
                }
            }

            $genderMap = ['nam' => 'male', 'nữ' => 'female'];
            $dbGender  = $genderMap[mb_strtolower(trim($row['gender'] ?? ''))] ?? 'other';

            $toInsert[] = [                                 //Gom lại → insert 1 lần (bulk)
                'student_code' => $studentCode,
                'name'         => $name,
                'email'        => $email,
                'cohort_class' => $row['cohort_class'] ?? null,
                'date_of_birth'=> $dob,
                'gender'       => $dbGender,
                'phone_number' => $row['phone_number'] ?? null,
            ];
        }

        if (empty($toInsert)) return;

       // Bulk insert Users (1 query)
        $now        = now();

        //mảng all users để insert
        $userRows   = array_map(fn($d) => [
            'name'       => $d['name'],
            'email'      => $d['email'],
            'password'   => Hash::make($d['student_code']),
            'role'       => 'student',
            'created_at' => $now, //phải setup thủ công
            'updated_at' => $now,
        ], $toInsert);

        DB::transaction(function () use ($toInsert, $userRows, $now) {

            // Insert tất cả users 1 lần (bulk)
            User::insert($userRows);

            // Lấy lại id của các user vừa tạo
            $emails   = array_column($toInsert, 'email');
            $userMap  = User::whereIn('email', $emails)->pluck('id', 'email'); //sql: select from where
            // trả về ['a@test.com' => 101, 'b@test.com' => 102, ...]  // ← Map email → user_id

            $studentRows = [];

            foreach ($toInsert as $d) {
                $userId = $userMap[$d['email']] ?? null; //gắn user_id ← từ user vừa tạo
                if (!$userId) continue;

                $studentRows[] = [
                    'user_id'       => $userId,
                    'student_code'  => $d['student_code'],
                    'cohort_class'  => $d['cohort_class'],
                    'date_of_birth' => $d['date_of_birth'],
                    'gender'        => $d['gender'],
                    'phone_number'  => $d['phone_number'],
                ];
            }

            // Insert tất cả students 1 lần (bulk)
            Student::insert($studentRows);
        });


        // ── Dispatch QR jobs after transaction succeeds ─────────────────────────
        $codes    = array_column($toInsert, 'student_code');
        $students = Student::whereIn('student_code', $codes)->get()->keyBy('student_code');

        foreach ($toInsert as $d) {
            //lấy student vừa tạo
            $student = $students[$d['student_code']] ?? null;
            if (!$student) continue;

            GenerateStudentQrJob::dispatch(    //không tạo QR ngay, đẩy vào queue, ghi 1 record vào bảng jobs
                $student->id,
                $student->student_code,
                $d['name'],
            );

            $this->results['created']++;
            $this->results['qr_pending']++;
        }
    }
}
