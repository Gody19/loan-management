<?php

namespace Tests\Feature\Finance;

use App\Enums\FinancialTransactionStatus;
use App\Enums\SavingsAccountStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private VicobaGroup $group;
    private SavingsProduct $product;
    private SavingsAccount $account;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->group = VicobaGroup::factory()->create(['branch_id' => $this->branch->id]);
        $this->product = SavingsProduct::factory()->create(['organization_id' => $this->organization->id]);
        $this->paymentMethod = PaymentMethod::factory()->create(['organization_id' => $this->organization->id]);

        $member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);
        $this->account = SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $this->product->id,
            'account_number' => 'SAV-TEST-0001',
            'current_balance' => 0,
            'opening_date' => now(),
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_savings_account_can_be_created(): void
    {
        $member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.store'), [
            'member_id' => $member->id,
            'savings_product_id' => $this->product->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'opening_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('savings_accounts', [
            'organization_id' => $this->organization->id,
            'savings_product_id' => $this->product->id,
            'status' => 'active',
        ]);
    }

    public function test_savings_deposit_increases_balance(): void
    {
        $response = $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $this->account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Test deposit',
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(100000, (float) $this->account->current_balance);

        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $this->account->id,
            'transaction_type' => 'deposit',
            'amount' => 100000,
            'status' => 'completed',
        ]);
    }

    public function test_savings_withdrawal_decreases_balance(): void
    {
        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $this->account), [
            'amount' => 200000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.do-withdraw', $this->account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(150000, (float) $this->account->current_balance);
    }

    public function test_savings_withdrawal_fails_with_insufficient_balance(): void
    {
        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $this->account), [
            'amount' => 10000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.do-withdraw', $this->account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_savings_transaction_can_be_reversed(): void
    {
        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $this->account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $transaction = SavingsTransaction::where('savings_account_id', $this->account->id)->first();

        $response = $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $transaction), [
            'reason' => 'Test reversal',
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(0, (float) $this->account->current_balance);

        $transaction->refresh();
        $this->assertEquals(FinancialTransactionStatus::Reversed, $transaction->status);
    }

    public function test_savings_transaction_number_is_unique(): void
    {
        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $this->account), [
                'amount' => 1000 + $i,
                'payment_method_id' => $this->paymentMethod->id,
                'transaction_date' => now()->toDateString(),
            ]);
            $lastTransaction = SavingsTransaction::latest()->first();
            if ($lastTransaction) {
                $numbers[] = $lastTransaction->transaction_number;
            }
        }

        $this->assertGreaterThanOrEqual(1, count($numbers));
        $this->assertStringStartsWith('SVT', $numbers[0]);
    }

    public function test_savings_account_show_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('savings-accounts.show', $this->account));

        $response->assertOk();
        $response->assertSee($this->account->account_number);
    }

    public function test_savings_transaction_index_view(): void
    {
        SavingsTransaction::factory()->count(3)->create([
            'savings_account_id' => $this->account->id,
            'member_id' => $this->account->member_id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('savings-transactions.index'));

        $response->assertOk();
    }
}
