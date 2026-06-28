<?php

use App\Exceptions\TapPayException;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            // Exception domain TapPay dengan error code spesifik.
            if ($e instanceof TapPayException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => ['code' => $e->errorCode],
                ], $e->statusCode);
            }

            if ($e instanceof Illuminate\Validation\ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid atau belum login.',
                    'errors' => null,
                ], 401);
            }

            if ($e instanceof Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource tidak ditemukan.',
                    'errors' => null,
                ], 404);
            }

            return null;
        });
    })
    ->create();
