# Dialling

`Phone::of(...)->dialFrom()` and friends — the digits to actually dial, which are not always the ones
you stored.

## The problem

E.164 is canonical. It is not dialable.

| Calling `+44 20 7183 8750` from | You dial |
|---|---|
| Kenya | `000 44 20 7183 8750` |
| United States | `011 44 20 7183 8750` |
| United Kingdom | `020 7183 8750` |

One stored value, three strings, and the differences are not derivable from the number — they are
properties of the *caller's* country. Most applications never need this, and then need it badly:
usually the first time somebody exports a click-to-call list for a call centre in another country.

## Dialling from somewhere

```php
Phone::of('+442071838750')->dialFrom('KE');   // '000 44 20 7183 8750'
Phone::of('+442071838750')->dialFrom('GB');   // '020 7183 8750'
```

Includes the caller's international dialling prefix where one is needed, and drops it entirely for a
domestic call.

## Handing a number to a handset

```php
Phone::of($n)->forMobile('KE');                        // digits only
Phone::of($n)->forMobile('KE', withFormatting: true);  // with human spacing
```

Differs from E.164 for a handful of plans where a mobile needs a prefix or an extra digit the
canonical form omits. libphonenumber knows which; guessing produces a number that dials on a landline
and fails on a phone.

## Reachability

```php
Phone::of($n)->isInternationallyDiallable();
```

False for short codes, most premium and service numbers, and some domestic-only ranges. Worth
checking before putting a number in front of an international audience, because the failure is
silent — the call simply does not connect.

## Showing a number back as it was typed

```php
Phone::of('(0712) 123-456')->country('KE')->asEntered();
```

For confirmation screens. Showing a user the canonical form of what they typed invites "that is not
what I entered", even when it is the same number.

## Cleaning an over-long import

```php
Phone::of($fromCsv)->country('KE')->truncated();
```

Drops digits from the end until the length is possible — for fields that ran into the next column.
Returns null when nothing survives.

> Truncating to something **possible** is not the same as truncating to something **correct**. Treat
> the result as a candidate for review, never as a repair. A number that is one digit too long
> because of a stray keystroke and one that is too long because two fields merged look identical
> here.

## Configuration

`laranail.phone.dialling.from` sets the assumed origin country, for callers that do not pass one.

---

[← Docs index](../../README.md#documentation)
