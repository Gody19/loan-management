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
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SavingsAccountController;
use App\Http\Controllers\SavingsProductController;
use App\Http\Controllers\SavingsTransactionController;
use App\Http\Controllers\ShareAccountController;
use App\Http\Controllers\ShareProductController;
use App\Http\Controllers\ShareTransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VicobaGroupController;
use App\Http\Controllers\WelfareAccountController;
use App\Http\Controllers\WelfareFundController;
use App\Http\Controllers\LoanEligibilityController;
use App\Http\Controllers\LoanPlanController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanApplicationGuarantorController;
use App\Http\Controllers\LoanApplicationCollateralController;
use App\Http\Controllers\LoanApprovalLevelController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanDisbursementController;
use App\Http\Controllers\WelfareTransactionController;
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
    Route::post('/organizations/{organization}/assign-admin', [OrganizationController::class, 'assignAdmin'])
        ->name('organizations.assign-admin');
    Route::delete('/organizations/{organization}/remove-admin/{user}', [OrganizationController::class, 'removeAdmin'])
        ->name('organizations.remove-admin');

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

    // Financial Management - Savings
    Route::resource('savings-products', SavingsProductController::class);
    Route::resource('savings-accounts', SavingsAccountController::class)->except(['edit', 'update', 'destroy']);
    Route::get('/savings-accounts/{savingsAccount}/deposit', [SavingsAccountController::class, 'deposit'])
        ->name('savings-accounts.deposit');
    Route::post('/savings-accounts/{savingsAccount}/deposit', [SavingsAccountController::class, 'doDeposit'])
        ->name('savings-accounts.do-deposit');
    Route::get('/savings-accounts/{savingsAccount}/withdraw', [SavingsAccountController::class, 'withdraw'])
        ->name('savings-accounts.withdraw');
    Route::post('/savings-accounts/{savingsAccount}/withdraw', [SavingsAccountController::class, 'doWithdraw'])
        ->name('savings-accounts.do-withdraw');
    Route::post('/savings-transactions/{transaction}/reverse', [SavingsAccountController::class, 'reverse'])
        ->name('savings-transactions.reverse');
    Route::resource('savings-transactions', SavingsTransactionController::class)->only(['index', 'show']);
    Route::get('/savings-transactions/{savingsTransaction}/receipt', [SavingsTransactionController::class, 'receipt'])
        ->name('savings-transactions.receipt');

    // Financial Management - Shares
    Route::resource('share-products', ShareProductController::class);
    Route::resource('share-accounts', ShareAccountController::class)->except(['edit', 'update', 'destroy']);
    Route::get('/share-accounts/{shareAccount}/purchase', [ShareAccountController::class, 'purchase'])
        ->name('share-accounts.purchase');
    Route::post('/share-accounts/{shareAccount}/purchase', [ShareAccountController::class, 'doPurchase'])
        ->name('share-accounts.do-purchase');
    Route::get('/share-accounts/{shareAccount}/redeem', [ShareAccountController::class, 'redeem'])
        ->name('share-accounts.redeem');
    Route::post('/share-accounts/{shareAccount}/redeem', [ShareAccountController::class, 'doRedeem'])
        ->name('share-accounts.do-redeem');
    Route::post('/share-transactions/{transaction}/reverse', [ShareAccountController::class, 'reverse'])
        ->name('share-transactions.reverse');
    Route::resource('share-transactions', ShareTransactionController::class)->only(['index', 'show']);
    Route::get('/share-transactions/{shareTransaction}/receipt', [ShareTransactionController::class, 'receipt'])
        ->name('share-transactions.receipt');

    // Financial Management - Welfare
    Route::resource('welfare-funds', WelfareFundController::class);
    Route::resource('welfare-accounts', WelfareAccountController::class)->except(['edit', 'update', 'destroy']);
    Route::get('/welfare-accounts/{welfareAccount}/contribute', [WelfareAccountController::class, 'contribute'])
        ->name('welfare-accounts.contribute');
    Route::post('/welfare-accounts/{welfareAccount}/contribute', [WelfareAccountController::class, 'doContribute'])
        ->name('welfare-accounts.do-contribute');
    Route::get('/welfare-accounts/{welfareAccount}/benefit', [WelfareAccountController::class, 'benefit'])
        ->name('welfare-accounts.benefit');
    Route::post('/welfare-accounts/{welfareAccount}/benefit', [WelfareAccountController::class, 'doBenefit'])
        ->name('welfare-accounts.do-benefit');
    Route::post('/welfare-transactions/{transaction}/reverse', [WelfareAccountController::class, 'reverse'])
        ->name('welfare-transactions.reverse');
    Route::resource('welfare-transactions', WelfareTransactionController::class)->only(['index', 'show']);
    Route::get('/welfare-transactions/{welfareTransaction}/receipt', [WelfareTransactionController::class, 'receipt'])
        ->name('welfare-transactions.receipt');

    // Financial Management - Payment Methods
    Route::resource('payment-methods', PaymentMethodController::class);

    // Financial Management - Loan Plans
    Route::resource('loan-plans', LoanPlanController::class);
    Route::post('/loan-plans/{loan_plan}/activate', [LoanPlanController::class, 'activate'])
        ->name('loan-plans.activate');
    Route::post('/loan-plans/{loan_plan}/deactivate', [LoanPlanController::class, 'deactivate'])
        ->name('loan-plans.deactivate');

    // Financial Management - Loan Eligibility
    Route::get('/loan-eligibility', [LoanEligibilityController::class, 'index'])
        ->name('loan-eligibility.index');
    Route::post('/loan-eligibility/check', [LoanEligibilityController::class, 'check'])
        ->name('loan-eligibility.check');

    // Financial Management - Loan Applications
    Route::resource('loan-applications', LoanApplicationController::class);
    Route::post('/loan-applications/{loanApplication}/submit', [LoanApplicationController::class, 'submit'])
        ->name('loan-applications.submit');
    Route::post('/loan-applications/{loanApplication}/cancel', [LoanApplicationController::class, 'cancel'])
        ->name('loan-applications.cancel');
    Route::post('/loan-applications/{loanApplication}/review', [LoanApplicationController::class, 'review'])
        ->name('loan-applications.review');
    Route::post('/loan-applications/{loanApplication}/approve', [LoanApplicationController::class, 'approve'])
        ->name('loan-applications.approve');
    Route::post('/loan-applications/{loanApplication}/reject', [LoanApplicationController::class, 'reject'])
        ->name('loan-applications.reject');

    // Loan Application - Guarantors
    Route::post('/loan-applications/{loanApplication}/guarantors', [LoanApplicationGuarantorController::class, 'store'])
        ->name('loan-applications.guarantors.store');
    Route::delete('/loan-applications/{loanApplication}/guarantors/{guarantor}', [LoanApplicationGuarantorController::class, 'destroy'])
        ->name('loan-applications.guarantors.destroy');
    Route::post('/loan-applications/guarantors/{guarantor}/respond', [LoanApplicationGuarantorController::class, 'respond'])
        ->name('loan-guarantors.respond');

    // Loan Application - Collateral
    Route::post('/loan-applications/{loanApplication}/collaterals', [LoanApplicationCollateralController::class, 'store'])
        ->name('loan-applications.collaterals.store');
    Route::delete('/loan-applications/{loanApplication}/collaterals/{collateral}', [LoanApplicationCollateralController::class, 'destroy'])
        ->name('loan-applications.collaterals.destroy');

    // Loan Approval Levels
    Route::resource('loan-approval-levels', LoanApprovalLevelController::class);

    // Financial Management - Loans
    Route::resource('loans', LoanController::class)->only(['index', 'show']);
    Route::post('/loans/from-application/{loanApplication}', [LoanController::class, 'createFromApplication'])
        ->name('loans.create-from-application');
    Route::post('/loans/{loan}/cancel', [LoanController::class, 'cancel'])
        ->name('loans.cancel');
    Route::get('/loans/{loan}/schedule', [LoanController::class, 'schedule'])
        ->name('loans.schedule');

    // Loan Disbursements
    Route::resource('loan-disbursements', LoanDisbursementController::class)->only(['index', 'store', 'show']);
    Route::post('/loan-disbursements/{disbursement}/confirm', [LoanDisbursementController::class, 'confirm'])
        ->name('loan-disbursements.confirm');
    Route::post('/loan-disbursements/{disbursement}/reject', [LoanDisbursementController::class, 'reject'])
        ->name('loan-disbursements.reject');

    // Member Financial Summary
    Route::get('/members/{member}/statement', [\App\Http\Controllers\MemberController::class, 'statement'])
        ->name('members.statement');

    // Permission Management
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->name('permissions.index');

    Route::post('/permissions/{role}', [PermissionController::class, 'update'])
        ->name('permissions.update');
});
