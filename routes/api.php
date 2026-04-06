<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\CourseClassController as AdminClassController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Teacher\SessionController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\Teacher\CourseClassController as TeacherClassController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes (no token required)
Route::post('/auth/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',     [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
});

// ── Admin ─────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Bulk import from Excel
    Route::post('import/students', [ImportController::class, 'students']);
    Route::post('import/teachers', [ImportController::class, 'teachers']);
    Route::post('import/schedule', [ImportController::class, 'schedule']);

    // User management
    Route::get('users',                        [UserController::class, 'index']); //all users
    Route::get('users/{id}',                   [UserController::class, 'show']); //single users
    Route::post('users',                       [UserController::class, 'store']); //create users
    Route::put('users/{id}',                   [UserController::class, 'update']); //update users
    Route::delete('users/{id}',                [UserController::class, 'destroy']); //delete users
    Route::patch('users/{id}/toggle-active',   [UserController::class, 'toggleActive']); //active/inactive users
    Route::post('users/{id}/reset-password',   [UserController::class, 'resetPassword']); //reset password users

    // Subjects
    Route::apiResource('subjects', SubjectController::class);

    // Course classes
    Route::apiResource('course-classes', AdminClassController::class);
    Route::post('course-classes/{id}/enroll', [AdminClassController::class, 'enroll']);

    // Reports
    Route::get('reports/attendance',                          [ReportController::class, 'attendance']);
    Route::get('reports/attendance/export',                   [ReportController::class, 'export']);
    Route::get('reports/students/{studentId}/history',        [ReportController::class, 'studentHistory']);
});

// ── Teacher ───────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:teacher'])->prefix('teacher')->group(function () {

    // Own class list
    Route::get('course-classes',       [TeacherClassController::class, 'index']);
    Route::post('course-classes',      [TeacherClassController::class, 'store']);
    Route::get('course-classes/{id}',  [TeacherClassController::class, 'show']);

    // QR Session management
    Route::post('sessions',            [SessionController::class, 'open']);
    Route::post('sessions/{id}/close', [SessionController::class, 'close']);
    Route::get('sessions/{id}/live',   [SessionController::class, 'live']);

    // Attendance — teacher scans student QR
    Route::post('attendance/scan',     [TeacherAttendanceController::class, 'scan']);
    Route::patch('attendance/{id}',    [TeacherAttendanceController::class, 'override']);

    // Reports
    Route::get('reports/{courseClassId}',        [TeacherAttendanceController::class, 'report']);
    Route::get('reports/{courseClassId}/export', [TeacherAttendanceController::class, 'export']);
});

// ── Student ───────────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum', 'role:student'])->prefix('student')->group(function () {

    //Profile
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile',    [ProfileController::class, 'update']);
    Route::get('qr-code',    [ProfileController::class, 'qrCode']);
 
    // Attendance - scan room QR
    Route::post('attendance/check-in', [StudentAttendanceController::class, 'checkIn']);
    Route::get('attendance/summary',   [StudentAttendanceController::class, 'summary']);
    Route::get('attendance',           [StudentAttendanceController::class, 'history']);

    // Schedule
    Route::get('schedule', [ProfileController::class, 'schedule']);
    Route::get('courses', [ProfileController::class, 'getCourses']);

});
