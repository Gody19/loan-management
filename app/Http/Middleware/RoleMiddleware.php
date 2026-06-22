<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect('/auth/login');
        }

        if (in_array(auth()->user()->role, $roles)) {
            return $next($request);
        }

        return response()->view('errors.403', [], 403);
    }
}
