<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes for the VICOBA Finance Management System.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Authentication Routes (Phase 1)
|--------------------------------------------------------------------------
| Uncomment when authentication is implemented.
|
| Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
| Route::post('/login', [AuthController::class, 'login']);
| Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
| Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
| Route::post('/register', [AuthController::class, 'register']);
|
*/
