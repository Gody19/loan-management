<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberDocumentController;
use App\Http\Controllers\MemberNextOfKinController;
use App\Http\Controllers\MemberPortalController;
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
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\LoanRepaymentCollectionController;
use App\Http\Controllers\LoanCollectionController;
use App\Http\Controllers\WelfareTransactionController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AccountingConfigurationController;
use App\Http\Controllers\MemberLoanController;
use App\Http\Controllers\MemberGuarantorController;
use App\Http\Controllers\MemberRepaymentController;
use App\Http\Controllers\LandingPageController;
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
Route::get('/', [LandingPageController::class, 'index'])
    ->name('home');

Route::get('/register-organization', [LandingPageController::class, 'showRegistration'])
    ->name('register-organization');

Route::post('/register-organization', [LandingPageController::class, 'storeRegistration'])
    ->name('register-organization.store');

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

    // Member Portal
    Route::prefix('member')->name('member.')->middleware('member')->group(function () {
        Route::get('/dashboard', [MemberPortalController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('/profile', [MemberPortalController::class, 'profile'])
            ->name('profile');
        Route::get('/profile/edit', [MemberPortalController::class, 'editProfile'])
            ->name('profile.edit');
        Route::post('/profile/update', [MemberPortalController::class, 'updateProfile'])
            ->name('profile.update');
        Route::get('/savings', [MemberPortalController::class, 'savings'])
            ->name('savings');
        Route::post('/savings/deposit', [MemberPortalController::class, 'depositSavings'])
            ->name('savings.deposit');
        Route::post('/savings/withdraw', [MemberPortalController::class, 'withdrawSavings'])
            ->name('savings.withdraw');
        Route::get('/shares', [MemberPortalController::class, 'shares'])
            ->name('shares');
        Route::post('/shares/purchase', [MemberPortalController::class, 'purchaseShares'])
            ->name('shares.purchase');
        Route::post('/shares/redeem', [MemberPortalController::class, 'redeemShares'])
            ->name('shares.redeem');
        Route::get('/welfare', [MemberPortalController::class, 'welfare'])
            ->name('welfare');
        Route::post('/welfare/contribute', [MemberPortalController::class, 'contributeWelfare'])
            ->name('welfare.contribute');
        Route::post('/welfare/benefit-request', [MemberPortalController::class, 'requestBenefit'])
            ->name('welfare.benefit-request');
        Route::get('/loans', [MemberLoanController::class, 'index'])
            ->name('loans');
        Route::get('/loans/plans/{loanPlan}', [MemberLoanController::class, 'plan'])
            ->name('loans.plan');
        Route::get('/loans/eligibility/{loanPlan}', [MemberLoanController::class, 'eligibility'])
            ->name('loans.eligibility');
        Route::get('/loans/apply/{loanPlan}', [MemberLoanController::class, 'apply'])
            ->name('loans.apply');
        Route::post('/loans/apply/{loanPlan}', [MemberLoanController::class, 'store'])
            ->name('loans.store');
        Route::get('/loans/applications', [MemberLoanController::class, 'applications'])
            ->name('loans.applications');
        Route::get('/loans/applications/{loanApplication}', [MemberLoanController::class, 'application'])
            ->name('loans.application');
        Route::post('/loans/applications/{loanApplication}/cancel', [MemberLoanController::class, 'cancelApplication'])
            ->name('loans.cancel');
        Route::post('/loans/applications/{loanApplication}/submit', [MemberLoanController::class, 'submitApplication'])
            ->name('loans.submit');
        Route::post('/loans/applications/{loanApplication}/guarantors', [MemberLoanController::class, 'addGuarantor'])
            ->name('loans.add-guarantor');
        Route::delete('/loans/applications/{loanApplication}/guarantors/{guarantor}', [MemberLoanController::class, 'removeGuarantor'])
            ->name('loans.remove-guarantor');
        Route::put('/loans/applications/{loanApplication}/guarantors/{guarantor}', [MemberLoanController::class, 'updateGuarantor'])
            ->name('loans.update-guarantor');
        Route::post('/loans/applications/{loanApplication}/collaterals', [MemberLoanController::class, 'addCollateral'])
            ->name('loans.add-collateral');
        Route::delete('/loans/applications/{loanApplication}/collaterals/{collateral}', [MemberLoanController::class, 'removeCollateral'])
            ->name('loans.remove-collateral');

        // Guarantor Requests (member is the guarantor)
        Route::get('/guarantor-requests', [MemberGuarantorController::class, 'index'])
            ->name('guarantor.requests');
        Route::get('/guarantor-requests/{guarantor}', [MemberGuarantorController::class, 'show'])
            ->name('guarantor.request');
        Route::post('/guarantor-requests/{guarantor}/accept', [MemberGuarantorController::class, 'accept'])
            ->name('guarantor.accept');
        Route::post('/guarantor-requests/{guarantor}/reject', [MemberGuarantorController::class, 'reject'])
            ->name('guarantor.reject');
        Route::get('/guarantor-offer', [MemberGuarantorController::class, 'offerForm'])
            ->name('guarantor.offer');
        Route::post('/guarantor-offer', [MemberGuarantorController::class, 'storeOffer'])
            ->name('guarantor.store-offer');
        Route::get('/guarantor-search', [MemberGuarantorController::class, 'searchMembers'])
            ->name('guarantor.search');
        Route::get('/my-guarantees', [MemberGuarantorController::class, 'myGuarantees'])
            ->name('my-guarantees');

        // Loan Repayments
        Route::get('/loans/{loan}', [MemberRepaymentController::class, 'loanDetail'])
            ->name('loans.show');
        Route::get('/loans/{loan}/schedule', [MemberRepaymentController::class, 'repaymentSchedule'])
            ->name('loans.schedule');
        Route::get('/loans/{loan}/statement', [MemberRepaymentController::class, 'statement'])
            ->name('loans.statement');
        Route::get('/repay', [MemberRepaymentController::class, 'selectLoan'])
            ->name('repay');
        Route::get('/loans/{loan}/repay', [MemberRepaymentController::class, 'makePayment'])
            ->name('loans.repay');
        Route::post('/loans/{loan}/repay', [MemberRepaymentController::class, 'storeRepayment'])
            ->name('loans.repay.store');
        Route::get('/repayments', [MemberRepaymentController::class, 'repaymentHistory'])
            ->name('repayments');
        Route::get('/repayments/{repayment}', [MemberRepaymentController::class, 'showRepayment'])
            ->name('repayments.show');

        // Loan Disbursements (member view)
        Route::get('/disbursements', [MemberPortalController::class, 'disbursements'])
            ->name('disbursements');

        // Collections & Delinquency (member view - repayment schedule overview)
        Route::get('/collections', [MemberPortalController::class, 'collections'])
            ->name('collections');

        Route::get('/transactions', [MemberPortalController::class, 'transactions'])
            ->name('transactions');
        Route::get('/notifications', [MemberPortalController::class, 'notifications'])
            ->name('notifications');

        // Statements (unified member financial statement)
        Route::get('/statements', [MemberPortalController::class, 'statements'])
            ->name('statements');

        // Repayment Schedule (member's active loan schedule)
        Route::get('/repayment-schedule', [MemberRepaymentController::class, 'mySchedule'])
            ->name('repayment-schedule');

        // Settings
        Route::get('/settings', [MemberPortalController::class, 'settings'])
            ->name('settings');
        Route::post('/settings/password', [MemberPortalController::class, 'updatePassword'])
            ->name('settings.password');

        // Help & Support
        Route::get('/help', [MemberPortalController::class, 'help'])
            ->name('help');
    });

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
    Route::post('/loan-applications/guarantors/{guarantor}/approve', [LoanApplicationGuarantorController::class, 'approve'])
        ->name('loan-guarantors.approve');
    Route::post('/loan-applications/guarantors/{guarantor}/reject', [LoanApplicationGuarantorController::class, 'rejectGuarantor'])
        ->name('loan-guarantors.reject');
    Route::get('/guarantor-reviews', [LoanApplicationGuarantorController::class, 'reviewQueue'])
        ->name('guarantor-reviews.index');

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

    // Loan Repayments
    Route::get('/loans/{loan}/repayments', [LoanRepaymentController::class, 'index'])
        ->name('loan-repayments.index');
    Route::get('/loans/{loan}/repayments/create', [LoanRepaymentController::class, 'create'])
        ->name('loan-repayments.create');
    Route::post('/loans/{loan}/repayments', [LoanRepaymentController::class, 'store'])
        ->name('loan-repayments.store');
    Route::get('/loan-repayments/{loanRepayment}', [LoanRepaymentController::class, 'show'])
        ->name('loan-repayments.show');
    Route::post('/loan-repayments/{loanRepayment}/reverse', [LoanRepaymentController::class, 'reverse'])
        ->name('loan-repayments.reverse');

    // Loan Repayment Collection (on behalf of members)
    Route::get('/loan-repayments-collection', [LoanRepaymentCollectionController::class, 'index'])
        ->name('loan-repayments-collection.index');
    Route::get('/loan-repayments-collection/search-members', [LoanRepaymentCollectionController::class, 'searchMembers'])
        ->name('loan-repayments-collection.search-members');
    Route::get('/loan-repayments-collection/{member}/loans', [LoanRepaymentCollectionController::class, 'memberLoans'])
        ->name('loan-repayments-collection.member-loans');
    Route::get('/loan-repayments-collection/{loan}/repayment/create', [LoanRepaymentCollectionController::class, 'createRepayment'])
        ->name('loan-repayments-collection.create-repayment');
    Route::post('/loan-repayments-collection/{loan}/repayment', [LoanRepaymentCollectionController::class, 'storeRepayment'])
        ->name('loan-repayments-collection.store-repayment');

    // Loan Collections & Delinquency
    Route::get('/loan-collections', [LoanCollectionController::class, 'index'])
        ->name('loan-collections.index');
    Route::get('/loans/{loan}/statement', [LoanCollectionController::class, 'statement'])
        ->name('loans.statement');

    // Member Financial Summary
    Route::get('/members/{member}/statement', [\App\Http\Controllers\MemberController::class, 'statement'])
        ->name('members.statement');

    // Accounting
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::resource('accounts', ChartOfAccountController::class);
        Route::post('accounts/{id}/toggle', [ChartOfAccountController::class, 'toggle'])->name('accounts.toggle');
        Route::resource('periods', AccountingPeriodController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('periods/{id}/close', [AccountingPeriodController::class, 'close'])->name('periods.close');
        Route::resource('journals', JournalEntryController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('journals/{id}/post', [JournalEntryController::class, 'post'])->name('journals.post');
        Route::post('journals/{id}/reverse', [JournalEntryController::class, 'reverse'])->name('journals.reverse');
        Route::get('reports/general-ledger', [AccountingReportController::class, 'generalLedger'])->name('reports.general-ledger');
        Route::get('reports/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('reports/balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('reports/income-statement', [AccountingReportController::class, 'incomeStatement'])->name('reports.income-statement');
        Route::get('reports/cash-ledger', [AccountingReportController::class, 'cashLedger'])->name('reports.cash-ledger');
        Route::get('configuration', [AccountingConfigurationController::class, 'index'])->name('configuration.index');
        Route::put('configuration', [AccountingConfigurationController::class, 'update'])->name('configuration.update');
    });

    // Permission Management
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->name('permissions.index');

    Route::post('/permissions/{role}', [PermissionController::class, 'update'])
        ->name('permissions.update');
});
