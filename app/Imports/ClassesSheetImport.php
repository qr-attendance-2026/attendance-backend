<?php

namespace App\Imports;

use App\Models\CourseClass;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ClassesSheetImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public array $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

    public function collection(Collection $rows): void
    {
        // Pre-load all subjects and teachers once to avoid N+1 inside the loop
        $subjectCache = Subject::pluck('id', 'subject_code');
        $teacherCache = Teacher::pluck('id', 'teacher_code');

        // Parse và validate tất cả rows, chưa đụng DB
        $newSubjects    = [];  // subjects cần INSERT mới
        $courseClassKeys = []; // dùng để upsert CourseClass sau
        $scheduleRows   = [];  // dữ liệu Schedule sẽ bulk insert

        foreach ($rows as $index => $row) {
            try {
                $subjectCode = trim((string) $row['ma_mon']);
                $teacherCode = trim((string) $row['ma_gv']);
                $classCode   = trim((string) $row['nmh']);
                $semester    = trim((string) $row['hoc_ky']);
                $academicYear = trim((string) $row['nam_hoc']);

                // // ── 1. Resolve subject ──────────────────────────────────────
                // if (! $subjectCache->has($subjectCode)) {
                //     $subject = Subject::firstOrCreate(
                //         ['subject_code' => $subjectCode],
                //         ['subject_name' => trim((string) $row['ten_mon'])]
                //     );
                //     $subjectCache->put($subjectCode, $subject->id);
                // }

                // $subjectId = $subjectCache->get($subjectCode);

                // Resolve teacher (must already exist)
                if (! $teacherCache->has($teacherCode)) {
                    throw new \RuntimeException(
                        "Teacher not found: [{$teacherCode}]. Please import teachers first."
                    );
                }

                // $teacherId = $teacherCache->get($teacherCode);

                // Gom subject mới vào mảng, chưa INSERT
                if (! $subjectCache->has($subjectCode) && ! isset($newSubjects[$subjectCode])) {
                    $newSubjects[$subjectCode] = [
                        'subject_code' => $subjectCode,
                        'subject_name' => $subjectName,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
                // // ── 3. Upsert CourseClass ────────────────────────────────────
                // $courseClass = CourseClass::firstOrCreate(
                //     [
                //         'subject_id'    => $subjectId,
                //         'class_code'    => $classCode,
                //         'semester'      => $semester,
                //         'academic_year' => $academicYear,
                //     ],
                //     ['teacher_id' => $teacherId]
                // );

                // Gom CourseClass keys để upsert sau
                $ck = "{$subjectCode}|{$classCode}|{$semester}|{$academicYear}";
                if (! isset($courseClassKeys[$ck])) {
                    $courseClassKeys[$ck] = [
                        'subject_code'  => $subjectCode, // tạm lưu, đổi thành ID sau
                        'teacher_id'    => $teacherCache->get($teacherCode),
                        'class_code'    => $classCode,
                        'semester'      => $semester,
                        'academic_year' => $academicYear,
                    ];
                }

                // // Parse dates (same strategy as StudentsImport)
                // $startDate = $this->parseDate($row['ngay_bat_dau']);
                // $endDate   = $this->parseDate($row['ngay_ket_thuc']);

                // // Create schedule row
                // Schedule::create([
                //     'course_class_id' => $courseClass->id,
                //     'day_of_week'     => (int) $row['thu'],
                //     'start_period'    => (int) $row['tiet_bat_dau'],
                //     'end_period'      => (int) $row['tiet_ket_thuc'],
                //     'room'            => trim((string) $row['phong']),
                //     'start_date'      => $startDate,
                //     'end_date'        => $endDate,
                // ]);

                // Gom Schedule data vào mảng
                $scheduleRows[] = [
                    '_class_key'   => $ck, // key tạm để map sau
                    'day_of_week'  => (int) ($row['thu']           ?? 0),
                    'start_period' => (int) ($row['tiet_bat_dau']  ?? 0),
                    'end_period'   => (int) ($row['tiet_ket_thuc'] ?? 0),
                    'room'         => trim((string) ($row['phong'] ?? '')),
                    'start_date'   => $this->parseDate($row['ngay_bat_dau'] ?? null),
                    'end_date'     => $this->parseDate($row['ngay_ket_thuc'] ?? null),
                ];
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
         if (empty($scheduleRows)) return;

        DB::transaction(function () use ($newSubjects, $courseClassKeys, $scheduleRows, &$subjectCache) {

            // ── Bước 3: Bulk insert Subjects mới (nếu có) ───────────────────
            // ✅ Thay vì N lần firstOrCreate → 1 INSERT IGNORE
            if (! empty($newSubjects)) {
                Subject::insertOrIgnore(array_values($newSubjects));

                // Reload cache để có ID của subjects vừa tạo + subjects cũ
                $subjectCache = Subject::pluck('id', 'subject_code');
            }

            // ── Bước 4: Upsert CourseClasses ────────────────────────────────
            // Thay subject_code → subject_id rồi upsert 1 lần
            $courseClassData = array_map(function ($item) use ($subjectCache) {
                return [
                    'subject_id'    => $subjectCache->get($item['subject_code']),
                    'teacher_id'    => $item['teacher_id'],
                    'class_code'    => $item['class_code'],
                    'semester'      => $item['semester'],
                    'academic_year' => $item['academic_year'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }, $courseClassKeys);

            CourseClass::upsert(
                array_values($courseClassData),
                ['subject_id', 'class_code', 'semester', 'academic_year'], // unique keys
                ['teacher_id', 'updated_at']                                // update nếu đã tồn tại
            );

            // ── Bước 5: Lấy lại CourseClass IDs để gán cho Schedule ─────────
            // 1 query whereIn thay vì N lần CourseClass::where()->first()
            $courseClassMap = CourseClass::whereIn('subject_id', $subjectCache->values())
                ->get()
                ->keyBy(fn($cc) => 
                    $subjectCache->flip()->get($cc->subject_id)
                    . '|' . $cc->class_code
                    . '|' . $cc->semester
                    . '|' . $cc->academic_year
                );
            // $courseClassMap = ['CS03043|1|2|2026-2027' => CourseClass{id:5}, ...]

            // ── Bước 6: Bulk insert Schedules ───────────────────────────────
            //  INSERT cho tất cả schedules
            $toInsertSchedules = [];
            $now = now();

            foreach ($scheduleRows as $sr) {
                $cc = $courseClassMap->get($sr['_class_key']);
                if (! $cc) continue;

                $toInsertSchedules[] = [
                    'course_class_id' => $cc->id,
                    'day_of_week'     => $sr['day_of_week'],
                    'start_period'    => $sr['start_period'],
                    'end_period'      => $sr['end_period'],
                    'room'            => $sr['room'],
                    'start_date'      => $sr['start_date'],
                    'end_date'        => $sr['end_date'],
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            Schedule::insertOrIgnore($toInsertSchedules);
            $this->results['created'] += count($toInsertSchedules);
        });
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
