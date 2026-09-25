<?php

namespace App\Enums;

enum AiKnowledgeDocumentType: string
{
    case Constitution = 'constitution';
    case LoanPolicy = 'loan_policy';
    case SavingsPolicy = 'savings_policy';
    case SharePolicy = 'share_policy';
    case WelfarePolicy = 'welfare_policy';
    case AccountingPolicy = 'accounting_policy';
    case MemberHandbook = 'member_handbook';
    case StaffManual = 'staff_manual';
    case Faq = 'faq';
    case Procedure = 'procedure';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Constitution => 'Constitution',
            self::LoanPolicy => 'Loan Policy',
            self::SavingsPolicy => 'Savings Policy',
            self::SharePolicy => 'Share Policy',
            self::WelfarePolicy => 'Welfare Policy',
            self::AccountingPolicy => 'Accounting Policy',
            self::MemberHandbook => 'Member Handbook',
            self::StaffManual => 'Staff Manual',
            self::Faq => 'FAQ',
            self::Procedure => 'Procedure',
            self::General => 'General',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}