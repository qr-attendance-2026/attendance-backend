<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController
{
    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/profile
    // Own profile information (no QR — use GET /api/student/qr-code for that)
    // ────────────────────────────────────────────────────────────────────
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user();
        $student = $user->student;

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'student_code' => $student->student_code,
                'cohort_class' => $student->cohort_class,
                'date_of_birth'=> $student->date_of_birth,
                'gender'       => $student->gender,
                'phone_number' => $student->phone_number,
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // PUT /api/student/profile
    // Students can update: phone_number, cohort_class, and password.
    // Other fields (name, email, student_code, gender, dob) are managed
    // by the admin and are not editable by the student.
    // ────────────────────────────────────────────────────────────────────
    public function update(Request $request): JsonResponse
    {
        $user    = $request->user();
        $student = $user->student;

        $validated = $request->validate([
            'phone_number'        => ['nullable', 'string', 'max:15'],
            'cohort_class'        => ['nullable', 'string', 'max:50'],
            'current_password'    => ['required_with:password', 'string'],
            'password'            => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Update student table fields
        $student->update([
            'phone_number' => $validated['phone_number'] ?? $student->phone_number,
            'cohort_class' => $validated['cohort_class'] ?? $student->cohort_class,
        ]);

        // Update password only if provided and current password is correct
        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mật khẩu hiện tại không đúng.',
                ], 422);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công.',
            'data'    => [
                'phone_number' => $student->phone_number,
                'cohort_class' => $student->cohort_class,
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/qr-code
    // Returns the student's personal QR code (pre-generated SVG).
    // The QR was created by the admin during Excel import and saved to
    // storage/app/public/qr/students/{student_code}.svg
    // qr_code_path in the students table holds the relative storage path.
    // ────────────────────────────────────────────────────────────────────
    public function qrCode(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        if (!$student->qr_code_path) {
            return response()->json([
                'success' => false,
                'message' => 'Mã QR chưa được tạo. Vui lòng liên hệ quản trị viên.',
            ], 404);
        }

        // Build a publicly accessible URL for the SVG file.
        // The file is stored under storage/app/public/, symlinked to public/storage/
        $qrUrl = Storage::url($student->qr_code_path);

        return response()->json([
            'success' => true,
            'data'    => [
                'student_code' => $student->student_code,
                'qr_url'       => $qrUrl,            // e.g. /storage/qr/students/student_SV001.svg
                'qr_path'      => $student->qr_code_path, // relative path in storage
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/schedule
    // Own enrolled classes with upcoming sessions.
    // ────────────────────────────────────────────────────────────────────
    public function schedule(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        $classes = $student->courseClasses()->with([
            'subject',
            'teacher.user',
            'sessions' => fn($q) => $q
                ->where('date', '>=', now()->toDateString())
                ->orderBy('date'),
        ])->get();

        $schedule = $classes->map(fn($class) => [
            'class_code'   => $class->class_code,
            'subject_name' => $class->subject->subject_name,
            'teacher_name' => $class->teacher->user->name ?? null,
            'semester'     => $class->semester,
            'sessions'     => $class->sessions->map(fn($s) => [
                'session_id'   => $s->id,
                'date'         => $s->date,
                'check_number' => $s->check_number,
                'is_active'    => $s->isActive(),
            ]),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $schedule,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // GET /api/student/courses
    // List of enrolled course classes (lightweight, no sessions).
    // ────────────────────────────────────────────────────────────────────
    public function getCourses(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        $courses = $student->courseClasses()->with('subject')->get()
            ->map(fn($class) => [
                'id'           => $class->id,
                'class_code'   => $class->class_code,
                'subject_name' => $class->subject->subject_name,
                'semester'     => $class->semester,
                'academic_year'=> $class->academic_year,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $courses,
        ]);
    }
}