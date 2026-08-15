# `PhoneFormatter`

Five methods, and the only class in this package permitted to call libphonenumber —
`Simtabi\Laranail\Phone\PhoneFormatter`, resolved as a singleton and fronted by the `Phone` facade.

```php
use Simtabi\Laranail\Phone\Facades\Phone;      // the facade
use Simtabi\Laranail\Phone\PhoneFormatter;     // or inject it
```

## `parse(?string $input, ?string $country = null): PhoneNumberValue`

The entry point. Normalises the input, resolves a region, parses, and returns a
[`PhoneNumberValue`](value-objects.md).

The `$country` argument is a **hint**. It is consulted only when the input does not already carry its
own calling code, so passing the wrong one cannot corrupt an E.164 value:

```php
Phone::parse('+254712123456', 'US')->country;   // 'KE'
```

### The three-tier fallback

This is the behaviour the rest of the package is built on:

1. **Strict** — parse against the resolved region.
2. **Lenient** — retry without the region when the first attempt fails on a number that carries its
   own calling code.
3. **Untouched** — return a value whose `raw` is the normalised input and whose `e164` is null.

It never throws. libphonenumber raises `NumberParseException` on input it cannot read, and a form
field is exactly where unreadable input arrives; letting that escape means a 500 on a signup page
instead of a validation error.

## `format(?string $input, PhoneNumberFormat $format = E164, ?string $country = null): ?string`

`parse()->format()` in one call, for the common case of rendering a stored value in a column or an
entry. Returns null for a null or blank input; returns the input unchanged when it cannot be parsed.

```php
Phone::format($row->phone, PhoneNumberFormat::International);   // '+254 712 123456'
```

## `toE164(?string $input, ?string $country = null): ?string`

The canonical storage form, or null when there is nothing parseable. The one you want when writing to
a column.

```php
Phone::toE164('0712 123456', 'KE');   // '+254712123456'
Phone::toE164('call reception');      // null
```

Note the asymmetry with `format()`: this returns **null** for junk rather than the input, because a
storage column should not accept a value that is not a number. `format()` returns the input because a
display surface should not hide one.

## `example(string $country, PhoneNumberType $type = Mobile): ?PhoneNumberValue`

A real, correctly shaped number for a country, from libphonenumber's own metadata. Guaranteed not to
belong to anybody, which is what makes it safe for placeholders, factories and seeders.

```php
Phone::example('KE')->national;   // '0712 123456'
```

Null for a country with no example of that type — some territories have no mobile allocation at all.

## `callingCodeFor(string $country): ?int`

```php
Phone::callingCodeFor('KE');   // 254
```

An integer, not a string, and without the `+`. Prefix it yourself for display.

## Constructing it directly

The container wires it from config, but it takes plain constructor arguments and can be built by
hand — useful in a test that needs a specific default country:

```php
$formatter = new PhoneFormatter(
    normalizer: new PhoneNormalizer(convertVanityLetters: true),
    intel: null,
    defaultCountry: 'KE',
);
```

Passing `intel: null` disables carrier, description and timezone lookups on every value it produces.

---

[← Docs index](../../README.md#documentation)
