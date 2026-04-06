<?php

namespace App\Http\Controllers\Teacher;


use Illuminate\Support\Facades\Auth;
use App\Models\CourseClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseClassController 
{
    
    public function index(Request $request): JsonResponse
    {
        $teacher = Auth::id();

        $classes = CourseClass::with('students.user')
            ->where('teacher_id', $teacher)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }

    
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id'    => ['required','integer','exists:subjects,id'],
            'class_code'    => ['required','string','unique:course_classes,class_code'],
            'semester'      => ['required','integer','min:1','max:3'],
            'academic_year' => ['required','string']
        ]);

        $teacher = (int) Auth::id();

        $class = CourseClass::create([
            'subject_id'    => $request->subject_id,
            'teacher_id'    => $teacher,
            'class_code'    => $request->class_code,
            'semester'      => $request->semester,
            'academic_year' => $request->academic_year
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo lớp thành công',
            'data'    => $class
        ], 201);
    }

   
    public function show(Request $request, int $id): JsonResponse
    {
        $teacher = Auth::id();

        $class = CourseClass::with('students.user')
            ->where('teacher_id', $teacher)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $class
        ]);
    }

    
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'class_code'    => ['string','unique:course_classes,class_code,'.$id],
            'semester'      => ['integer','min:1','max:3'],
            'academic_year' => ['string']
        ]);

        $teacher = Auth::id();

        $class = CourseClass::where('teacher_id', $teacher)
            ->findOrFail($id);

        $class->update($request->only([
            'class_code',
            'semester',
            'academic_year'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thành công',
            'data'    => $class
        ]);
    }

   
    public function destroy(Request $request, int $id): JsonResponse
    {
        $teacher = Auth::id();

        $class = CourseClass::where('teacher_id', $teacher)
            ->findOrFail($id);

        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá lớp thành công'
        ]);
    }

   
    public function enrollStudents(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'student_ids'   => ['required','array'],
            'student_ids.*' => ['integer','exists:students,id']
        ]);

        $teacher = Auth::id();

        $class = CourseClass::where('teacher_id', $teacher)
            ->findOrFail($id);

        $class->students()->syncWithoutDetaching($request->student_ids);

        return response()->json([
            'success' => true,
            'message' => 'Thêm sinh viên thành công'
        ]);
    }

    
    public function removeStudent(Request $request, int $id, int $studentId): JsonResponse
    {
        $teacher = Auth::id();

        $class = CourseClass::where('teacher_id', $teacher)
            ->findOrFail($id);

        $class->students()->detach($studentId);

        return response()->json([
            'success' => true,
            'message' => 'Đã xoá sinh viên khỏi lớp'
        ]);
    }
}