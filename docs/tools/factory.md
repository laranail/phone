# `PhoneNumberFactory`

Six methods producing test numbers that are correctly shaped and belong to nobody —
`Simtabi\Laranail\Phone\PhoneNumberFactory`, resolved as a singleton.

```php
use Simtabi\Laranail\Phone\PhoneNumberFactory;

$factory = app(PhoneNumberFactory::class);
```

Every number comes from libphonenumber's own example metadata, so it is valid for its plan and
guaranteed unallocated. That is the point: a hand-written `+15551234567` is **not** a valid US number
— `isValidNumber()` rejects it — so a fixture built from one tests your validator rather than your
feature.

## `make(?string $country = null, PhoneNumberType $type = Mobile): ?PhoneNumberValue`

A full value object. Falls back to the configured default country when none is given.

## `e164(?string $country = null, PhoneNumberType $type = Mobile): ?string`

```php
$factory->e164('KE');   // '+254712123456'
```

The one you want in a factory definition:

```php
public function definition(): array
{
    return [
        'phone' => app(PhoneNumberFactory::class)->e164('KE'),
    ];
}
```

## `national(?string $country = null, PhoneNumberType $type = Mobile): ?string`

The national form, for seeding a form field or a legacy column.

## `invalid(?string $country = null): ?string`

Something **correctly shaped whose range has not been allocated** — which is what real invalid input
looks like, and much more useful than `'not a phone number'`.

```php
$factory->invalid('KE');   // shaped like a Kenyan number, not a real one
```

Use it to assert that a validator rejects a plausible number rather than only obvious junk. A test
that only ever feeds `'abc'` to a phone rule proves very little.

## `junk(): string`

Something that is not a phone number at all, for the unparseable path:

```php
$factory->junk();   // e.g. 'call reception'
```

## `spread(array $countries, PhoneNumberType $type = Mobile): array`

One number per country, for testing anything that iterates a catalogue:

```php
$factory->spread(['KE', 'US', 'DE', 'BR']);
// ['KE' => '+254…', 'US' => '+1…', 'DE' => '+49…', 'BR' => '+55…']
```

Deliberately include Germany and Brazil in a spread when testing masks — those are the plans with
more than one national length, where [`MaskGenerator`](mask-generator.md) returns null.

## Determinism

Every method returns the same number for the same arguments, because the example metadata is fixed.
There is no randomness to seed and no flakiness to chase.

If you need distinct numbers per row, vary them yourself — append a sequence and re-parse, or use
`spread()` across countries.

---

[← Docs index](../../README.md#documentation)
