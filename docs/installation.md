# Installation

Composer install, the versions this package supports, and the two optional extensions.

## Requirements

| | |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |
| Extensions | `ext-mbstring` (required) · `ext-intl` (optional) |

## Install

```bash
composer require laranail/phone
```

`laranail/*` packages resolve through git rather than Packagist, so add the VCS repository to your
root `composer.json` if you have not already:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/phone" }
]
```

The service provider and the `Phone` facade are auto-discovered.

## Publishing the config

Only if you want to change the default country, the intel locale, the mask cache store, or to turn on
the HTTP API:

```bash
php artisan vendor:publish --tag=laranail::phone-config
```

This writes `config/laranail/phone.php` — a nested path, which matters: Laravel keys config by
filename, so a flat file would load under a different key and the package would never read it. The
published file is merged back over the packaged defaults at boot, so a partially-edited copy still
inherits everything it does not mention.

Everything resolves under the vendor-namespaced key `config('laranail.phone.*')` — see
[Configuration](configuration.md).

## The HTTP API is off

Installing this package adds **no routes**. If you want the analyse / batch / audit / scan endpoints,
enable them deliberately and authenticate them — see [HTTP API](tools/api.md).

## What comes with it

`giggsey/libphonenumber-for-php` is a hard requirement, and it is the **full** package rather than
`-lite`. That is deliberate: `-lite` strips `geocoding/`, `carrier/`, `timezone/`, `PhoneNumberMatcher`
and — the one that matters here — `AsYouTypeFormatter`. The full package `replace`s lite, so anything
that requires lite is still satisfied; lite `conflict`s with full, so the two can never coexist.

It is Apache-2.0 inside an MIT package. That is compatible, and it is recorded in
[NOTICE](../NOTICE) as the licence requires.

## Optional extensions

Neither is used by this package. Both are listed because calling code frequently wants them
alongside it, and it is easier to say so here than to have you discover it:

- **`ext-intl`** — `Locale::getDisplayRegion('-KE', 'fr')` gives localised country names. This
  package deals in ISO codes and never resolves a name, so this only matters to your own code.
- **`symfony/intl`** — the same names without needing `ext-intl`, and deterministic across hosts
  rather than varying with the system's ICU version. It bundles the whole ICU dataset, which is
  heavy enough that it ships a compression script.

## Verifying the install

```php
use Simtabi\Laranail\Phone\Facades\Phone;

Phone::toE164('0712 123456', 'KE');   // '+254712123456'
```

---

[← Docs index](../README.md#documentation)
