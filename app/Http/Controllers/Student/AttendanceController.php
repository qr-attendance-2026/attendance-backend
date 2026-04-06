<?php

namespace App\Http\Controllers\Student;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController
{
    // ────────────────────────────────────────────────────────────────────
    // POST /api/student/attendance/check-in
    // Body: { qr_payload: '<raw JSON string decoded from the room QR image>' }
    //
    // Validation chain:
    //  1. Decode qr_payload JSON
    //  2. Find matching session
    //  3. Check session is still active (not expired)
    //  4. Check student is enrolled in this course class
    //  5. Check not already checked in
    //  6. Insert record (status = 'present')
    // ────────────────────────────────────────────────────────────────────
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'qr_payload' => ['required', 'string'],
        ]);

        // ── Step 1: Decode QR payload ─────────────────────────────────
        $decoded = json_decode($request->qr_payload, true);

        if (!$decoded || !isset($decoded['course_class_id'], $decoded['date'], $decoded['check_number'])) {
            return response()->json([
                'success' => false,
                'message' => 'Mã QR không hợp lệ.',
            ], 422);
        }

        // ── Step 2: Find the session ──────────────────────────────────
        $session = AttendanceSession::where('course_class_id', $decoded['course_class_id'])
            ->where('date', $decoded['date'])
            ->where('check_number', $decoded['check_number'])
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy phiên điểm danh.',
            ], 404);
        }

        // ── Step 3: Check expiry ──────────────────────────────────────
        if (!$session->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Mã QR đã hết hạn. Vui lòng liên hệ giảng viên.',
            ], 410);
        }

        // ── Step 4: Check enrollment ──────────────────────────────────
        $student = $request->user()->student;

        $isEnrolled = $session->courseClass
            ->students()
            ->where('students.id', $student->id)
            ->exists();

        if (!$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có trong danh sách lớp học này.',
            ], 403);
        }

        // ── Step 5: Check duplicate ───────────────────────────────────
        $already = AttendanceRecord::where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->exists();

        if ($already) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã điểm danh cho buổi học này rồi.',
            ], 409);
        }

        // ── Step 6: Insert record ─────────────────────────────────────
        $record = AttendanceRecord::create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'status'     => 'present',
            'method'     => 'qr',
            'checked_at' => now(),
            'is_makeup'  => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Điểm danh thành công!',
            'data'    => [
                'status'     => $record->status,
                'checked_at' => $record->checked_at,
                'class_code' => $session->courseClass->class_code,
                'date'       => $session->date,
            ],
        ], 201);
    }

    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/attendance
    // Own attendance history. Optional filter: ?course_class_id=
    // ────────────────────────────────────────────────────────────────────
    public function history(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        $records = AttendanceRecord::with(['session.courseClass.subject'])
            ->where('student_id', $student->id)
            ->when($request->course_class_id, fn($q) =>
                $q->whereHas('session', fn($q2) =>
                    $q2->where('course_class_id', $request->course_class_id)
                )
            )
            ->orderByDesc('checked_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/attendance/summary
    // Attendance rate per subject: total sessions, present, absent, rate %.
    // ────────────────────────────────────────────────────────────────────
    public function summary(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        $classes = $student->courseClasses()->with([
            'subject',
            'sessions.records' => fn($q) => $q->where('student_id', $student->id),
        ])->get();

        $summary = $classes->map(function ($class) {
            $totalSessions = $class->sessions->count();
            $records       = $class->sessions->flatMap->records;

            return [
                'class_code'   => $class->class_code,
                'subject_name' => $class->subject->subject_name,
                'total'        => $totalSessions,
                'present'      => $records->where('status', 'present')->count(),
                'absent'       => $records->where('status', 'absent')->count(),
                'rate'         => $totalSessions > 0
                    ? round($records->where('status', 'present')->count() / $totalSessions * 100, 1)
                    : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $summary,
        ]);
    }
}
