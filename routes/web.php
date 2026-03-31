<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
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


Route::get('/', function () {
    return view('class');
});


//Attendance
Route::get('/attendance',[AttendanceController::class,'index']);
Route::post('/attendance/scan',[AttendanceController::class,'store']);
Route::get('/list',[AttendanceController::class,'list']);


