<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

class ApiAuthenticate extends Authenticate
{
    /**
     * @param Request $request
     */
    public function handle($request, Closure $next, ...$guards)
    {
        try {
            return parent::handle($request, $next, 'sanctum');
        } catch (\Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }
    }

    /**
     * API authentication must never redirect to a web login route.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
