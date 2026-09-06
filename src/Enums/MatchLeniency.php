<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Enums;

use libphonenumber\Leniency;
use libphonenumber\Leniency\AbstractLeniency;

/**
 * How hard a scan over free text tries to call something a phone number.
 *
 * The trade is false positives against misses, and the right point on it depends entirely on the
 * text. A support ticket should be scanned leniently; a legal document being redacted should not,
 * because a wrongly-matched invoice number is a redaction that destroys information.
 */
enum MatchLeniency: string
{
    /** Anything that could be a number. Highest recall, most false positives. */
    case Possible = 'POSSIBLE';

    /** Must be a valid number for some region. The sensible default. */
    case Valid = 'VALID';

    /** Valid, and grouped the way that region groups numbers. */
    case StrictGrouping = 'STRICT_GROUPING';

    /** Valid, and grouped exactly as the region's own formatting would print it. */
    case ExactGrouping = 'EXACT_GROUPING';

    public function toLibPhoneNumber(): AbstractLeniency
    {
        return match ($this) {
            self::Possible       => Leniency::POSSIBLE(),
            self::Valid          => Leniency::VALID(),
            self::StrictGrouping => Leniency::STRICT_GROUPING(),
            self::ExactGrouping  => Leniency::EXACT_GROUPING(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Possible       => 'Possible',
            self::Valid          => 'Valid',
            self::StrictGrouping => 'Strictly grouped',
            self::ExactGrouping  => 'Exactly grouped',
        };
    }
}
