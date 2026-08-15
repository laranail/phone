# Validate a mobile number

Reject landlines, short codes and numbers from the wrong country — with a message that says which.

Rules live in `laranail/validation`. Install it alongside this package:

```bash
composer require laranail/validation
```

## The rule

```php
use Simtabi\Laranail\Validation\FluentRule;

public function rules(): array
{
    return [
        'phone' => FluentRule::phone()->required()->country('KE')->mobile(),
    ];
}
```

## Any country, but a real one

```php
FluentRule::phone()->required()->international()->mobile();
```

`international()` requires the input to carry its own calling code, which is the right constraint for
a global signup form: it removes the ambiguity rather than guessing at it.

## The country comes from another field

For a form with a separate country picker:

```php
'phone_country' => FluentRule::string()->required()->size(2),
'phone'         => FluentRule::phone()->required()->countryFrom('phone_country')->mobile(),
```

## Why `mobile()` accepts American numbers

The North American Numbering Plan does not distinguish mobile from fixed-line, so libphonenumber
reports `FIXED_LINE_OR_MOBILE` for every US and Canadian number. `->mobile()` counts that as a match.

Rejecting it would fail every valid American mobile number — a bug that is easy to ship and hard to
notice from a single-country test suite.

## Accepting numbers the metadata has not caught up with

```php
FluentRule::phone()->required()->possible();
```

Checks the shape rather than the allocation. Use it for numbering plans that move faster than
libphonenumber's release cadence; the cost is letting through numbers that look right and do not
exist yet.

## No duplicates

```php
FluentRule::phone()->required()->unique('contacts', 'phone');
```

The phone rule overrides `unique()` so it normalises to E.164 before querying. Laravel's generic one
does not, so a user typing `0712 123456` sails past a stored `+254712123456` and you get a duplicate
contact nobody can explain from looking at the table.

For an edit form, exclude the row being edited or it always collides with itself:

```php
FluentRule::phone()->unique('contacts', 'phone', fn (UniquePhone $rule) => $rule->ignore($id));
```

## Testing it

Use real example numbers, not invented ones:

```php
use Simtabi\Laranail\Phone\PhoneNumberFactory;

$factory = app(PhoneNumberFactory::class);

$valid = $factory->e164('KE');       // '+254712123456'
$invalid = $factory->invalid('KE');  // correctly shaped, unallocated
$junk = $factory->junk();            // not a number at all
```

`+15551234567` is **not** a valid US number — `isValidNumber()` rejects it, because 555-1234 is
unallocated. A fixture built from it tests your validator rather than your feature.

---

[← Docs index](../../README.md#documentation)
