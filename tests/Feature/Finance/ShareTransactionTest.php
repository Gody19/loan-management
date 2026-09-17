<?php

namespace Tests\Feature\Finance;

use App\Enums\ShareAccountStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class ShareTransactionTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private VicobaGroup $group;
    private ShareProduct $product;
    private ShareAccount $account;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->group = VicobaGroup::factory()->create(['branch_id' => $this->branch->id]);
        $this->product = ShareProduct::factory()->create(['organization_id' => $this->organization->id]);
        $this->paymentMethod = PaymentMethod::factory()->create(['organization_id' => $this->organization->id]);

        $this->admin->organizations()->attach($this->organization->id);

        $member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);
        $this->account = ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $this->product->id,
            'account_number' => 'SHR-TEST-0001',
            'total_shares' => 0,
            'total_value' => 0,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $this->setUpAccountingFor($this->admin, $this->organization);
    }

    public function test_share_account_can_be_created(): void
    {
        $member = Member::factory()->create(['organization_id' => $this->organization->id, 'branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.store'), [
            'member_id' => $member->id,
            'share_product_id' => $this->product->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('share_accounts', [
            'organization_id' => $this->organization->id,
            'share_product_id' => $this->product->id,
        ]);
    }

    public function test_share_purchase_increases_shares(): void
    {
        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $this->account), [
            'quantity' => 10,
            'share_price' => $this->product->share_price,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(10, $this->account->total_shares);

        $this->assertDatabaseHas('share_transactions', [
            'share_account_id' => $this->account->id,
            'transaction_type' => 'purchase',
            'quantity' => 10,
            'status' => 'completed',
        ]);
    }

    public function test_share_redeem_decreases_shares(): void
    {
        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $this->account), [
            'quantity' => 20,
            'share_price' => $this->product->share_price,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-redeem', $this->account), [
            'quantity' => 5,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();

        $this->account->refresh();
        $this->assertEquals(15, $this->account->total_shares);
    }

    public function test_share_account_number_generation(): void
    {
        $number = ShareAccount::generateAccountNumber();

        $this->assertStringStartsWith('SHR-', $number);
        $this->assertNotEquals($this->account->account_number, $number);
    }

    public function test_share_transaction_index_view(): void
    {
        $response = $this->actingAs($this->admin)->get(route('share-transactions.index'));

        $response->assertOk();
    }
}
