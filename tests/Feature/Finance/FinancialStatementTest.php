<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Services\FinancialStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private VicobaGroup $group;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->group = VicobaGroup::factory()->create(['branch_id' => $this->branch->id]);
        $this->member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id, 'vicoba_group_id' => $this->group->id]);
    }

    public function test_member_financial_summary(): void
    {
        $savingsProduct = SavingsProduct::factory()->create(['organization_id' => $this->organization->id]);
        $shareProduct = ShareProduct::factory()->create(['organization_id' => $this->organization->id]);
        $welfareFund = WelfareFund::factory()->create(['organization_id' => $this->organization->id]);

        SavingsAccount::create([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $savingsProduct->id,
            'account_number' => 'SAV-TEST-001',
            'opening_date' => now(),
            'status' => 'active',
            'current_balance' => 500000,
            'created_by' => $this->admin->id,
        ]);

        ShareAccount::create([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $shareProduct->id,
            'account_number' => 'SHR-TEST-001',
            'total_shares' => 100,
            'total_value' => 1000000,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        WelfareAccount::create([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'welfare_fund_id' => $welfareFund->id,
            'account_number' => 'WFA-TEST-001',
            'current_balance' => 200000,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $service = new FinancialStatementService();
        $summary = $service->getMemberFinancialSummary($this->member);

        $this->assertEquals(500000, $summary['total_savings']);
        $this->assertEquals(1, $summary['savings_accounts_count']);
        $this->assertEquals(100, $summary['total_shares']);
        $this->assertEquals(1000000, $summary['total_share_value']);
        $this->assertEquals(200000, $summary['welfare_balance']);
    }

    public function test_member_statement_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('members.statement', $this->member));

        $response->assertOk();
    }

    public function test_member_statement_with_type_filter(): void
    {
        $response = $this->actingAs($this->admin)->get(route('members.statement', [$this->member, 'type' => 'shares']));

        $response->assertOk();
    }

    public function test_member_statement_with_welfare_type(): void
    {
        $response = $this->actingAs($this->admin)->get(route('members.statement', [$this->member, 'type' => 'welfare']));

        $response->assertOk();
    }
}
