<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_savings_product_show_page_with_enum_statuses(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $org = Organization::factory()->create(['status' => 'active']);
        $branch = Branch::factory()->create(['organization_id' => $org->id]);

        $admin = User::factory()->create(['password' => bcrypt('password')]);
        $admin->assignRole('Organization Administrator');
        $admin->organizations()->attach($org->id);
        $admin->branches()->attach($branch->id);

        $product = SavingsProduct::factory()->create([
            'organization_id' => $org->id,
            'status' => 'active',
        ]);

        $member = Member::factory()->create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'savings_product_id' => $product->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('savings-products.show', $product));
        $response->assertOk();
        $response->assertSee($product->name);
        $response->assertSee($product->code);
        $response->assertSee('Active');
        $response->assertSee($member->full_name);
    }
}