# Catalogue

`Phone::catalogue()` — what libphonenumber knows about regions and calling codes, rather than about a
number.

## The method that earns it its place

```php
Phone::catalogue()->regionsForCallingCode(1);
// ['US', 'AG', 'AI', 'AS', 'BB', 'BM', … ] — 25 of them
```

**A calling code does not identify a country.** `+1` is the North American Numbering Plan, shared by
twenty-odd countries and territories, and every design that assumes otherwise files Trinidad under
the United States.

Anything mapping dial codes to countries needs the plural answer. `primaryRegionForCallingCode()`
gives the singular one where a singular one is meaningful, and null where it is not.

## Regions

```php
Phone::catalogue()->regions();          // 245 codes
Phone::catalogue()->callingCodeFor('KE');  // 254
Phone::catalogue()->isNanp('TT');       // true
```

> Not the same as the ISO 3166-1 list. It omits places with no numbering plan of their own and
> includes a few that are not countries.

## Line types a region actually allocates

```php
Phone::catalogue()->typesFor('US');
```

Worth consulting before offering a "mobile only" toggle. In the NANP there is no such distinction to
make, so the toggle would reject every valid number — see
[the note in the fluent builder](fluent-builder.md#line-types).

## Portability — the carrier caveat as data

```php
Phone::catalogue()->isPortable('KE');   // true
```

Where this is true, a carrier name is the network the number was **issued** on and may not be the
network it is on now. That is the caveat every carrier lookup carries, expressed as something you can
branch on rather than a paragraph in a README.

## National prefix

```php
Phone::catalogue()->nationalPrefix('KE');   // '0'
Phone::catalogue()->nationalPrefix('IT');   // null
```

The digits a region prepends when dialling domestically. Italy prepends nothing, which is why a
blanket "strip the leading zero" rule corrupts Italian numbers — and why anything reconstructing a
national form from E.164 has to ask rather than assume.

---

[← Docs index](../../README.md#documentation)
