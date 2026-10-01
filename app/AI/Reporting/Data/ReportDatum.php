<?php

namespace App\AI\Reporting\Data;

use App\Enums\ReportDatumClassification;
use App\Enums\ReportTrendDirection;

/**
 * One classified datum of an intelligence report (Phase 12.0).
 *
 * A datum is the smallest unit a report can carry. Every datum declares how it
 * was derived, which makes it impossible to present an advisory or a
 * prediction as an established fact: the classification travels with the value
 * into the UI, the export and the AI narrative context alike.
 *
 * Facts and trends are always computed by the deterministic reporting service
 * from authoritative FinancePro records. Predictions and advisories are
 * carried through from the Phase 11.8 / Phase 11.9 services unchanged.
 */
final class ReportDatum
{
    /**
     * @param  string  $key  stable identifier, unique within its section
     * @param  string  $label  human label, always present (never a bare number)
     * @param  float|int|string|null  $value  the authoritative value
     * @param  string  $format  presentation hint: money|percent|integer|decimal|text|boolean|date
     * @param  string  $source  the authoritative service or record the value came from
     */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly float|int|string|null $value,
        public readonly ReportDatumClassification $classification,
        public readonly string $format,
        public readonly string $source,
        public readonly ?string $unit = null,
        public readonly ?float $previousValue = null,
        public readonly ?float $absoluteChange = null,
        public readonly ?float $percentageChange = null,
        public readonly ?ReportTrendDirection $direction = null,
        public readonly ?string $note = null,
        public readonly array $meta = [],
    ) {}

    /**
     * A value read directly from an authoritative FinancePro record or an
     * existing reporting service.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function fact(
        string $key,
        string $label,
        float|int|string|null $value,
        string $format,
        string $source,
        ?string $note = null,
        array $meta = [],
        ?string $unit = null,
    ): self {
        return new self(
            key: $key,
            label: $label,
            value: $value,
            classification: ReportDatumClassification::Fact,
            format: $format,
            source: $source,
            unit: $unit,
            note: $note,
            meta: $meta,
        );
    }

    /**
     * A deterministic comparison of the reporting period against the previous
     * comparable period. Computed here (never by the AI). When the previous
     * period has no measurable value the direction is `unavailable` and no
     * percentage is manufactured — zero is never substituted for missing data.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function trend(
        string $key,
        string $label,
        float|int|string|null $value,
        string $format,
        string $source,
        ?float $previousValue = null,
        ?string $note = null,
        array $meta = [],
        ?string $unit = null,
    ): self {
        [$absolute, $percentage, $direction] = self::compare(
            is_numeric($value) ? (float) $value : null,
            $previousValue,
        );

        return new self(
            key: $key,
            label: $label,
            value: $value,
            classification: ReportDatumClassification::Trend,
            format: $format,
            source: $source,
            unit: $unit,
            previousValue: $previousValue,
            absoluteChange: $absolute,
            percentageChange: $percentage,
            direction: $direction,
            note: $note,
            meta: $meta,
        );
    }

    /**
     * A Phase 11.8 statistical outlook, carried through with its own status,
     * confidence, data-quality and window metadata so the report can never
     * state more than Phase 11.8 supports.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function prediction(
        string $key,
        string $label,
        string $source,
        ?string $note = null,
        array $meta = [],
    ): self {
        return new self(
            key: $key,
            label: $label,
            value: null,
            classification: ReportDatumClassification::Prediction,
            format: 'text',
            source: $source,
            note: $note,
            meta: $meta,
        );
    }

    /**
     * A Phase 11.9 proactive insight, carried through with the advisory caveat
     * intact. The lifecycle (acknowledge / resolve / dismiss) stays a human
     * dashboard action and is never presented as required by the AI.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function advisory(
        string $key,
        string $label,
        string $source,
        ?string $note = null,
        array $meta = [],
    ): self {
        return new self(
            key: $key,
            label: $label,
            value: null,
            classification: ReportDatumClassification::Advisory,
            format: 'text',
            source: $source,
            note: $note,
            meta: $meta,
        );
    }

    /**
     * Deterministic comparison. Returns [absolute change, percentage change,
     * direction]. A null current value, or a previous value that is absent or
     * zero, yields an `unavailable` direction with no percentage — the report
     * says it cannot compare rather than showing a fabricated 0%.
     *
     * @return array{0: ?float, 1: ?float, 2: ReportTrendDirection}
     */
    private static function compare(?float $current, ?float $previous): array
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return [null, null, ReportTrendDirection::Unavailable];
        }

        $absolute = round($current - $previous, 2);
        $percentage = round((($current - $previous) / abs($previous)) * 100, 2);

        $direction = match (true) {
            $absolute > 0 => ReportTrendDirection::Up,
            $absolute < 0 => ReportTrendDirection::Down,
            default => ReportTrendDirection::Flat,
        };

        return [$absolute, $percentage, $direction];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'classification' => $this->classification->value,
            'classification_label' => $this->classification->label(),
            'format' => $this->format,
            'source' => $this->source,
            'unit' => $this->unit,
            'previous_value' => $this->previousValue,
            'absolute_change' => $this->absoluteChange,
            'percentage_change' => $this->percentageChange,
            'direction' => $this->direction?->value,
            'direction_label' => $this->direction?->label(),
            'note' => $this->note,
            'meta' => $this->meta,
        ];
    }
}
