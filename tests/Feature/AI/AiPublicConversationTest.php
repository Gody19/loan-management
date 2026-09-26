<?php

namespace Tests\Feature\AI;

use App\Enums\AiConversationType;
use App\Models\AiConversation;
use Illuminate\Support\Str;

/**
 * Session anchoring and isolation of public conversations (Phase 11.7.1).
 *
 * The public conversation identity lives ONLY in the visitor session as a
 * server-generated uuid; the browser never supplies an id. These tests prove
 * a visitor keeps one stable conversation, a fresh visitor cannot discover or
 * read one, different sessions cannot cross-read, and public conversations are
 * never listed or readable through the authenticated chat surfaces.
 */
class AiPublicConversationTest extends AiTestCase
{
    public function test_a_session_keeps_one_stable_conversation_across_messages(): void
    {
        $first = $this->postJson('/ai/public/chat', ['message' => 'First question']);
        $first->assertOk();
        $conversationId = $first->json('data.conversation_id');

        $second = $this->postJson('/ai/public/chat', ['message' => 'Second question']);
        $second->assertOk();

        $this->assertSame($conversationId, $second->json('data.conversation_id'));
        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertDatabaseCount('ai_messages', 4);
    }

    public function test_current_endpoint_restores_the_session_conversation(): void
    {
        $this->postJson('/ai/public/chat', ['message' => 'Hello there'])->assertOk();

        $response = $this->getJson('/ai/public/conversations/current');

        $response->assertOk();
        $this->assertIsInt($response->json('data.conversation_id'));
        $this->assertCount(2, $response->json('data.messages'));
        $this->assertSame('user', $response->json('data.messages.0.role'));
        $this->assertSame('Hello there', $response->json('data.messages.0.content'));
        $this->assertSame('assistant', $response->json('data.messages.1.role'));
    }

    public function test_current_returns_empty_for_a_fresh_visitor(): void
    {
        $this->getJson('/ai/public/conversations/current')
            ->assertOk()
            ->assertJsonPath('data.conversation_id', null)
            ->assertJsonPath('data.messages', []);
    }

    public function test_restore_uses_the_session_not_a_supplied_id(): void
    {
        $uuid = (string) Str::uuid();
        $conversation = AiConversation::factory()->public()->create(['uuid' => $uuid]);

        $this->withSession(['ai_public_conversation_uuid' => $uuid])
            ->getJson('/ai/public/conversations/current')
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id);
    }

    public function test_a_fresh_visitor_cannot_discover_an_existing_conversation(): void
    {
        $conversation = AiConversation::factory()->public()->create(['uuid' => (string) Str::uuid()]);

        // No session tells the server about this uuid, and the endpoint accepts
        // no identifier from the browser, so it cannot be enumerated.
        $this->getJson('/ai/public/conversations/current')
            ->assertOk()
            ->assertJsonPath('data.conversation_id', null);

        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_different_sessions_cannot_read_each_others_conversations(): void
    {
        $this->postJson('/ai/public/chat', ['message' => 'A private thought'])->assertOk();

        // Second visitor arrives with a completely fresh session.
        $this->flushSession();

        $this->getJson('/ai/public/conversations/current')
            ->assertOk()
            ->assertJsonPath('data.conversation_id', null);
    }

    public function test_public_conversations_are_never_listed_for_authenticated_users(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);

        $this->actingAs($user)->postJson('/ai/public/chat', ['message' => 'Anonymous question'])->assertOk();

        $this->assertDatabaseHas('ai_conversations', ['type' => AiConversationType::Public->value]);

        $this->actingAs($user)
            ->getJson('/ai/conversations')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_authenticated_chat_cannot_read_a_public_conversation(): void
    {
        $public = AiConversation::factory()->public()->create(['uuid' => (string) Str::uuid()]);

        $org = $this->makeOrganization();
        $user = $this->staff($org);

        $this->actingAs($user)
            ->getJson("/ai/conversations/{$public->id}")
            ->assertStatus(403);
    }

    public function test_authenticated_chat_cannot_continue_a_public_conversation(): void
    {
        $public = AiConversation::factory()->public()->create(['uuid' => (string) Str::uuid()]);

        $org = $this->makeOrganization();
        $user = $this->staff($org);

        $this->actingAs($user)
            ->postJson('/ai/chat', ['message' => 'Continue public', 'conversation_id' => $public->id])
            ->assertStatus(403);
    }

    public function test_public_flag_never_reclassifies_existing_conversations(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        $this->actingAs($user);

        $this->postJson('/ai/chat', ['message' => 'My data question'])->assertOk();

        $this->assertDatabaseMissing('ai_conversations', ['type' => AiConversationType::Public->value]);
        $this->assertDatabaseHas('ai_conversations', ['type' => AiConversationType::Private->value]);
    }
}
