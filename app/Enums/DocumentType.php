<?php

namespace App\Enums;

enum DocumentType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';
    case DrivingLicence = 'driving_licence';
    case VoterId = 'voter_id';
    case BusinessLicence = 'business_licence';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID',
            self::Passport => 'Passport',
            self::DrivingLicence => 'Driving Licence',
            self::VoterId => 'Voter ID',
            self::BusinessLicence => 'Business Licence',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
