# Configuration

Six blocks. Every default is safe to leave alone except the HTTP API, which is off and should stay
off until someone has decided how it will be authenticated.

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
| `scanning.leniency` | `'VALID'` | How readily free-text scanning accepts a candidate |
| `scanning.limit` | `PHP_INT_MAX` | A ceiling on matches per scan |
| `dialling.from` | `null` | The country calls are assumed to originate from |
| `api.enabled` | `false` | **No routes exist until this is true** |
| `api.prefix` | `api/laranail/phone` | Where the endpoints mount |
| `api.middleware` | `['api']` | Not authentication — see below |
| `api.throttle` | `'60,1'` | Appended unless the middleware already throttles; null opts out |
| `api.max_batch` | `1000` | Enforced with a 422, never a truncation |
| `api.allow_intel` | `true` | Whether a request may ask for carrier, geocoding and timezone |

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

## `scanning`

Defaults for `Phone::find()`, which locates numbers inside prose rather than parsing a field.

```php
'scanning' => [
    'leniency' => env('PHONE_SCAN_LENIENCY', 'VALID'),
    'limit' => (int) env('PHONE_SCAN_LIMIT', PHP_INT_MAX),
],
```

`leniency` is the trade between missing numbers and inventing them, and the right point depends
entirely on the text. `VALID` is the sensible default: a candidate must be a real number for some
region, so an invoice reference is not mistaken for a phone number. `POSSIBLE` finds more and suits a
support inbox; `EXACT_GROUPING` finds fewer and suits redacting a document, where a false positive
destroys data. See [Scanner](tools/scanner.md).

`limit` guards against a pathological input producing an unbounded result set.

## `dialling`

```php
'dialling' => [
    'from' => env('PHONE_DIAL_FROM'),
],
```

The origin assumed by `dialFrom()` and `forMobile()` when none is given. E.164 is what you store; it
is not what you dial. Calling a UK number from Kenya is `000 44 …`, from the United States
`011 44 …`, and from inside the UK `020 …` — one stored value, three strings. See
[Dialling](tools/dialling.md).

## `api`

Off, and turning it on is the whole security decision. Nothing is registered until `enabled` is
`true`, so an install that never touches this adds no routes.

```php
'api' => [
    'enabled' => env('PHONE_API_ENABLED', false),
    'prefix' => env('PHONE_API_PREFIX', 'api/laranail/phone'),
    'middleware' => ['api'],
    'throttle' => env('PHONE_API_THROTTLE', '60,1'),
    'max_batch' => (int) env('PHONE_API_MAX_BATCH', 1000),
    'allow_intel' => env('PHONE_API_ALLOW_INTEL', true),
],
```

> **`middleware` is not authentication.** `api` is Laravel's stock group — throttling and route-model
> binding. Enabling the API with that alone publishes an endpoint that will parse anything anyone
> sends it. Put `auth:sanctum`, a token middleware or an IP allow-list in the list first.

The throttle is **appended** to whatever you configure, so your authentication runs first — rejecting
an unauthenticated request should not spend its rate-limit budget. A throttle already in the list is
left alone, because two limiters give a rate that is neither of the numbers written down.

`allow_intel: false` refuses carrier, geocoding and timezone outright rather than relying on callers
not to ask for them. Full reference: [HTTP API](tools/api.md).

---

[← Docs index](../README.md#documentation)
