<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
 
class StudentsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => [], 'students' => []];
 
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $row = collect($row)->mapWithKeys(function ($value, $key) {
                $normalizedKey = strtolower(str_replace(' ', '_', trim($key)));
                return [$normalizedKey => $value];
            })->toArray();  

            if ($index === 0) {
                logger($row);
            }   

            // Loại bỏ khoảng trắng 2 đầu và kiểm tra xem có dòng nào thực sự rỗng không
            $studentCode = trim($row['student_code'] ?? '');
            $name        = trim($row['name'] ?? '');
            $email       = trim($row['email'] ?? '');

            if (empty($studentCode) && empty($name) && empty($email)) {
                // Ignore completely empty rows not captured by SkipsEmptyRows
                continue;
            }

            if (empty($studentCode) || empty($name) || empty($email)) {
                $this->results['errors'][] = [
                    'row'    => $index + 2,
                    'email'  => $email ?: '?',
                    'reason' => 'student_code, name, and email fields are required.',
                ];
                continue;
            }

            $student = null;
            $user    = null;
            $skipped = false;

            try {
                DB::transaction(function () use ($row, &$student, &$user, &$skipped, $studentCode, $name, $email) {

                    // Skip nếu email đã tồn tại
                    if (User::where('email', $email)->exists()) {
                        $skipped = true;
                        return;
                    }

                    // Xử lý format dữ liệu từ file excel
                    $genderMap = [
                        'nam' => 'male',
                        'nữ'  => 'female',
                    ];
                    $rawGender = mb_strtolower(trim($row['gender'] ?? ''));
                    $dbGender  = $genderMap[$rawGender] ?? 'other';

                    $dob = null;
                    if (!empty($row['date_of_birth'])) {
                        if (is_numeric($row['date_of_birth'])) {
                            $dob = Date::excelToDateTimeObject($row['date_of_birth'])->format('Y-m-d');
                        } else {
                            $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['date_of_birth'])));
                        }
                    }

                    $user = User::create([
                        'name'     => $name,
                        'email'    => $email,
                        'password' => Hash::make($studentCode),
                        'role'     => 'student',
                    ]);

                    $student = Student::create([
                        'user_id'      => $user->id,
                        'student_code' => $studentCode,
                        'name'         => $row['name'],
                        'cohort_class' => $row['cohort_class'] ?? null,
                        'date_of_birth'=> $dob,
                        'gender'       => $dbGender,
                        'phone_number' => $row['phone_number'] ?? null,
                    ]);
                });

                if ($skipped) {
                    $this->results['skipped']++;
                    continue;
                }

                    $qrData = json_encode([
                        'type'         => 'student',
                        'student_code' => $student->student_code,
                        'name'         => $user->name,
                    ],JSON_UNESCAPED_UNICODE);

                    $qrSvgString = QrCode::format('svg')
                        ->size(300)
                        ->encoding('UTF-8')
                        ->errorCorrection('H')
                        ->generate($qrData);

                    $base64Svg = "data:image/svg+xml;base64," . base64_encode($qrSvgString);

                    $uploadedFileUrl = Cloudinary::uploadApi()->upload($base64Svg, [
                        'folder'    => 'qr/students',
                        'public_id' => 'student_' . $student->student_code,
                    ])['secure_url'];

                    $student->update(['qr_code_path' => $uploadedFileUrl]);

                    $this->results['students'][] = [
                        'student_code' => $student->student_code,
                        'name'         => $user->name,
                        'email'        => $user->email,
                        'qr_code_path' => $uploadedFileUrl,
                    ];

                $this->results['created']++;

            } catch (\Throwable $e) {
                $this->results['errors'][] = [
                    'row'    => $index + 2,
                    'email'  => $row['email'] ?? '?',
                    'reason' => $e->getMessage(),
                ];
            }
        }
    }
}
