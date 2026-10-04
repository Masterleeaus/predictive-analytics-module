<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        try {
            return app(Authenticate::class)->handle($request, $next, 'sanctum');
        } catch (\Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }
    }
}
