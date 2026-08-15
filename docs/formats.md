# Formats

Four formats, one of which is the right answer for storage and the other three for display.

## The four

| Case | Example | Carries its own country | Use for |
|---|---|:---:|---|
| `E164` | `+254712123456` | **yes** | Storage, APIs, deduplication, comparison |
| `International` | `+254 712 123456` | **yes** | Showing a number to someone who may be elsewhere |
| `National` | `0712 123456` | no | Input fields, and display within one country |
| `Rfc3966` | `tel:+254-712-345678` | **yes** | `href` on a click-to-call link |

```php
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;

$number = Phone::parse('+254712123456');

$number->format(PhoneNumberFormat::E164);           // '+254712123456'
$number->format(PhoneNumberFormat::International);  // '+254 712 123456'
$number->format(PhoneNumberFormat::National);       // '0712 123456'
$number->format(PhoneNumberFormat::Rfc3966);        // 'tel:+254-712-345678'
```

## Store E.164, and only E.164

The three properties above the fold are not stylistic preferences. `PhoneNumberFormat::isUnambiguous()`
returns true for exactly three of the four cases, and `National` is the one it excludes:

```php
PhoneNumberFormat::National->isUnambiguous();   // false
```

`0712 123456` is a Kenyan mobile number, a Ugandan one, and several others, depending entirely on
context you did not store. A column holding national numbers cannot be read without also knowing
which country each row belongs to — which means a second column that must never drift, a join, or a
guess.

This is the defect behind the two most-reported bugs in the Filament phone packages this one was
built against: one forces national storage the moment you configure a country column, destroying the
canonical value; the other's fork removes that line without replacing it, so the dial code is written
twice.

## The enum

```php
enum PhoneNumberFormat: string
{
    case E164 = 'E164';
    case International = 'INTERNATIONAL';
    case National = 'NATIONAL';
    case Rfc3966 = 'RFC3966';
}
```

String-backed rather than int-backed, deliberately. libphonenumber's own enum is int-backed, so a
config file or a database column holding `1` tells you nothing, and a change in its ordering would
silently repoint every stored value. `'E164'` says what it is.

`toLibPhoneNumber()` converts to libphonenumber's case when the underlying library is called
directly. `label()` gives a human name for a form select.

## Formatting an unparseable value

Every format call falls back to the raw input rather than throwing or returning null:

```php
Phone::parse('call reception')->format(PhoneNumberFormat::International);   // 'call reception'
```

A column is not the place to discover that a legacy row holds junk. Hiding it behind a dash makes the
row look empty and the data impossible to find; showing it means somebody can fix it.

## Number types

`PhoneNumberType` has fifteen cases. The one worth knowing about:

```php
PhoneNumberType::FixedLineOrMobile->isMobile();   // true
```

The North American Numbering Plan does not distinguish mobile from fixed-line, so libphonenumber
reports `FIXED_LINE_OR_MOBILE` for every US and Canadian number. Treating that as "not mobile" would
reject every valid American mobile number, so `isMobile()` counts the ambiguous case as a yes.

---

[← Docs index](../README.md#documentation)
