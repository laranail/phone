# laranail/phone

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/phone.svg)](https://packagist.org/packages/laranail/phone)
[![Tests](https://github.com/laranail/phone/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/phone/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/phone/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/phone/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Phone numbers for Laravel — an immutable value object over libphonenumber, input normalisation that handles what people actually paste, per-country input masks, Eloquent casts and a test-number factory.

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

## Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/phone](https://opensource.simtabi.com/documentation/laranail/phone/)** — installation, getting started, the three formatting decisions, country resolution, validation, architecture, and per-subsystem reference for the value object, formatter, normalizer, mask generator, casts and factory.

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE). This package depends on
[libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php), which is Apache-2.0 — see
[NOTICE](NOTICE).
