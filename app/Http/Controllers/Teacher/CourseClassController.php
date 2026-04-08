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
        $teacher = $request->user()->teacher;

        $classes = CourseClass::with(['students.user', 'subject'])
            ->where('teacher_id', $teacher->id)
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

        $teacher = $request->user()->teacher;

        $class = CourseClass::create([
            'subject_id'    => $request->subject_id,
            'teacher_id'    => $teacher->id,
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
        $teacher = $request->user()->teacher;

        $classDetail = CourseClass::with([
            'subject',        
            'students.user',        
            'sessions'
        ])
            ->where('teacher_id', $teacher->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $classDetail
        ]);
    }

    
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'class_code'    => ['string','unique:course_classes,class_code,'.$id],
            'semester'      => ['integer','min:1','max:3'],
            'academic_year' => ['string']
        ]);

        $teacher = $request->user()->teacher;

        $class = CourseClass::where('teacher_id', $teacher->id)
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
        $teacher = $request->user()->teacher;

        $class = CourseClass::where('teacher_id', $teacher->id)
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

        $teacher = $request->user()->teacher;

        $class = CourseClass::where('teacher_id', $teacher->id)
            ->findOrFail($id);

        $class->students()->syncWithoutDetaching($request->student_ids);

        return response()->json([
            'success' => true,
            'message' => 'Thêm sinh viên thành công'
        ]);
    }

    
    public function removeStudent(Request $request, int $id, int $studentId): JsonResponse
    {
        $teacher = $request->user()->teacher;

        $class = CourseClass::where('teacher_id', $teacher->id)
            ->findOrFail($id);

        $class->students()->detach($studentId);

        return response()->json([
            'success' => true,
            'message' => 'Đã xoá sinh viên khỏi lớp'
        ]);
    }
}