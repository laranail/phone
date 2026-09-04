<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use Stringable;
use JsonSerializable;
use InvalidArgumentException;
use Simtabi\Laranail\Phone\Enums\MatchStrength;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\Enums\ShortNumberCost;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PossibilityReason;

/**
 * The fluent entry point: say what you have, then ask what you want.
 *
 * ```php
 * Phone::of('0712 123456')->country('KE')->e164();            // '+254712123456'
 * Phone::of($stored)->dialFrom('GB');                          // '00 254 712 123456'
 * Phone::of($stored)->masked();                                // '+254•••••••56'
 * Phone::of($input)->country('KE')->why()->isCorrectable();     // true for a typo
 * ```
 *
 * ## Why a builder rather than more arguments
 *
 * The engine underneath — {@see PhoneFormatter}, {@see PhoneDialler}, {@see ShortNumbers},
 * {@see PhoneCatalogue} — is four objects with different constructor needs, and reaching the useful
 * combinations through them means resolving each one and threading the same country hint through
 * every call. `Phone::of($n)->country('KE')` says it once.
 *
 * It is a **narrowing** builder, not a mutating one: every configuration method returns a new
 * instance, so a partially-configured builder can be held and reused without a later call changing
 * what an earlier caller sees. That matters most where one is stored on a model or passed into a
 * view.
 *
 * The parse is performed once, lazily, on the first terminal read and memoised — so chaining ten
 * accessors costs one parse, and building a chain you never read costs none.
 */
final class PhoneBuilder implements JsonSerializable, Stringable
{
    private ?PhoneNumberValue $resolved = null;

    public function __construct(
        private readonly ?string $input,
        private readonly ?string $country = null,
        private readonly PhoneNumberType $type = PhoneNumberType::Mobile,
    ) {}

    public function __toString(): string
    {
        return (string) $this->value();
    }

    // ---------------------------------------------------------------- narrowing

    /**
     * The country to parse a bare national number against.
     *
     * Only ever consulted when the input does not already carry its own calling code, so passing the
     * wrong one cannot corrupt a value written in E.164.
     */
    public function country(?string $iso2): self
    {
        return new self($this->input, $iso2 === null ? null : strtoupper($iso2), $this->type);
    }

    /** Reads better than `country()` when the country describes the caller rather than the number. */
    public function from(?string $iso2): self
    {
        return $this->country($iso2);
    }

    /** The line type used by {@see example()} and by the mask and placeholder helpers. */
    public function type(PhoneNumberType $type): self
    {
        return new self($this->input, $this->country, $type);
    }

    public function mobile(): self
    {
        return $this->type(PhoneNumberType::Mobile);
    }

    public function fixedLine(): self
    {
        return $this->type(PhoneNumberType::FixedLine);
    }

    // ---------------------------------------------------------------- the value

    /** The parsed value object. Everything below is a convenience over this. */
    public function value(): PhoneNumberValue
    {
        return $this->resolved ??= $this->formatter()->parse($this->input, $this->country);
    }

    public function isEmpty(): bool
    {
        return $this->value()->isEmpty();
    }

    public function isValid(): bool
    {
        return $this->value()->isValid;
    }

    public function isPossible(): bool
    {
        return $this->value()->isPossible;
    }

    /** Why it is not possible — actionable where `isPossible()` is not. */
    public function why(): PossibilityReason
    {
        return $this->value()->possibility();
    }

