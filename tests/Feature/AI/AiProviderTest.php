<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiUnavailableException;
use App\AI\Providers\FakeAiProvider;
use App\AI\Providers\OpenAiProvider;
use App\AI\Services\AiProviderService;
use App\Enums\AiMessageRole;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AiProviderTest extends AiTestCase
{
    private function request(): AiRequestData
    {
        return new AiRequestData(
            messages: [
                new AiMessageData(AiMessageRole::User, 'Hello there'),
            ],
            model: 'gpt-4o-mini',
            temperature: 0.7,
            maxOutputTokens: 64,
        );
    }

    public function test_fake_provider_returns_deterministic_response(): void
    {
        $provider = new FakeAiProvider('fake-model-1');

        $response = $provider->generate($this->request());

        $this->assertSame('fake', $response->provider);
        $this->assertSame('fake-model-1', $response->model);
        $this->assertNotSame('', $response->content);
        $this->assertNotNull($response->inputTokens);
        $this->assertNotNull($response->outputTokens);
        $this->assertNotNull($response->totalTokens);
        $this->assertSame('stop', $response->finishReason);
    }

    public function test_openai_success_is_mapped_to_normalized_response(): void
    {
        Http::fake([
            'https://api.openai.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-123',
                'model' => 'gpt-4o-mini-2026',
                'choices' => [
                    ['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'Hi there!']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $provider = new OpenAiProvider('test-key', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $response = $provider->generate($this->request());

        $this->assertSame('openai', $response->provider);
        $this->assertSame('gpt-4o-mini-2026', $response->model);
        $this->assertSame('Hi there!', $response->content);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(5, $response->outputTokens);
        $this->assertSame(15, $response->totalTokens);
        $this->assertSame('stop', $response->finishReason);
        $this->assertSame('chatcmpl-123', $response->providerRequestId);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->url() === 'https://api.openai.test/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $request['messages'][0]['role'] === 'user'
                && $request['messages'][0]['content'] === 'Hello there'
                && $request['max_tokens'] === 64
                && $request['temperature'] === 0.7;
        });
    }

    public function test_openai_auth_failure_is_sanitized(): void
    {
        Http::fake(['*' => Http::response([], 401)]);
        $provider = new OpenAiProvider('bad-key', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $this->assertProviderError($provider, 'Provider authentication failed.');
    }

    public function test_openai_rate_limit_is_sanitized(): void
    {
        Http::fake(['*' => Http::response([], 429)]);
        $provider = new OpenAiProvider('test-key', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $this->assertProviderError($provider, 'Provider rate limit or timeout exceeded.');
    }

    public function test_openai_server_error_is_sanitized(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $provider = new OpenAiProvider('test-key', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $this->assertProviderError($provider, 'Provider is temporarily unavailable.');
    }

    public function test_openai_timeout_is_sanitized(): void
    {
        Http::fake(['*' => fn () => throw new ConnectionException('Timed out')]);
        $provider = new OpenAiProvider('test-key', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $this->assertProviderError($provider, 'Provider request timed out.');
    }

    public function test_openai_malformed_payload_is_rejected(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        $provider = new OpenAiProvider('test-key', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $this->assertProviderError($provider, 'Provider returned an invalid response.');
    }

    public function test_missing_api_key_prevents_openai_requests(): void
    {
        $provider = new OpenAiProvider('', 'https://api.openai.test/v1', 5, 'gpt-4o-mini');

        $this->assertProviderError($provider, 'Provider configuration is incomplete.');
    }

    public function test_service_wraps_provider_failure_into_unavailable(): void
    {
        $this->enableAi();
        config([
            'ai.default_provider' => 'openai',
            'ai.providers.openai.api_key' => 'test-key',
            'ai.providers.openai.base_url' => 'https://api.openai.test/v1',
        ]);

        Http::fake(['*' => Http::response([], 500)]);

        try {
            app(AiProviderService::class)->generate($this->request());
            $this->fail('AiUnavailableException was not thrown.');
        } catch (AiUnavailableException $exception) {
            $this->assertSame(AiUnavailableException::SAFE_MESSAGE, $exception->getMessage());
            $this->assertSame(503, $exception->getStatusCode());
        }
    }

    public function test_service_refuses_generation_when_disabled(): void
    {
        $this->disableAi();

        try {
            app(AiProviderService::class)->generate($this->request());
            $this->fail('AiUnavailableException was not thrown.');
        } catch (AiUnavailableException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
    }

    private function assertProviderError(OpenAiProvider $provider, string $message): void
    {
        try {
            $provider->generate($this->request());
            $this->fail('AiProviderException was not thrown.');
        } catch (AiProviderException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}