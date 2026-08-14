<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use JsonSerializable;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
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
    public function masked(string $maskChar = '•'): string
    {
        if ($this->e164 === null) {
            return $this->raw === '' ? '' : str_repeat($maskChar, mb_strlen($this->raw));
        }

        $prefix = '+' . ($this->callingCode ?? '');
        $rest = substr($this->e164, strlen($prefix));

        if (strlen($rest) <= 2) {
            return $this->e164;
        }

        return $prefix . str_repeat($maskChar, strlen($rest) - 2) . substr($rest, -2);
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
}
