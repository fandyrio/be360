<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



// Route::get('generate-admin', 'api\loginController@generateAccount');
Route::get('hello', function () {
    return response()->json(['msg' => 'Hello from API!']);
});