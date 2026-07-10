<?php

namespace App\Providers;

use Carbon\CarbonInterval;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

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
}
