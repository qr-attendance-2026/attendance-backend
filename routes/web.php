<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/users', [UserController::class,'index']);
Route::get('/users/{id}', [UserController::class,'show']);
Route::post('/users', [UserController::class,'store']);

// Temporary route to migrate the database from Cloud Run
Route::get('/setup-db', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return 'Quá trình tạo bảng (Migrate) đã hoàn tất!';
});

Route::put('/users/{id}', [UserController::class,'update']);
Route::delete('/users/{id}', [UserController::class,'destroy']);
