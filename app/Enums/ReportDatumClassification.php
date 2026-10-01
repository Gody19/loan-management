<?php

namespace App\Enums;

/**
 * How a report datum was derived (Phase 12.0). The classification is the
 * contract that keeps the reporting layer honest: an advisory or a prediction is
 * never rendered or injected as an established fact, and every consumer (UI,
 * export, AI provider) reads the same explicit label.
 */
enum ReportDatumClassification: string
{
    /**
     * Directly derived from authoritative FinancePro records by an existing
     * reporting service.
     */
    case Fact = 'fact';

    /**
     * A deterministic comparison of two authoritative periods computed by the
     * reporting service (absolute/percentage change and direction).
     */
    case Trend = 'trend';

    /**
     * A Phase 11.8 statistical outlook, carried through with its own status,
     * confidence and data-quality metadata.
     */
    case Prediction = 'prediction';

    /**
     * A Phase 11.9 proactive insight, or an explicitly generated advisory
     * narrative, for a human decision.
     */
    case Advisory = 'advisory';

    public function label(): string
    {
        return match ($this) {
            self::Fact => 'Observed fact',
            self::Trend => 'Trend',
            self::Prediction => 'Prediction',
            self::Advisory => 'Advisory',
        };
    }

    /**
     * Bootstrap badge context so the UI never has to restate the mapping.
     */
    public function color(): string
    {
        return match ($this) {
            self::Fact => 'primary',
            self::Trend => 'info',
            self::Prediction => 'warning',
            self::Advisory => 'secondary',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
