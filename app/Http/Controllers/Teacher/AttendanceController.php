<?php

namespace App\Http\Controllers\Teacher;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseClass;
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
                'checked_at' => now(),
                'is_makeup' => false
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
                'cohort_class' => $student->cohort_class,
                'status' => $record->status,
                'checked_at' => $record->checked_at,
                'already_done' => !$wasJustCreated,
            ]
        ], 200);
    }


    public function override(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:present,absent'],
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

    public function report(Request $request, int $courseClassId): JsonResponse
    {
        $teacher = $request->user()->teacher;

        if (!$teacher) {
            return response()->json([
                'success' => false,
                'message' => 'User không phải teacher.'
            ], 403);
        }

        $courseClass = CourseClass::with([
            'sessions' => function ($query) {
                $query->orderBy('date')->orderBy('check_number');
            },
            'students.user',
            'students.attendanceRecords' => function ($query) use ($courseClassId) {
                $query->whereHas('session', function ($q) use ($courseClassId) {
                    $q->where('course_class_id', $courseClassId);
                });
            }
        ])->find($courseClassId);

        if (!$courseClass) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lớp học.'
            ], 404);
        }

        if ($courseClass->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xem báo cáo lớp này.'
            ], 403);
        }

        $sessionsData = $courseClass->sessions->map(function ($session) {
            return [
                'id' => $session->id,
                'date' => $session->date ? $session->date->toDateString() : null,
                'check_number' => $session->check_number,
            ];
        });

        $sessionIds = $courseClass->sessions->pluck('id')->toArray();

        $studentsData = $courseClass->students->map(function ($student) use ($sessionIds) {
            $attendanceData = [];
            $totalPresent = 0;
            $totalLate = 0;
            $totalAbsent = 0;

            foreach ($sessionIds as $sessionId) {
                $record = $student->attendanceRecords->firstWhere('session_id', $sessionId);
                $status = $record ? $record->status : 'absent';
                $attendanceData[(string)$sessionId] = $status;

                if ($status === 'present') {
                    $totalPresent++;
                } elseif ($status === 'late') {
                    $totalLate++;
                } else {
                    $totalAbsent++;
                }
            }

            return [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->user ? $student->user->name : null,
                'attendance' => $attendanceData,
                'summary' => [
                    'total_present' => $totalPresent,
                    'total_absent' => $totalAbsent,
                ]
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => $sessionsData,
                'students' => $studentsData,
            ]
        ], 200);
    }

}