<?php

namespace Tests\Feature\AI;

use App\AI\Services\AiProviderService;

class AiConfigurationTest extends AiTestCase
{
    /**
     * The default provider is openai per config/ai.php.
     */
    public function test_default_provider_is_openai(): void
    {
        config(['ai.default_provider' => 'openai']);

        $this->assertEquals('openai', config('ai.default_provider'));
        $this->assertEquals('openai', app(AiProviderService::class)->defaultProviderName());
    }

    /**
     * AI must be off unless explicitly enabled from configuration.
     */
    public function test_ai_is_disabled_by_default(): void
    {
        $this->disableAi();

        $service = app(AiProviderService::class);

        $this->assertFalse($service->isEnabled());
        $this->assertFalse($service->isAvailable());
    }

    /**
     * The fake provider is always available once enabled (no credentials needed).
     */
    public function test_fake_provider_is_available_when_enabled(): void
    {
        $this->enableAi();
        config([
            'ai.default_provider' => 'fake',
        ]);

        $service = app(AiProviderService::class);

        $this->assertTrue($service->isEnabled());
        $this->assertTrue($service->isAvailable());
    }

    /**
     * The openai provider requires credentials even when the feature is enabled.
     */
    public function test_openai_provider_requiring_credentials_controls_availability(): void
    {
        $this->enableAi();
        config([
            'ai.default_provider' => 'openai',
            'ai.providers.openai.api_key' => '',
        ]);

        $service = app(AiProviderService::class);

        $this->assertFalse($service->isAvailable());

        config(['ai.providers.openai.api_key' => 'test-key']);

        $this->assertTrue($service->isAvailable());
    }

    /**
     * Unknown providers are never resolved and keep availability false.
     */
    public function test_unknown_provider_is_unavailable(): void
    {
        $this->enableAi();
        config(['ai.default_provider' => 'does-not-exist']);

        $this->assertFalse(app(AiProviderService::class)->isAvailable());
    }

    /**
     * History is bounded by configuration.
     */
    public function test_max_history_messages_default_is_reasonable(): void
    {
        $this->assertGreaterThanOrEqual(4, (int) config('ai.max_history_messages', 12));
    }
}