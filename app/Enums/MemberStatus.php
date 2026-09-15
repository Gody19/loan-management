<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
    case Exited = 'exited';
    case Blacklisted = 'blacklisted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Inactive => 'Inactive',
            self::Exited => 'Exited',
            self::Blacklisted => 'Blacklisted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Inactive => 'secondary',
            self::Exited => 'info',
            self::Blacklisted => 'dark',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Pending => in_array($newStatus, [self::Active, self::Inactive, self::Exited]),
            self::Active => in_array($newStatus, [self::Suspended, self::Inactive, self::Exited]),
            self::Suspended => in_array($newStatus, [self::Active, self::Inactive, self::Exited]),
            self::Inactive => in_array($newStatus, [self::Active, self::Exited]),
            self::Exited => false,
            self::Blacklisted => false,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
