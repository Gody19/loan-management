<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blade::directive('tzs', function (string $expression) {
            return "<?php echo \App\Helpers\Currency::tzs($expression); ?>";
        });
    }
}
