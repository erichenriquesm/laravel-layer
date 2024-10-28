<?php

namespace App\Http\Controllers;

use Domain\User\Contracts\StoreUserContract;
use Domain\User\DTOs\StoreUserDTO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        protected readonly StoreUserContract $storeUserContract
    ){}

    public function register(Request $request)
    {
        Validator::make($request->input(), [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|confirmed',
        ]);

        return $this->storeUserContract->exec(new StoreUserDTO(
            $request->input('name'),
            $request->input('email'),
            $request->input('password'),
        ));
    }
}
