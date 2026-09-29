<?php

namespace App\Enums;

enum DocumentType: string
{
    case Photo = 'photo';
    case AadhaarFront = 'aadhaar_front';
    case AadhaarBack = 'aadhaar_back';
    case Sslc = 'sslc';
    case EducationCert = 'education_cert';
    case SkillCert = 'skill_cert';
    case ExperienceCert = 'experience_cert';
    case Passport = 'passport';
    case Cv = 'cv';
    case ProfilePdf = 'profile_pdf';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }

    /**
     * Whether a candidate may upload more than one file of this type.
     */
    public static function allowsMultiple(self $type): bool
    {
        return in_array($type, [
            self::EducationCert,
            self::SkillCert,
            self::ExperienceCert,
            self::Passport,
        ], true);
    }

    /**
     * Document types that always count towards profile completeness.
     * The passport is handled conditionally on has_passport elsewhere.
     *
     * @return list<self>
     */
    public static function requiredForCompleteness(): array
    {
        return [
            self::Photo,
            self::AadhaarFront,
            self::AadhaarBack,
            self::Sslc,
        ];
    }

    /**
     * Maximum accepted upload size in bytes for this type.
     */
    public static function maxSizeBytes(self $type): int
    {
        return $type === self::ProfilePdf
            ? 50 * 1024 * 1024
            : 10 * 1024 * 1024;
    }
}
