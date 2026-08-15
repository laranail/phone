<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * How to actually dial a number from somewhere.
 *
 * E.164 is what you *store*; it is not always what you *dial*. Calling a UK number from Kenya means
 * `000 44 …`, from the United States `011 44 …`, and from inside the UK `020 …` — three different
 * strings for one stored value. Most applications never need this and then need it badly, usually
 * the moment somebody exports a click-to-call list for a call centre in another country.
 */
final readonly class PhoneDialler
{
    private PhoneNumberUtil $util;

    public function __construct(
        private PhoneFormatter $formatter = new PhoneFormatter,
        ?PhoneNumberUtil $util = null,
    ) {
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    /**
     * The digits to dial when calling from a given country.
     *
     * Includes that country's international dialling prefix where one is needed, and drops it
     * entirely for a domestic call.
     *
     * @param string $from ISO 3166-1 alpha-2 of the caller
     */
    public function from(?string $input, string $from, ?string $country = null): ?string
    {
        $parsed = $this->parse($input, $country);

        return $parsed === null
            ? null
            : $this->util->formatOutOfCountryCallingNumber($parsed, strtoupper($from));
    }

    /**
     * The form to hand a mobile handset.
     *
     * Differs from E.164 for a handful of plans where a mobile needs a prefix or an extra digit that
     * the canonical form omits — libphonenumber knows which, and guessing produces a number that
     * dials on a landline and fails on a phone.
     *
     * @param bool $withFormatting Keep the spacing a human would read, rather than digits only
     */
    public function forMobile(?string $input, string $from, ?string $country = null, bool $withFormatting = false): ?string
    {
        $parsed = $this->parse($input, $country);

        return $parsed === null
            ? null
            : $this->util->formatNumberForMobileDialing($parsed, strtoupper($from), $withFormatting);
    }

    /**
     * Whether the number can be reached from outside its own country at all.
     *
     * False for short codes, most premium and service numbers, and some domestic-only ranges. Worth
     * checking before putting a number in front of an international audience, because the failure is
     * silent: the call simply does not connect.
     */
    public function isInternationallyDiallable(?string $input, ?string $country = null): bool
    {
        $parsed = $this->parse($input, $country);

        return $parsed !== null && $this->util->canBeInternationallyDialled($parsed);
    }

    /**
     * The number as originally written, where that is recoverable.
     *
     * Useful for showing a user back what they typed rather than what you decided it meant.
     */
    public function asEntered(?string $input, ?string $country = null): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        try {
            $parsed = $this->util->parseAndKeepRawInput($input, $country === null ? null : strtoupper($country));
        } catch (NumberParseException) {
            return $input;
        }

        return $this->util->formatInOriginalFormat($parsed, $country === null ? 'ZZ' : strtoupper($country));
    }

    /**
     * Drop digits from the end until the number is a possible length.
     *
     * For cleaning imports where a field ran into the next one. Returns null when nothing survives —
     * truncating to something *possible* is not the same as truncating to something *correct*, and a
     * caller should treat the result as a candidate rather than a fix.
     */
    public function truncate(?string $input, ?string $country = null): ?string
    {
        $parsed = $this->parse($input, $country);

        if ($parsed === null) {
            return null;
        }

        return $this->util->truncateTooLongNumber($parsed)
            ? $this->util->format($parsed, PhoneNumberFormat::E164)
            : null;
    }

    private function parse(?string $input, ?string $country): ?PhoneNumber
    {
        $value = $this->formatter->parse($input, $country);

        if ($value->e164 === null) {
            return null;
        }

        try {
            return $this->util->parse($value->e164, null);
        } catch (NumberParseException) {
            return null;
        }
    }
}
