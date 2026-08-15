# Countries

How a number's country gets resolved, why a stored ISO column is a hint rather than a fact, and the
`+1` problem.

## The resolution order

Given an input and an optional country hint, the country is decided in this order:

1. **The number's own calling code**, if it has one. `+254…` is Kenyan whatever you passed.
2. **The hint** at the call site.
3. **`config('laranail.phone.default_country')`**.
4. Nothing — the number stays unparsed rather than being attributed to a country nobody chose.

```php
Phone::parse('+254712123456', 'US')->country;   // 'KE'   — the number wins
Phone::parse('0712 123456', 'KE')->country;     // 'KE'   — the hint is used
Phone::parse('0712 123456')->country;           // null   — with no default configured
```

Step 4 is the part worth arguing about, and the answer is deliberate. Falling back to
`app()->getLocale()` is a real bug in one of the packages this was built against: a locale is not a
country. `en` is not `US`, and an application serving `en` to Kenya, Nigeria and India would file
every bare number under America.

## A stored ISO column is a hint

If you keep a `phone_country` column beside the number — and there are good reasons to, see
[the recipe](recipes/store-e164-and-country.md) — it is a **convenience**, never the source of truth.
E.164 already carries the country. When the two disagree, the number wins.

`CountryReconciler` is the class that says so out loud:

```php
use Simtabi\Laranail\Phone\CountryReconciler;

$verdict = app(CountryReconciler::class)->reconcile('+254712123456', 'UG');

$verdict->country;      // 'KE'
$verdict->conflicted;   // true
$verdict->reason;       // why
```

It returns a verdict rather than silently picking, so a data-quality sweep can find the rows where
the two columns drifted instead of quietly rewriting them.

## The `+1` problem

A calling code does not identify a country. `+1` is the North American Numbering Plan, shared by
twenty-odd countries and territories — the United States, Canada, Jamaica, Trinidad, and the rest of
the Caribbean.

Two consequences:

- **You cannot derive a country from a dial code alone.** `+1 868` is Trinidad; `+1 876` is Jamaica.
  libphonenumber resolves these from the full number, which is why `parse()` gets it right and
  string-matching a prefix does not.
- **Longest match wins.** Any code that scans a list of dial codes must prefer `+1868` over `+1`.
  A shortest-match scan files every Caribbean number under the United States.

The same applies to `+7` (Russia and Kazakhstan), `+44` (the UK, Guernsey, Jersey, the Isle of Man)
and `+61` (Australia, Christmas Island, the Cocos Islands).

## Country names

This package does not resolve them. It deals in ISO 3166-1 alpha-2 codes throughout, because that is
what a numbering plan is keyed by and what a database column should hold.

Names are a presentation concern and belong to whatever is doing the presenting —
[`laranail/atlas`](https://github.com/laranail/atlas) for the ISO catalogue with flags and dial codes,
or `Locale::getDisplayRegion()` / `symfony/intl` for localised names. See
[Localise country names](recipes/localise-country-names.md).

## Geographic descriptions

Not the same thing as a country name. libphonenumber's geocoder describes where a number's *prefix*
is registered, which is often a city or a region:

```php
Phone::parse('+12015550123')->description();   // 'New Jersey'
Phone::parse('+254712123456')->description();  // 'Kenya'
```

Useful for showing a user where a number appears to be from. Not useful as a country field — use
`->country` for that.

---

[← Docs index](../README.md#documentation)
