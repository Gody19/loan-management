<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberAssociated
{
    /**
     * Ensure the authenticated user has an associated Member record.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->member) {
            abort(403, 'You do not have an associated member profile.');
        }

        if ($user->member->membership_status->value !== 'active') {
            abort(403, 'Your membership is not active.');
        }

        return $next($request);
    }
}
