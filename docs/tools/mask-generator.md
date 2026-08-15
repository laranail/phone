# `MaskGenerator`

Three methods producing per-country input templates —
`Simtabi\Laranail\Phone\MaskGenerator`, resolved as a singleton.

```php
use Simtabi\Laranail\Phone\MaskGenerator;

$generator = app(MaskGenerator::class);

$generator->national('KE');      // '9999 999999'
$generator->international('KE'); // '+999 999 999999'
$generator->placeholder('KE');   // '0712 123456'
```

Masks use `9` as the digit token, which is the convention Alpine's `x-mask` and most JavaScript mask
plugins expect. Punctuation and the `+` are preserved literally.

## `national(string $country, PhoneNumberType $type = Mobile): ?string`

A mask for the national form — what you want beside a country picker, where the picker holds the
calling code.

## `international(string $country, PhoneNumberType $type = Mobile): ?string`

A mask including the `+` and the calling code — for a single field holding the whole number.

## `placeholder(string $country, PhoneNumberType $type = Mobile): ?string`

A real example number in national form. **Always available, even where a mask is not**: a placeholder
only suggests, so the variable-length problem below does not apply to it.

## Null is an answer

Roughly 16% of countries get `null` from `national()` and `international()`, including Germany,
Brazil, the Netherlands and Argentina. That is not a lookup failure — it is the generator refusing.

German mobile numbers are **ten or eleven digits**. A ten-digit mask silently swallows the eleventh
keystroke: no error, no rejection, just a key that did nothing, and a user who cannot tell why their
number will not finish. A field rendered unmasked is strictly better than one that is confidently
wrong.

```php
$generator->national('DE');      // null
$generator->placeholder('DE');   // '01512 3456789'  — still useful
```

The check is whether the numbering plan publishes exactly one possible length for that type. Where a
type has no lengths of its own, the general descriptor is used instead — the normal case in the NANP,
where mobile and fixed-line share ranges.

Callers should treat null as "render unmasked", not as "no data".

## Caching

Deriving a mask reads a region's metadata, which is a per-region file load. Both methods memoise
in-process, and `config('laranail.phone.masks')` controls the persistent cache store and TTL.

The TTL default is null — forever — and that is correct rather than lazy: the answer changes only
when libphonenumber is upgraded, which is a deploy.

> **Building a table for every country is expensive.** Measured on libphonenumber 9.0.36: 245 regions
> cost roughly 510 ms and 4 MB on a cold pass. Cache the whole table rather than calling this per
> request; see how `laranail/tayari-ui-livewire` does it for its picker.

---

[← Docs index](../../README.md#documentation)
