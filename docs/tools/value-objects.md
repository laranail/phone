# `PhoneNumberValue`

Twelve readonly properties and eighteen methods describing one parsed number —
`Simtabi\Laranail\Phone\PhoneNumberValue`, returned by `Phone::parse()` and by every
`Phone::of(...)` chain.

It is immutable and it never throws. Every accessor has an answer for a number that could not be
parsed, because the input that could not be parsed is exactly the input a form submitted.

## Properties

| Property | Type | |
|---|---|---|
| `raw` | `string` | The normalised input, always present |
| `e164` | `?string` | `+254712123456` — null when unparseable |
| `national` | `?string` | `0712 123456` |
| `international` | `?string` | `+254 712 123456` |
| `rfc3966` | `?string` | `tel:+254-712-345678` |
| `country` | `?string` | ISO 3166-1 alpha-2 |
| `extension` | `?string` | Digits only, without any `ext` marker |
| `callingCode` | `?int` | `254` |
| `type` | `PhoneNumberType` | `Unknown` when it could not be determined |
| `isValid` | `bool` | Allocated in the numbering plan |
| `isPossible` | `bool` | Correctly shaped, allocated or not |
| `failure` | `?PossibilityReason` | Why the parse threw, when it did — see below |

## Methods

### `isEmpty(): bool`

Whether there is a usable number here at all — that is, whether `e164` is null. The single check
worth making before doing anything with a parsed value.

```php
if (Phone::parse($input)->isEmpty()) {
    // the input was not a phone number
}
```

### `format(PhoneNumberFormat $format): string`

Renders in one of the four formats, **falling back to `raw`** when the number could not be parsed.
Returns a string rather than a nullable one, so a Blade template never has to guard it.

### `telLink(): ?string`

The `href` for a click-to-call link — the RFC 3966 form. Null when unparseable, so an anchor can be
conditionally rendered rather than emitted broken.

### `whatsAppLink(?string $message = null): ?string`

A `wa.me` URL with the E.164 digits, no `+` and no punctuation, optionally pre-filling a message.

Returns **null for a number that cannot receive one** — a landline, a short code — rather than
producing a link that opens to an error. The check is `type->isMobile()`.

### `possibility(): PossibilityReason`

*Why* the number is not usable, rather than merely that it is not. `isPossible` is a boolean and a
boolean cannot be acted on: "too short" tells a user to keep typing, "unknown calling code" tells
them they pasted the wrong thing, and `PossibilityReason::isCorrectable()` separates the two.

```php
Phone::parse('+2547', 'KE')->possibility();          // PossibilityReason::TooShort
Phone::parse('hello')->possibility()->label();       // 'Not a phone number'
```

> **A string the parser refuses outright now reports `NotANumber`.** Earlier versions had nothing to
> go on once the parse had thrown, and guessed `InvalidCountryCode` for everything — so an audit of a
> truncated CSV column reported a column of unknown calling codes, which sends an operator looking in
> exactly the wrong place. The parse failure is now recorded on `failure` and preferred here.

### `matches(self|string|null $other): MatchStrength`

How closely this matches another number, graded rather than boolean — the honest answer depends on
how much country context each side carries. `->matches($other)->isSame()` for a yes or no.

### `areaCode(): ?string` · `nationalDestinationCode(): ?string` · `isGeographic(): bool` · `isVanity(): bool`

The parts of the number below the calling code, and two questions about its shape. `areaCode()` is
null where the plan has no geographic area code at all, which is most mobile ranges.

### `masked(string $maskChar = '•', ?int $keep = null): string` · `maskedByPercent(int $percent = 60, string $maskChar = '•'): string`

Obscures all but the calling code and the last digits:

```php
Phone::parse('+254712123456')->masked();          // '+254 ••• •••456'
Phone::parse('+254712123456')->masked('*');       // '+254 *** ***456'
Phone::parse('+254712123456')->masked(keep: 2);   // keep two trailing digits
Phone::parse('+254712123456')->maskedByPercent(80);
```

For screens where the number is context rather than content — a support queue, an audit log — and for
exports that should not carry a full contact list.

### `carrier(?string $locale = null)` · `description(?string $locale = null)` · `timezones(): array`

Intel lookups, each loading its own prefix-keyed metadata on first use. All three return `null`/`[]`
rather than throwing when `config('laranail.phone.intel.enabled')` is false, so calling code does not
branch on configuration.

```php
$number->carrier();      // 'Safaricom'
$number->description();  // 'Kenya'
$number->timezones();    // ['Africa/Nairobi']
```

> Carrier data is registration-based and therefore **wrong for ported numbers** — a number that moved
> networks still reports the network it was issued on. Treat it as a hint, never as a billing input.

### `equals(?self $other): bool`

Compares on E.164, so `0712 123456` parsed against Kenya equals `+254 712 123456`. Two unparseable
values are equal when their raw forms match.

### `toArray(): array` · `jsonSerialize(): array` · `__toString(): string`

`__toString()` is the E.164 form, falling back to `raw`. That is what makes a value object
interchangeable with a string in a query binding or a log line.

## Enums

Six, all under `Simtabi\Laranail\Phone\Enums` and all string-backed — see
[Architecture](../architecture.md) for why.

| Enum | Cases | Beyond `label()` |
|---|---|---|
| `PhoneNumberFormat` | 4 | `isUnambiguous()` |
| `PhoneNumberType` | 15 | `isMobile()`, `isPremium()` |
| `PossibilityReason` | 7 | `isPossible()`, `isCorrectable()` |
| `MatchStrength` | 5 | `isSame()` |
| `MatchLeniency` | 4 | `toLibPhoneNumber()` |
| `ShortNumberCost` | 4 | `isChargeable()` |

```php
PhoneNumberType::FixedLineOrMobile->isMobile();   // true — the NANP case
```

---

[← Docs index](../../README.md#documentation)
