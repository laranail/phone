<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use Throwable;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PossibilityReason;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;

/**
 * The single point of contact with libphonenumber.
 *
 * Nothing else in this package may `use libphonenumber\*` beyond the two enums' bridge methods. That
 * is not tidiness — it is what makes a libphonenumber major upgrade a one-file change. Version 9
 * turned `PhoneNumberFormat` and `PhoneNumberType` from classes of `const` ints into native enums,
 * and the packages surveyed for this design each carry an ad-hoc shim for that break in whichever
 * file happened to need it first.
 *
 * ### Parsing never throws
 *
 * Every path here degrades: strict parse, then lenient parse, then hand back the input untouched
 * inside an invalid {@see PhoneNumberValue}. `NumberParseException` is the single most reported
 * exception across all three upstream packages, and it is reported because they let it escape into
 * request handling — where a user typing `+` into an empty field is enough to raise it.
 */
final readonly class PhoneFormatter
{
    private PhoneNumberUtil $util;

    public function __construct(
        private PhoneNormalizer $normalizer = new PhoneNormalizer,
        private ?ResolvesPhoneIntel $intel = null,
        private ?string $defaultCountry = null,
        ?PhoneNumberUtil $util = null,
    ) {
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    /**
     * Parse anything into a value object.
     *
     * @param string|null $country ISO 3166-1 alpha-2 hint. Only consulted when the input does not
     *                             already carry its own country code, so passing the wrong one
     *                             cannot corrupt a number written in E.164.
     */
    public function parse(?string $input, ?string $country = null): PhoneNumberValue
    {
        $normalized = $this->normalizer->normalize($input);

        if ($normalized === null) {
            return PhoneNumberValue::empty($input ?? '');
        }

        $region = $this->resolveRegion($normalized, $country);
        $failure = null;
        $parsed = $this->attemptParse($normalized, $region, $failure);

        if (! $parsed instanceof LibPhoneNumber) {
            return new PhoneNumberValue(
                raw: $normalized,
                intel: $this->intel,
                failure: $failure === null ? null : PossibilityReason::fromParseException($failure),
            );
        }

        return $this->hydrate($normalized, $parsed);
    }

    /**
     * Format a raw value directly, returning the input unchanged when it cannot be parsed.
     *
     * The convenience wrapper over `parse()->format()`, for the common case of rendering a stored
     * value in a column or an entry.
     */
    public function format(
        ?string $input,
        PhoneNumberFormat $format = PhoneNumberFormat::E164,
        ?string $country = null,
    ): ?string {
        if ($input === null || trim($input) === '') {
            return null;
        }

        return $this->parse($input, $country)->format($format);
    }

    /** The canonical storage form, or null when there is nothing parseable. */
    public function toE164(?string $input, ?string $country = null): ?string
    {
        return $this->parse($input, $country)->e164;
    }

    /**
     * An example number for a country, for placeholders, masks, factories and seeders.
     *
     * These come from libphonenumber's own metadata, so they are correctly shaped and guaranteed not
     * to belong to anybody.
     */
    public function example(string $country, PhoneNumberType $type = PhoneNumberType::Mobile): ?PhoneNumberValue
    {
        try {
            $example = $this->util->getExampleNumberForType(strtoupper($country), $type->toLibPhoneNumber());
        } catch (Throwable) {
            return null;
        }

        if (! $example instanceof LibPhoneNumber) {
            return null;
        }

        return $this->hydrate(
            $this->util->format($example, PhoneNumberFormat::E164->toLibPhoneNumber()),
            $example,
        );
    }

    /**
     * The country calling code for a region, e.g. `KE` → `254`.
     *
     * Returns null rather than libphonenumber's `0` sentinel for an unknown region, so the absence is
     * not mistaken for a real code in a numeric context.
     */
    public function callingCodeFor(string $country): ?int
    {
        $code = $this->util->getCountryCodeForRegion(strtoupper($country));

        return $code === 0 ? null : $code;
    }

    /**
     * Decide which region to parse against.
     *
     * A number already in international form carries its own, so no hint is consulted — this is what
     * makes passing a wrong `$country` harmless for E.164 input. Otherwise the explicit hint wins,
     * then the configured default.
     */
    private function resolveRegion(string $normalized, ?string $country): ?string
    {
        if (str_starts_with($normalized, '+')) {
            return null;
        }

        $region = $country ?? $this->defaultCountry;

        return $region === null ? null : strtoupper($region);
    }

    /**
     * Strict parse, then lenient, then give up quietly.
     *
     * The lenient pass (`keepRawInput: true`) accepts numbers libphonenumber considers structurally
     * off but can still make sense of. It matters most for freshly allocated ranges, which are
     * parseable long before Google's metadata catches up.
     */
    private function attemptParse(string $value, ?string $region, ?NumberParseException &$failure = null): ?LibPhoneNumber
    {
        try {
            return $this->util->parse($value, $region);
        } catch (NumberParseException) {
            // Fall through to the lenient attempt.
        }

        try {
            return $this->util->parse($value, $region, null, true);
        } catch (NumberParseException $exception) {
            // Kept, not swallowed. It carries the only account of *why* the string is not a number,
            // and the value object has nowhere else to get one from — an unparseable input leaves no
            // parsed object to interrogate afterwards.
            $failure = $exception;

            return null;
        }
    }

    /** Build the value object from a successfully parsed number. */
    private function hydrate(string $raw, LibPhoneNumber $parsed): PhoneNumberValue
    {
        $isValid = $this->util->isValidNumber($parsed);
        $isPossible = $this->util->isPossibleNumber($parsed);

        return new PhoneNumberValue(
            raw: $raw,
            e164: $this->util->format($parsed, PhoneNumberFormat::E164->toLibPhoneNumber()),
            national: $this->util->format($parsed, PhoneNumberFormat::National->toLibPhoneNumber()),
            international: $this->util->format($parsed, PhoneNumberFormat::International->toLibPhoneNumber()),
            rfc3966: $this->util->format($parsed, PhoneNumberFormat::Rfc3966->toLibPhoneNumber()),
            country: $this->util->getRegionCodeForNumber($parsed),
            extension: $parsed->hasExtension() ? $parsed->getExtension() : null,
            callingCode: $parsed->getCountryCode(),
            type: PhoneNumberType::fromLibPhoneNumber($this->util->getNumberType($parsed)),
            isValid: $isValid,
            isPossible: $isPossible,
            intel: $this->intel,
        );
    }
}
