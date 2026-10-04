<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (AuthenticationException $exception, Request $request) {
            $route = $request->route();
            $routeUri = is_object($route) ? $route->uri() : '';

            if (
                str_starts_with($routeUri, 'api/v1/')
                || str_starts_with($request->getPathInfo(), '/api/v1/')
            ) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $throwable): bool {
            return str_starts_with($request->getPathInfo(), '/api/v1/') || $request->expectsJson();
        });
    })->create();
