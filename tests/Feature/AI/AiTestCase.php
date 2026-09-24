<?php

namespace Tests\Feature\AI;

use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
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

    protected function makeOrganization(): Organization
    {
        return Organization::factory()->create(['status' => 'active']);
    }

    /**
     * A staff user in a given organization with a seeded role (defaults to the
     * Loan Officer role, which carries the ai.* chat and business permissions).
     */
    protected function staff(Organization $org, string $role = 'Loan Officer'): User
    {
        $user = $this->user($role);
        $user->organizations()->attach($org->id);

        return $user;
    }

    protected function branch(Organization $org): Branch
    {
        return Branch::factory()->create(['organization_id' => $org->id]);
    }

    protected function group(Organization $org): VicobaGroup
    {
        return VicobaGroup::factory()->create(['branch_id' => $this->branch($org)->id]);
    }

    /**
     * An active member of the organization, optionally linked to a user.
     */
    protected function member(Organization $org, ?User $linkedUser = null): Member
    {
        $branch = $this->branch($org);

        return Member::factory()->create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'vicoba_group_id' => VicobaGroup::factory()->create(['branch_id' => $branch->id])->id,
            'membership_status' => MemberStatus::Active,
            'user_id' => $linkedUser?->id,
        ]);
    }

    /**
     * A VICOBA Member user linked to a member record in the organization.
     */
    protected function vicobaUser(Organization $org, Member $ownMember): User
    {
        $user = $this->user('VICOBA Member');
        $user->organizations()->attach($org->id);
        $ownMember->update(['user_id' => $user->id]);

        return $user;
    }
}