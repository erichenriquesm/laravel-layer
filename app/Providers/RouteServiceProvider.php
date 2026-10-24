<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            $this->loadRoutesFromDirectory(base_path('routes'), 'api');
        });
    }

    private function configureRateLimiting(): void
    {
        # Applied to every request by the global middleware stack, including Passport's /oauth/*.
        # The default guard is passport, so user() resolves the bearer token before routing.
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        # Module-specific limiters (login, register, refresh…) live in each module's provider.
    }

    protected function loadRoutesFromDirectory($path, $middleware, $prefix = null)
    {
        if (is_dir($path)) {
            foreach (glob("$path/*.php") as $routeFile) {
                Route::middleware($middleware)
                    ->prefix($prefix)
                    ->group($routeFile);
            }
        }
    }
}
