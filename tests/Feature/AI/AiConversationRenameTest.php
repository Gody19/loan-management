<?php

namespace Tests\Feature\AI;

use App\Enums\UserStatus;
use App\Models\AiConversation;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Rename-chat surface: PATCH /ai/conversations/{id} relabels a conversation
 * the acting user owns. Like deletion this is deliberately stricter than read —
 * an organization-shared conversation can be read by colleagues but only
 * renamed by its owner (or a Super Administrator). The submitted title is
 * normalized and length-checked server-side and is never trusted as given.
 */
class AiConversationRenameTest extends AiTestCase
{
    private function conversationFor(int $userId, ?int $organizationId = null): AiConversation
    {
        return AiConversation::factory()->create([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'title' => 'Original title',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $conversation = $this->conversationFor($this->user()->id);

        $this->patch("/ai/conversations/{$conversation->id}", ['title' => 'Nope'])
            ->assertRedirect(route('login'));
    }

    public function test_user_without_ai_view_cannot_rename(): void
    {
        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions(['dashboard.view']);

        $user = $this->user($role->name);
        $conversation = $this->conversationFor($user->id);

        $this->actingAs($user);

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'Renamed'])
            ->assertStatus(403);

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Original title',
        ]);
    }

    public function test_rename_returns_503_when_ai_is_disabled(): void
    {
        $this->disableAi();

        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'Renamed'])
            ->assertStatus(503);
    }

    public function test_owner_renames_own_conversation_and_it_is_audited(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'Loan health review'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Loan health review');

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Loan health review',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.conversation.renamed',
            'auditable_type' => AiConversation::class,
            'auditable_id' => $conversation->id,
        ]);
    }

    public function test_vicoba_member_can_rename_own_conversation(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $conversation = $this->conversationFor($user->id, $org->id);

        $this->actingAs($user);

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'My savings'])
            ->assertOk();

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'My savings',
        ]);
    }

    public function test_whitespace_is_collapsed_to_a_single_line(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patchJson("/ai/conversations/{$conversation->id}", [
            'title' => "  Savings   and\nshares  ",
        ])->assertOk()->assertJsonPath('data.title', 'Savings and shares');

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Savings and shares',
        ]);
    }

    public function test_a_blank_title_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => '    '])
            ->assertStatus(422);

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Original title',
        ]);
    }

    public function test_a_missing_title_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patchJson("/ai/conversations/{$conversation->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_an_over_long_title_is_rejected(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => str_repeat('a', 121)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Original title',
        ]);
    }

    public function test_colleague_can_read_but_not_rename_org_shared_conversation(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $colleague = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($colleague);

        $this->getJson("/ai/conversations/{$conversation->id}")->assertOk();

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Original title',
        ]);
    }

    public function test_member_cannot_rename_another_members_conversation(): void
    {
        $org = $this->makeOrganization();
        $otherMember = $this->member($org);
        $otherUser = $this->vicobaUser($org, $otherMember);
        $conversation = $this->conversationFor($otherUser->id, $org->id);

        $member = $this->member($org);
        $this->actingAs($this->vicobaUser($org, $member));

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Original title',
        ]);
    }

    public function test_super_admin_can_rename_any_conversation(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($staff->id, $org->id);

        $this->actingAs($this->superAdmin());

        $this->patchJson("/ai/conversations/{$conversation->id}", ['title' => 'Audited review'])
            ->assertOk();

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Audited review',
        ]);
    }

    public function test_suspended_user_is_sent_back_to_login(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $owner->update(['status' => UserStatus::Suspended]);
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->patch("/ai/conversations/{$conversation->id}", ['title' => 'Renamed'])
            ->assertRedirect(route('login'));
    }
}
