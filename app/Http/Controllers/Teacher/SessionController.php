<?php

namespace App\Http\Controllers\Teacher;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CourseClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;
// use SimpleSoftwareIO\QrCode\Facades\QrCode;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;


class SessionController
{

    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'course_class_id'  => ['required', 'integer', 'exists:course_classes,id'],
            'check_number'     => ['integer', 'min:1', 'max:10'],
            'duration_minutes' => ['integer', 'min:1', 'max:120'],
        ]);

        $teacher = $request->user()->teacher;

        // Verify this class belongs to the authenticated teacher
        $class = CourseClass::where('id', $request->course_class_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $checkNumber     = (int) $request->input('check_number', 1);
        $durationMinutes = (int) $request->input('duration_minutes', 15);


        $payload = json_encode([
            'session_token'   => Str::random(64),
            'course_class_id' => $class->id,
            'class_code'      => $class->class_code,
            'date'            => now()->toDateString(),
            'check_number'    => $checkNumber,
            'expires_at'      => now()->addMinutes($durationMinutes)->toDateTimeString(),
        ]);


        $session = AttendanceSession::updateOrCreate(
            [
                'course_class_id' => $class->id,
                'date'            => now()->toDateString(),
                'check_number'    => $checkNumber,
            ],
            [
                'qr_payload'    => $payload, // stored in DB — used for server-side validation
                'qr_expires_at' => now()->addMinutes($durationMinutes),
            ]
        );


        return response()->json([
            'success' => true,
            'message' => 'Phiên điểm danh đã mở.',
            'data'    => [
                'session_id'    => $session->id,
                'class_code'    => $class->class_code,
                'date'          => $session->date,
                'check_number'  => $session->check_number,
                'qr_expires_at' => $session->qr_expires_at,
                'qr_payload'    => $payload, // frontend uses this to draw the QR
            ],
        ], 201);
    }


    public function close(Request $request, int $id): JsonResponse
    {
        $session = AttendanceSession::findOrFail($id);


        $session->update(['qr_expires_at' => now()]);


        $enrolledStudentIds = $session->courseClass->students()->pluck('students.id');

        $checkedStudentIds = $session->records()->pluck('student_id');

        $absentIds = $enrolledStudentIds->diff($checkedStudentIds);

        $absentRecords = $absentIds->map(fn($sid) => [
            'session_id' => $session->id,
            'student_id' => $sid,
            'status'     => 'absent',
            'method'     => 'manual',
            'checked_at' => null,
            'is_makeup'  => false,
        ])->values()->all();

        AttendanceRecord::insertOrIgnore($absentRecords);

        $stats = [
            'total'   => $enrolledStudentIds->count(),
            'present' => $session->records()->where('status', 'present')->count(),
            'absent'  => $session->records()->where('status', 'absent')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Phiên điểm danh đã đóng.',
            'data'    => $stats,
        ], 200);
    }


    public function live(Request $request, int $id): JsonResponse
    {
        $session = AttendanceSession::with([
            'records.student.user',
            'courseClass.students.user',
        ])->findOrFail($id);

        $checked = $session->records->map(fn($r) => [
            'student_id'   => $r->student_id,
            'student_code' => $r->student->student_code,
            'name'         => $r->student->user->name,
            'status'       => $r->status,
            'method'       => $r->method,
            'checked_at'   => $r->checked_at,
        ])->keyBy('student_id');

        $all = $session->courseClass->students->map(fn($s) => [
            'student_id'   => $s->id,
            'student_code' => $s->student_code,
            'name'         => $s->user->name,
            'status'       => $checked->get($s->id)['status'] ?? 'pending',
            'checked_at'   => $checked->get($s->id)['checked_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id'    => $session->id,
                'is_active'     => $session->isActive(),
                'qr_expires_at' => $session->qr_expires_at,
                'students'      => $all,
            ],
        ], 200);
    }

}
