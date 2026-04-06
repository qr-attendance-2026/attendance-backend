<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class CourseClassController
{   
    public function index()
    {
        return CourseClass::with(['subject', 'teacher'])->get();
    }   

    public function show(int $id): JsonResponse
    {
        $class = CourseClass::with([
            'subject',
            'teacher',
            'students' => function ($q) {
                $q->with('user:id,name,email');
            },
            'schedules',
        ])->findOrFail($id);

        return response()->json(['data' => $class]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id'     => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id'     => ['required', 'integer', 'exists:teachers,id'],
            'class_code'     => ['required', 'string', 'max:50'],
            'semester'       => ['required', 'string', 'max:50'],
            'academic_year'  => ['required', 'string', 'max:50'],
            'schedules'      => ['required', 'array'],
            'schedules.*.day_of_week'   => ['required', 'integer', 'between:2,8'],
            'schedules.*.start_period'  => ['required', 'integer', 'min:1', 'max:15'],
            'schedules.*.end_period'    => ['required', 'integer', 'min:1', 'max:15'],
            'schedules.*.room'          => ['required', 'string', 'max:50'],
            'schedules.*.start_date'    => ['required', 'date'],
            'schedules.*.end_date'      => ['required', 'date'],
        ]);

        $class = CourseClass::create($validated);

        foreach ($validated['schedules'] as $s) {
            Schedule::create([
                'course_class_id' => $class->id,
                'day_of_week'   => $s['day_of_week'],
                'start_period'  => $s['start_period'],
                'end_period'    => $s['end_period'],
                'room'          => $s['room'],
                'start_date'    => $s['start_date'],
                'end_date'      => $s['end_date'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Course class created successfully',
            'data'    => $class,
        ], 201);
    }   

    public function update(Request $request, int $id): JsonResponse
    {
        $class = CourseClass::findOrFail($id);

        $validated = $request->validate([
            'subject_id'     => ['sometimes', 'integer', 'exists:subjects,id'],
            'teacher_id'     => ['sometimes', 'integer', 'exists:teachers,id'],
            'class_code'     => ['sometimes', 'string', 'max:50'],
            'semester'       => ['sometimes', 'string', 'max:50'],
            'academic_year'  => ['sometimes', 'string', 'max:50'],
            'schedules'      => ['sometimes', 'array'],
            'schedules.*.day_of_week'   => ['sometimes', 'integer', 'between:2,8'],
            'schedules.*.start_period'  => ['sometimes', 'integer', 'min:1', 'max:15'],
            'schedules.*.end_period'    => ['sometimes', 'integer', 'min:1', 'max:15'],
            'schedules.*.room'          => ['sometimes', 'string', 'max:50'],
            'schedules.*.start_date'    => ['sometimes', 'date'],
            'schedules.*.end_date'      => ['sometimes', 'date'],
        ]);

        $class->update($validated);

        if (isset($validated['schedules'])) {
            $class->schedules()->delete();
            foreach ($validated['schedules'] as $s) {
                Schedule::create([
                    'course_class_id' => $class->id,
                    'day_of_week'   => $s['day_of_week'],
                    'start_period'  => $s['start_period'],
                    'end_period'    => $s['end_period'],
                    'room'          => $s['room'],
                    'start_date'    => $s['start_date'],
                    'end_date'      => $s['end_date'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Course class updated successfully',
            'data'    => $class,
        ]);
    }       

    public function destroy(int $id): JsonResponse
    {
        $class = CourseClass::findOrFail($id);
        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course class deleted successfully',
        ]);
    }   

    // enroll() method — body: { student_ids: [1, 2, 3] }
    public function enroll(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'student_ids'   => ['required', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $class = CourseClass::findOrFail($id);
        $class->students()->syncWithoutDetaching($request->student_ids);

        return response()->json([
            'success' => true,
            'message' => count($request->student_ids) . ' students enrolled.',
        ]);
}
}
