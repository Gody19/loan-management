<?php

namespace Tests\Feature\Finance;

use App\Enums\WelfareAccountStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelfareTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private VicobaGroup $group;
    private WelfareFund $fund;
    private WelfareAccount $account;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->group = VicobaGroup::factory()->create(['branch_id' => $this->branch->id]);
        $this->fund = WelfareFund::factory()->create(['organization_id' => $this->organization->id]);
        $this->paymentMethod = PaymentMethod::factory()->create(['organization_id' => $this->organization->id]);

        $member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);
        $this->account = WelfareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'welfare_fund_id' => $this->fund->id,
            'account_number' => 'WFA-TEST-0001',
            'current_balance' => 0,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_welfare_account_can_be_created(): void
    {
        $member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.store'), [
            'member_id' => $member->id,
            'welfare_fund_id' => $this->fund->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('welfare_accounts', [
            'organization_id' => $this->organization->id,
            'welfare_fund_id' => $this->fund->id,
        ]);
    }

    public function test_welfare_contribution_increases_balance(): void
    {
        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $this->account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(50000, (float) $this->account->current_balance);

        $this->assertDatabaseHas('welfare_transactions', [
            'welfare_account_id' => $this->account->id,
            'transaction_type' => 'contribution',
            'amount' => 50000,
            'status' => 'completed',
        ]);
    }

    public function test_welfare_benefit_decreases_balance(): void
    {
        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $this->account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-benefit', $this->account), [
            'amount' => 30000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(70000, (float) $this->account->current_balance);
    }

    public function test_welfare_benefit_fails_with_insufficient_balance(): void
    {
        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $this->account), [
            'amount' => 10000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-benefit', $this->account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_welfare_account_number_generation(): void
    {
        $number = WelfareAccount::generateAccountNumber();

        $this->assertStringStartsWith('WFA-', $number);
        $this->assertNotEquals($this->account->account_number, $number);
    }

    public function test_welfare_fund_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('welfare-funds.store'), [
            'name' => 'Emergency Fund',
            'code' => 'EMF-001',
            'organization_id' => $this->organization->id,
            'contribution_type' => 'fixed',
            'default_amount' => 25000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('welfare_funds', [
            'name' => 'Emergency Fund',
            'code' => 'EMF-001',
        ]);
    }
}
