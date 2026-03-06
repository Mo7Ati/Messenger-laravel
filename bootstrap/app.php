<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__ . '/../routes/channels.php',
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Instantiate the ExceptionHandler
        $exceptionHandler = new \App\Exceptions\ExceptionHandler();

        $exceptions->render(function (Throwable $e) use ($exceptionHandler) {

            Log::error('API Exception', [
                'message' => $e->getMessage(),
            ]);

            // Handle API or JSON-based responses
            if (request()->is('api/*'))
                return $exceptionHandler->handleApiException($e);
            // Handle Web-based (non-API) responses
        });
    })->create();
