# Contributing

Thanks for helping improve `laranail/phone`.

## Getting set up

```bash
composer install
composer test
composer lint          # pint --test, phpstan, rector --dry-run
```

`ext-intl` is optional. Install it to work on localised country names; leave it out to work on the
fallback path. CI runs both.

## What the checks enforce

| Command | Gate |
|---|---|
| `composer test` | Pest, on PHP 8.4 and 8.5, at `prefer-lowest` and `prefer-stable` |
| `composer phpstan` | larastan level 8 over `src` and `tests` |
| `composer pint` | Laravel preset with `declare(strict_types=1)` required |
| `composer rector` | the `php84` set — not `php85`, so nothing is rewritten into syntax the 8.4 leg cannot parse |
| `composer validate --strict` | the manifest |

`prefer-lowest` is the leg that matters most here. It resolves the oldest versions `composer.json`
permits, so a constraint looser than the code fails there and nowhere else.

## Conventions specific to this package

**libphonenumber is touched in four places and no more.** `PhoneFormatter` (parsing and formatting),
`MaskGenerator` (numbering-plan metadata), `PhoneIntel` (carrier, geocoding, timezones) and the two
enums' bridge methods. Everything else works with `PhoneNumberValue`. A pull request that adds a
fifth `use libphonenumber\*` will be asked to route through one of those instead — the boundary is
what keeps a libphonenumber major upgrade a contained change rather than a sweep.

**Country data comes from `laranail/atlas`, never from a literal.** Dial codes, ISO-3166 codes, names
and flags all resolve through `Atlas::`. A hardcoded country list in a pull request will be asked to
use atlas instead — the `+1` case alone (twenty-odd countries share it) is reason enough.

**Formatting never throws.** `PhoneFormatter` falls back strict → lenient → return the input
untouched. Unparseable junk must survive a round trip unchanged rather than raise
`NumberParseException`; a user's half-typed number is not an exceptional condition. Tests assert this
directly.

**Enums are string-backed.** `PhoneNumberFormat` and `PhoneNumberType` back onto `'E164'`, `'MOBILE'`
and so on, not integers, because those values are carried into config, JSON and `data-*` attributes.
The int mapping lives in `toLibPhoneNumber()`.

**New commands follow `laranail::phone.<command>`.** No bare `phone:` alias — a short alias hands back
exactly the collision the namespaced name exists to prevent.

## Adding a country-specific behaviour

Don't, unless libphonenumber genuinely lacks it. The metadata is Google's and is updated roughly
fortnightly upstream; a special case written here is a special case that stops being true. If
libphonenumber is wrong, the fix belongs in that project.

The legitimate exceptions are **presentation** concerns libphonenumber has no opinion on — mask
templates, how a country is labelled in a picker — and those live in `MaskGenerator` and the atlas
catalogue respectively.

## Pull requests

- One concern per pull request.
- Add a test that fails without the change. For a parsing or formatting fix, add the number that was
  wrong to the relevant matrix rather than writing a new test file.
- Update `CHANGELOG.md` under `## [Unreleased]`.
- Commit subjects are imperative and under 72 characters. Explain *why* in the body, not *what*.

## Reporting a security issue

Do not open a public issue. See [SECURITY.md](SECURITY.md).
