<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.delete', 'guard_name' => 'web']);

        // Create roles
        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $memberRole = Role::create(['name' => 'VICOBA Member', 'guard_name' => 'web']);
    }

    public function test_unauthenticated_users_redirected_to_login(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect(route('login'));
    }

    public function test_authorized_users_can_view_users(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/users');

        $response->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_view_users(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('VICOBA Member');

        $this->actingAs($user);

        $response = $this->get('/users');

        $response->assertStatus(403);
    }

    public function test_authorized_users_can_create_user(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->post('/users', [
            'fullname' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'nida_number' => '12345678901234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['VICOBA Member'],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_authorized_users_can_update_user(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $targetUser = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($user);

        $response = $this->put("/users/{$targetUser->id}", [
            'fullname' => 'Updated Name',
            'username' => $targetUser->username,
            'email' => $targetUser->email,
            'nida_number' => $targetUser->nida_number,
            'status' => 'active',
            'roles' => ['VICOBA Member'],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'fullname' => 'Updated Name']);
    }

    public function test_authorized_users_can_delete_user(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $targetUser = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($user);

        $response = $this->delete("/users/{$targetUser->id}");

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_user_cannot_delete_own_account(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->delete("/users/{$user->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
