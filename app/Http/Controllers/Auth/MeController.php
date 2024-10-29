<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MeController extends Controller
{
    public function exec() : JsonResponse
    {
        return response()->json(Auth::user());
    }
}
