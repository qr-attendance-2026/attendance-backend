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
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => [], 'qr_pending' => 0];

    public function collection(Collection $rows): void
    {
        // ── Pre-load existing emails & student codes (1 query each instead of N) ──
        $existingEmails   = User::pluck('email')->flip();       // O(1) lookup
        $existingCodes    = Student::pluck('student_code')->flip();

        // ── Parse all valid rows first ──────────────────────────────────────────
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

            // Avoid duplicates within same file
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

            $toInsert[] = [
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

        // ── Single transaction for all rows ────────────────────────────────────
        $newStudents = [];

        DB::transaction(function () use ($toInsert, &$newStudents) {
            foreach ($toInsert as $data) {
                try {
                    $user = User::create([
                        'name'     => $data['name'],
                        'email'    => $data['email'],
                        'password' => Hash::make($data['student_code']),
                        'role'     => 'student',
                    ]);

                    $student = Student::create([
                        'user_id'       => $user->id,
                        'student_code'  => $data['student_code'],
                        'name'          => $data['name'],
                        'cohort_class'  => $data['cohort_class'],
                        'date_of_birth' => $data['date_of_birth'],
                        'gender'        => $data['gender'],
                        'phone_number'  => $data['phone_number'],
                    ]);

                    $newStudents[] = ['student' => $student, 'userName' => $user->name];
                } catch (\Throwable $e) {
                    // Log per-row errors without rolling back entire batch
                    $this->results['errors'][] = [
                        'email'  => $data['email'],
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        });

        // ── Dispatch QR jobs after transaction succeeds ─────────────────────────
        foreach ($newStudents as $entry) {
            GenerateStudentQrJob::dispatch(
                $entry['student']->id,
                $entry['student']->student_code,
                $entry['userName'],
            );

            $this->results['created']++;
            $this->results['qr_pending']++;
        }
    }
}
