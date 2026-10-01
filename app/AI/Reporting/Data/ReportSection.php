<?php

namespace App\AI\Reporting\Data;

use App\Enums\ReportDatumClassification;

/**
 * A named group of classified data in an intelligence report (Phase 12.0).
 *
 * A section owns four datum groups — facts, trends, predictions and advisories
 * — so a fact can never be filed under a prediction and an advisory can never
 * be filed as a fact. Every section may additionally declare explicit data
 * quality notes ("no previous comparable period", "prediction unavailable")
 * instead of silently omitting the missing data.
 */
final class ReportSection
{
    /**
     * @param  array<int, ReportDatum>  $facts
     * @param  array<int, ReportDatum>  $trends
     * @param  array<int, ReportDatum>  $predictions
     * @param  array<int, ReportDatum>  $advisories
     * @param  array<int, string>  $dataQuality  explicit, honest limitations
     * @param  array<int, array<string, mixed>>  $rows  optional tabular detail
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly array $facts = [],
        public readonly array $trends = [],
        public readonly array $predictions = [],
        public readonly array $advisories = [],
        public readonly array $dataQuality = [],
        public readonly array $rows = [],
    ) {}

    public function withDataQuality(string ...$notes): self
    {
        return new self(
            key: $this->key,
            title: $this->title,
            facts: $this->facts,
            trends: $this->trends,
            predictions: $this->predictions,
            advisories: $this->advisories,
            dataQuality: [...$this->dataQuality, ...$notes],
            rows: $this->rows,
        );
    }

    /**
     * @param  array<int, ReportDatum>  $predictions
     */
    public function withPredictions(array $predictions): self
    {
        return new self(
            key: $this->key,
            title: $this->title,
            facts: $this->facts,
            trends: $this->trends,
            predictions: [...$this->predictions, ...$predictions],
            advisories: $this->advisories,
            dataQuality: $this->dataQuality,
            rows: $this->rows,
        );
    }

    /**
     * @param  array<int, ReportDatum>  $advisories
     */
    public function withAdvisories(array $advisories): self
    {
        return new self(
            key: $this->key,
            title: $this->title,
            facts: $this->facts,
            trends: $this->trends,
            predictions: $this->predictions,
            advisories: [...$this->advisories, ...$advisories],
            dataQuality: $this->dataQuality,
            rows: $this->rows,
        );
    }

    /**
     * A section is empty only when it carries no datum, no tabular detail and no
     * explicit data-quality limitation. A section holding just a data-quality
     * note ("prediction unavailable", "no previous comparable period") is
     * deliberately NOT empty: dropping it would silently hide the very
     * limitation the reporting layer exists to disclose.
     */
    public function isEmpty(): bool
    {
        return $this->facts === []
            && $this->trends === []
            && $this->predictions === []
            && $this->advisories === []
            && $this->rows === []
            && $this->dataQuality === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'facts' => array_map(fn (ReportDatum $datum) => $datum->toArray(), $this->facts),
            'trends' => array_map(fn (ReportDatum $datum) => $datum->toArray(), $this->trends),
            'predictions' => array_map(fn (ReportDatum $datum) => $datum->toArray(), $this->predictions),
            'advisories' => array_map(fn (ReportDatum $datum) => $datum->toArray(), $this->advisories),
            'data_quality' => $this->dataQuality,
            'rows' => $this->rows,
            'counts' => [
                ReportDatumClassification::Fact->value => count($this->facts),
                ReportDatumClassification::Trend->value => count($this->trends),
                ReportDatumClassification::Prediction->value => count($this->predictions),
                ReportDatumClassification::Advisory->value => count($this->advisories),
            ],
        ];
    }
}
