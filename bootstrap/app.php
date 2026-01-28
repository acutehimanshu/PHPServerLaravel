<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
         /**
         * Force JSON for API routes
         */
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        /**
         * 1. Authentication failure (auth middleware)
         */
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Authentication token is missing, expired, or invalid',
                ], 401);
            }
        });

        /**
         * 2. JWT-specific errors (optional but recommended)
         */
        $exceptions->render(function (TokenExpiredException $exception, Request $request) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Authentication token has expired',
            ], 401);
        });

        $exceptions->render(function (TokenInvalidException $exception, Request $request) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Authentication token is invalid',
            ], 401);
        });

        $exceptions->render(function (JWTException $exception, Request $request) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Authentication token not found',
            ], 401);
        });

        /**
         * 3. Fallback for all other exceptions
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {

                $statusCode = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : 500;

                $response = [
                    'status'  => 'error',
                    'message' => $statusCode === 500
                        ? 'Internal server error'
                        : $e->getMessage(),
                ];

                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'file'      => $e->getFile(),
                        'line'      => $e->getLine(),
                        'trace'     => array_slice($e->getTrace(), 0, 5),
                    ];
                }

                return response()->json($response, $statusCode);
            }
        });
    })->create();
