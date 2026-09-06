<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Enums;

use libphonenumber\MatchType;

/**
 * How closely two numbers match.
 *
 * String equality is the wrong question for phone numbers: `+254712123456`, `0712 123456` and
 * `712123456` may be the same subscriber written three ways, and whether they *are* depends on how
 * much country context each one carries. This is that answer, graded.
 */
enum MatchStrength: string
{
    /** At least one side is not a phone number at all. */
    case NotANumber = 'NOT_A_NUMBER';

    case NoMatch = 'NO_MATCH';

    /** The national numbers agree, but one is a suffix of the other — an area code may be missing. */
    case ShortNsnMatch = 'SHORT_NSN_MATCH';

    /** The national significant numbers are identical; the country codes were not both present. */
    case NsnMatch = 'NSN_MATCH';

    /** Same country code and same national number. The only unambiguous yes. */
    case ExactMatch = 'EXACT_MATCH';

    public static function fromLibPhoneNumber(MatchType $type): self
    {
        return match ($type) {
            MatchType::NOT_A_NUMBER    => self::NotANumber,
            MatchType::NO_MATCH        => self::NoMatch,
            MatchType::SHORT_NSN_MATCH => self::ShortNsnMatch,
            MatchType::NSN_MATCH       => self::NsnMatch,
            MatchType::EXACT_MATCH     => self::ExactMatch,
        };
    }

    /**
     * Whether to treat the two as the same number.
     *
     * `NsnMatch` counts, `ShortNsnMatch` does not. A missing country code is usually context the
     * caller already has; a missing *area* code means the two could be different subscribers in
     * different cities, and deduplicating on that would merge strangers.
     */
    public function isSame(): bool
    {
        return $this === self::ExactMatch || $this === self::NsnMatch;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotANumber    => 'Not a number',
            self::NoMatch       => 'Different numbers',
            self::ShortNsnMatch => 'Possibly the same, area code missing',
            self::NsnMatch      => 'Same national number',
            self::ExactMatch    => 'Identical',
        };
    }
}
