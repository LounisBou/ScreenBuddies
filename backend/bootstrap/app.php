<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Integrate Sentry for error reporting
        Integration::handles($exceptions);

        // Don't report validation errors to Sentry (they're user errors, not bugs)
        $exceptions->dontReport(ValidationException::class);

        // Don't report 404 errors to Sentry (they're expected behavior)
        $exceptions->dontReport(NotFoundHttpException::class);
    })->create();
