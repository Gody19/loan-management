<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_login_is_audited(): void
    {
        $user = User::factory()->create();

        // Simulate authentication for the audit service
        Auth::shouldReceive('id')->andReturn($user->id);

        $auditService = new AuditService;
        $auditService->logLogin($user);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'user.login',
        ]);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();

        Auth::shouldReceive('id')->andReturn($user->id);

        $auditService = new AuditService;
        $auditService->logLogout($user);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'user.logout',
        ]);
    }

    public function test_user_creation_is_audited(): void
    {
        $user = User::factory()->create();
        $auditService = new AuditService;

        $auditService->logUserCreated($user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.created',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_user_update_is_audited(): void
    {
        $user = User::factory()->create();
        $auditService = new AuditService;

        $oldData = ['fullname' => 'Old Name'];
        $newData = ['fullname' => 'New Name'];

        $auditService->logUserUpdated($user, $oldData, $newData);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.updated',
            'old_values' => json_encode($oldData),
            'new_values' => json_encode($newData),
        ]);
    }

    public function test_role_assignment_is_audited(): void
    {
        $user = User::factory()->create();
        $auditService = new AuditService;

        $auditService->logRoleAssigned($user, 'Admin');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'role.assigned',
            'new_values' => json_encode(['role' => 'Admin']),
        ]);
    }
}
