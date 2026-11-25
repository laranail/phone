<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Enums;

use libphonenumber\PhoneNumberFormat as LibFormat;

/**
 * The four renderings of a phone number.
 *
 * Deliberately **string**-backed, where libphonenumber's own enum is int-backed. The backing value is
 * carried into config files, JSON payloads and `data-*` attributes, and `'E164'` survives all three
 * legibly where `0` does not. {@see toLibPhoneNumber()} is the only bridge, so libphonenumber's type
 * never reaches a consumer's method signature.
 *
 * Which one to use is three separate decisions, not one — see `docs/formats.md`:
 * what is **stored** (E164, almost always), what is **displayed** (NATIONAL, usually), and what is
 * shown while the field has **focus** (nothing, usually).
 */
enum PhoneNumberFormat: string
{
    /** `+254712345678` — no spaces, no punctuation. The only format safe to store and compare. */
    case E164 = 'E164';

    /** `+254 712 345678` — grouped for reading, still unambiguous about the country. */
    case International = 'INTERNATIONAL';

    /** `0712 345678` — how someone in that country would write it. Ambiguous outside it. */
    case National = 'NATIONAL';

    /** `tel:+254-712-345678` — the RFC 3966 URI, for `href` attributes. */
    case Rfc3966 = 'RFC3966';

    /** Build from libphonenumber's own case, for values that arrive from that side of the bridge. */
    public static function fromLibPhoneNumber(LibFormat $format): self
    {
        return match ($format) {
            LibFormat::E164 => self::E164,
            LibFormat::INTERNATIONAL => self::International,
            LibFormat::NATIONAL => self::National,
            LibFormat::RFC3966 => self::Rfc3966,
        };
    }

    /**
     * The libphonenumber case this maps onto.
     *
     * A plain `match` rather than `LibFormat::from()` on an int: the mapping is a deliberate,
     * reviewable contract, and the constraint is `^9.0`, where these are native enum cases.
     * (Before 9.0 they were `const` ints on a class. Packages that support both carry a shim for
     * this; ours does not need one, and inventing the abstraction anyway would be dead code.)
     */
    public function toLibPhoneNumber(): LibFormat
    {
        return match ($this) {
            self::E164 => LibFormat::E164,
            self::International => LibFormat::INTERNATIONAL,
            self::National => LibFormat::NATIONAL,
            self::Rfc3966 => LibFormat::RFC3966,
        };
    }

    /**
     * Whether this rendering carries its own country.
     *
     * `NATIONAL` does not: `0712 345678` is a different number in Kenya and in the UK. Anything that
     * stores a national-format value must store the country beside it or the number is unrecoverable.
     */
    public function isUnambiguous(): bool
    {
        return $this !== self::National;
    }

    /** A short human label, for a `<select>` of formats. */
    public function label(): string
    {
        return match ($this) {
            self::E164 => 'E.164',
            self::International => 'International',
            self::National => 'National',
            self::Rfc3966 => 'RFC 3966 (tel: URI)',
        };
    }
}
