<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/dashboard',[LoginController::class, 'create'])->name('dashboard');
Route::prefix('/auth')->group(function(){
    Route::get('/Register', [LoginController::class, 'register'])->name('register');
    Route::get('/login',[LoginController::class,'index'])->name('login');
    
});