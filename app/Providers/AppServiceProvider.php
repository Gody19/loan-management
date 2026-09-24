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
    }
}
