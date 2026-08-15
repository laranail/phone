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

- `Phone::of()` — a fluent builder. Narrow with `country()`/`from()`/`type()`, then ask: `isValid()`,
  `why()`, `masked()`, `dialFrom()`, `areaCode()`, `matches()`. Immutable, and the parse is memoised
  so twenty questions about one number cost one parse.
- `PhoneScanner` — `find()`, `replaceIn()` and `redact()` over free text, wrapping libphonenumber's
  `PhoneNumberMatcher`. Four leniency levels via `MatchLeniency`; `replaceIn()` walks matches in
  reverse so earlier offsets stay valid.
- `PhoneDialler` — `dialFrom()` and `forMobile()`, wrapping `formatOutOfCountryCallingNumber()` and
  `formatNumberForMobileDialing()`. What a caller in one country actually dials to reach a number in
  another, IDD prefix and all — not the same string as the international format.
- `ShortNumbers` — emergency and short-code questions via `ShortNumberInfo`: `connectsToEmergency()`,
  `isEmergency()`, `cost()`, `isCarrierSpecific()`, `acceptsSms()`. These need a region and say so
  rather than guessing one.
- `PhoneCatalogue` — the numbering plan itself: `regionsForCallingCode()` (so `+1` is a set, not
  `US`), `primaryRegionForCallingCode()`, `callingCodeFor()`, `isNanp()`, `typesFor()`,
  `isPortable()`, `nationalPrefix()`.
- `PossibilityReason`, `MatchStrength`, `MatchLeniency` and `ShortNumberCost` — string-backed enums
  over libphonenumber's `ValidationResult`, `MatchType`, `Leniency` and `ShortNumberCost`, with the
  question each answers named on it (`isCorrectable()`, `isSame()`, `isChargeable()`).
- `PhoneNumberValue::masked()`, `maskedByPercent()`, `possibility()`, `matches()`, `areaCode()`,
  `nationalDestinationCode()`, `isGeographic()` and `isVanity()`.
- Configuration for the new surfaces: `scanning.leniency`, `scanning.limit` and `dialling.from`.

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
