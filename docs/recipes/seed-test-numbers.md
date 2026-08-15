# Seed test numbers

Fill factories and seeders with numbers that are valid for their country and belong to nobody.

## Why not just make one up

Because invented numbers are usually invalid, and a fixture built from one tests your validator
rather than your feature:

```php
Phone::parse('+15551234567')->isValid;   // false — 555-1234 is unallocated
```

`555` numbers are reserved for fiction in the NANP. A factory using one will pass every test that
does not validate and fail every test that does, for reasons that look like a bug in your code.

## In a factory

```php
use Simtabi\Laranail\Phone\PhoneNumberFactory;

public function definition(): array
{
    return [
        'name' => fake()->name(),
        'phone' => app(PhoneNumberFactory::class)->e164('KE'),
    ];
}
```

## Spreading across countries

The useful default for anything that iterates a catalogue:

```php
$numbers = app(PhoneNumberFactory::class)->spread(['KE', 'US', 'DE', 'BR', 'GB']);
```

Include **Germany and Brazil** deliberately when the thing under test involves masks — those are
plans with more than one national length, where [`MaskGenerator`](../tools/mask-generator.md) returns
null and the field must render unmasked. A spread of only single-length countries never exercises
that branch.

## Testing the unhappy paths

```php
$factory = app(PhoneNumberFactory::class);

$factory->invalid('KE');   // correctly shaped, unallocated — what real bad input looks like
$factory->junk();          // not a number at all
```

Both matter, and they test different things. `junk()` proves nothing throws; `invalid()` proves the
validator rejects a *plausible* number rather than only obvious rubbish.

## Distinct numbers per row

Every factory method is deterministic — same arguments, same number — because the underlying example
metadata is fixed. There is no seed to set and no flakiness to chase, but there is also no variation.

When rows must differ, vary them yourself:

```php
$base = app(PhoneNumberFactory::class)->national('KE');   // '0712 123456'

// Replace the last four digits with a sequence, then re-parse to confirm it is still valid.
$candidate = substr($base, 0, -4) . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
$number = Phone::parse($candidate, 'KE');

$phone = $number->isValid ? $number->e164 : null;
```

Re-parsing is not optional. Digit-swapping can walk a number out of its allocated range, and a
factory that silently produces invalid numbers is worse than one that produces identical ones.

## Numbers to never use in a seeder

Do not seed real numbers, including your own. Seeded data ends up in staging environments,
screenshots, bug reports and support tickets — and eventually somebody's SMS provider dials it.

---

[← Docs index](../../README.md#documentation)
