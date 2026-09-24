<?php

namespace Tests\Feature\AI;

use App\Enums\AiModelVersionStatus;
use App\Models\AiModelVersion;

class AiModelVersionTest extends AiTestCase
{
    public function test_factory_creates_active_version(): void
    {
        $version = AiModelVersion::factory()->create();

        $this->assertSame('openai', $version->provider);
        $this->assertSame(AiModelVersionStatus::Active, $version->status);
        $this->assertIsArray($version->configuration);
    }

    public function test_provider_model_combination_is_unique(): void
    {
        $version = AiModelVersion::factory()->create();

        $this->expectException(\Illuminate\Database\QueryException::class);
        AiModelVersion::factory()->create([
            'provider' => $version->provider,
            'model' => $version->model,
        ]);
    }

    public function test_active_scope_filters_versions(): void
    {
        AiModelVersion::factory()->create(['status' => AiModelVersionStatus::Active->value]);
        AiModelVersion::factory()->inactive()->create();

        $this->assertSame(1, AiModelVersion::active()->count());
    }

    public function test_for_provider_scope_filters_by_provider(): void
    {
        $openai = AiModelVersion::factory()->create(['provider' => 'openai']);
        AiModelVersion::factory()->create(['provider' => 'fake']);

        $ids = AiModelVersion::forProvider('openai')->pluck('id')->all();

        $this->assertSame([$openai->id], $ids);
    }
}