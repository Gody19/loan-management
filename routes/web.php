<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberDocumentController;
use App\Http\Controllers\MemberNextOfKinController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VicobaGroupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [LoginController::class, 'login']);

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Password Reset Routes
|--------------------------------------------------------------------------
*/
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
    ->name('password.request')
    ->middleware('guest');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
    ->name('password.email')
    ->middleware('guest');

Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
    ->name('password.reset')
    ->middleware('guest');

Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
    ->name('password.update')
    ->middleware('guest');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'suspended'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // User Management
    Route::resource('users', UserController::class);

    // Role Management
    Route::resource('roles', RoleController::class)->except(['show']);

    // Organization Management
    Route::resource('organizations', OrganizationController::class);
    Route::post('/organizations/{organization}/assign-user', [OrganizationController::class, 'assignUser'])
        ->name('organizations.assign-user');
    Route::post('/organizations/{organization}/remove-user', [OrganizationController::class, 'removeUser'])
        ->name('organizations.remove-user');
    Route::get('/organizations/{organization}/users', [OrganizationController::class, 'users'])
        ->name('organizations.users');

    // Branch Management
    Route::resource('branches', BranchController::class);
    Route::post('/branches/{branch}/assign-user', [BranchController::class, 'assignUser'])
        ->name('branches.assign-user');
    Route::post('/branches/{branch}/remove-user', [BranchController::class, 'removeUser'])
        ->name('branches.remove-user');
    Route::get('/branches/{branch}/users', [BranchController::class, 'users'])
        ->name('branches.users');

    // VICOBA Group Management
    Route::resource('vicoba-groups', VicobaGroupController::class);

    // Member Management
    Route::resource('members', MemberController::class);
    Route::post('/members/{member}/change-status', [MemberController::class, 'changeStatus'])
        ->name('members.change-status');
    Route::get('/members-branches', [MemberController::class, 'getBranches'])
        ->name('members.get-branches');
    Route::get('/members-groups', [MemberController::class, 'getGroups'])
        ->name('members.get-groups');
    Route::post('/members/{member}/next-of-kin', [MemberNextOfKinController::class, 'store'])
        ->name('members.next-of-kin.store');
    Route::put('/members/{member}/next-of-kin/{kin}', [MemberNextOfKinController::class, 'update'])
        ->name('members.next-of-kin.update');
    Route::delete('/members/{member}/next-of-kin/{kin}', [MemberNextOfKinController::class, 'destroy'])
        ->name('members.next-of-kin.destroy');
    Route::post('/members/{member}/documents', [MemberDocumentController::class, 'store'])
        ->name('members.documents.store');
    Route::get('/members/{member}/documents/{document}/download', [MemberDocumentController::class, 'download'])
        ->name('members.documents.download');
    Route::post('/members/{member}/documents/{document}/verify', [MemberDocumentController::class, 'verify'])
        ->name('members.documents.verify');
    Route::delete('/members/{member}/documents/{document}', [MemberDocumentController::class, 'destroy'])
        ->name('members.documents.destroy');

    // Permission Management
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->name('permissions.index');

    Route::post('/permissions/{role}', [PermissionController::class, 'update'])
        ->name('permissions.update');
});
