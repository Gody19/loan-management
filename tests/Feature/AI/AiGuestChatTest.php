<?php

namespace Tests\Feature\AI;

use App\AI\Exceptions\AiUnavailableException;

/**
 * Public landing-page FAQ chat for unauthenticated visitors.
 *
 * POST /ai/chat/guest is the only AI surface that does not require a
 * signed-in user. It must stay stateless (no conversation/message rows),
 * never touch member data or the knowledge base, stay unavailable whenever
 * the AI feature is off, and be throttled per source IP.
 */
class AiGuestChatTest extends AiTestCase
{
    public function test_guest_can_send_public_question(): void
    {
        $response = $this->postJson('/ai/chat/guest', ['message' => 'What is a VICOBA group?']);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.conversation_id', null)
            ->assertJsonPath('data.message_id', null);

        $this->assertNotEmpty($response->json('data.content'));

        $usage = $response->json('data.usage');
        $this->assertIsInt($usage['input_tokens']);
        $this->assertIsInt($usage['output_tokens']);
        $this->assertGreaterThan(0, $usage['total_tokens']);
    }

    public function test_guest_chat_is_stateless_across_requests(): void
    {
        $this->postJson('/ai/chat/guest', ['message' => 'First question']);
        $this->postJson('/ai/chat/guest', ['message' => 'Second question']);

        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_guest_chat_requires_a_message(): void
    {
        $this->postJson('/ai/chat/guest', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->postJson('/ai/chat/guest', ['message' => str_repeat('x', 4001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_guest_chat_rejects_privilege_keys(): void
    {
        $this->postJson('/ai/chat/guest', ['message' => 'Hi', 'organization_id' => 1])
            ->assertStatus(422);
    }

    public function test_guest_chat_returns_503_when_ai_disabled(): void
    {
        $this->disableAi();

        $this->postJson('/ai/chat/guest', ['message' => 'Hi'])
            ->assertStatus(503)
            ->assertJsonPath('message', AiUnavailableException::SAFE_MESSAGE);
    }

    public function test_guest_chat_is_rate_limited_per_ip(): void
    {
        config(['ai.guest_rate_limit' => 1]);

        $this->postJson('/ai/chat/guest', ['message' => 'One'])->assertOk();
        $this->postJson('/ai/chat/guest', ['message' => 'Two'])->assertStatus(429);
    }

    public function test_guest_cannot_use_the_authenticated_chat_route(): void
    {
        $this->postJson('/ai/chat', ['message' => 'Hello'])
            ->assertStatus(401);

        $this->getJson('/ai/conversations')
            ->assertStatus(401);
    }
}
