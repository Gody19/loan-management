<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\Enums\AiConversationStatus;
use App\Enums\AiMessageRole;
use App\Enums\AiModelVersionStatus;

class AiDtoTest extends AiTestCase
{
    public function test_message_data_holds_role_and_content(): void
    {
        $message = new AiMessageData(AiMessageRole::User, 'Hello');

        $this->assertSame(AiMessageRole::User, $message->role);
        $this->assertSame('Hello', $message->content);
    }

    public function test_request_data_has_sane_defaults(): void
    {
        $request = new AiRequestData([], 'gpt-4o-mini');

        $this->assertSame([], $request->messages);
        $this->assertSame('gpt-4o-mini', $request->model);
        $this->assertNull($request->temperature);
        $this->assertNull($request->maxOutputTokens);
        $this->assertSame([], $request->metadata);
        $this->assertNull($request->conversationId);
    }

    public function test_response_data_fields_are_nullable(): void
    {
        $response = new AiResponseData('fake', 'fake-model-1', 'reply');

        $this->assertSame('reply', $response->content);
        $this->assertNull($response->inputTokens);
        $this->assertNull($response->outputTokens);
        $this->assertNull($response->totalTokens);
        $this->assertNull($response->finishReason);
        $this->assertNull($response->providerRequestId);
        $this->assertSame([], $response->metadata);
    }

    public function test_message_roles_cover_system_user_assistant(): void
    {
        $this->assertSame(
            ['system', 'user', 'assistant'],
            AiMessageRole::values()
        );
    }

    public function test_lifecycle_enums_expose_expected_values(): void
    {
        $this->assertSame(['active', 'archived'], AiConversationStatus::values());
        $this->assertSame(['active', 'inactive', 'archived'], AiModelVersionStatus::values());
    }
}