    public function isType(PhoneNumberType ...$types): bool
    {
        $actual = $this->value()->type;

        foreach ($types as $type) {
            if ($actual === $type) {
                return true;
            }

            // The NANP publishes no mobile/fixed-line split, so every American number is reported as
            // the ambiguous case. Treating that as "not mobile" fails every valid US mobile.
            if ($actual === PhoneNumberType::FixedLineOrMobile
                && ($type === PhoneNumberType::Mobile || $type === PhoneNumberType::FixedLine)) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------- rendering

    public function e164(): ?string
    {
        return $this->value()->e164;
    }

    public function national(): ?string
    {
        return $this->value()->national;
    }

    public function international(): ?string
    {
        return $this->value()->international;
    }

    public function rfc3966(): ?string
    {
        return $this->value()->rfc3966;
    }

    public function format(PhoneNumberFormat $format = PhoneNumberFormat::E164): string
    {
        return $this->value()->format($format);
    }

    public function masked(string $maskChar = '•', ?int $keep = null): string
    {
        return $this->value()->masked($maskChar, $keep);
    }

    public function maskedByPercent(int $percent = 60, string $maskChar = '•'): string
    {
        return $this->value()->maskedByPercent($percent, $maskChar);
    }

    public function telLink(): ?string
    {
        return $this->value()->telLink();
    }

    public function whatsAppLink(?string $message = null): ?string
    {
        return $this->value()->whatsAppLink($message);
    }

    // ---------------------------------------------------------------- dialling

    /** The digits to dial when calling from a given country. */
    public function dialFrom(string $iso2): ?string
    {
        return $this->dialler()->from($this->input, $iso2, $this->country);
    }

    /** The form to hand a mobile handset, which is not always E.164. */
    public function forMobile(string $iso2, bool $withFormatting = false): ?string
    {
        return $this->dialler()->forMobile($this->input, $iso2, $this->country, $withFormatting);
    }

    public function isInternationallyDiallable(): bool
    {
        return $this->dialler()->isInternationallyDiallable($this->input, $this->country);
    }

    /** The number as originally written, where that is recoverable. */
    public function asEntered(): ?string
    {
        return $this->dialler()->asEntered($this->input, $this->country);
    }

    /** Drop trailing digits until the length is possible. A candidate, not a fix. */
    public function truncated(): ?string
    {
        return $this->dialler()->truncate($this->input, $this->country);
    }

    // ---------------------------------------------------------------- structure

    public function areaCode(): ?string
    {
        return $this->value()->areaCode();
    }

    public function nationalDestinationCode(): ?string
    {
        return $this->value()->nationalDestinationCode();
    }

    public function isGeographic(): bool
    {
        return $this->value()->isGeographic();
    }

    public function matches(PhoneNumberValue|string|null $other): MatchStrength
    {
        return $this->value()->matches($other);
    }

    public function is(PhoneNumberValue|string|null $other): bool
    {
        return $this->matches($other)->isSame();
    }

    // ---------------------------------------------------------------- by country

    /**
     * A real example number for the configured country and type.
     *
     * From libphonenumber's own metadata, so it is correctly shaped and belongs to nobody — which is
     * what makes it safe for placeholders, factories and seeders. An invented number usually is not:
     * `+15551234567` fails `isValid()`, so a fixture built from one tests the validator rather than
     * the feature.
     */
    public function example(): ?PhoneNumberValue
    {
        return $this->country === null ? null : $this->formatter()->example($this->country, $this->type);
    }

    /**
     * An input mask for the configured country, or null where one would be a lie.
     *
     * Null is a real answer. German mobile numbers are ten *or* eleven digits, so a ten-digit mask
     * silently swallows the eleventh keystroke and the user gets no feedback at all.
     */
    public function mask(): ?string
    {
        return $this->country === null ? null : app(MaskGenerator::class)->national($this->country, $this->type);
    }

    /** An example number to show as a placeholder. Available even where a mask is not. */
    public function placeholder(): ?string
    {
        return $this->country === null ? null : app(MaskGenerator::class)->placeholder($this->country, $this->type);
    }

    // ---------------------------------------------------------------- intel

    public function carrier(?string $locale = null): ?string
    {
        return $this->value()->carrier($locale);
    }

    public function description(?string $locale = null): ?string
    {
        return $this->value()->description($locale);
    }

    /** @return list<string> */
    public function timezones(): array
    {
        return $this->value()->timezones();
    }

    // ---------------------------------------------------------------- short codes

    /**
     * Short-code questions need the region stated, because there is no calling code to infer it
     * from — `999` is only meaningful once you know where it is being dialled.
     */
    public function isShortCode(?string $region = null): bool
    {
        return $this->shortNumbers()->isValid($this->input, $this->requireRegion($region));
    }

    public function connectsToEmergency(?string $region = null): bool
    {
        return $this->shortNumbers()->connectsToEmergency($this->input, $this->requireRegion($region));
    }

    public function shortCodeCost(?string $region = null): ?ShortNumberCost
    {
        return $this->shortNumbers()->cost($this->input, $this->requireRegion($region));
    }

    // ---------------------------------------------------------------- output

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->value()->toArray();
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->value()->jsonSerialize();
    }

    private function requireRegion(?string $region): string
    {
        $resolved = $region ?? $this->country;

        if ($resolved === null) {
            throw new InvalidArgumentException(
                'Short-code checks need a region: a short code carries no calling code, so there is '
                . 'nothing to infer one from. Pass it to the method or set it with ->country().',
            );
        }

        return strtoupper($resolved);
    }

    private function formatter(): PhoneFormatter
    {
        return app(PhoneFormatter::class);
    }

    private function dialler(): PhoneDialler
    {
        return app(PhoneDialler::class);
    }

    private function shortNumbers(): ShortNumbers
    {
        return app(ShortNumbers::class);
    }
}
