# laranail/phone

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/phone.svg)](https://packagist.org/packages/laranail/phone)
[![Tests](https://github.com/laranail/phone/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/phone/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/phone/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/phone/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Phone numbers for Laravel — a fluent API over libphonenumber, from one number to a whole list: parsing, normalisation, validity with a reason, free-text scanning, per-country masks, Eloquent casts, batch auditing and an opt-in HTTP API.

Requires PHP `^8.4.1` on Laravel `^13`.

## Install

```bash
composer require laranail/phone
```

The service provider and the `Phone` facade are auto-discovered. Publish the config if you want to
change the default country or the mask cache:

```bash
php artisan vendor:publish --tag=laranail::phone-config
```

Config resolves under the vendor-namespaced key `config('laranail.phone.*')`.

## <a name="documentation"></a>Documentation

Full documentation is at
**[opensource.simtabi.com/documentation/laranail/phone](https://opensource.simtabi.com/documentation/laranail/phone/)**.

### Guides

- [Installation](docs/installation.md) — Composer install, supported versions, the two optional extensions
- [Getting started](docs/getting-started.md) — parse, store and display a number, end to end
- [Configuration](docs/configuration.md) — six blocks, all safe to leave alone except the API
- [Formats](docs/formats.md) — the four formats, and why only one belongs in a column
- [Countries](docs/countries.md) — how a country is resolved, and why `+1` is not a country
- [Validation](docs/validation.md) — why the rules live in `laranail/validation`, and the seam
- [Architecture](docs/architecture.md) — the one libphonenumber contact point, and the rejected alternatives
- [Release](docs/release.md) — versioning, tagging, and the CI-managed changelog

### Reference

- [Fluent builder](docs/tools/fluent-builder.md) — `Phone::of(...)`, and why narrowing returns a new instance
- [Batch and audit](docs/tools/batch.md) — judging a whole list, streaming a million rows, and the queued job
- [HTTP API](docs/tools/api.md) — five endpoints, off by default, and how to turn them on safely
- [Scanner](docs/tools/scanner.md) — finding numbers in free text, and why it is not a regex
- [Dialling](docs/tools/dialling.md) — the digits to actually dial, which are not the ones you stored
- [Short numbers](docs/tools/short-numbers.md) — `999`, `112`, and what a short code costs to dial
- [Catalogue](docs/tools/catalogue.md) — regions, calling codes, and why `+1` is not a country
- [`PhoneNumberValue`](docs/tools/value-objects.md) — thirteen properties and nine methods over one parsed number
- [`PhoneFormatter`](docs/tools/formatter.md) — the parse entry point and its three-tier fallback
- [`PhoneNormalizer`](docs/tools/normalizer.md) — what gets cleaned out of input before parsing
- [`MaskGenerator`](docs/tools/mask-generator.md) — per-country input masks, and when it refuses to give one
- [Eloquent casts](docs/tools/casts.md) — `AsPhoneNumber` and `E164`, and which to reach for
- [`PhoneNumberFactory`](docs/tools/factory.md) — test numbers that are valid and belong to nobody
- [`phoneNumber` macro](docs/tools/blueprint-macro.md) — the migration helper and the columns it creates

### Recipes

- [Store E.164 and a country column](docs/recipes/store-e164-and-country.md) — without letting the two drift
- [Validate a mobile number](docs/recipes/validate-a-mobile-number.md) — reject landlines, short codes and wrong countries
- [Normalise a legacy column](docs/recipes/normalise-a-legacy-column.md) — backfill without destroying the rows that fail
- [Seed test numbers](docs/recipes/seed-test-numbers.md) — why an invented number is usually invalid
- [Localise country names](docs/recipes/localise-country-names.md) — three catalogues, and why none ships here
- [Find numbers in text](docs/recipes/find-numbers-in-text.md) — link or redact numbers inside prose
- [Deduplicate contacts](docs/recipes/deduplicate-contacts.md) — merge rows holding one number written four ways
- [Audit a contact import](docs/recipes/audit-a-contact-import.md) — judge a CSV before it reaches the database
- [Audit a table in the background](docs/recipes/audit-a-table-in-the-background.md) — a million rows on the queue
- [Expose the HTTP API](docs/recipes/expose-the-http-api.md) — enable the endpoints, and authenticate them first

### Project

- [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md) · [Code of conduct](CODE_OF_CONDUCT.md) · [Notice](NOTICE)

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE). This package depends on
[libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php), which is Apache-2.0 — see
[NOTICE](NOTICE).
