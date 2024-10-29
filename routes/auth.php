<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;


Route::post('register', [RegisterController::class, 'exec']);
Route::post('login', [LoginController::class, 'exec']);

Route::group(['middleware' => 'auth'], function () {
    Route::get('me', [MeController::class, 'exec']);
});
