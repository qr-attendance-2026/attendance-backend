<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class ReportController
{
    public function attendance(Request $request)
    {
        $request->validate([
            'course_class_id' => ['required', 'integer', 'exists:course_classes,id'],
            'date'            => ['nullable', 'date'],
        ]);

        $classId = $request->input('course_class_id');
        $date    = $request->input('date') ?? now()->toDateString();

        $class = CourseClass::with([
            'students.user',
            'sessions' => function ($q) use ($date) {
                $q->where('date', $date);
            },
        ])->findOrFail($classId);

        $sessions = $class->sessions->keyBy('check_number');

        $students = $class->students->map(function ($s) use ($sessions) {
            $records = $s->records()
                ->where('date', $date)
                ->get()
                ->keyBy('check_number');

            $checks = [];
            for ($i = 1; $i <= 10; $i++) {
                $rec = $records->get($i);
                $checks[$i] = [
                    'status'     => $rec['status'] ?? 'pending',
                    'method'     => $rec['method'] ?? null,
                    'checked_at' => $rec['checked_at'] ?? null,
                ];
            }

            return [
                'student_id'   => $s->id,
                'student_code' => $s->student_code,
                'name'         => $s->user->name,
                'checks'       => $checks,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'class_id' => $class->id,
                'class_name' => $class->class_name,
                'date' => $date,
                'students' => $students,
            ],
        ]);
    }
}
