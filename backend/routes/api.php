<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;

Route::post('/connect', [LogController::class, 'connect']);
Route::match(['get', 'post'], '/logs', [LogController::class, 'getLogs']);
Route::match(['get', 'post'], '/log-content', [LogController::class, 'getLogContent']);
