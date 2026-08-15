<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use JsonSerializable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;
use Simtabi\Laranail\Phone\Enums\MatchStrength;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\Enums\PossibilityReason;
use Stringable;

/**
 * One phone number, parsed.
 *
 * Immutable and self-describing: every rendering is precomputed, so a template or a serialiser never
 * has to reach back to a formatter. Construct it through {@see PhoneFormatter::parse()} rather than
 * directly — the constructor takes already-decided values and does no parsing of its own.
 *
 * **An unparseable number still produces a value object.** `$isValid` is false, the renderings are
 * `null`, and `$raw` holds exactly what the user typed. This is deliberate: a half-typed number is
 * an ordinary state of a form field, not an exceptional one, and throwing here is what forces every
 * consumer to wrap parsing in a try/catch. Check `$isValid` before using the renderings.
 *
 * ### Validity has two levels, and the difference matters
 *
 * `$isPossible` asks whether the number is the right *shape* for its country — the right length, the
 * right leading digits. `$isValid` asks whether it falls inside a range that has actually been
 * allocated. A number can be possible and not valid, and that gap is where most real-world data
 * lives: newly allocated ranges are possible before Google's metadata knows about them. Rejecting on
 * `! $isValid` will turn away a small number of genuine customers; rejecting on `! $isPossible` will
 * not. Pick deliberately — see `docs/validation.md`.
 */
