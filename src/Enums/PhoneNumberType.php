<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Enums;

use libphonenumber\PhoneNumberType as LibType;

/**
 * What kind of line a number is.
 *
 * String-backed for the same reason as {@see PhoneNumberFormat} — these values end up in config,
 * validation rule strings (`phone:mobile`) and JSON.
 *
 * The type is derived from the numbering plan, not from the line's current owner. Two consequences
 * worth stating before anyone relies on it:
 *
 * - **`FixedLineOrMobile` is a real answer, not a failure.** In the NANP (and much of the Caribbean)
 *   mobile and fixed-line numbers share ranges, so `+1 555 123 4567` is genuinely both. Code that
 *   treats "is it mobile?" as a boolean will reject valid US mobiles; use {@see isMobile()}, which
 *   counts the ambiguous case as a yes.
 * - **Ported numbers are reported under their original range.** A mobile ported to VoIP still reads
 *   as `Mobile`. No offline library can know otherwise; only an HLR lookup can.
 */
enum PhoneNumberType: string
{
    case FixedLine = 'FIXED_LINE';
    case Mobile = 'MOBILE';
    case FixedLineOrMobile = 'FIXED_LINE_OR_MOBILE';
    case TollFree = 'TOLL_FREE';
    case PremiumRate = 'PREMIUM_RATE';
    case SharedCost = 'SHARED_COST';
    case Voip = 'VOIP';
    case PersonalNumber = 'PERSONAL_NUMBER';
    case Pager = 'PAGER';
    case Uan = 'UAN';
    case Voicemail = 'VOICEMAIL';
    case Emergency = 'EMERGENCY';
    case ShortCode = 'SHORT_CODE';
    case StandardRate = 'STANDARD_RATE';
    case Unknown = 'UNKNOWN';

    public static function fromLibPhoneNumber(LibType $type): self
    {
        return match ($type) {
            LibType::FIXED_LINE => self::FixedLine,
            LibType::MOBILE => self::Mobile,
            LibType::FIXED_LINE_OR_MOBILE => self::FixedLineOrMobile,
            LibType::TOLL_FREE => self::TollFree,
            LibType::PREMIUM_RATE => self::PremiumRate,
            LibType::SHARED_COST => self::SharedCost,
            LibType::VOIP => self::Voip,
            LibType::PERSONAL_NUMBER => self::PersonalNumber,
            LibType::PAGER => self::Pager,
            LibType::UAN => self::Uan,
            LibType::VOICEMAIL => self::Voicemail,
            LibType::EMERGENCY => self::Emergency,
            LibType::SHORT_CODE => self::ShortCode,
            LibType::STANDARD_RATE => self::StandardRate,
            LibType::UNKNOWN => self::Unknown,
        };
    }

    public function toLibPhoneNumber(): LibType
    {
        return match ($this) {
            self::FixedLine => LibType::FIXED_LINE,
            self::Mobile => LibType::MOBILE,
            self::FixedLineOrMobile => LibType::FIXED_LINE_OR_MOBILE,
            self::TollFree => LibType::TOLL_FREE,
            self::PremiumRate => LibType::PREMIUM_RATE,
            self::SharedCost => LibType::SHARED_COST,
            self::Voip => LibType::VOIP,
            self::PersonalNumber => LibType::PERSONAL_NUMBER,
            self::Pager => LibType::PAGER,
            self::Uan => LibType::UAN,
            self::Voicemail => LibType::VOICEMAIL,
            self::Emergency => LibType::EMERGENCY,
            self::ShortCode => LibType::SHORT_CODE,
            self::StandardRate => LibType::STANDARD_RATE,
            self::Unknown => LibType::UNKNOWN,
        };
    }

    /**
     * Whether this number can receive an SMS.
     *
     * `FixedLineOrMobile` counts as yes — see the class docblock. Treating it as no is the single
     * most common way an implementation rejects valid North American mobiles.
     */
    public function isMobile(): bool
    {
        return $this === self::Mobile || $this === self::FixedLineOrMobile;
    }

    /** Whether calling this number may cost the caller more than a normal call. */
    public function isPremium(): bool
    {
        return $this === self::PremiumRate || $this === self::SharedCost;
    }

    /** A short human label. */
    public function label(): string
    {
        return match ($this) {
            self::FixedLine => 'Landline',
            self::Mobile => 'Mobile',
            self::FixedLineOrMobile => 'Landline or mobile',
            self::TollFree => 'Toll free',
            self::PremiumRate => 'Premium rate',
            self::SharedCost => 'Shared cost',
            self::Voip => 'VoIP',
            self::PersonalNumber => 'Personal number',
            self::Pager => 'Pager',
            self::Uan => 'Universal access number',
            self::Voicemail => 'Voicemail',
            self::Emergency => 'Emergency',
            self::ShortCode => 'Short code',
            self::StandardRate => 'Standard rate',
            self::Unknown => 'Unknown',
        };
    }
}
