<?php

use App\Http\Controllers\AccountingConfigurationController;
use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AiEvaluationController;
use App\Http\Controllers\AiFeedbackController;
use App\Http\Controllers\AiFinancialIntelligenceController;
use App\Http\Controllers\AiIntelligenceReportController;
use App\Http\Controllers\AiKnowledgeDocumentController;
use App\Http\Controllers\AiLearningDatasetController;
use App\Http\Controllers\AiPublicChatController;
use App\Http\Controllers\AiReportScheduleController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LoanApplicationCollateralController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanApplicationGuarantorController;
use App\Http\Controllers\LoanApprovalLevelController;
use App\Http\Controllers\LoanCollectionController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanDisbursementController;
use App\Http\Controllers\LoanEligibilityController;
use App\Http\Controllers\LoanPlanController;
use App\Http\Controllers\LoanRepaymentCollectionController;
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\ManagementActionController;
use App\Http\Controllers\ManagementActionEffectivenessController;
use App\Http\Controllers\ManagementIntelligenceCenterController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberDocumentController;
use App\Http\Controllers\MemberGuarantorController;
use App\Http\Controllers\MemberLoanController;
use App\Http\Controllers\MemberNextOfKinController;
use App\Http\Controllers\MemberPortalController;
use App\Http\Controllers\MemberRepaymentController;
use App\Http\Controllers\NotificationController;
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
Route::get('/', [LandingPageController::class, 'index'])
    ->name('home');

Route::get('/register-organization', [LandingPageController::class, 'showRegistration'])
    ->name('register-organization');

Route::post('/register-organization', [LandingPageController::class, 'storeRegistration'])
    ->name('register-organization.store');

Route::post('/contact', [ContactMessageController::class, 'store'])
    ->name('contact.store');

/*
 * Public landing-page FAQ chat for unauthenticated visitors. Stateless and
 * mechanically throttled per source IP (see the ai.guest limiter); answers
 * come from the system instructions plus the guest-scope note, never from
 * stored conversations, member data or the knowledge base. Signed-in chat
 * continues to use the authenticated POST /ai/chat route.
 */
Route::post('/ai/chat/guest', [AiController::class, 'storeGuest'])
    ->name('ai.chat.guest')
    ->middleware('throttle:ai.guest');

/*
 * Public landing-page assistant (Phase 11.7.1): a session-anchored, anonymous
 * conversation surface. The visitor's conversation identity is a server-
 * generated uuid stored only in the visitor session — the browser never
 * supplies an id — so there is no enumeration or cross-visitor access surface.
 * It may only answer from public knowledge (visibility=public RAG documents);
 * nothing else is reachable. Throttled per (IP + session) via ai.public. The
 * stateless guest FAQ chat above remains for backwards compatibility.
 */
Route::post('/ai/public/chat', [AiPublicChatController::class, 'store'])
    ->name('ai.public.chat')
    ->middleware('throttle:ai.public');

