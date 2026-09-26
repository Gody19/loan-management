<?php

namespace App\AI\FinancialIntelligence\DTOs;

/**
 * A single rule-based anomaly finding. The finding is always informational;
 * it never authorizes anything and never touches business data. Scope is
 * carried on the finding so the display and the AI tool can restrict it to
 * the viewer's own tenant boundaries.
 */
final class FinancialFindingData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $type,
        public readonly string $typeLabel,
        public readonly string $severity,
        public readonly string $severityLabel,
        public readonly string $title,
        public readonly string $description,
        public readonly ?float $amount,
        public readonly string $currency,
        public readonly string $sourceType,
        public readonly ?int $sourceId,
        public readonly ?int $organizationId,
        public readonly ?int $branchId,
        public readonly ?int $memberId,
        public readonly ?int $loanId,
        public readonly string $detectedAt,
        public readonly string $status,
        public readonly string $statusLabel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->typeLabel,
            'severity' => $this->severity,
            'severity_label' => $this->severityLabel,
            'title' => $this->title,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'organization_id' => $this->organizationId,
            'branch_id' => $this->branchId,
            'member_id' => $this->memberId,
            'loan_id' => $this->loanId,
            'detected_at' => $this->detectedAt,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
        ];
    }
}
