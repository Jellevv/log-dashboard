<?php

use Illuminate\Support\Facades\Route;
use App\Services\LogParser;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-parser', function () {
    $rawLogs = file(storage_path('logs/test.log'));

    $entries = LogParser::parseCraftLogs($rawLogs);

    return response()->json($entries);
});
