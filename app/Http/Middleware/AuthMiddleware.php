<?php

namespace App\Http\Middleware;

use Closure;
use Domain\Auth\Exceptions\UnauthenticatedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            throw new UnauthenticatedException();
        }

        return $next($request);
    }
}
