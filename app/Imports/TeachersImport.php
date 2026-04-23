<?php

namespace App\Imports;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class TeachersImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows, WithChunkReading
{
     public array $results = ['created' => 0, 'skipped' => 0, 'errors' => []];
    
    public function chunkSize(): int { return 200; }
    
    public function collection(Collection $rows): void
    {
         // Pre-load toàn bộ DB 1 lần, không query trong loop
        $existingEmails = User::pluck('email')->flip();
        $existingCodes  = Teacher::pluck('teacher_code')->flip();

        $toInsert = [];

        foreach ($rows as $index => $row) {
            $teacherCode = trim((string) ($row['teacher_code'] ?? ''));
            $name        = trim((string) ($row['name']         ?? ''));
            $email       = trim((string) ($row['email']        ?? ''));
            $department  = trim((string) ($row['department']   ?? ''));

            if (empty($teacherCode) || empty($name) || empty($email)) {
                $this->results['errors'][] = [
                    'row'    => $index + 2,
                    'email'  => $email ?: '?',
                    'reason' => 'teacher_code, name, email là bắt buộc.',
                ];
                continue;
            }

            // Bỏ WithValidation — tự validate để kiểm soát lỗi per-row
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->results['errors'][] = [
                    'row'    => $index + 2,
                    'email'  => $email,
                    'reason' => 'Email không hợp lệ.',
                ];
                continue;
            }

            if ($existingEmails->has($email) || $existingCodes->has($teacherCode)) {
                $this->results['skipped']++;
                continue;
            }

            // Đánh dấu trong-file duplicate ngay lập tức
            $existingEmails[$email]      = true;
            $existingCodes[$teacherCode] = true;

            $toInsert[] = [
                'teacher_code' => $teacherCode,
                'name'         => $name,
                'email'        => $email,
                'department'   => $department,
            ];
        }

        if (empty($toInsert)) return;

        $now = now();

        DB::transaction(function () use ($toInsert, $now) {
            // Bulk insert Users — 1 query thay vì N queries
            User::insert(array_map(fn($d) => [
                'name'       => $d['name'],
                'email'      => $d['email'],
                'password'   => Hash::make($d['teacher_code']),
                'role'       => 'teacher',
                'created_at' => $now,
                'updated_at' => $now,
            ], $toInsert));

            // Lấy lại IDs vừa tạo bằng 1 query
            $emails  = array_column($toInsert, 'email');
            $userMap = User::whereIn('email', $emails)->pluck('id', 'email');

            // Bulk insert Teachers — 1 query thay vì N queries
            Teacher::insert(array_map(fn($d) => [
                'user_id'      => $userMap[$d['email']],
                'teacher_code' => $d['teacher_code'],
                'department'   => $d['department'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $toInsert));
        });

        $this->results['created'] += count($toInsert);
    }

    public function rules(): array
    {
        return [
            '*.teacher_code' => ['required', 'string'],
            '*.name'         => ['required', 'string'],
            '*.email'        => ['required', 'email'],
            '*.department'   => ['required', 'string'],
        ];
    }


}
