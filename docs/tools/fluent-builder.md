# Fluent builder

`Phone::of(...)` — say what you have, then ask what you want.

```php
use Simtabi\Laranail\Phone\Facades\Phone;

Phone::of('0712 123456')->country('KE')->e164();      // '+254712123456'
Phone::of($stored)->dialFrom('GB');                    // '00 254 712 123456'
Phone::of($stored)->masked();                          // '+254•••••••56'
Phone::of($input)->country('KE')->why()->isCorrectable();
```

In this section: [Narrowing](#narrowing) · [Reading](#reading) · [Dialling](#dialling) ·
[Structure](#structure) · [Per-country helpers](#per-country-helpers) · [Short codes](#short-codes) ·
[Method reference](#method-reference)

## Why a builder

The engine underneath is five objects with different constructor needs — the formatter, the dialler,
the scanner, the catalogue and the short-number reader. Reaching the useful combinations through them
directly means resolving each one and threading the same country hint through every call.
`Phone::of($n)->country('KE')` says it once.

## Narrowing

Every configuration method returns a **new** instance:

```php
$base = Phone::of('0712 123456');
$kenyan = $base->country('KE');

$kenyan->e164();   // '+254712123456'
$base->e164();     // null — unchanged
```

That matters wherever a builder is held: on a model, in a view, passed to a job. A later call cannot
change what an earlier caller sees.

| Method | |
|---|---|
| `country(?string $iso2)` | The country to parse a bare national number against |
| `from(?string $iso2)` | The same thing, reading better when the country describes the caller |
| `type(PhoneNumberType)` · `mobile()` · `fixedLine()` | The line type for the per-country helpers |
| `keepSubaddress()` | *(email builder only — see `laranail/email`)* |

The country is only ever consulted when the input does not already carry its own calling code, so
passing the wrong one cannot corrupt a value written in E.164.

## Reading

The parse happens **once**, lazily, on the first terminal read, and is memoised. Chaining ten
accessors costs one parse; building a chain you never read costs none.

```php
$number = Phone::of('+254712123456');

$number->e164();           // '+254712123456'
$number->national();       // '0712 123456'
$number->international();  // '+254 712 123456'
$number->rfc3966();        // 'tel:+254-712-123456'
$number->format(PhoneNumberFormat::International);
$number->value();          // the PhoneNumberValue, for anything not covered here
```

### Validity, and *why*

```php
Phone::of('0712')->country('KE')->isValid();            // false
Phone::of('0712')->country('KE')->why();                // PossibilityReason::TooShort
Phone::of('0712')->country('KE')->why()->isCorrectable(); // true
```

`isValid()` is a boolean and a boolean cannot be acted on. "Too short" tells a user to keep typing;
"unknown calling code" tells them they pasted the wrong thing. `isCorrectable()` is the difference.

### Line types

```php
Phone::of('+12015550123')->isType(PhoneNumberType::Mobile);   // true
```

> **`isType(Mobile)` is true for every American number**, and deliberately. The North American
> Numbering Plan does not distinguish mobile from fixed-line, so libphonenumber reports
> `FIXED_LINE_OR_MOBILE` — and rejecting that fails every valid US mobile.

### Masking

```php
Phone::of($n)->masked();                  // '+254•••••••56'
Phone::of($n)->masked('*', 4);            // '+254*****3456'
Phone::of($n)->maskedByPercent(60);       // hide 60% of the national part
```

The percentile form comes from `simtabi/pheg` and is worth having beside the fixed one: a rule
expressed as "hide most of it" travels across numbering plans where "hide all but two" does not.

## Dialling

E.164 is what you **store**. It is not always what you **dial**.

```php
Phone::of('+442071838750')->dialFrom('KE');   // '000 44 20 7183 8750'
Phone::of('+442071838750')->dialFrom('US');   // '011 44 20 7183 8750'
Phone::of('+442071838750')->dialFrom('GB');   // '020 7183 8750'
```

One stored value, three strings. Most applications never need this and then need it badly — usually
the moment somebody exports a click-to-call list for a call centre in another country.

| Method | |
|---|---|
| `dialFrom(string $iso2)` | Digits to dial when calling from that country |
| `forMobile(string $iso2, bool $withFormatting = false)` | The form to hand a handset, which differs on some plans |
| `isInternationallyDiallable()` | False for short codes and domestic-only ranges |
| `asEntered()` | The number as originally written, where recoverable |
| `truncated()` | Drop trailing digits until the length is possible — a candidate, not a fix |

## Structure

```php
Phone::of('+12015550123')->areaCode();                  // '201'
Phone::of('+254712123456')->areaCode();                 // null — mobiles are not geographic
Phone::of('+254712123456')->nationalDestinationCode();  // '712' — the operator prefix
Phone::of('+254712123456')->isGeographic();             // false
```

`areaCode()` returns **null** rather than an empty string where the plan has none. Distinguishing
"no area code" from "area code unknown" matters when the value is being stored.

### Comparing

```php
Phone::of('+254712123456')->is('0712123456');       // true
Phone::of('+254712123456')->matches('0712123456');  // MatchStrength::NsnMatch
```

String equality is the wrong question: those two may be the same subscriber written twice, and
whether they *are* depends on how much country context each carries. `matches()` grades it; `is()`
collapses the grade to a yes or no.

> `NsnMatch` counts as the same number; `ShortNsnMatch` does not. A missing country code is usually
> context you already have. A missing *area* code means the two could be different subscribers in
> different cities, and deduplicating on that merges strangers.

## Per-country helpers

These are what the type narrowing is for:

```php
Phone::of(null)->country('KE')->mobile()->example();      // a real, unallocated number
Phone::of(null)->country('KE')->mobile()->mask();         // '9999 999999'
Phone::of(null)->country('KE')->mobile()->placeholder();  // '0712 123456'
Phone::of(null)->country('DE')->mobile()->mask();         // null — see below
```

`mask()` returns **null** where the numbering plan allows more than one national length. German
mobile numbers are ten *or* eleven digits, so a ten-digit mask silently swallows the eleventh
keystroke and the user gets no feedback at all. A placeholder is still offered.

## Short codes

Short codes carry no calling code, so there is nothing to infer a region from — it has to be stated:

```php
Phone::of('999')->country('GB')->isShortCode();          // true
Phone::of('999')->country('GB')->connectsToEmergency();  // true
Phone::of('999')->country('GB')->shortCodeCost();        // ShortNumberCost::TollFree
```

Asking without one raises rather than guessing. See [Short numbers](short-numbers.md).

## Method reference

Everything above, plus `carrier()`, `description()`, `timezones()`, `telLink()`, `whatsAppLink()`,
`isEmpty()`, `toArray()` and `jsonSerialize()` — each forwarding to the same method on
[`PhoneNumberValue`](value-objects.md), which is what `value()` returns.

## More than one

The builder answers questions about one number. For a list — a CSV column, an import, a table you
inherited — [`Phone::audit()`](batch.md) makes one pass and answers both what each row is and what is
wrong with the list, parsing each distinct input only once.

---

[← Docs index](../../README.md#documentation)
