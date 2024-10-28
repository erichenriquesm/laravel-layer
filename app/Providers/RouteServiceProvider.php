<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Carrega dinamicamente todos os arquivos de rota na pasta 'routes/api'
            $this->loadRoutesFromDirectory(base_path('routes'), 'api');
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
