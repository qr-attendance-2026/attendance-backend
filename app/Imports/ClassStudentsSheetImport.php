<?php

namespace App\Imports;

use App\Models\CourseClass;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClassStudentsSheetImport implements ToCollection, WithHeadingRow
{
    public array $results = ['enrolled' => 0, 'skipped' => 0, 'errors' => []];

    public function collection(Collection $rows): void
    {
        // Pre-load subjects once to avoid N+1 per row
        $subjectCache = Subject::pluck('id', 'subject_code');

        foreach ($rows as $index => $row) {
            try {
                $studentCode  = trim((string) $row['ma_sinh_vien']);
                $subjectCode  = trim((string) $row['ma_mon']);
                $classCode    = trim((string) $row['nmh']);
                $semester     = trim((string) $row['hoc_ky']);
                $academicYear = trim((string) $row['nam_hoc']);

                // ── 1. Resolve student ───────────────────────────────────────
                $student = Student::where('student_code', $studentCode)->first();

                if (! $student) {
                    throw new \RuntimeException(
                        "Student not found: [{$studentCode}]. Please import students first."
                    );
                }

                // ── 2. Resolve subject ────────────────────────────────────────
                if (! $subjectCache->has($subjectCode)) {
                    throw new \RuntimeException(
                        "Subject not found: [{$subjectCode}]. Please ensure Sheet 1 was processed first."
                    );
                }

                $subjectId = $subjectCache->get($subjectCode);

                // ── 3. Find the CourseClass ───────────────────────────────────
                $courseClass = CourseClass::where('subject_id', $subjectId)
                    ->where('class_code', $classCode)
                    ->where('semester', $semester)
                    ->where('academic_year', $academicYear)
                    ->first();

                if (! $courseClass) {
                    throw new \RuntimeException(
                        "CourseClass not found for Subject [{$subjectCode}], NMH [{$classCode}], "
                        . "Semester [{$semester}], Year [{$academicYear}]."
                    );
                }

                // ── 4. Enroll student (idempotent, no duplicates) ─────────────
                $courseClass->students()->syncWithoutDetaching([$student->id]);

                $this->results['enrolled']++;
            } catch (\Throwable $e) {
                $this->results['errors'][] = [
                    'sheet'        => 'Student List',
                    'row'          => $index + 2,
                    'ma_sinh_vien' => $row['ma_sinh_vien'] ?? '?',
                    'ma_mon'       => $row['ma_mon'] ?? '?',
                    'reason'       => $e->getMessage(),
                ];
            }
        }
    }
}
