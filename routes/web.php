<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\BudgetController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('/auth')->group(function(){
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login');
    Route::get('/Register', [LoginController::class, 'register'])->name('register');
    Route::post('/register', [LoginController::class, 'storeRegister'])->name('auth.register');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('/dashboard/user')->group(function () {
        Route::get('/', [UserDashboardController::class, 'index'])->name('dashboard.user');
        Route::resource('loans', LoanController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('portfolio', PortfolioController::class)->only(['index', 'create', 'store']);
        Route::resource('budget', BudgetController::class)->only(['index', 'create', 'store']);
    });

    Route::prefix('/dashboard/admin')->middleware('role:admin,manager,loan_officer')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard.admin');
        Route::get('/loans/pending', [LoanController::class, 'pending'])->name('loans.pending');
        Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
        Route::get('/users', [AdminDashboardController::class, 'users'])->name('admin.users');
    });
});
