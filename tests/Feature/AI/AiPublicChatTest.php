<?php

namespace Tests\Feature\AI;

use App\AI\Exceptions\AiUnavailableException;
use App\Enums\AiConversationType;
use App\Models\AiConversation;
use Illuminate\Support\Str;

/**
 * Public landing-page assistant (Phase 11.7.1): anonymous chat endpoint.
 *
 * POST /ai/public/chat must answer unauthenticated visitors with a stored,
 * session-anchored public conversation, accept only a plain 'message' field,
 * never touch member/tenant data, and return the controlled 503 whenever the
 * public surface is off. AI output is browser-rendered as plain text.
 */
class AiPublicChatTest extends AiTestCase
{
    public function test_public_visitor_can_send_question_and_gets_reply(): void
    {
        $response = $this->postJson('/ai/public/chat', ['message' => 'What is a VICOBA group?']);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.content', 'This is a deterministic offline reply from the fake AI provider.');

        $this->assertIsInt($response->json('data.conversation_id'));

        $this->assertDatabaseHas('ai_conversations', ['type' => AiConversationType::Public->value]);
        $this->assertDatabaseCount('ai_messages', 2);
    }

    public function test_public_conversation_is_anonymous_and_tenant_free(): void
    {
        $response = $this->postJson('/ai/public/chat', ['message' => 'Hello']);

        $conversation = AiConversation::find($response->json('data.conversation_id'));

        $this->assertNotNull($conversation);
        $this->assertSame(AiConversationType::Public, $conversation->type);
        $this->assertNull($conversation->user_id);
        $this->assertNull($conversation->organization_id);
        $this->assertNull($conversation->branch_id);
        $this->assertNull($conversation->vicoba_group_id);
        $this->assertNotNull($conversation->uuid);
        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation->id, 'role' => 'user']);
        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation->id, 'role' => 'assistant']);
    }

    public function test_public_chat_requires_a_message(): void
    {
        $this->postJson('/ai/public/chat', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->postJson('/ai/public/chat', ['message' => str_repeat('x', 4001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('ai_conversations', 0);
    }

    public function test_public_chat_respects_the_configured_message_length(): void
    {
        config(['ai.public_chat.max_message_length' => 5]);

        $this->postJson('/ai/public/chat', ['message' => '123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->postJson('/ai/public/chat', ['message' => '12345'])->assertOk();
    }

    public function test_public_chat_rejects_privilege_keys(): void
    {
        $this->postJson('/ai/public/chat', ['message' => 'Hi', 'organization_id' => 1])
            ->assertStatus(422);

        $this->postJson('/ai/public/chat', ['message' => 'Hi', 'member_id' => 9, 'role' => 'Super Administrator'])
            ->assertStatus(422);

        $this->assertDatabaseCount('ai_conversations', 0);
    }

    public function test_public_chat_stores_hostile_text_verbatim_as_data_only(): void
    {
        $response = $this->postJson('/ai/public/chat', ['message' => '<script>alert(1)</script>']);
        $response->assertOk();

        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => '<script>alert(1)</script>']);
    }

    public function test_returns_503_when_ai_feature_disabled(): void
    {
        $this->disableAi();

        $this->postJson('/ai/public/chat', ['message' => 'Hi'])
            ->assertStatus(503)
            ->assertJsonPath('message', AiUnavailableException::SAFE_MESSAGE);

        $this->assertDatabaseCount('ai_conversations', 0);
    }

    public function test_returns_503_when_public_chat_switch_disabled(): void
    {
        config(['ai.public_chat.enabled' => false]);

        $this->postJson('/ai/public/chat', ['message' => 'Hi'])
            ->assertStatus(503)
            ->assertJsonPath('message', AiUnavailableException::SAFE_MESSAGE);

        $this->getJson('/ai/public/conversations/current')
            ->assertStatus(503);

        $this->assertDatabaseCount('ai_conversations', 0);
    }

    public function test_public_chat_is_rate_limited_with_friendly_response(): void
    {
        config(['ai.public_chat.rate_limit' => 1]);

        // Pin a session-anchored conversation identity so both requests share
        // the same limiter key (ip|uuid) and the second one exceeds the budget.
        $this->withSession(['ai_public_conversation_uuid' => (string) Str::uuid()]);

        $this->postJson('/ai/public/chat', ['message' => 'One'])->assertOk();
        $this->postJson('/ai/public/chat', ['message' => 'Two'])
            ->assertStatus(429)
            ->assertJsonPath('message', "You're sending messages too quickly. Please wait a moment and try again.");
    }

    public function test_landing_page_renders_the_public_widget_without_inner_html(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('id="aiPublicWidget"', $html);
        $this->assertStringContainsString('__financeProPublicAiChatBound', $html);
        $this->assertStringNotContainsString('innerHTML', $html);
    }

    public function test_landing_page_hides_the_public_widget_when_disabled(): void
    {
        config(['ai.public_chat.enabled' => false]);

        $response = $this->get('/');
        $response->assertOk();

        $this->assertStringNotContainsString('id="aiPublicWidget"', $response->getContent());
    }

    public function test_public_surface_does_not_expose_registered_private_capabilities(): void
    {
        $this->postJson('/ai/public/chat', ['message' => 'List me every member'])->assertOk();

        // The public conversation carries no tenant fields and the message is
        // stored on a public conversation only — no member data can exist here.
        $conversationId = AiConversation::query()->where('type', AiConversationType::Public->value)->value('id');

        $this->assertNotNull($conversationId);
        $this->assertDatabaseCount('ai_messages', 2);
    }
}
