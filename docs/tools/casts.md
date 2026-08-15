# Eloquent casts

Two casts, and choosing between them is a question of whether anything reads the number back.

| Cast | Attribute becomes | Use when |
|---|---|---|
| [`AsPhoneNumber`](#asphonenumber) | `PhoneNumberValue` | Anything formats, links to, or inspects the number |
| [`E164`](#e164) | `string` | The column is only ever written and sent |

Both normalise on write, so the column holds E.164 either way.

## `AsPhoneNumber`

```php
use Simtabi\Laranail\Phone\Casts\AsPhoneNumber;

protected function casts(): array
{
    return [
        'phone' => AsPhoneNumber::class,
        // or, to keep a country column in step:
        'phone' => AsPhoneNumber::class . ':phone_country',
    ];
}
```

```php
$contact->phone = '0712 123456';   // assigned as a string
$contact->save();

$contact->phone;                    // PhoneNumberValue
$contact->phone->national;          // '0712 123456'
$contact->phone->telLink();         // 'tel:+254-712-345678'
```

Reading an unparseable legacy value gives a value object whose `isEmpty()` is true and whose `raw` is
what the column held. Nothing throws, and the bad row stays visible.

### The country column

The optional parameter names a sibling column that receives the ISO code derived from the number
being saved — derived, not copied from whatever a form last submitted, so the two columns cannot
drift.

It is a **convenience**: it makes the picker's selection cheap to restore and lets you filter by
country in SQL. It is never the source of truth. E.164 already carries the country, and when the two
disagree the number wins. See [`CountryReconciler`](../countries.md#a-stored-iso-column-is-a-hint).

### Why it hooks `saving`

Writing two attributes from one assignment cannot happen in `set()`, because the country is only
knowable after the number has been parsed — and the parse is the expensive part, so doing it twice is
not an option either.

The cast therefore registers a model `saving` listener the first time it is used, **keyed on the
dispatcher's object id** so that a second model class does not re-register the same closure and a
second test does not accumulate listeners against a fresh application instance.

## `E164`

```php
use Simtabi\Laranail\Phone\Casts\E164;

protected function casts(): array
{
    return [
        'sms_to' => E164::class,
        'sms_to' => E164::class . ':KE',   // parse bare national input against Kenya
    ];
}
```

The attribute stays a string. The cast normalises on write and hands the stored value straight back
on read.

Reach for it when the column is a destination rather than a contact — an SMS recipient, a webhook
target — where nothing formats it and the value-object allocation is waste.

The optional parameter is a **country hint** for bare national input, not a column name. That is the
one place the two casts' parameters differ, and it is worth reading twice.

## Both, on one model

Nothing stops it:

```php
protected function casts(): array
{
    return [
        'phone' => AsPhoneNumber::class . ':phone_country',
        'sms_to' => E164::class,
    ];
}
```

## Migrations

```php
$table->phoneNumber();   // phone varchar(20) + phone_country char(2) + index
```

See [The `phoneNumber` blueprint macro](blueprint-macro.md).

---

[← Docs index](../../README.md#documentation)
