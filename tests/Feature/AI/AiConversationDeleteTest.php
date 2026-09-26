<?php

namespace Tests\Feature\AI;

use App\Enums\UserStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Delete-chat surface (Phase 11.4 follow-up): DELETE /ai/conversations/{id}
 * permanently removes a conversation the acting user owns. Deletion is
 * deliberately stricter than read — an organization-shared conversation can be
 * read by colleagues but only destroyed by its owner (or a Super
 * Administrator). Messages, feedback and evaluations cascade on delete.
 */
class AiConversationDeleteTest extends AiTestCase
{
    private function conversationFor(int $userId, ?int $organizationId = null): AiConversation
    {
        return AiConversation::factory()->create([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'title' => 'Delete me',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $conversation = $this->conversationFor($this->user()->id);

        $this->delete("/ai/conversations/{$conversation->id}")
            ->assertRedirect(route('login'));
    }

    public function test_user_without_ai_view_cannot_delete(): void
    {
        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions(['dashboard.view']);

        $user = $this->user($role->name);
        $conversation = $this->conversationFor($user->id);

        $this->actingAs($user);

        $this->deleteJson("/ai/conversations/{$conversation->id}")->assertStatus(403);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_delete_returns_503_when_ai_is_disabled(): void
    {
        $this->disableAi();

        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->deleteJson("/ai/conversations/{$conversation->id}")->assertStatus(503);
    }

    public function test_owner_deletes_own_conversation_with_cascade_and_audit(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $message = AiMessage::factory()->user()->create(['ai_conversation_id' => $conversation->id]);

        $this->actingAs($owner);

        $this->deleteJson("/ai/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $conversation->id);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_messages', ['id' => $message->id]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.conversation.deleted',
            'auditable_type' => AiConversation::class,
            'auditable_id' => $conversation->id,
        ]);
    }

    public function test_vicoba_member_can_delete_own_conversation(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $conversation = $this->conversationFor($user->id, $org->id);

        $this->actingAs($user);

        $this->deleteJson("/ai/conversations/{$conversation->id}")->assertOk();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_colleague_can_read_but_not_delete_org_shared_conversation(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $colleague = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($colleague);

        $this->getJson("/ai/conversations/{$conversation->id}")->assertOk();

        $this->deleteJson("/ai/conversations/{$conversation->id}")->assertStatus(403);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_member_cannot_delete_another_members_conversation(): void
    {
        $org = $this->makeOrganization();
        $otherMember = $this->member($org);
        $otherUser = $this->vicobaUser($org, $otherMember);
        $conversation = $this->conversationFor($otherUser->id, $org->id);

        $member = $this->member($org);
        $this->actingAs($this->vicobaUser($org, $member));

        $this->deleteJson("/ai/conversations/{$conversation->id}")->assertStatus(403);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_super_admin_can_delete_any_conversation(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org, 'Loan Officer');
        $conversation = $this->conversationFor($staff->id, $org->id);

        $this->actingAs($this->superAdmin());

        $this->deleteJson("/ai/conversations/{$conversation->id}")->assertOk();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_suspended_user_is_sent_back_to_login(): void
    {
        $org = $this->makeOrganization();
        $owner = $this->staff($org, 'Loan Officer');
        $owner->update(['status' => UserStatus::Suspended]);
        $conversation = $this->conversationFor($owner->id, $org->id);

        $this->actingAs($owner);

        $this->delete("/ai/conversations/{$conversation->id}")->assertRedirect(route('login'));
    }
}