final readonly class PhoneNumberValue implements JsonSerializable, Stringable
{
    /**
     * @param string $raw What the user actually supplied, after normalisation
     * @param string|null $e164 `+254712345678`, or null when unparseable
     * @param string|null $national `0712 345678`, or null when unparseable
     * @param string|null $international `+254 712 345678`, or null when unparseable
     * @param string|null $rfc3966 `tel:+254-712-345678`, or null when unparseable
     * @param string|null $country ISO 3166-1 alpha-2, or null when it could not be resolved
     * @param string|null $extension Digits only, without any `ext` marker
     * @param int|null $callingCode The country calling code as an integer, e.g. `254`
     */
    public function __construct(
        public string $raw,
        public ?string $e164 = null,
        public ?string $national = null,
        public ?string $international = null,
        public ?string $rfc3966 = null,
        public ?string $country = null,
        public ?string $extension = null,
        public ?int $callingCode = null,
        public PhoneNumberType $type = PhoneNumberType::Unknown,
        public bool $isValid = false,
        public bool $isPossible = false,
        private ?ResolvesPhoneIntel $intel = null,
    ) {}

    /** An empty value, for a null or blank input. */
    public static function empty(string $raw = ''): self
    {
        return new self(raw: $raw);
    }

    /**
     * Why the number is not usable, rather than merely that it is not.
     *
     * `isPossible` is a boolean and a boolean cannot be acted on. "Too short" tells a user to keep
     * typing; "unknown calling code" tells them they pasted the wrong thing. See
     * {@see PossibilityReason::isCorrectable()}.
     */
    public function possibility(): PossibilityReason
    {
        $parsed = $this->parsed();

        if (! $parsed instanceof LibPhoneNumber) {
            return PossibilityReason::InvalidCountryCode;
        }

        return PossibilityReason::fromLibPhoneNumber(
            PhoneNumberUtil::getInstance()->isPossibleNumberWithReason($parsed),
        );
    }

    /**
     * How closely this matches another number.
     *
     * Graded rather than boolean, because the honest answer depends on how much country context each
     * side carries — see {@see MatchStrength}. Use `->matches($other)->isSame()` for a yes or no.
     */
    public function matches(self|string|null $other): MatchStrength
    {
        if ($other === null) {
            return MatchStrength::NotANumber;
        }

        $util = PhoneNumberUtil::getInstance();
        $mine = $this->e164 ?? $this->raw;
        $theirs = $other instanceof self ? ($other->e164 ?? $other->raw) : $other;

        if ($mine === '' || $theirs === '') {
            return MatchStrength::NotANumber;
        }

        return MatchStrength::fromLibPhoneNumber($util->isNumberMatch($mine, $theirs));
    }

    /**
     * The geographic area code, where the plan has one.
     *
     * Null rather than an empty string for a number with no area code — mobiles in most of Europe,
     * and every number in a plan that does not allocate geographically. Distinguishing "no area
     * code" from "area code unknown" matters when the value is being stored.
     */
    public function areaCode(): ?string
    {
        $parsed = $this->parsed();

        if (! $parsed instanceof LibPhoneNumber) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();
        $length = $util->getLengthOfGeographicalAreaCode($parsed);

        return $length === 0 ? null : substr($util->getNationalSignificantNumber($parsed), 0, $length);
    }

    /**
     * The national destination code — the operator or region prefix inside the national number.
     *
     * Wider than {@see areaCode()}: it is present for mobile ranges too, where it identifies the
     * network rather than a place. This is the part that stays constant across a carrier's block.
     */
    public function nationalDestinationCode(): ?string
    {
        $parsed = $this->parsed();

        if (! $parsed instanceof LibPhoneNumber) {
            return null;
        }

        $util = PhoneNumberUtil::getInstance();
        $length = $util->getLengthOfNationalDestinationCode($parsed);

        return $length === 0 ? null : substr($util->getNationalSignificantNumber($parsed), 0, $length);
    }

    /** Whether the number is tied to a place at all, rather than being mobile or non-geographic. */
    public function isGeographic(): bool
    {
        $parsed = $this->parsed();

        return $parsed instanceof LibPhoneNumber && PhoneNumberUtil::getInstance()->isNumberGeographical($parsed);
    }

    /** Whether the input used letters, as vanity numbers do. */
    public function isVanity(): bool
    {
        return $this->raw !== '' && PhoneNumberUtil::getInstance()->isAlphaNumber($this->raw);
    }

    /** Whether there is a usable number here at all. */
    public function isEmpty(): bool
    {
        return $this->e164 === null;
    }

    /** Render in a given format, falling back to the raw input when unparseable. */
    public function format(PhoneNumberFormat $format): string
    {
        return match ($format) {
            PhoneNumberFormat::E164 => $this->e164,
            PhoneNumberFormat::International => $this->international,
            PhoneNumberFormat::National => $this->national,
            PhoneNumberFormat::Rfc3966 => $this->rfc3966,
        } ?? $this->raw;
    }

    /** An `href` for a click-to-call link. */
    public function telLink(): ?string
    {
        return $this->rfc3966;
    }

    /**
     * An `href` for a WhatsApp conversation.
     *
     * `wa.me` wants the E.164 digits with no `+` and no punctuation. Returns null for a number that
     * cannot take a message — a landline, a short code — rather than producing a link that opens to
     * an error.
     */
    public function whatsAppLink(?string $message = null): ?string
    {
        if ($this->e164 === null || ! $this->type->isMobile()) {
            return null;
        }

        $link = 'https://wa.me/' . ltrim($this->e164, '+');

        return $message === null ? $link : $link . '?text=' . rawurlencode($message);
    }

    /**
     * The number with its middle digits replaced, for logs, support screens and audit trails.
     *
     * Keeps the calling code and the last two digits, which is enough for a human to confirm they are
     * looking at the right record and not enough to dial it.
     */
    /**
     * Obscure the number, keeping the calling code and the last digits.
     *
     * The default hides everything between, which is the right shape for a support queue or an audit
     * log: enough to recognise a number you already know, not enough to dial one you do not.
     *
     * @param int|null $keep How many trailing digits to leave visible. Null keeps two.
     */
    public function masked(string $maskChar = '•', ?int $keep = null): string
    {
        if ($this->e164 === null) {
            return $this->raw === '' ? '' : str_repeat($maskChar, mb_strlen($this->raw));
        }

        $prefix = '+' . ($this->callingCode ?? '');
        $rest = substr($this->e164, strlen($prefix));
        $keep = max(0, $keep ?? 2);

        if (strlen($rest) <= $keep) {
            return $this->e164;
        }

        return $prefix . str_repeat($maskChar, strlen($rest) - $keep) . ($keep === 0 ? '' : substr($rest, -$keep));
    }

    /**
     * Obscure a proportion of the number rather than a fixed count.
     *
     * Borrowed from `simtabi/pheg`, which masks by percentile, and worth having beside the fixed
     * form: a rule expressed as "hide most of it" travels across numbering plans where "hide all but
     * two" does not. A nine-digit Kenyan mobile and a fifteen-digit international number masked to
     * two visible digits leak very different proportions of themselves.
     *
     * @param int $percent How much of the national part to hide, 0–100
     */
    public function maskedByPercent(int $percent = 60, string $maskChar = '•'): string
    {
        if ($this->e164 === null) {
            return $this->masked($maskChar);
        }

        $prefix = '+' . ($this->callingCode ?? '');
        $rest = substr($this->e164, strlen($prefix));
        $percent = max(0, min(100, $percent));

        $hidden = (int) round(strlen($rest) * ($percent / 100));

        return $this->masked($maskChar, max(0, strlen($rest) - $hidden));
    }

    /**
     * The carrier the number's range was allocated to, if known.
     *
     * **Allocation, not ownership.** A number ported to another network still reports the carrier
     * that originally held the range, and no offline database can know otherwise — only an HLR
     * lookup can. Treat this as a hint for display, never as a routing or billing decision.
     *
     * Returns null when no intel resolver was supplied, when the number is unparseable, or when
     * libphonenumber has no carrier data for that range.
     */
    public function carrier(?string $locale = null): ?string
    {
        return $this->intel?->carrier($this, $locale);
    }

    /**
     * A human description of where the number is, e.g. `Nairobi` or `California`.
     *
     * Geographic only. Mobile numbers in most countries are not tied to a place, so this is
     * frequently null even for a perfectly valid number.
     */
    public function description(?string $locale = null): ?string
    {
        return $this->intel?->description($this, $locale);
    }

    /**
     * Candidate IANA timezones for the number.
     *
     * A list, not a value: a country calling code can span several zones, and a mobile number is not
     * bound to any of them. Useful for defaulting a "best time to call" hint, not for scheduling.
     *
     * @return list<string>
     */
    public function timezones(): array
    {
        return $this->intel?->timezones($this) ?? [];
    }

    /** Whether two numbers are the same, comparing E.164 and ignoring how each was written. */
    public function equals(?self $other): bool
    {
        if ($other === null || $this->e164 === null || $other->e164 === null) {
            return false;
        }

        return $this->e164 === $other->e164;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'raw' => $this->raw,
            'e164' => $this->e164,
            'national' => $this->national,
            'international' => $this->international,
            'rfc3966' => $this->rfc3966,
            'country' => $this->country,
            'calling_code' => $this->callingCode,
            'extension' => $this->extension,
            'type' => $this->type->value,
            'is_valid' => $this->isValid,
            'is_possible' => $this->isPossible,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The E.164 form, or the raw input when there is none.
     *
     * This is what lands in a string context — `"{$phone}"`, a `where()` binding, an interpolated
     * log line — so it is the canonical, unambiguous form rather than the pretty one.
     */
    public function __toString(): string
    {
        return $this->e164 ?? $this->raw;
    }

    /**
     * Re-parse for the handful of reads that need libphonenumber's own object.
     *
     * The value object deliberately holds strings — it is serialisable, cacheable and comparable —
     * so the few accessors that need structure re-derive it from E.164, which is lossless. Cheaper
     * than carrying a library object through every cast and cache round trip for accessors most
     * callers never touch.
     */
    private function parsed(): ?LibPhoneNumber
    {
        if ($this->e164 === null) {
            return null;
        }

        try {
            return PhoneNumberUtil::getInstance()->parse($this->e164, null);
        } catch (NumberParseException) {
            return null;
        }
    }
}
