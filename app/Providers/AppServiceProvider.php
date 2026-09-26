<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ai.chat', function (Request $request) {
            return Limit::perMinute((int) config('ai.chat_rate_limit', 30))
                ->by($request->user()?->id ?: (string) $request->ip());
        });

        RateLimiter::for('ai.tool', function (Request $request) {
            return Limit::perMinute((int) config('ai.tool_rate_limit', 30))
                ->by($request->user()?->id ?: (string) $request->ip());
        });

        // Public landing-page FAQ chat: unauthenticated, so the bound is purely
        // per source IP and deliberately tighter than the authenticated limits.
        RateLimiter::for('ai.guest', function (Request $request) {
            return Limit::perMinute((int) config('ai.guest_rate_limit', 15))
                ->by((string) $request->ip());
        });

        // Public landing-page assistant (Phase 11.7.1): unauthenticated and
        // session-aware, so the bound is per (IP + the visitor's session-
        // anchored public conversation uuid). The uuid is generated server-side
        // and stored in the session — never supplied by the browser — so it is
        // the visitor's stable per-session identity and a shared LAN IP cannot
        // drain the budget of every visitor on it. Requests before any
        // conversation exists fall back to the source IP alone. On exhaustion
        // the controlled JSON message keeps the surface friendly and abuse cheap.
        RateLimiter::for('ai.public', function (Request $request) {
            $key = (string) $request->ip();

            if ($request->hasSession()) {
                $uuid = $request->session()->get('ai_public_conversation_uuid');

                if (is_string($uuid) && $uuid !== '') {
                    $key .= '|'.$uuid;
                }
            }

            return Limit::perMinute((int) config('ai.public_chat.rate_limit', 10))
                ->by($key)
                ->response(fn () => response()->json([
                    'message' => "You're sending messages too quickly. Please wait a moment and try again.",
                ], 429));
        });
    }
}
