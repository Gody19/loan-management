<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected const MIGRATION_FILE = '2026_09_23_000002_add_organization_id_to_audit_logs_table.php';

    protected Organization $orgA;
    protected Organization $orgB;
    protected Branch $branchA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->orgA = Organization::factory()->create(['status' => 'active']);
        $this->orgB = Organization::factory()->create(['status' => 'active']);
        $this->branchA = Branch::factory()->create(['organization_id' => $this->orgA->id]);
    }

    private function loadMigration()
    {
        return require database_path('migrations/'.self::MIGRATION_FILE);
    }

    // ===== AuditService organization resolution =====

    public function test_log_resolves_organization_from_auditable_model(): void
    {
        $this->actingAs(User::factory()->create());

        app(AuditService::class)->log('branch.updated', $this->branchA);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'branch.updated',
            'organization_id' => $this->orgA->id,
        ]);
    }

    public function test_log_resolves_organization_from_organization_auditable(): void
    {
        $this->actingAs(User::factory()->create());

        app(AuditService::class)->log('organization.updated', $this->orgB);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'organization.updated',
            'organization_id' => $this->orgB->id,
        ]);
    }

    public function test_log_resolves_organization_from_user_with_single_organization(): void
    {
        $user = User::factory()->create();
        $user->organizations()->attach($this->orgA->id);

        $this->actingAs($user);

        app(AuditService::class)->log('settings.updated');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.updated',
            'organization_id' => $this->orgA->id,
        ]);
    }

    public function test_log_keeps_organization_null_for_user_in_multiple_organizations(): void
    {
        $user = User::factory()->create();
        $user->organizations()->attach([$this->orgA->id, $this->orgB->id]);

        $this->actingAs($user);

        app(AuditService::class)->log('settings.updated');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.updated',
            'organization_id' => null,
        ]);
    }

    public function test_log_keeps_organization_null_for_platform_event(): void
    {
        $this->actingAs(User::factory()->create());

        AuditLog::create([
            'user_id' => null,
            'event' => 'platform.cleaned',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'platform.cleaned',
            'organization_id' => null,
        ]);
    }

    // ===== Migration backfill =====

    public function test_backfill_resolves_legacy_rows(): void
    {
        $orgA = $this->orgA->id;
        $orgB = $this->orgB->id;
        $branchA = $this->branchA->id;

        $userSingle = User::factory()->create();
        $userSingle->organizations()->attach($this->orgA->id);

        $userMulti = User::factory()->create();
        $userMulti->organizations()->attach([$this->orgA->id, $this->orgB->id]);

        $legacy = [
            // Model exposing organization_id
            ['user_id' => null, 'auditable_type' => Branch::class, 'auditable_id' => $branchA, 'event' => 'legacy.branch'],
            // User with a single organization
            ['user_id' => null, 'auditable_type' => User::class, 'auditable_id' => $userSingle->id, 'event' => 'legacy.user'],
            // User with multiple organizations stays null
            ['user_id' => null, 'auditable_type' => User::class, 'auditable_id' => $userMulti->id, 'event' => 'legacy.user.multi'],
            // Organization auditable
            ['user_id' => null, 'auditable_type' => Organization::class, 'auditable_id' => $orgB, 'event' => 'legacy.organization'],
            // Complete fallback to single-org actor
            ['user_id' => $userSingle->id, 'auditable_type' => null, 'auditable_id' => null, 'event' => 'legacy.actor'],
            // Ambiguous actor stays null
            ['user_id' => $userMulti->id, 'auditable_type' => null, 'auditable_id' => null, 'event' => 'legacy.actor.multi'],
            // Platform event stays null
            ['user_id' => null, 'auditable_type' => null, 'auditable_id' => null, 'event' => 'legacy.platform'],
        ];

        foreach ($legacy as $row) {
            AuditLog::create($row);
        }

        $migration = $this->loadMigration();
        $migration->backfill();

        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.branch', 'organization_id' => $orgA]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.user', 'organization_id' => $orgA]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.organization', 'organization_id' => $orgB]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.actor', 'organization_id' => $orgA]);

        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.user.multi', 'organization_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.actor.multi', 'organization_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'legacy.platform', 'organization_id' => null]);
    }

    // ===== Migration up / down / up cycle =====

    public function test_migration_survives_down_up_cycle(): void
    {
        $orgA = $this->orgA->id;

        $userSingle = User::factory()->create();
        $userSingle->organizations()->attach($this->orgA->id);

        $log = AuditLog::create([
            'user_id' => $userSingle->id,
            'event' => 'cycle.seed',
        ]);

        $migration = $this->loadMigration();
        $migration->backfill();

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'organization_id' => $orgA]);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('audit_logs', 'organization_id'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('audit_logs', 'organization_id'));
        $this->assertTrue(Schema::hasIndex('audit_logs', 'audit_logs_organization_id_index'));

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'event' => 'cycle.seed']);
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'organization_id' => $orgA]);
    }

    public function test_audit_logs_table_uses_single_column_index(): void
    {
        $indexNames = collect(DB::select('PRAGMA index_list(audit_logs)'))->pluck('name')->all();

        $this->assertContains('audit_logs_organization_id_index', $indexNames);
        $this->assertNotEmpty(DB::select('PRAGMA index_info(audit_logs_organization_id_index)'));
    }
}