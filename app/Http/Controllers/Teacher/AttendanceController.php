<?php

namespace App\Http\Controllers\Teacher;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController 
{

    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'   => ['required', 'integer', 'exists:attendance_sessions,id'],
            'student_code' => ['required', 'string'],
        ]);

        $session = AttendanceSession::findOrFail($request->session_id);

        // Lấy teacher từ user
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'User không phải teacher.'
            ], 403);
        }

        // Kiểm tra quyền lớp
        if ($session->courseClass->teacher_id !== $teacher->id) {
            return response()->json([
                'success'=>false,
                'message'=>'Bạn không có quyền điểm danh lớp này.'
            ], 403);
        }

        // Tìm sinh viên
        $student = Student::where('student_code', $request->student_code)->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sinh viên: '.$request->student_code,
            ], 404);
        }

        // Kiểm tra sinh viên trong lớp
        $enrolled = $session->courseClass
            ->students()
            ->where('students.id', $student->id)
            ->exists();

        if (!$enrolled) {
            return response()->json([
                'success' => false,
                'message' => $student->user->name.' không trong danh sách lớp này.',
            ], 403);
        }

        // Tạo record điểm danh
        $record = AttendanceRecord::firstOrCreate(
            [
                'session_id' => $session->id,
                'student_id' => $student->id
            ],
            [
                'status' => 'present',
                'method' => 'qr',
                'checked_at' => now()
            ]
        );

        $wasJustCreated = $record->wasRecentlyCreated;

        return response()->json([
            'success' => true,
            'message' => $wasJustCreated
                ? 'Điểm danh thành công!'
                : 'Sinh viên đã được điểm danh trước đó.',
            'data' => [
                'student_code' => $student->student_code,
                'name' => $student->user->name,
                'status' => $record->status,
                'checked_at' => $record->checked_at,
                'already_done' => !$wasJustCreated,
            ]
        ], 200);
    }


    public function override(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:present,late,absent'],
        ]);

        $record = AttendanceRecord::findOrFail($id);

        $record->update([
            'status' => $request->status,
            'method' => 'manual',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật trạng thái điểm danh.',
            'data' => $record,
        ], 200);
    }

}