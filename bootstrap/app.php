<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ForceJsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force JSON response for all API routes
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        //Tắt bảo mật CSRF cho các đường dẫn API
        $middleware->validateCsrfTokens(except: [
            'users',   // Dành cho POST /users
            'users/*'  // Dành cho PUT và DELETE /users/{id}
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
