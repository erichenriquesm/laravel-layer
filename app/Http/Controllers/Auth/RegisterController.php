<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Domain\Shared\Helpers\APIResponse;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Auth\DTOs\RegisterUserDTO;
use Domain\Shared\DomainTypes\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function __construct(
        protected readonly RegisterUserContract $registerUserContract
    ){}

    public function exec(Request $request) : JsonResponse
    {
        try {
            $validation = Validator::make($request->input(), [
                'name'     => 'required|string',
                'email'    => 'required|unique:users,email',
                'password' => 'required|string|confirmed',
            ]);

            if($validation->fails()){
                return APIResponse::unprocessableEntity($validation->errors());
            }

            return APIResponse::success($this->registerUserContract->exec(new RegisterUserDTO(
                name: $request->input('name'),
                email: new Email($request->input('email')),
                password: $request->input('password'),
            )));
        }catch(\Exception $e) {
            Log::error(__CLASS__, [
                'message'       => $e->getMessage(),
                'trace'         => $e->getTrace()
            ]);

            return APIResponse::internalServerError([
                'error' => 'error to register user'
            ]);
        }
    }
}
