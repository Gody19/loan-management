<?php

namespace Database\Factories;

use App\Enums\AiKnowledgeDocumentStatus;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use App\Models\AiKnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiKnowledgeDocumentFactory extends Factory
{
    protected $model = AiKnowledgeDocument::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'branch_id' => null,
            'vicoba_group_id' => null,
            'title' => fake()->unique()->words(4, true),
            'document_type' => AiKnowledgeDocumentType::General,
            'description' => fake()->optional(0.6)->sentence(),
            'source' => 'internal',
            'content' => fake()->paragraphs(2, true),
            'version' => 1,
            'status' => AiKnowledgeDocumentStatus::Active,
            'visibility' => AiKnowledgeScope::Global,
            'checksum' => null,
            'metadata' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => AiKnowledgeDocumentStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => AiKnowledgeDocumentStatus::Archived]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => AiKnowledgeDocumentStatus::Failed]);
    }

    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn () => [
            'organization_id' => $organizationId,
            'visibility' => AiKnowledgeScope::Organization,
        ]);
    }

    public function forBranch(int $branchId, int $organizationId): static
    {
        return $this->state(fn () => [
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'visibility' => AiKnowledgeScope::Branch,
        ]);
    }

    public function forGroup(int $groupId, int $branchId, int $organizationId): static
    {
        return $this->state(fn () => [
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'vicoba_group_id' => $groupId,
            'visibility' => AiKnowledgeScope::Group,
        ]);
    }

    /**
     * A document published to the public landing-page assistant: no tenant
     * columns, retrievable by any visitor via AiKnowledgeRetrievalService.
     */
    public function forPublic(): static
    {
        return $this->state(fn () => [
            'organization_id' => null,
            'branch_id' => null,
            'vicoba_group_id' => null,
            'visibility' => AiKnowledgeScope::Public,
        ]);
    }
}
