<?php

namespace App\Imports;

use App\Models\CourseClass;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ClassesSheetImport implements ToCollection, WithHeadingRow
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

    public function collection(Collection $rows): void
    {
        // Pre-load all subjects and teachers once to avoid N+1 inside the loop
        $subjectCache = Subject::pluck('id', 'subject_code');
        $teacherCache = Teacher::pluck('id', 'teacher_code');

        foreach ($rows as $index => $row) {
            try {
                $subjectCode = trim((string) $row['ma_mon']);
                $teacherCode = trim((string) $row['ma_gv']);
                $classCode   = trim((string) $row['nmh']);
                $semester    = trim((string) $row['hoc_ky']);
                $academicYear = trim((string) $row['nam_hoc']);

                // ── 1. Resolve subject ──────────────────────────────────────
                if (! $subjectCache->has($subjectCode)) {
                    $subject = Subject::firstOrCreate(
                        ['subject_code' => $subjectCode],
                        ['subject_name' => trim((string) $row['ten_mon'])]
                    );
                    $subjectCache->put($subjectCode, $subject->id);
                }

                $subjectId = $subjectCache->get($subjectCode);

                // ── 2. Resolve teacher (must already exist) ─────────────────
                if (! $teacherCache->has($teacherCode)) {
                    throw new \RuntimeException(
                        "Teacher not found: [{$teacherCode}]. Please import teachers first."
                    );
                }

                $teacherId = $teacherCache->get($teacherCode);

                // ── 3. Upsert CourseClass ────────────────────────────────────
                $courseClass = CourseClass::firstOrCreate(
                    [
                        'subject_id'    => $subjectId,
                        'class_code'    => $classCode,
                        'semester'      => $semester,
                        'academic_year' => $academicYear,
                    ],
                    ['teacher_id' => $teacherId]
                );

                // ── 4. Parse dates (same strategy as StudentsImport) ─────────
                $startDate = $this->parseDate($row['ngay_bat_dau']);
                $endDate   = $this->parseDate($row['ngay_ket_thuc']);

                // ── 5. Create schedule row ────────────────────────────────────
                Schedule::create([
                    'course_class_id' => $courseClass->id,
                    'day_of_week'     => (int) $row['thu'],
                    'start_period'    => (int) $row['tiet_bat_dau'],
                    'end_period'      => (int) $row['tiet_ket_thuc'],
                    'room'            => trim((string) $row['phong']),
                    'start_date'      => $startDate,
                    'end_date'        => $endDate,
                ]);

                $this->results['created']++;
            } catch (\Throwable $e) {
                $this->results['errors'][] = [
                    'sheet'  => 'Teaching Schedule',
                    'row'    => $index + 2,
                    'ma_mon' => $row['ma_mon'] ?? '?',
                    'nmh'    => $row['nmh'] ?? '?',
                    'reason' => $e->getMessage(),
                ];
            }
        }
    }

    private function parseDate(mixed $value): string
    {
        if (empty($value)) {
            return now()->toDateString();
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return date('Y-m-d', strtotime(str_replace('/', '-', (string) $value)));
    }
}
