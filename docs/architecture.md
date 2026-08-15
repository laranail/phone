# Architecture

Eight classes, one of which is allowed to touch libphonenumber. This page is the rationale — the
"why", not the "what".

## The shape

```
PhoneNormalizer   →  PhoneFormatter  →  PhoneNumberValue
   (clean input)      (the only            (immutable result)
                       libphonenumber
                       contact point)
                            ↓
              MaskGenerator · CountryReconciler · PhoneNumberFactory
                       (all derive from the formatter)
                            ↓
                    Casts\AsPhoneNumber · Casts\E164
```

Everything downstream of `PhoneFormatter` asks it rather than libphonenumber. That single rule is
what keeps the fallback behaviour in one place instead of at every call site.

## Why one contact point

libphonenumber throws `NumberParseException` on input it cannot read, and a phone field is precisely
where unreadable input arrives. Handling that at every call site means every call site gets it
slightly differently, and the ones that get it wrong surface as a 500 on a signup form.

`PhoneFormatter::parse()` has a three-tier fallback — strict, then lenient, then hand the input back
untouched — and nothing else in the package is allowed to call the library directly. The behaviour is
therefore uniform by construction rather than by discipline.

## Why not `propaganistas/laravel-phone`

It is the obvious existing answer and it does the validation half well. It was not used because the
half this package needs most is the half it does not have:

- **No value object.** It returns strings, so every consumer re-parses to ask a second question.
- **No input normalisation.** A number pasted with Arabic-Indic digits, an IDD prefix or a
  zero-width space fails, and the user is told their valid number is invalid.
- **No mask generation, no test-number factory, no Eloquent casts.**
- **No translated messages.** Its README tells you to add the key yourself.

The parts it does own — validation rules — are the parts this package deliberately does not have; see
[Validation](validation.md).

## Why the full libphonenumber, not `-lite`

`-lite` strips `geocoding/`, `carrier/`, `timezone/`, `PhoneNumberMatcher`/`Leniency` and
`AsYouTypeFormatter`. Three of those are features here — carrier, description and timezones on the
value object — and the mask generator's whole approach rests on example numbers, which lite keeps but
which sit next to the pieces it does not.

The full package `replace`s lite, so a dependency requiring lite is still satisfied. Lite `conflict`s
with full, so they can never both be installed.

## Why `PhoneNumberFormat` is string-backed

libphonenumber's own enums became int-backed in 9.0 (they were classes of `const int` before). This
package's enums are string-backed over the top of them.

An int-backed format in a config file or a database column tells a reader nothing, and a change in
the library's ordering would silently repoint every stored value. `'E164'` says what it is, survives
a `config:cache`, and reads correctly in a database dump.

`toLibPhoneNumber()` converts at the boundary. There is no compatibility shim for the pre-9.0 int
constants, because the package's floor is `^9.0` — a shim for a version that cannot be installed is
dead code that looks like caution.

## Why the mask generator refuses

`MaskGenerator::national()` returns `null` for any country whose numbering plan allows more than one
national length — about 16% of them, including Germany, Brazil and the Netherlands.

That refusal is the feature. German mobile numbers are ten *or* eleven digits. A ten-digit mask
silently swallows the eleventh keystroke, and the user gets no feedback at all — no error, no
rejection, just a key that did nothing. A field rendered unmasked is strictly better than a field
that is confidently wrong.

A placeholder is still offered in those cases, because showing an example never constrains what can
be typed.

## Why country data lives elsewhere

This package deals in ISO 3166-1 alpha-2 codes and nothing else. No names, no flags, no dial-code
catalogue.

A numbering plan is keyed by ISO code; a name is a presentation concern that varies by locale and by
political opinion. Keeping them apart means this package has no opinion to be wrong about, and
consumers pick whichever catalogue they already have — `laranail/atlas`, `symfony/intl`, or their own.

This was not always true. An earlier revision listed `laranail/atlas` as a hard requirement while
using it nowhere in `src/`, dragging the ISO catalogue into every consumer for nothing. It is now a
dev dependency, used only by the test harness.

## Why the casts hook `saving` rather than `set`

`AsPhoneNumber` with a country column has to write two attributes from one assignment. Eloquent's
`set()` can return multiple attributes, but the country is only knowable after the number has been
parsed — and the parse is the expensive part.

The cast therefore registers a per-class `saving` hook, keyed on the dispatcher's object id so a
second model class does not re-register the same closure. See [Casts](tools/casts.md).

## What this package is not

- **Not an SMS or OTP library.** It parses and formats; it does not send.
- **Not a porting-accurate carrier lookup.** Carrier data is registration-based, so a ported number
  reports the network it was issued on. Documented, not fixed — it cannot be fixed from metadata.
- **Not a validator.** See [Validation](validation.md).

---

[← Docs index](../README.md#documentation)
