<?php

namespace App\Enums;

/**
 * Tenant visibility of an approved knowledge document.
 *
 * Scope precedence is hierarchical: GLOBAL is visible to every authorized AI
 * user; ORGANIZATION narrows to a single organization; BRANCH narrows further
 * to a branch of an organization; GROUP narrows to a VICOBA group of a branch.
 * PUBLIC is reserved for the landing-page assistant: it is visible to any
 * visitor (no signed-in user) and stores no tenant columns, matching GLOBAL.
 *
 * The stored scope columns mirror the hierarchy: global documents keep all
 * three tenant columns null; group documents fill all three; branch documents
 * leave vicoba_group_id null; organization documents leave branch_id null.
 */
enum AiKnowledgeScope: string
{
    case Public = 'public';
    case Global = 'global';
    case Organization = 'organization';
    case Branch = 'branch';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Global => 'Global',
            self::Organization => 'Organization',
            self::Branch => 'Branch',
            self::Group => 'Group',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
