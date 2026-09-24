<?php

namespace Tests\Feature\AI;

use App\AI\Exceptions\AiAuthorizationException;
use App\AI\Exceptions\AiUnavailableException;
use App\AI\Policies\AiAuthorizationCategory;
use App\AI\Services\AiGuardrailService;
use App\Models\AiConversation;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AiGuardrailTest extends AiTestCase
{
    private function guardrail(): AiGuardrailService
    {
        return app(AiGuardrailService::class);
    }

    private function roleWith(array $permissions = ['ai.view', 'ai.use']): Role
    {
        $role = Role::firstOrCreate(['name' => Str::random(8), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    private function chatUser(\App\Models\User $user = null): \App\Models\User
    {
        $user = $user ?? $this->user($this->roleWith()->name);

        return $user;
    }

    public function test_feature_disabled_throws_unavailable(): void
    {
        $this->disableAi();
        $this->chatUser();

        $this->expectException(AiUnavailableException::class);
        $this->guardrail()->authorize('ai.chat', []);
    }

    public function test_unauthenticated_authorize_is_denied_and_audited(): void
    {
        try {
            $this->guardrail()->authorize('ai.chat', []);
            $this->fail('Expected AiAuthorizationException.');
        } catch (AiAuthorizationException $exception) {
            $this->assertSame(AiAuthorizationCategory::Unauthenticated->value, $exception->category);
            $this->assertSame('ai.chat', $exception->capability);
        }

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.authorization.denied',
        ]);
    }

    public function test_allowed_chat_returns_context_and_audits_allowed(): void
    {
        $user = $this->chatUser();
        $this->actingAs($user);
        $org = Organization::factory()->create(['status' => 'active']);
        $user->organizations()->attach($org->id);

        $context = $this->guardrail()->authorize('ai.chat', []);

        $this->assertSame((int) $user->id, $context->userId);
        $this->assertContains((int) $org->id, $context->organizationIds);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.authorization.allowed',
        ]);
    }

    public function test_missing_permission_is_denied_and_audited(): void
    {
        $this->actingAs($this->user($this->roleWith(['ai.view'])->name));

        try {
            $this->guardrail()->authorize('ai.chat', []);
            $this->fail('Expected AiAuthorizationException.');
        } catch (AiAuthorizationException $exception) {
            $this->assertSame(AiAuthorizationCategory::MissingPermission->value, $exception->category);
            $this->assertSame('ai.use', $exception->metadata['denied_key']);
        }

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.authorization.denied',
        ]);
    }

    public function test_scope_escalation_argument_is_denied_and_audited(): void
    {
        $this->actingAs($this->chatUser());

        try {
            $this->guardrail()->authorize('ai.chat', ['organization_id' => 999]);
            $this->fail('Expected AiAuthorizationException.');
        } catch (AiAuthorizationException $exception) {
            $this->assertSame(AiAuthorizationCategory::EscalationAttempt->value, $exception->category);
        }

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.authorization.denied',
        ]);
    }

    public function test_conversation_access_super_admin_platform_scope(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user()->id,
            'organization_id' => Organization::factory()->create(['status' => 'active'])->id,
        ]);

        $this->guardrail()->checkConversationAccess($conversation, $this->guardrail()->authorize('ai.conversation.read', []));

        $this->addToAssertionCount(1);
    }

    public function test_conversation_access_owner_allowed(): void
    {
        $user = $this->chatUser();
        $this->actingAs($user);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'organization_id' => Organization::factory()->create(['status' => 'active'])->id,
        ]);

        $context = $this->guardrail()->authorize('ai.conversation.read', ['conversation_id' => (int) $conversation->id]);
        $this->guardrail()->checkConversationAccess($conversation, $context);

        $this->addToAssertionCount(1);
    }

    public function test_conversation_access_staff_member_of_organization_allowed(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);
        $other = $this->user();
        $staff = $this->chatUser();
        $staff->organizations()->attach($org->id);
        $this->actingAs($staff);

        $conversation = AiConversation::factory()->create([
            'user_id' => $other->id,
            'organization_id' => $org->id,
        ]);

        $context = $this->guardrail()->authorize('ai.conversation.read', ['conversation_id' => (int) $conversation->id]);
        $this->guardrail()->checkConversationAccess($conversation, $context);

        $this->addToAssertionCount(1);
    }

    public function test_conversation_access_non_member_of_organization_denied(): void
    {
        $orgB = Organization::factory()->create(['status' => 'active']);
        $staff = $this->chatUser();
        $staff->organizations()->attach($orgB->id);
        $this->actingAs($staff);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user()->id,
            'organization_id' => Organization::factory()->create(['status' => 'active'])->id,
        ]);

        try {
            $context = $this->guardrail()->authorize('ai.conversation.read', ['conversation_id' => (int) $conversation->id]);
            $this->guardrail()->checkConversationAccess($conversation, $context);
            $this->fail('Expected AiAuthorizationException.');
        } catch (AiAuthorizationException $exception) {
            $this->assertSame(AiAuthorizationCategory::ConversationScopeViolation->value, $exception->category);
        }

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.authorization.denied',
        ]);
    }

    public function test_vicoba_member_conversation_access_is_owner_only(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);

        $memberOwner = $this->chatUser();
        Member::factory()->create([
            'user_id' => $memberOwner->id,
            'organization_id' => $org->id,
        ]);
        $memberOwner->assignRole('VICOBA Member');
        $this->actingAs($memberOwner);

        $own = AiConversation::factory()->create(['user_id' => $memberOwner->id, 'organization_id' => $org->id]);
        $foreign = AiConversation::factory()->create([
            'user_id' => $this->chatUser()->id,
            'organization_id' => $org->id,
        ]);

        $context = $this->guardrail()->authorize('ai.conversation.read', ['conversation_id' => (int) $own->id]);
        $this->guardrail()->checkConversationAccess($own, $context);
        $this->addToAssertionCount(1);

        try {
            $this->guardrail()->checkConversationAccess($foreign, $context);
            $this->fail('Expected AiAuthorizationException.');
        } catch (AiAuthorizationException $exception) {
            $this->assertSame(AiAuthorizationCategory::ConversationNotOwned->value, $exception->category);
        }
    }
}