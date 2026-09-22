<?php

namespace App\Enums;

enum CollateralDocumentType: string
{
    case OwnershipDocument = 'ownership_document';
    case RegistrationCard = 'registration_card';
    case ValuationReport = 'valuation_report';
    case InsuranceCertificate = 'insurance_certificate';
    case Photographs = 'photographs';
    case TitleDocument = 'title_document';
    case SurveyDocument = 'survey_document';
    case PurchaseReceipt = 'purchase_receipt';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OwnershipDocument => 'Ownership Document',
            self::RegistrationCard => 'Registration Card',
            self::ValuationReport => 'Valuation Report',
            self::InsuranceCertificate => 'Insurance Certificate',
            self::Photographs => 'Photographs',
            self::TitleDocument => 'Title Document',
            self::SurveyDocument => 'Survey Document',
            self::PurchaseReceipt => 'Purchase Receipt',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
