<?php

namespace App\Http\Controllers;

use Domain\Auth\DTOs\LoginDTO;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Auth\Services\Login;
use Domain\Auth\Services\RegisterUser;
use Domain\Shared\DomainTypes\Email;
use Domain\Shared\Helpers\APIResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        protected readonly Login $loginService,
        protected readonly RegisterUser $registerUserService,
    ) {
    }
    public function register(Request $request): JsonResponse
    {
        try {
            $validation = Validator::make($request->input(), [
                'name'     => 'required|string',
                'email'    => 'required|unique:users,email',
                'password' => 'required|string|confirmed',
            ]);

            if ($validation->fails()) {
                return APIResponse::unprocessableEntity($validation->errors());
            }

            return APIResponse::success($this->registerUserService->exec(new RegisterUserDTO(
                name: $request->input('name'),
                email: new Email($request->input('email')),
                password: $request->input('password'),
            )));
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            if ($errorMessage === 'Invalid e-mail.') {
                return APIResponse::unprocessableEntity([
                    'email' => 'Email field is invalid',
                ]);
            }

            Log::error(__CLASS__, [
                'message' => $errorMessage,
                'trace'   => $e->getTrace(),
            ]);

            return APIResponse::badRequest([
                'error' => 'error to register user',
            ]);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validation = Validator::make($request->input(), [
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validation->fails()) {
                return APIResponse::unprocessableEntity($validation->errors());
            }

            return APIResponse::success($this->loginService->exec(new LoginDTO(
                email: $request->input('email'),
                password: $request->input('password'),
            )));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if ($message !== 'invalid_credentials') {
                Log::error(__CLASS__, [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTrace(),
                ]);
            }

            return APIResponse::unauthorized(['Verify your credentials']);
        }
    }

    public function me(): JsonResponse
    {
        return response()->json(Auth::user());
    }
}

