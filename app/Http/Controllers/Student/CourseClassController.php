<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CourseClassController 
{
    /**
     * Xem danh sách các lớp học đang tham gia và Thời khóa biểu (Hình 1 - Home)
     */
    public function index()
    {
        $student = Auth::user()->student;

        // Lấy các lớp học kèm theo môn học và các buổi học (sessions) sắp tới
        $classes = $student->courseClasses()->with(['subject', 'sessions' => function($query) {
            $query->where('date', '>=', now()->toDateString())
                  ->orderBy('date', 'asc');
        }])->get();

        return view('student.course_classes.index', compact('classes'));
    }

    /**
     * Xem chi tiết một lớp học và Lịch sử điểm danh của riêng lớp đó
     */
    public function show($id)
    {
        $student = Auth::user()->student;

        // Tìm lớp học cụ thể
        $courseClass = $student->courseClasses()
            ->with(['subject', 'teacher'])
            ->findOrFail($id);

        // Lấy lịch sử điểm danh của sinh viên TRONG LỚP NÀY
        $attendanceHistory = \App\Models\AttendanceRecord::where('student_id', $student->id)
            ->whereHas('session', function($query) use ($id) {
                $query->where('course_class_id', $id);
            })
            ->with('session')
            ->orderBy('checked_at', 'desc')
            ->get();

        return view('student.course_classes.show', compact('courseClass', 'attendanceHistory'));
    }
}