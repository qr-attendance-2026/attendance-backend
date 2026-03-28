<?php


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AttendanceController;


Route::get('/', function () {
    return view('welcome');
});

//User
Route::get('/users', [UserController::class,'index']);
Route::get('/users/{id}', [UserController::class,'show']);
Route::post('/users', [UserController::class,'store']);
Route::put('/users/{id}', [UserController::class,'update']);
Route::delete('/users/{id}', [Controller::class,'destroy']);

//Attendance
Route::get('/attendance',[AttendanceController::class,'index']);
Route::post('/attendance/scan',[AttendanceController::class,'store']);
Route::get('/list',[AttendanceController::class,'list']);


