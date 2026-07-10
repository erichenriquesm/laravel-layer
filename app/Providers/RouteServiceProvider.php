<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

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

        # Two limits: the IP one stops spraying many accounts from one source, the account one
        # stops credential stuffing against a single account from a rotating pool of IPs.
        RateLimiter::for('login', function (Request $request) {
            $limits = [Limit::perMinute(5)->by($request->ip())];

            $email = Str::lower(trim((string) $request->input('email')));

            # A missing email would make every malformed request share one bucket.
            if ($email !== '') {
                $limits[] = Limit::perMinute(10)->by('login-account:'.$email);
            }

            return $limits;
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('refresh', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
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
