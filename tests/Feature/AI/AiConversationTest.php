<?php

namespace Tests\Feature\AI;

use App\AI\Exceptions\AiUnavailableException;
use App\AI\Services\AiConversationService;
use App\Enums\AiConversationStatus;
use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Organization;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AiConversationTest extends AiTestCase
{
    private function service(): AiConversationService
    {
        return app(AiConversationService::class);
    }

    public function test_create_persists_active_conversation_for_user(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        $this->service()->create(user: $user, title: 'First chat');

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $user->id,
            'title' => 'First chat',
            'status' => AiConversationStatus::Active->value,
        ]);
    }

    public function test_create_writes_audit_event(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        $conversation = $this->service()->create(user: $user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.conversation.created',
            'auditable_type' => AiConversation::class,
            'auditable_id' => $conversation->id,
        ]);
    }

    public function test_owner_can_retrieve_conversation(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        $found = $this->service()->findForUser((int) $conversation->id, $user);

        $this->assertSame($conversation->id, $found->id);
    }

    public function test_other_user_cannot_retrieve_conversation(): void
    {
        $owner = $this->superAdmin();
        $this->actingAs($owner);
        $conversation = $this->service()->create(user: $owner);

        $intruder = $this->user();

        $this->assertForbidden(fn () => $this->service()->findForUser((int) $conversation->id, $intruder));
    }

    public function test_cross_organization_conversation_is_isolated(): void
    {
        $orgA = Organization::factory()->create(['status' => 'active']);
        $userA = $this->user();
        $userA->organizations()->attach($orgA->id);
        $this->actingAs($userA);

        $conversation = $this->service()->create(user: $userA, organizationId: (int) $orgA->id);

        $orgB = Organization::factory()->create(['status' => 'active']);
        $userB = $this->user();
        $userB->organizations()->attach($orgB->id);

        $this->assertForbidden(fn () => $this->service()->findForUser((int) $conversation->id, $userB));
    }

    public function test_super_administrator_can_access_any_conversation(): void
    {
        $orgA = Organization::factory()->create(['status' => 'active']);
        $userA = $this->user();
        $this->actingAs($userA);
        $conversation = $this->service()->create(user: $userA, organizationId: (int) $orgA->id);

        $found = $this->service()->findForUser((int) $conversation->id, $this->superAdmin());

        $this->assertSame($conversation->id, $found->id);
    }

    public function test_list_is_scoped_to_own_and_organization_conversations(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);
        $user = $this->user();
        $user->organizations()->attach($org->id);
        $this->actingAs($user);

        $own = $this->service()->create(user: $user, title: 'Own');
        $orgLevel = $this->service()->create(user: $user, organizationId: (int) $org->id, title: 'Org');

        $other = $this->user();
        $this->actingAs($other);
        $otherConv = $this->service()->create(user: $other, title: 'Other user');

        $ids = $this->service()->listForUser($user)->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($orgLevel->id, $ids);
        $this->assertNotContains($otherConv->id, $ids);
    }

    public function test_append_stores_message_and_audits_without_content(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        $this->service()->appendMessage($conversation, AiMessageRole::User, 'secret prompt content');

        $message = AiMessage::where('ai_conversation_id', $conversation->id)->first();

        $this->assertNotNull($message);
        $this->assertSame(AiMessageRole::User, $message->role);
        $this->assertSame('secret prompt content', $message->content);

        $audit = \App\Models\AuditLog::where('event', 'ai.message.created')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('secret prompt content', json_encode($audit->new_values));
    }

    public function test_history_is_bounded_and_oldest_first(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        for ($i = 1; $i <= 6; $i++) {
            $this->service()->appendMessage($conversation, AiMessageRole::User, "message {$i}");
        }

        $history = $this->service()->history($conversation, 4);

        $this->assertCount(4, $history);
        $this->assertSame('message 3', $history[0]->content);
        $this->assertSame('message 6', $history[3]->content);
    }

    public function test_prune_removes_oldest_beyond_window(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        for ($i = 1; $i <= 6; $i++) {
            $this->service()->appendMessage($conversation, AiMessageRole::User, "message {$i}");
        }

        $deleted = $this->service()->pruneHistory($conversation, 4);

        $this->assertSame(2, $deleted);
        $this->assertSame(4, $conversation->messages()->count());
        $this->assertDatabaseMissing('ai_messages', ['content' => 'message 1']);
        $this->assertDatabaseHas('ai_messages', ['content' => 'message 6']);
    }

    public function test_send_completes_full_loop_with_fake_provider(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        $response = $this->service()->send($conversation, 'Hello AI');

        $this->assertSame('fake', $response->provider);
        $this->assertNotSame('', $response->content);

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::User->value,
            'content' => 'Hello AI',
        ]);
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::Assistant->value,
            'content' => $response->content,
        ]);

        $fresh = $conversation->fresh();
        $this->assertSame('fake', $fresh->provider);
        $this->assertSame('fake-model-1', $fresh->model);

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.provider.requested']);
    }

    public function test_send_when_disabled_throws_unavailable(): void
    {
        $this->disableAi();

        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        $this->expectException(AiUnavailableException::class);
        $this->service()->send($conversation, 'Hello AI');
    }

    public function test_close_archives_and_audits(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user);
        $conversation = $this->service()->create(user: $user);

        $this->service()->close($conversation);

        $this->assertSame(AiConversationStatus::Archived, $conversation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ai.conversation.closed',
            'auditable_id' => $conversation->id,
        ]);
    }

    private function assertForbidden(callable $callable): void
    {
        try {
            $callable();
            $this->fail('Expected 403 was not thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}