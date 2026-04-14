<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Test Import Flow
Route::get('/test-import', function () {
    return view('test-import');
});
Route::post('/test-import/upload', [\App\Http\Controllers\Admin\ImportController::class, 'students']);

// Test QR Scanner Flow (Bypasses auth for testing)
Route::get('/test-scan', function () {
    return view('test-scan');
});

Route::post('/test-scan/submit', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'session_id'   => ['required', 'integer', 'exists:attendance_sessions,id'],
        'student_code' => ['required', 'string'],
    ]);

    $session = \App\Models\AttendanceSession::findOrFail($request->session_id);
    $student = \App\Models\Student::where('student_code', $request->student_code)->first();

    if (!$student) {
        return response()->json(['success' => false, 'message' => 'Student not found.'], 404);
    }

    $enrolled = $session->courseClass->students()->where('students.id', $student->id)->exists();

    if (!$enrolled) {
        return response()->json(['success' => false, 'message' => 'Student not enrolled in this class.'], 403);
    }

    $record = \App\Models\AttendanceRecord::firstOrCreate(
        [
            'session_id' => $session->id,
            'student_id' => $student->id
        ],
        [
            'status' => 'present',
            'method' => 'qr',
            'checked_at' => now(),
            'is_makeup' => false,
        ]
    );

    return response()->json([
        'success' => true,
        'message' => $record->wasRecentlyCreated ? 'Scanned successfully.' : 'Already checked in.',
        'data' => $record
    ]);
});
