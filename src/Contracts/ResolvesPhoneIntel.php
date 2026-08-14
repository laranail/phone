<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Contracts;

use Simtabi\Laranail\Phone\PhoneNumberValue;

/**
 * The three lookups that cost more than parsing does.
 *
 * Carrier, geographic description and timezone each load their own metadata files on first use,
 * keyed by number prefix — a cost worth paying on demand and not worth paying to construct a value
 * object nobody will ask. Splitting them behind this interface is also what lets
 * {@see PhoneNumberValue} stay a pure data class: it holds an implementation or it holds null, and
 * a value object built in a test needs neither.
 */
interface ResolvesPhoneIntel
{
    /**
     * The carrier a number's range was allocated to, or null when unknown.
     *
     * Allocation, not ownership — a ported number reports its original carrier.
     */
    public function carrier(PhoneNumberValue $number, ?string $locale = null): ?string;

    /** A human description of the number's geography, or null when it has none. */
    public function description(PhoneNumberValue $number, ?string $locale = null): ?string;

    /**
     * Candidate IANA timezones for the number.
     *
     * @return list<string>
     */
    public function timezones(PhoneNumberValue $number): array;
}
