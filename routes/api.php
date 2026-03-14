<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/setup-db', function () {
    Artisan::call('migrate', ['--force' => true]);
    return 'Quá trình tạo bảng (Migrate) đã hoàn tất!';
});