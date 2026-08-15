# Short numbers

`999`, `112`, `40404` — a separate body of metadata, and a separate question.

```php
Phone::of('999')->country('GB')->isShortCode();          // true
Phone::of('999')->country('GB')->connectsToEmergency();  // true
Phone::of('999')->country('GB')->shortCodeCost();        // ShortNumberCost::TollFree
```

## Why it is separate

`999` is not a valid phone number by any normal test, and is nonetheless the most important number in
the country. A contact form that asks only `isValidNumber()` rejects it; a form that accepts
everything stores it as a customer's mobile.

## A region is required

Short codes carry no calling code, so there is nothing to infer a region from — `999` is only
meaningful once you know where it is being dialled. Asking without one raises rather than guessing:

```php
Phone::of('999')->isShortCode();
// InvalidArgumentException: Short-code checks need a region…
```

## Emergency numbers

Two checks, and the looser one is the default:

```php
Phone::of($n)->country('GB')->connectsToEmergency();   // begins with an emergency code
app(ShortNumbers::class)->isEmergency($n, 'GB');       // is exactly one
```

`connectsToEmergency()` matches a number that *starts* with an emergency code, because on most
networks dialling `112` followed by anything still connects. Treating `1121` as safe would be wrong
in the one direction that matters.

> Worth checking on any field something later dials automatically. An emergency number sitting in a
> contact record that an auto-dialler reaches is an incident, not a bug.

## Cost

```php
ShortNumberCost::TollFree | StandardRate | PremiumRate | Unknown

$cost->isChargeable();   // true only for PremiumRate
```

"Is a short code" does not distinguish a free helpline from a premium line that bills the caller per
minute. This does.

## The rest

Reached through `app(ShortNumbers::class)` or `Phone::shortNumbers()`:

| Method | |
|---|---|
| `isValid($input, $region)` | A short code the region actually uses |
| `isPossible($input, $region)` | Merely short enough to be one |
| `isCarrierSpecific($input, $region)` | Only works on one network |
| `acceptsSms($input, $region)` | Takes SMS rather than only voice |
| `example($region)` | A real short code, for tests and placeholders |

---

[← Docs index](../../README.md#documentation)
