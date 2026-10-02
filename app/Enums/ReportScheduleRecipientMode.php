<?php

namespace App\Enums;

/**
 * Who a scheduled management report is delivered to (Phase 12.1).
 *
 * Delivery always resolves to individual, currently authorized users, and the
 * user's report capability and tenant scope are re-verified immediately before
 * a notification is created — a schedule stores an audience *mode*, never a
 * frozen copy of a recipient list, so removing somebody's access immediately
 * stops their delivery instead of leaving a stale subscription behind.
 */
enum ReportScheduleRecipientMode: string
{
    case OrganizationManagers = 'organization_managers';

    case BranchManagers = 'branch_managers';

    case SpecificUsers = 'specific_users';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationManagers => 'Organization managers',
            self::BranchManagers => 'Branch managers',
            self::SpecificUsers => 'Specific authorized users',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OrganizationManagers => 'Everyone in the organization who currently holds the management reporting capability.',
            self::BranchManagers => 'Everyone assigned to the scheduled branch who currently holds the management reporting capability.',
            self::SpecificUsers => 'A chosen set of users, each re-verified for access on every run.',
        };
    }

    /**
     * Whether the mode carries an explicit recipient list on the schedule.
     */
    public function requiresRecipients(): bool
    {
        return $this === self::SpecificUsers;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
