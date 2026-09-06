<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\ShortNumberInfo;
use libphonenumber\NumberParseException;
use Simtabi\Laranail\Phone\Enums\ShortNumberCost;

/**
 * Short codes: 999, 112, 40404, and the premium-rate ones that cost money to dial.
 *
 * A separate body of metadata from the main numbering plans, and a separate question. `999` is not a
 * valid phone number by any normal test and is nonetheless the most important number in the country
 * — so a contact form that only asks `isValidNumber()` rejects it, and a contact form that accepts
 * everything stores it as a customer's mobile.
 *
 * Two things here are worth reaching for by name. {@see connectsToEmergency()}, because an emergency
 * number sitting in a field that something later dials automatically is an incident. And
 * {@see cost()}, because "is a short code" does not distinguish a free helpline from a premium line
 * that bills the caller per minute.
 */
final readonly class ShortNumbers
{
    private ShortNumberInfo $info;

    private PhoneNumberUtil $util;

    public function __construct(?ShortNumberInfo $info = null, ?PhoneNumberUtil $util = null)
    {
        $this->info = $info ?? ShortNumberInfo::getInstance();
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    /** Whether the input is a short code that region actually uses. */
    public function isValid(?string $input, string $region): bool
    {
        $parsed = $this->parse($input, $region);

        return $parsed !== null && $this->info->isValidShortNumberForRegion($parsed, strtoupper($region));
    }

    /** Whether it is merely short enough to be one, allocated or not. */
    public function isPossible(?string $input, string $region): bool
    {
        $parsed = $this->parse($input, $region);

        return $parsed !== null && $this->info->isPossibleShortNumberForRegion($parsed, strtoupper($region));
    }

    /**
     * Whether dialling this reaches emergency services.
     *
     * Deliberately the looser of libphonenumber's two checks. It matches a number that *begins* with
     * an emergency code, because on most networks dialling `112` followed by anything still connects
     * — so treating `1121` as safe would be wrong in the one direction that matters.
     */
    public function connectsToEmergency(?string $input, string $region): bool
    {
        $digits = $this->digits($input);

        return $digits !== null && $this->info->connectsToEmergencyNumber($digits, strtoupper($region));
    }

    /** Whether it is exactly an emergency number, rather than merely starting with one. */
    public function isEmergency(?string $input, string $region): bool
    {
        $digits = $this->digits($input);

        return $digits !== null && $this->info->isEmergencyNumber($digits, strtoupper($region));
    }

    /** What dialling it costs the caller. */
    public function cost(?string $input, string $region): ?ShortNumberCost
    {
        $parsed = $this->parse($input, $region);

        return $parsed === null
            ? null
            : ShortNumberCost::fromLibPhoneNumber($this->info->getExpectedCostForRegion($parsed, strtoupper($region)));
    }

    /** Whether it only works on one carrier's network. */
    public function isCarrierSpecific(?string $input, string $region): bool
    {
        $parsed = $this->parse($input, $region);

        return $parsed !== null && $this->info->isCarrierSpecificForRegion($parsed, strtoupper($region));
    }

    /** Whether it accepts SMS rather than only voice. */
    public function acceptsSms(?string $input, string $region): bool
    {
        $parsed = $this->parse($input, $region);

        return $parsed !== null && $this->info->isSmsServiceForRegion($parsed, strtoupper($region));
    }

    /** A real short code for the region, for tests and placeholders. */
    public function example(string $region): ?string
    {
        $example = $this->info->getExampleShortNumber(strtoupper($region));

        return $example === '' ? null : $example;
    }

    private function parse(?string $input, string $region): ?PhoneNumber
    {
        $digits = $this->digits($input);

        if ($digits === null) {
            return null;
        }

        try {
            return $this->util->parse($digits, strtoupper($region));
        } catch (NumberParseException) {
            return null;
        }
    }

    private function digits(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = trim($input);

        return $digits === '' ? null : $digits;
    }
}
