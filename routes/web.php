<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MalOauth;

Route::get('/', function () {
    return view('welcome');
});

Route::post( '/mal-oauth/init', [MalOauth::class, 'init'])->name('mal.oauth.init');
Route::get('/mal-oauth/callback', [MalOauth::class, 'handleOauthCallback'])->name('mal.oauth.callback');
Route::get('/mal-oauth', [MalOauth::class, 'show'])->name('mal.oauth.show');