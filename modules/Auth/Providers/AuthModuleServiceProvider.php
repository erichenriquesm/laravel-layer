<?php

declare(strict_types=1);

namespace Modules\Auth\Providers;

use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Modules\Auth\Actions\GetAuthenticatedUser;
use Modules\Auth\Actions\Login;
use Modules\Auth\Actions\Logout;
use Modules\Auth\Actions\RefreshToken;
use Modules\Auth\Actions\RegisterUser;
use Modules\Auth\Contracts\GetAuthenticatedUserContract;
use Modules\Auth\Contracts\LoginContract;
use Modules\Auth\Contracts\LogoutContract;
use Modules\Auth\Contracts\RefreshTokenContract;
use Modules\Auth\Contracts\RegisterUserContract;
use Modules\Auth\Events\UserRegistered;
use Modules\Auth\Http\Middleware\AuthMiddleware;
use Modules\Auth\Listeners\SendWelcomeNotification;

class AuthModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        GetAuthenticatedUserContract::class => GetAuthenticatedUser::class,
        LoginContract::class               => Login::class,
        LogoutContract::class              => Logout::class,
        RefreshTokenContract::class        => RefreshToken::class,
        RegisterUserContract::class        => RegisterUser::class,
    ];

    /**
     * The module owns its routes, middleware, rate limits, token policy and event wiring,
     * so changing any of them never touches app/.
     */
    public function boot(Router $router): void
    {
        Event::listen(UserRegistered::class, SendWelcomeNotification::class);

        $router->aliasMiddleware('auth', AuthMiddleware::class);

        $this->configurePassport();
        $this->configureRateLimiting();

        if (! $this->app->routesAreCached()) {
            Route::middleware('api')->group(__DIR__.'/../Routes/auth.php');
        }
    }

    private function configurePassport(): void
    {
        # Disabled by default since Passport 11. Without it /oauth/token answers
        # unsupported_grant_type and no refresh token can ever be issued.
        Passport::enablePasswordGrant();

        Passport::tokensExpireIn(
            CarbonInterval::minutes(config('tokens.access_token_minutes'))
        );

        Passport::refreshTokensExpireIn(
            CarbonInterval::days(config('tokens.refresh_token_days'))
        );
    }

    private function configureRateLimiting(): void
    {
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
}
