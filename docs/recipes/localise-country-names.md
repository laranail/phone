# Localise country names

Show `Kenya`, `Kenia` or `케냐` beside a number — using a catalogue, because this package does not
carry one.

## Why it is not here

`laranail/phone` deals in ISO 3166-1 alpha-2 codes throughout. A numbering plan is keyed by ISO code;
a *name* is a presentation concern that varies by locale and, occasionally, by political opinion.
Keeping them apart means this package has no opinion to be wrong about.

So `Phone::parse(…)->country` gives you `'KE'`, and you pick where the word comes from.

## Option 1 — `ext-intl`

Zero Composer dependencies if the extension is present:

```php
$name = \Locale::getDisplayRegion('-' . $number->country, app()->getLocale());
// 'Kenya' · 'Kenia' · '케냐'
```

The leading hyphen matters — `getDisplayRegion()` expects a locale, and `-KE` is a locale with only a
region.

> Names come from the **host's** ICU data, so two servers with different ICU versions can disagree.
> Fine for display; do not key anything on the result.

## Option 2 — `symfony/intl`

Deterministic across hosts, and no extension needed:

```bash
composer require symfony/intl
```

```php
use Symfony\Component\Intl\Countries;

$name = Countries::getName($number->country, app()->getLocale());
```

It bundles the whole ICU dataset, which is heavy enough that it ships a compression script. Worth it
when consistency matters more than install size.

## Option 3 — `laranail/atlas`

When you need more than a name — dial codes, flags, continents, currencies — the in-family catalogue:

```bash
composer require laranail/atlas
```

```php
use Simtabi\Laranail\Atlas\Facades\Atlas;

$country = Atlas::country($number->country);

$country->name;           // 'Kenya'
$country->callingCode();  // '254'
$country->flag();         // 🇰🇪
```

This is what `laranail/tayari-ui-livewire` uses to build its phone-input picker.

> `flag()` returns regional-indicator emoji, which render as a bare letter pair (`KE`) on most Windows
> browsers. Always show the dial code or the name beside a flag rather than relying on the glyph
> alone.

## A geographic description is not a country name

libphonenumber's geocoder describes where a number's *prefix* is registered, which is often a city or
a region rather than a country:

```php
Phone::parse('+12015550123')->description();   // 'New Jersey'
Phone::parse('+254712123456')->description();  // 'Kenya'
```

Useful for telling a user where a number appears to be from. Not a substitute for `->country`, and
not something to put in a country column.

It is locale-aware too — `description('fr')`, or set `config('laranail.phone.intel.locale')`.
libphonenumber ships geocoding in about forty languages and falls back to English where a translation
is missing.

---

[← Docs index](../../README.md#documentation)
