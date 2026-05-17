<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



// Route::get('generate-admin', 'api\loginController@generateAccount');
Route::get('hello', function () {
    return response()->json(['msg' => 'Hello from API!']);
});
Route::get('monitor', 'monitoringController@index');
Route::post("list-not-found", "monitoringController@listNotFound");
Route::post("list-double", "monitoringController@listDouble");
Route::post("fix-404", 'monitoringController@fixNotFound');