<?php
 
namespace App\Http\Controllers\Student;
 
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
 
class AttendanceController extends Controller
{
    // ────────────────────────────────────────────────────────────────────
    // POST /api/student/attendance/check-in
    // Body: { qr_payload: '<the raw string decoded from the QR image>' }
    //
    // Validation chain:
    //  1. Decode qr_payload JSON
    //  2. Find matching session
    //  3. Check session is still active (not expired)
    //  4. Check student is enrolled in this course class
    //  5. Check not already checked in
    //  6. Determine status: present vs late
    //  7. Insert record
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
            ], 410);  // 410 Gone
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
            ], 409);  // 409 Conflict
        }
 
        // ── Step 6: Determine present vs late ─────────────────────────
        // Grace period: 15 minutes after class start.
        // 'start_time' is not in attendance_sessions, so use created_at as proxy,
        // OR add a start_time column to attendance_sessions for precise late logic.
        // Simple approach: compare now() to qr_expires_at - duration.
        // For now: always 'present' if within expiry. Extend later if needed.
        $status = 'present';
 
        // ── Step 7: Insert record ─────────────────────────────────────
        $record = AttendanceRecord::create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'status'     => $status,
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
    // Returns the student's full attendance history.
    // Query params: ?course_class_id= (optional filter)
    // ────────────────────────────────────────────────────────────────────
    public function history(Request $request): JsonResponse
    {
        $student = $request->user()->student;
 
        $records = AttendanceRecord::with([
            'session.courseClass.subject',
        ])
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
        ], 200);
    }
 
    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/attendance/summary
    // Attendance rate per subject: total sessions, present, late, absent.
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
                'class_code'    => $class->class_code,
                'subject_name'  => $class->subject->subject_name,
                'total'         => $totalSessions,
                'present'       => $records->where('status', 'present')->count(),
                'late'          => $records->where('status', 'late')->count(),
                'absent'        => $records->where('status', 'absent')->count(),
                'rate'          => $totalSessions > 0
                    ? round($records->whereIn('status', ['present','late'])->count() / $totalSessions * 100, 1)
                    : 0,
            ];
        });
 
        return response()->json([
            'success' => true,
            'data'    => $summary,
        ], 200);
    }
    public function index(Request $request)
    {
        // Lấy tất cả lịch sử điểm danh của sinh viên đang đăng nhập
        $attendances = \App\Models\Attendance::where('student_id', $request->user()->student->id)
            ->with('courseClass') // Load thêm thông tin tên môn học
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attendances
        ]);
    }

    public function testMyQr()
{
    // Giả sử MSSV của bạn là DH52201784
    $studentCode = "DH52201784"; 
    $name = "Nguyen Thao Vy";

    // Tạo nội dung QR (Dạng JSON để sau này máy quét dễ đọc)
    $content = json_encode([
        'mssv' => $studentCode,
        'name' => $name,
        'type' => 'student_id'
    ]);

    // Trả về trực tiếp hình ảnh QR để xem trên trình duyệt
    return response(
        QrCode::size(300)
            ->backgroundColor(255, 255, 255)
            ->color(0, 0, 0)
            ->margin(1)
            ->generate($content)
    )->header('Content-Type', 'image/svg+xml');
}
}
