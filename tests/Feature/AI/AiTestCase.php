<?php

namespace Tests\Feature\AI;

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->enableAi();
    }

    /**
     * Turn the AI capability on using the deterministic offline fake provider.
     */
    protected function enableAi(): void
    {
        config([
            'ai.enabled' => true,
            'ai.default_provider' => 'fake',
            'ai.temperature' => 0.7,
            'ai.max_output_tokens' => 64,
            'ai.max_history_messages' => 4,
        ]);
    }

    /**
     * Turn the AI capability off (the production default).
     */
    protected function disableAi(): void
    {
        config(['ai.enabled' => false]);
    }

    /**
     * Create an active user, optionally with a role.
     */
    protected function user(?string $role = null): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function superAdmin(): User
    {
        return $this->user('Super Administrator');
    }
}