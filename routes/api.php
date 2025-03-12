<?php

use Illuminate\Support\Facades\Route;
use App\Jobs\ProcessUserJob;
use Domain\Auth\Contracts\RegisterUserContract;
use Domain\Shared\Helpers\Queue;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('',  function () {
    $data = ['name' => 'John Doe', 'email' => 'john@example.com'];

    // dispatch(new ProcessUserJob($data))->onQueue('user');

    Queue::publish('email', RegisterUserContract::class, 'exec', $data);
    return 'OK';
});
