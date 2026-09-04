<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Enums;

use libphonenumber\ShortNumberCost as LibCost;

/**
 * What dialling a short code costs the caller.
 *
 * Worth surfacing rather than hiding behind a boolean: a premium-rate short code in a contact field
 * is a billing incident, and "is this a short number" does not distinguish it from an emergency
 * line.
 */
enum ShortNumberCost: string
{
    case TollFree = 'TOLL_FREE';
    case StandardRate = 'STANDARD_RATE';
    case PremiumRate = 'PREMIUM_RATE';
    case Unknown = 'UNKNOWN_COST';

    public static function fromLibPhoneNumber(LibCost $cost): self
    {
        return match ($cost) {
            LibCost::TOLL_FREE     => self::TollFree,
            LibCost::STANDARD_RATE => self::StandardRate,
            LibCost::PREMIUM_RATE  => self::PremiumRate,
            LibCost::UNKNOWN_COST  => self::Unknown,
        };
    }

    /** Whether dialling this could put a charge on the caller's bill beyond a normal call. */
    public function isChargeable(): bool
    {
        return $this === self::PremiumRate;
    }

    public function label(): string
    {
        return match ($this) {
            self::TollFree     => 'Toll free',
            self::StandardRate => 'Standard rate',
            self::PremiumRate  => 'Premium rate',
            self::Unknown      => 'Unknown cost',
        };
    }
}
