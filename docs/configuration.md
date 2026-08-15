# Configuration

Five settings, all with defaults that are safe to leave alone.

Publish with `php artisan vendor:publish --tag=laranail::phone-config`, which writes
`config/laranail/phone.php`. Everything resolves under `config('laranail.phone.*')`.

> There are no closures anywhere in the shipped config. A closure in a config file breaks
> `config:cache`, and the failure shows up at deploy time rather than in development.

## Reference

| Key | Default | What it does |
|---|---|---|
| `default_country` | `null` | The region bare national input is parsed against when no country is given at the call site |
| `convert_vanity_letters` | `false` | Turn `1-800-FLOWERS` into digits on input |
| `intel.enabled` | `true` | Whether carrier, geographic description and timezone lookups are available |
| `intel.locale` | `null` | The language those answers come back in; null follows the app locale |
| `masks.cache_store` | `null` | Which cache store holds generated mask templates; null uses the default |
| `masks.ttl` | `null` | How long to cache them; null means forever |

## `default_country`

```php
'default_country' => env('PHONE_DEFAULT_COUNTRY'),
```

Null by default, and that is the safer choice for an international audience. A wrong guess here does
not fail loudly — it silently turns one country's numbers into another's, and the damage is only
visible much later when somebody tries to call one.

It is only ever consulted for input that does not already carry a calling code, so it cannot corrupt
a value stored in E.164.

Set it when your application genuinely is single-country:

```php
'default_country' => 'KE',
```

## `convert_vanity_letters`

Off, and it should stay off unless vanity numbers are genuinely expected.

Letters in a phone field are not always keypad instructions. A Nigerian number written
`080 ABC 1234` uses them as an initialism, and converting blindly corrupts it into digits that dial
somewhere else entirely. Turn this on for a US-facing marketing form; leave it off for a contact
book.

## `intel`

Carrier name, geographic description and timezones. Each loads its own prefix-keyed metadata on
first use, so leaving this enabled costs nothing until something asks:

```php
$number = Phone::parse('+254712123456');

$number->carrier();      // 'Safaricom'
$number->description();  // 'Kenya'
$number->timezones();    // ['Africa/Nairobi']
```

`intel.locale` sets the language of those answers. libphonenumber ships carrier names in nine
languages and geocoding in about forty; a missing translation falls back to English rather than
failing.

> **Carrier data is registration-based, so it is wrong for ported numbers.** A number that moved
> networks still reports the network it was issued on. Treat it as a hint, never as a billing input.

Setting `intel.enabled` to `false` makes the three methods return `null` and `[]` rather than
throwing, so calling code does not have to branch on configuration.

## `masks`

Mask templates are derived from libphonenumber's example numbers, which means reading numbering-plan
metadata — a per-region file load. That is why they are cached.

`ttl` is null, meaning forever, and that is correct rather than lazy: the answer changes only when
libphonenumber itself is upgraded, which is a deploy.

```php
'masks' => [
    'cache_store' => env('PHONE_MASK_CACHE_STORE'),
    'ttl' => null,
],
```

> On the `array` cache driver, "forever" means "for this request". That is the driver's nature rather
> than this setting's, but it is worth knowing before profiling a slow page.

---

[← Docs index](../README.md#documentation)
