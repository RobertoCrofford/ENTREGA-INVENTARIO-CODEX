<?php

use App\Http\Middleware\EnsurePasswordHasChanged;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordHasChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (\Throwable $exception, Request $request) {
            // Diagnóstico puntual para el entorno local: permite obtener la causa sin
            // exponer trazas durante el uso habitual de la aplicación.
            if (app()->environment('local') && $request->boolean('diagnostic')) {
                return null;
            }

            if ($request->expectsJson()
                || $exception instanceof \Illuminate\Auth\AuthenticationException
                || $exception instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $message = match ($status) {
                403 => 'No tienes permisos para realizar esta acción.',
                404 => 'No encontramos la página o el registro solicitado.',
                422 => $exception->getMessage() ?: 'Revisa los datos ingresados e inténtalo nuevamente.',
                default => 'Ocurrió un problema inesperado. Intenta nuevamente o vuelve al panel principal.',
            };

            return response()->view('errors.application', compact('status', 'message'), $status);
        });
    })->create();

