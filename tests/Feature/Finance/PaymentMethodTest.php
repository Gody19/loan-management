<?php

namespace Tests\Feature\Finance;

use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create();
    }

    public function test_payment_method_can_be_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('payment-methods.store'), [
            'name' => 'M-Pesa',
            'code' => 'MPESA-001',
            'type' => 'mobile_money',
            'organization_id' => $this->organization->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payment_methods', [
            'name' => 'M-Pesa',
            'code' => 'MPESA-001',
            'type' => 'mobile_money',
        ]);
    }

    public function test_payment_method_can_be_updated(): void
    {
        $method = PaymentMethod::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->put(route('payment-methods.update', $method), [
            'name' => 'Updated Method',
            'code' => $method->code,
            'type' => 'bank',
            'organization_id' => $this->organization->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'name' => 'Updated Method',
        ]);
    }

    public function test_payment_method_can_be_deleted(): void
    {
        $method = PaymentMethod::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->delete(route('payment-methods.destroy', $method));

        $response->assertRedirect();
        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_payment_method_index_view(): void
    {
        PaymentMethod::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->get(route('payment-methods.index'));

        $response->assertOk();
    }

    public function test_payment_method_show_view(): void
    {
        $method = PaymentMethod::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->get(route('payment-methods.show', $method));

        $response->assertOk();
    }

    public function test_payment_method_code_must_be_unique(): void
    {
        PaymentMethod::factory()->create([
            'organization_id' => $this->organization->id,
            'code' => 'UNIQUE-CODE',
        ]);

        $response = $this->actingAs($this->admin)->post(route('payment-methods.store'), [
            'name' => 'Duplicate',
            'code' => 'UNIQUE-CODE',
            'type' => 'cash',
            'organization_id' => $this->organization->id,
        ]);

        $response->assertSessionHasErrors('code');
    }
}
