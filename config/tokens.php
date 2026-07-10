<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    |
    | Passport defaults every token to one year. These values are applied in
    | App\Providers\AuthServiceProvider and drive the access token, the refresh
    | token and the personal access token independently.
    |
    | A short access token limits the damage of a leak; the refresh token is
    | what keeps the user signed in without retyping the password.
    |
    */

    'access_token_minutes' => (int) env('AUTH_ACCESS_TOKEN_MINUTES', 15),

    'refresh_token_days' => (int) env('AUTH_REFRESH_TOKEN_DAYS', 14),

];
