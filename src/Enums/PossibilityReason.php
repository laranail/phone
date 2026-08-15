<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Enums;

use libphonenumber\ValidationResult;

/**
 * Why a number is not possible.
 *
 * `isPossible` answers yes or no; this answers *which way* it failed, which is the difference
 * between "your number is invalid" and "your number is one digit short". Only the second is
 * actionable, and it is the message a user can do something about.
 *
 * String-backed for the same reason as {@see PhoneNumberFormat}: an int in a log line, an API
 * response or a database column tells a reader nothing, and libphonenumber's own backing values are
 * an implementation detail that has already changed once.
 */
enum PossibilityReason: string
{
    case IsPossible = 'IS_POSSIBLE';

    /** Correctly shaped for the plan, but only dialable from inside its own area. */
    case IsPossibleLocalOnly = 'IS_POSSIBLE_LOCAL_ONLY';

    /** The `+` prefix is there but the digits after it match no calling code. */
    case InvalidCountryCode = 'INVALID_COUNTRY_CODE';

    case TooShort = 'TOO_SHORT';
    case TooLong = 'TOO_LONG';

    /** A length no plan allows — neither too short nor too long, simply not a length in use. */
    case InvalidLength = 'INVALID_LENGTH';

    public static function fromLibPhoneNumber(ValidationResult $result): self
    {
        return match ($result) {
            ValidationResult::IS_POSSIBLE => self::IsPossible,
            ValidationResult::IS_POSSIBLE_LOCAL_ONLY => self::IsPossibleLocalOnly,
            ValidationResult::INVALID_COUNTRY_CODE => self::InvalidCountryCode,
            ValidationResult::TOO_SHORT => self::TooShort,
            ValidationResult::TOO_LONG => self::TooLong,
            ValidationResult::INVALID_LENGTH => self::InvalidLength,
        };
    }

    /** Whether the number is usable, counting the local-only case as a no. */
    public function isPossible(): bool
    {
        return $this === self::IsPossible;
    }

    /**
     * Whether the user can fix this by editing what they typed.
     *
     * A length problem is a typo. An invalid calling code usually means they pasted something that
     * was never a phone number, and telling them to "check the length" is unhelpful.
     */
    public function isCorrectable(): bool
    {
        return match ($this) {
            self::TooShort, self::TooLong, self::InvalidLength => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::IsPossible => 'Possible',
            self::IsPossibleLocalOnly => 'Local only',
            self::InvalidCountryCode => 'Unknown calling code',
            self::TooShort => 'Too short',
            self::TooLong => 'Too long',
            self::InvalidLength => 'Not a valid length',
        };
    }
}
