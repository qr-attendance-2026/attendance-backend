<?php

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AttendanceController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/users', [UserController::class,'index']);
Route::get('/users/{id}', [UserController::class,'show']);
Route::post('/users', [UserController::class,'store']);
Route::put('/users/{id}', [UserController::class,'update']);
Route::delete('/users/{id}', [Controller::class,'destroy']);


Route::get('/',[AttendanceController::class,'index']);

Route::post('/attendance',[AttendanceController::class,'store']);

Route::get('/list',[AttendanceController::class,'list']);
