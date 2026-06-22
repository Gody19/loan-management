<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('/auth')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login');
    Route::get('/Register', [LoginController::class, 'register'])->name('register');
    Route::post('/register', [LoginController::class, 'storeRegister'])->name('auth.register');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::post('/payments/webhook/mongike', [PaymentController::class, 'webhook'])->name('payments.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('/profile')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('profile.update');
    });

    Route::post('/payments/{repayment}/pay', [PaymentController::class, 'pay'])->name('payments.pay');

    Route::prefix('/settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
        Route::patch('/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    });

    Route::prefix('/dashboard/user')->group(function () {
        Route::get('/', [UserDashboardController::class, 'index'])->name('dashboard.user');
        Route::resource('loans', LoanController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/my-history', [LoanController::class, 'userHistory'])->name('loans.user-history');
        Route::resource('portfolio', PortfolioController::class)->only(['index', 'create', 'store']);
        Route::resource('budget', BudgetController::class)->only(['index', 'create', 'store']);
    });

    Route::prefix('/dashboard/admin')->middleware('role:admin,manager,loan_officer')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard.admin');
        Route::get('/loans/pending', [LoanController::class, 'pending'])->name('loans.pending');
        Route::get('/loans/my-history', [LoanController::class, 'myHistory'])->name('loans.my-history');
        Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
        Route::get('/users', [AdminDashboardController::class, 'users'])->name('admin.users');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/all-processed', [AdminDashboardController::class, 'allProcessed'])->name('admin.all-processed');
    });
});
