<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;
use App\Http\Controllers\SavedConnectionController;

Route::post('/connect', [LogController::class, 'connect']);
Route::match(['get', 'post'], '/logs', [LogController::class, 'getLogs']);
Route::match(['get', 'post'], '/log-content', [LogController::class, 'getLogContent']);
Route::get('/connections', [SavedConnectionController::class, 'index']);
Route::post('/connections', [SavedConnectionController::class, 'store']);
Route::delete('/connections/{id}', [SavedConnectionController::class, 'destroy']);
