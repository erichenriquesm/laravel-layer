<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::group(['middleware' => 'auth'], function () {
    Route::get('me', [AuthController::class, 'me']);
});
