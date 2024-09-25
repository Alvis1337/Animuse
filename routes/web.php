<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MalOauth;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mal-oauth/step1', [MalOauth::class, 'step1']);

Route::get('/mal-oauth/callback', [MalOauth::class, 'handleOauthCallback']);