# Changelog

All notable changes to `laranail/phone` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Phone::audit()` — judges a whole list in one pass, and answers two questions from it: what each
  row is, and what is wrong with the list. The second is the one worth having. "53 invalid" sends an
  operator through 53 rows; `reasons()` saying "49 too short" tells them the column was truncated on
  export, and one fix clears all of them.
- `Phone::each()`, the same pass streamed, for a file larger than memory. Duplicate detection
  survives it — that needs only the first index seen per E.164 — and the report does not.
- `Phone::e164List()`, the shortest useful thing to do with a column of whatever people typed.
- Duplicates are detected on **E.164, not on the string**, so `0712 123456`, `+254 712 123456` and
  `254712123456` are one number. `duplicateOf` points at the first row that produced it, so
  de-duplicating is a filter and the survivor is deterministically the earliest row.
- An opt-in HTTP API: `analyze`, `batch`, `audit`, `scan` and `countries`. **Off by default** — a
  package that publishes endpoints by being installed changes an application's attack surface as a
  side effect of `composer require`. When enabled it is throttled automatically, the throttle is
  appended *after* your authentication so a rejected request does not spend its rate-limit budget,
  and an over-sized batch is a 422 rather than a silent truncation.
- `PossibilityReason::NotANumber`, for a string the parser refused outright.

### Changed

- **A parse failure now reports why.** `PhoneNumberValue::possibility()` had nothing to go on once
  libphonenumber had thrown, and guessed `INVALID_COUNTRY_CODE` for everything — so an audit of a
  truncated CSV column reported a column of unknown calling codes, which sends an operator looking in
  exactly the wrong place. The exception's error type is now recorded on the value object and
  preferred.
- PHPStan raised from level 8 to `max`, matching `laranail/email`. That surfaced two real cases: the
  Eloquent casts turned a non-string column value into a string with `(string)`, so an array in a
  phone column became the value object `"Array"` instead of null, and the service provider read
  `config()` values that a wrong `.env` entry could make any type at all.
- `config/phone.php` — the `masks` block had been separated from its own documentation by a later
  insertion, so the file read as if `scanning` were the mask configuration.

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