Route::get('/ai/public/conversations/current', [AiPublicChatController::class, 'current'])
    ->name('ai.public.conversations.current')
    ->middleware('throttle:ai.public');

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

    // Contact Messages (admin)
    Route::middleware('permission:contact_message.view')->prefix('contact-messages')->name('contact-messages.')->group(function () {
        Route::get('/', [ContactMessageController::class, 'index'])
            ->name('index');
        Route::patch('/{message}/read', [ContactMessageController::class, 'markRead'])
            ->name('read');
        Route::delete('/{message}', [ContactMessageController::class, 'destroy'])
            ->name('destroy');
    });

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
        // Profile: Next of Kin CRUD (member manages own)
        Route::post('/profile/next-of-kin', [MemberPortalController::class, 'storeNextOfKin'])
            ->name('next-of-kin.store');
        Route::put('/profile/next-of-kin/{kin}', [MemberPortalController::class, 'updateNextOfKin'])
            ->name('next-of-kin.update');
        Route::delete('/profile/next-of-kin/{kin}', [MemberPortalController::class, 'destroyNextOfKin'])
            ->name('next-of-kin.destroy');
        // Profile: Documents CRUD (member manages own)
        Route::post('/profile/documents', [MemberPortalController::class, 'storeDocument'])
            ->name('documents.store');
        Route::get('/profile/documents/{document}/download', [MemberPortalController::class, 'downloadDocument'])
            ->name('documents.download');
        Route::delete('/profile/documents/{document}', [MemberPortalController::class, 'destroyDocument'])
            ->name('documents.destroy');
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
        Route::post('/loans/applications/{loanApplication}/collaterals/{collateral}/documents', [MemberLoanController::class, 'uploadCollateralDocument'])
            ->name('loans.collateral-document.upload');
        Route::delete('/collateral-documents/{document}', [MemberLoanController::class, 'removeCollateralDocument'])
            ->name('loans.collateral-document.destroy');
        Route::get('/collateral-documents/{document}/download', [MemberLoanController::class, 'downloadCollateralDocument'])
            ->name('loans.collateral-document.download');

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
    Route::post('/loan-applications/{loanApplication}/collaterals/{collateral}/verify', [LoanApplicationCollateralController::class, 'verify'])
        ->name('loan-applications.collaterals.verify');
    Route::post('/loan-applications/{loanApplication}/collaterals/{collateral}/reject', [LoanApplicationCollateralController::class, 'reject'])
        ->name('loan-applications.collaterals.reject');
    Route::post('/loan-applications/{loanApplication}/collaterals/{collateral}/documents', [LoanApplicationCollateralController::class, 'uploadDocument'])
        ->name('loan-applications.collaterals.documents.upload');
    Route::get('/collateral-documents/{document}/download', [LoanApplicationCollateralController::class, 'downloadDocument'])
        ->name('loan-applications.collaterals.documents.download');
    Route::delete('/collateral-documents/{document}', [LoanApplicationCollateralController::class, 'destroyDocument'])
        ->name('loan-applications.collaterals.documents.destroy');

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
    Route::get('/members/{member}/statement', [MemberController::class, 'statement'])
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

    // AI Assistant: chat UI page + minimal server-consumed JSON endpoints.
    // POST endpoints are throttled and authorization is enforced server-side.
    Route::prefix('ai')->name('ai.')->middleware('permission:ai.view')->group(function () {
        Route::get('/', [AiChatController::class, 'index'])
            ->name('index');
        Route::get('/conversations', [AiController::class, 'index'])
            ->name('conversations.index');
        Route::get('/conversations/{conversation}', [AiController::class, 'show'])
            ->name('conversations.show');
        Route::delete('/conversations/{conversation}', [AiController::class, 'destroy'])
            ->name('conversations.destroy');
    });

    Route::post('/ai/chat', [AiController::class, 'store'])
        ->name('ai.chat')
        ->middleware('permission:ai.use', 'throttle:ai.chat');

    Route::post('/ai/tool', [AiController::class, 'tool'])
        ->name('ai.tool')
        ->middleware('permission:ai.use', 'throttle:ai.tool');

    // AI Financial Intelligence (Phase 11.7) + Predictive Intelligence
    // (Phase 11.8) + Proactive Intelligence (Phase 11.9): read-only advisory
    // dashboards over the trusted AI context. The route accepts any of the
    // eight ai.*.view capabilities (OR), the controller renders only the
    // sections the acting user holds, and tenant/branch scope is always
    // derived server-side (never from the request). Reviewing an anomaly
    // finding, resolving/dismissing an insight are human review markers behind
    // their own capability only, throttled like the tool endpoints.
    Route::prefix('ai/intelligence')->name('ai.intelligence.')->group(function () {
        $intelligencePermissions = implode(',', [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
            'ai.trend.view', 'ai.accounting.view', 'ai.anomaly.view',
            'ai.predictive.view', 'ai.insights.view',
        ]);

        Route::get('/', [AiFinancialIntelligenceController::class, 'index'])
            ->middleware("permission:{$intelligencePermissions}")
            ->name('index');

        Route::post('/anomalies/{finding}/review', [AiFinancialIntelligenceController::class, 'review'])
            ->middleware('permission:ai.anomaly.view', 'throttle:ai.tool')
            ->name('anomalies.review');

        Route::post('/predictions/refresh', [AiFinancialIntelligenceController::class, 'refreshPredictions'])
            ->middleware('permission:ai.predictive.view', 'throttle:ai.tool')
            ->name('predictions.refresh');

        Route::post('/insights/{insight}/acknowledge', [AiFinancialIntelligenceController::class, 'acknowledgeInsight'])
            ->middleware('permission:ai.insights.view', 'throttle:ai.tool')
            ->name('insights.acknowledge');

        Route::post('/insights/{insight}/resolve', [AiFinancialIntelligenceController::class, 'resolveInsight'])
            ->middleware('permission:ai.insights.view', 'throttle:ai.tool')
            ->name('insights.resolve');

        Route::post('/insights/{insight}/dismiss', [AiFinancialIntelligenceController::class, 'dismissInsight'])
            ->middleware('permission:ai.insights.view', 'throttle:ai.tool')
            ->name('insights.dismiss');
    });

    // Management intelligence reporting (Phase 12.0). A single read-only
    // capability, ai.reports.view, gates the whole surface. Tenant and branch
    // scope are derived server-side from the trusted context (the browser never
    // supplies an organization), and the accounting report additionally
    // re-checks ai.accounting.view inside the reporting service. Generation is
    // throttled like the tool endpoints because it aggregates authoritative
    // records; reading, printing and exporting are gated by the same capability
    // and only ever return a completed report.
    Route::prefix('ai/reports')->name('ai.reports.')->group(function () {
        Route::get('/', [AiIntelligenceReportController::class, 'index'])
            ->middleware('permission:ai.reports.view')
            ->name('index');

        Route::post('/', [AiIntelligenceReportController::class, 'store'])
            ->middleware('permission:ai.reports.view', 'throttle:ai.tool')
            ->name('store');

        // Recurring management reports (Phase 12.1). Gated by the separate
        // ai.reports.schedule capability: scheduling automatic distribution is a
        // management configuration act, not a reporting read. The service
        // re-checks tenant scope and capability on every action, so a foreign
        // schedule id is a 404/403 rather than a disclosure, and a manual run
        // shares the scheduler's idempotent execution path.
        Route::prefix('schedules')->name('schedules.')->group(function () {
            Route::post('/', [AiReportScheduleController::class, 'store'])
                ->middleware('permission:ai.reports.schedule', 'throttle:ai.tool')
                ->name('store');

            Route::put('/{schedule}', [AiReportScheduleController::class, 'update'])
                ->middleware('permission:ai.reports.schedule', 'throttle:ai.tool')
                ->whereNumber('schedule')
                ->name('update');

            Route::post('/{schedule}/toggle', [AiReportScheduleController::class, 'toggle'])
                ->middleware('permission:ai.reports.schedule', 'throttle:ai.tool')
                ->whereNumber('schedule')
                ->name('toggle');

            Route::post('/{schedule}/run', [AiReportScheduleController::class, 'runNow'])
                ->middleware('permission:ai.reports.schedule', 'throttle:ai.tool')
                ->whereNumber('schedule')
                ->name('run');

            Route::delete('/{schedule}', [AiReportScheduleController::class, 'destroy'])
                ->middleware('permission:ai.reports.schedule', 'throttle:ai.tool')
                ->whereNumber('schedule')
                ->name('destroy');
        });

        Route::get('/{report}', [AiIntelligenceReportController::class, 'show'])
            ->middleware('permission:ai.reports.view')
            ->whereNumber('report')
            ->name('show');

        Route::get('/{report}/print', [AiIntelligenceReportController::class, 'print'])
            ->middleware('permission:ai.reports.view')
            ->whereNumber('report')
            ->name('print');

        Route::get('/{report}/export', [AiIntelligenceReportController::class, 'export'])
            ->middleware('permission:ai.reports.view')
            ->whereNumber('report')
            ->name('export');
    });

    // Unified Management Intelligence Center (Phase 12.2). A single read-only
    // executive workspace that aggregates the existing Phase 11.7 descriptive
    // intelligence, Phase 11.8 predictions, Phase 11.9 insights, Phase 12.0
    // reports and Phase 12.1 schedules. The route accepts any of the ten
    // intelligence/reporting capabilities (OR); the controller renders only the
    // sections the acting user holds, and tenant/branch scope always comes from
    // the trusted AI context (never the request). Viewing the center changes no
    // state and never runs the scheduler, the report generator or detection.
    Route::prefix('ai/intelligence-center')->name('ai.intelligence-center.')->group(function () {
        $centerPermissions = implode(',', [
            'ai.portfolio.view', 'ai.delinquency.view', 'ai.collection.view',
            'ai.trend.view', 'ai.accounting.view', 'ai.anomaly.view',
            'ai.predictive.view', 'ai.insights.view',
            'ai.reports.view', 'ai.reports.schedule',
        ]);

        Route::get('/', [ManagementIntelligenceCenterController::class, 'index'])
            ->middleware("permission:{$centerPermissions}")
            ->name('index');
    });

    // Executive Action & Review Management (Phase 12.3). A human workflow layer
    // over the intelligence above: creating, assigning, starting, completing and
    // cancelling management actions. Three capabilities govern it — view, manage
    // and assign — kept deliberately coarse so the workflow does not proliferate
    // permissions. Every mutating act is re-authorized and re-scoped inside the
    // service, tenant and branch scope always come from the trusted AI context
    // (never the request), and nothing here executes a financial or business
    // operation.
    Route::prefix('ai/actions')->name('ai.actions.')->group(function () {
        Route::get('/', [ManagementActionController::class, 'index'])
            ->middleware('permission:ai.actions.view')
            ->name('index');

        Route::get('/create', [ManagementActionController::class, 'create'])
            ->middleware('permission:ai.actions.manage')
            ->name('create');

        Route::post('/', [ManagementActionController::class, 'store'])
            ->middleware('permission:ai.actions.manage', 'throttle:ai.tool')
            ->name('store');

        // Management Action Effectiveness & Executive Accountability (Phase 12.4).
        // A read-only measurement layer over the actions above. It reuses the
        // existing ai.actions.view capability (a separate executive reporting
        // permission is deliberately not introduced) and is declared before the
        // wildcard action route so the path is never shadowed. Loading it can
        // never create, assign, escalate, complete or cancel an action.
        Route::get('/effectiveness', [ManagementActionEffectivenessController::class, 'index'])
            ->middleware('permission:ai.actions.view')
            ->name('effectiveness');

        Route::get('/{action}', [ManagementActionController::class, 'show'])
            ->middleware('permission:ai.actions.view')
            ->whereNumber('action')
            ->name('show');

        Route::put('/{action}', [ManagementActionController::class, 'update'])
            ->middleware('permission:ai.actions.manage', 'throttle:ai.tool')
            ->whereNumber('action')
            ->name('update');

        Route::post('/{action}/assign', [ManagementActionController::class, 'assign'])
            ->middleware('permission:ai.actions.assign', 'throttle:ai.tool')
            ->whereNumber('action')
            ->name('assign');

        Route::post('/{action}/start', [ManagementActionController::class, 'start'])
            ->middleware('permission:ai.actions.manage', 'throttle:ai.tool')
            ->whereNumber('action')
            ->name('start');

        Route::post('/{action}/complete', [ManagementActionController::class, 'complete'])
            ->middleware('permission:ai.actions.manage', 'throttle:ai.tool')
            ->whereNumber('action')
            ->name('complete');

        Route::post('/{action}/cancel', [ManagementActionController::class, 'cancel'])
            ->middleware('permission:ai.actions.manage', 'throttle:ai.tool')
            ->whereNumber('action')
            ->name('cancel');
    });

    // In-app notification inbox (Phase 11.9): personal, auth-only. There is no
    // cross-user inbox; each user acts only on their own notification rows.
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationController::class, 'readAll'])->name('read-all');
        Route::post('/{notification}/read', [NotificationController::class, 'read'])->name('read');
    });

    // AI Knowledge Base administration (Phase 11.5): minimal, secured backend.
    // Every route is permission-gated (ai.knowledge.manage) and re-validates
    // tenant scope server-side from the trusted context. VICOBA Members can
    // never administer the knowledge base.
    Route::prefix('ai/knowledge')->name('ai.knowledge.')
        ->middleware('permission:ai.knowledge.manage')
        ->group(function () {
            Route::get('/documents', [AiKnowledgeDocumentController::class, 'index'])
                ->name('documents.index');
            Route::post('/documents', [AiKnowledgeDocumentController::class, 'store'])
                ->middleware('throttle:ai.tool')
                ->name('documents.store');
            Route::post('/documents/{document}/archive', [AiKnowledgeDocumentController::class, 'archive'])
                ->name('documents.archive');
        });

    // AI Feedback, Evaluation & Learning Dataset (Phase 11.6).
    //
    // These routes implement the governance pipeline, not a public API. Every
    // tenant value is derived server-side from the trusted AI context; the
    // browser never supplies an organization, branch, reviewer, or status.
    // There is deliberately no public dataset route: export is an authenticated
    // JSONL download behind ai.feedback.export.
    Route::prefix('ai/feedback')->name('ai.feedback.')->group(function () {
        // Submission is available to anyone who can chat, but only for a
        // message inside a conversation they already own or may read.
        Route::post('/', [AiFeedbackController::class, 'store'])
            ->middleware('permission:ai.feedback.submit', 'throttle:ai.tool')
            ->name('store');

        Route::get('/', [AiFeedbackController::class, 'index'])
            ->middleware('permission:ai.use')
            ->name('index');

        Route::get('/{feedback}', [AiFeedbackController::class, 'show'])
            ->middleware('permission:ai.use')
            ->name('show');

        Route::post('/{feedback}/withdraw', [AiFeedbackController::class, 'withdraw'])
            ->middleware('permission:ai.use', 'throttle:ai.tool')
            ->name('withdraw');
    });

    // Reviewer surfaces: opening and deciding an evaluation require the review
    // capability; approval additionally re-checks ai.feedback.approve in the
    // evaluation service so a reviewer-only account can never admit an example
    // to the dataset.
    Route::prefix('ai/evaluations')->name('ai.evaluations.')
        ->middleware('permission:ai.feedback.review')
        ->group(function () {
            Route::get('/', [AiEvaluationController::class, 'index'])
                ->name('index');
            Route::get('/queue', [AiEvaluationController::class, 'queue'])
                ->name('queue');
            Route::post('/{evaluation}', [AiEvaluationController::class, 'update'])
                ->middleware('throttle:ai.tool')
                ->name('update');
        });

    Route::post('/ai/feedback/{feedback}/evaluation', [AiEvaluationController::class, 'store'])
        ->middleware('permission:ai.feedback.review', 'throttle:ai.tool')
        ->name('ai.feedback.evaluation.store');

    // Approved learning dataset. Browsing is a review capability; export is a
    // separate, stricter grant (ai.feedback.export) and returns JSONL only.
    Route::prefix('ai/dataset')->name('ai.dataset.')->group(function () {
        Route::get('/', [AiLearningDatasetController::class, 'index'])
            ->middleware('permission:ai.feedback.review')
            ->name('index');

        Route::get('/analytics', [AiLearningDatasetController::class, 'analytics'])
            ->middleware('permission:ai.feedback.review')
            ->name('analytics');

        Route::get('/export', [AiLearningDatasetController::class, 'export'])
            ->middleware('permission:ai.feedback.export', 'throttle:ai.tool')
            ->name('export');

        Route::post('/examples/{example}/revoke', [AiLearningDatasetController::class, 'revoke'])
            ->middleware('permission:ai.feedback.approve', 'throttle:ai.tool')
            ->name('examples.revoke');
    });
});
