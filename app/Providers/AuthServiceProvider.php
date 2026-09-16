<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Models\WelfareTransaction;
use App\Models\LoanPlan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationGuarantor;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApprovalLevel;
use App\Policies\BranchPolicy;
use App\Policies\MemberPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\RolePolicy;
use App\Policies\SavingsAccountPolicy;
use App\Policies\SavingsProductPolicy;
use App\Policies\SavingsTransactionPolicy;
use App\Policies\ShareAccountPolicy;
use App\Policies\ShareProductPolicy;
use App\Policies\ShareTransactionPolicy;
use App\Policies\UserPolicy;
use App\Policies\VicobaGroupPolicy;
use App\Policies\WelfareAccountPolicy;
use App\Policies\WelfareFundPolicy;
use App\Policies\WelfareTransactionPolicy;
use App\Policies\LoanPlanPolicy;
use App\Policies\LoanApplicationPolicy;
use App\Policies\LoanApplicationGuarantorPolicy;
use App\Policies\LoanApplicationCollateralPolicy;
use App\Policies\LoanApprovalLevelPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy map for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Organization::class => OrganizationPolicy::class,
        Branch::class => BranchPolicy::class,
        VicobaGroup::class => VicobaGroupPolicy::class,
        Member::class => MemberPolicy::class,
        PaymentMethod::class => PaymentMethodPolicy::class,
        SavingsProduct::class => SavingsProductPolicy::class,
        SavingsAccount::class => SavingsAccountPolicy::class,
        SavingsTransaction::class => SavingsTransactionPolicy::class,
        ShareProduct::class => ShareProductPolicy::class,
        ShareAccount::class => ShareAccountPolicy::class,
        ShareTransaction::class => ShareTransactionPolicy::class,
        WelfareFund::class => WelfareFundPolicy::class,
        WelfareAccount::class => WelfareAccountPolicy::class,
        WelfareTransaction::class => WelfareTransactionPolicy::class,
        LoanPlan::class => LoanPlanPolicy::class,
        LoanApplication::class => LoanApplicationPolicy::class,
        LoanApplicationGuarantor::class => LoanApplicationGuarantorPolicy::class,
        LoanApplicationCollateral::class => LoanApplicationCollateralPolicy::class,
        LoanApprovalLevel::class => LoanApprovalLevelPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Super Administrator can do everything
        Gate::before(function (User $user) {
            if ($user->hasRole('Super Administrator')) {
                return true;
            }
        });
    }
}
