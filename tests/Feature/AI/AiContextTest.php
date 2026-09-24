<?php

namespace Tests\Feature\AI;

use App\AI\Services\AiContextBuilderService;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\VicobaGroup;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AiContextTest extends AiTestCase
{
    private function contextFor(\App\Models\User $user): \App\AI\DTOs\AiContextData
    {
        return app(AiContextBuilderService::class)->build($user);
    }

    public function test_context_reflects_authoritative_role_and_scope_state(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);
        $branch = Branch::factory()->create(['organization_id' => $org->id]);

        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions(['ai.view', 'ai.use']);

        $user = $this->user($role->name);
        $user->organizations()->attach($org->id);
        $branch->users()->attach($user->id);
        Member::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
        ]);

        $context = $this->contextFor($user);

        $this->assertFalse($context->isSuperAdmin);
        $this->assertContains($role->name, $context->roles);
        $this->assertTrue($context->hasPermission('ai.use'));
        $this->assertSame([(int) $org->id], $context->organizationIds);
        $this->assertSame([(int) $branch->id], $context->branchIds);
        $this->assertSame((int) $user->member->id, $context->memberId);
        $this->assertSame([], $context->vicobaGroupIds);
    }

    public function test_super_administrator_receives_explicit_platform_scope(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);
        $branch = Branch::factory()->create(['organization_id' => $org->id]);
        $group = VicobaGroup::factory()->create(['branch_id' => $branch->id]);

        $context = $this->contextFor($this->superAdmin());

        $this->assertTrue($context->isSuperAdmin);
        $this->assertTrue($context->hasRole('Super Administrator'));
        $this->assertContains((int) $org->id, $context->organizationIds);
        $this->assertContains((int) $branch->id, $context->branchIds);
        $this->assertContains((int) $group->id, $context->vicobaGroupIds);
        $this->assertSame(
            Organization::query()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $context->organizationIds
        );
    }

    public function test_unassigned_user_has_empty_scope(): void
    {
        $user = $this->user();

        $context = $this->contextFor($user);

        $this->assertFalse($context->isSuperAdmin);
        $this->assertSame([], $context->roles);
        $this->assertSame([], $context->permissions);
        $this->assertSame([], $context->organizationIds);
        $this->assertSame([], $context->branchIds);
        $this->assertNull($context->memberId);
    }

    public function test_no_vicoba_group_claim_for_non_super_users(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);

        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions(['ai.view']);

        $user = $this->user($role->name);
        $user->organizations()->attach($org->id);

        $context = $this->contextFor($user);

        $this->assertSame([], $context->vicobaGroupIds);
    }
}