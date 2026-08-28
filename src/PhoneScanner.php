<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberMatch;
use libphonenumber\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Support\PhoneMatch;
use Simtabi\Laranail\Phone\Enums\MatchLeniency;

/**
 * Finds phone numbers inside free text.
 *
 * The one part of libphonenumber that solves a problem no amount of parsing does: given a support
 * ticket, a CV, a scraped page or a chat log, *where* are the numbers and what are they. Parsing
 * answers "is this string a number"; this answers "which parts of this string are".
 *
 * It is not a regex over digits, and that distinction is the whole value. `Call me on 0712 123456
 * about invoice 2024-00123` contains one phone number and one invoice reference, and a digit-run
 * pattern cannot tell them apart — this can, because it checks each candidate against the numbering
 * plan before accepting it.
 *
 * Every match carries its byte offset, so the caller can highlight, redact or link in place rather
 * than search for the text again.
 */
final readonly class PhoneScanner
{
    private PhoneNumberUtil $util;

    public function __construct(
        private ?string $defaultCountry = null,
        private MatchLeniency $leniency = MatchLeniency::Valid,
        private int $limit = PHP_INT_MAX,
        ?PhoneNumberUtil $util = null,
    ) {
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    /**
     * Every number in the text, in the order it appears.
     *
     * @param string|null $country ISO 3166-1 alpha-2 the text is assumed to be written from. Numbers
     *                             carrying their own calling code are found regardless; this decides
     *                             whether a bare national number is recognised at all.
     *
     * @return list<PhoneMatch>
     */
    public function scan(?string $text, ?string $country = null, ?MatchLeniency $leniency = null): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $region = $country ?? $this->defaultCountry;
        $matches = [];

        $found = $this->util->findNumbers(
            $text,
            $region,
            ($leniency ?? $this->leniency)->toLibPhoneNumber(),
            $this->limit,
        );

        foreach ($found as $match) {
            // The matcher is an Iterator whose current() is nullable by contract, even though a
            // completed foreach never yields null in practice. Guarding is cheaper than assuming.
            if (! $match instanceof PhoneNumberMatch) {
                continue;
            }

            $matches[] = new PhoneMatch(
                raw: $match->rawString(),
                offset: $match->start(),
                number: $this->hydrate($match->number()),
            );
        }

        return $matches;
    }

    /**
     * Replace every number found with the result of a callback.
     *
     * Written in reverse offset order on purpose: replacing forwards shifts every later offset by
     * the difference in length, and the second replacement lands in the wrong place. Going backwards
     * means each offset is still valid when it is used.
     *
     * @param callable(PhoneMatch): string $replace
     */
    public function replace(?string $text, callable $replace, ?string $country = null): ?string
    {
        if ($text === null) {
            return null;
        }

        $matches = $this->scan($text, $country);

        foreach (array_reverse($matches) as $match) {
            $text = substr_replace($text, $replace($match), $match->offset, strlen($match->raw));
        }

        return $text;
    }

    /**
     * Redact every number found.
     *
     * The default replacement keeps the calling code, because a redaction that removes the country
     * usually removes the reason the text was worth keeping.
     */
    public function redact(?string $text, ?string $country = null, string $maskChar = '•'): ?string
    {
        return $this->replace(
            $text,
            static fn (PhoneMatch $match): string => $match->number->masked($maskChar),
            $country,
        );
    }

    private function hydrate(PhoneNumber $parsed): PhoneNumberValue
    {
        return app(PhoneFormatter::class)->parse(
            $this->util->format($parsed, PhoneNumberFormat::E164),
        );
    }
}
