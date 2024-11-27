<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Domain\Shared\Helpers\APIResponse;
use Domain\Auth\Contracts\LoginContract;
use Domain\Auth\DTOs\LoginDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function __construct(
        protected readonly LoginContract $LoginContract
    ){}

    public function exec(Request $request) : JsonResponse
    {
        try {
            $validation = Validator::make($request->input(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if($validation->fails()){
                return APIResponse::unprocessableEntity($validation->errors());
            }

            return APIResponse::success($this->LoginContract->exec(new LoginDTO(
                email: $request->input('email'),
                password: $request->input('password'),
            )));
        }catch(\Exception $e) {
            $message = $e->getMessage();
            if($message === 'invalid_credentials') {
                return APIResponse::unauthorized(['Verify your credentials']);
            }

            Log::error(__CLASS__, [
                'message'       => $e->getMessage(),
                'trace'         => $e->getTrace()
            ]);

            return APIResponse::internalServerError([
                'error' => 'error to authenticate user'
            ]);
        }
    }
}
