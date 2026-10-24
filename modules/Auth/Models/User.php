<?php

namespace Modules\Auth\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements AuthenticatableContract
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password'
    ];

    protected $hidden = [
        'password'
    ];

    # Laravel guesses factories only for App\Models\*; outside it the name must be explicit.
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
