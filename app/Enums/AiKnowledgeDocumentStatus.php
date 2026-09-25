<?php

namespace App\Enums;

enum AiKnowledgeDocumentStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Active = 'active';
    case Archived = 'archived';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Processing => 'Processing',
            self::Active => 'Active',
            self::Archived => 'Archived',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Processing => 'warning',
            self::Failed => 'danger',
            self::Archived => 'secondary',
            self::Draft => 'light',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}