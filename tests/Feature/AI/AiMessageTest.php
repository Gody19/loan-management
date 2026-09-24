<?php

namespace Tests\Feature\AI;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;

class AiMessageTest extends AiTestCase
{
    public function test_message_persists_with_enum_role_and_default_metadata(): void
    {
        $conversation = AiConversation::factory()->create(['user_id' => $this->superAdmin()->id]);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::Assistant->value,
            'content' => 'Hello from the assistant.',
        ]);

        $fresh = $message->fresh();

        $this->assertInstanceOf(AiMessageRole::class, $fresh->role);
        $this->assertSame(AiMessageRole::Assistant, $fresh->role);
        $this->assertIsArray($fresh->metadata);
    }

    public function test_conversation_cascade_removes_messages(): void
    {
        $conversation = AiConversation::factory()->create(['user_id' => $this->superAdmin()->id]);
        AiMessage::factory()->count(3)->create(['ai_conversation_id' => $conversation->id]);

        $this->assertSame(3, AiMessage::where('ai_conversation_id', $conversation->id)->count());

        $conversation->delete();

        $this->assertSame(0, AiMessage::where('ai_conversation_id', $conversation->id)->count());
    }

    public function test_usage_fields_cast_to_integers(): void
    {
        $conversation = AiConversation::factory()->create(['user_id' => $this->superAdmin()->id]);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::Assistant->value,
            'input_tokens' => 10,
            'output_tokens' => 5,
            'total_tokens' => 15,
        ]);

        $this->assertSame(10, $message->input_tokens);
        $this->assertSame(5, $message->output_tokens);
        $this->assertSame(15, $message->total_tokens);
    }
}