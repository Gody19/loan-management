<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleAuthorizationFailure
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 403 && $request->expectsJson()) {
            return response()->json([
                'message' => 'Access Denied! You don\'t have permission for this action.',
            ], 403);
        }

        if ($response->getStatusCode() === 403) {
            return redirect()->route('dashboard')
                ->with('error', 'Access Denied! You don\'t have permission for this action.');
        }

        return $response;
    }
}
