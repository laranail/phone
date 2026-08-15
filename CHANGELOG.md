# Changelog

All notable changes to `laranail/phone` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `laranail/atlas` moved from `require` to `require-dev`. It was a hard dependency used nowhere in
  `src/` — this package deals in ISO 3166-1 alpha-2 codes and never resolves a country *name* — so it
  dragged the ISO catalogue into every consumer for nothing. The `ext-intl` and `symfony/intl`
  suggestions were corrected to match: neither is used here either.

### Fixed

- `MaskGenerator::placeholder()` was not memoised while `national()` and `international()` were, so
  every call re-read a region's metadata. Building a table for all 245 regions cost 210 ms on a pass
  where the masks themselves were already cached and free; it is now 2.7 ms.

### Added

- `PhoneNumberValue` — an immutable value object carrying every rendering of a number (`e164`,
  `national`, `international`, `rfc3966`), its country, extension, type, validity, carrier, region
  and timezones.
- `PhoneNumberFormat` and `PhoneNumberType` — string-backed enums over libphonenumber's int-backed
  ones, so the value survives config files, JSON and `data-*` attributes legibly.
- `PhoneFormatter` — the single point of contact with libphonenumber. Falls back strict → lenient →
  input unchanged, so unparseable input never raises `NumberParseException`.
- `PhoneNormalizer` — turns what people actually paste into something parseable: IDD prefixes
  (`00`, `011`) to `+`, Arabic-Indic and extended Arabic-Indic digits to ASCII, non-breaking and
  zero-width characters stripped, and optional vanity-letter conversion (`1-800-FLOWERS`).
- `MaskGenerator` — a per-country input-mask template derived from libphonenumber's own example
  numbers.
- `CountryReconciler` — resolves the case where a stored ISO-3166 code and the dial code embedded in
  an E.164 number disagree.
- `AsPhoneNumber` and `E164` Eloquent casts.
- `PhoneNumberFactory` — valid, country-correct numbers for factories and seeders.
- A `phoneNumber()` Blueprint macro creating the number column, its country column and an index.

[Unreleased]: https://github.com/laranail/phone/commits/main
