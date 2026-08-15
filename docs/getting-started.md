# Getting started

Parse a number, store it, read it back — the whole loop in one page.

## The one rule worth learning first

**Store E.164.** `+254712123456` is the only format that carries its own country, so a column full of
E.164 needs no second column to be understood, no locale to be parsed, and no guessing when the same
row is read by a different part of the system.

Everything else in this package exists to get input *into* that form and back *out* of it in whatever
shape a human wants to read.

## Parsing

```php
use Simtabi\Laranail\Phone\Facades\Phone;

$number = Phone::parse('0712 123456', 'KE');

$number->e164;           // '+254712123456'
$number->national;       // '0712 123456'
$number->international;  // '+254 712 123456'
$number->country;        // 'KE'
$number->isValid;        // true
$number->type;           // PhoneNumberType::Mobile
```

The second argument is a **hint**, not an override. It is consulted only when the input does not
already carry its own calling code, so passing the wrong country cannot corrupt a number written in
E.164:

```php
Phone::parse('+254712123456', 'US')->country;   // 'KE' — the number wins
```

## Junk never throws

```php
Phone::parse('call reception')->isEmpty();   // true
Phone::parse('call reception')->raw;         // 'call reception'
Phone::format('call reception');             // 'call reception'
```

This is the single most important behavioural decision in the package. libphonenumber throws
`NumberParseException` on input it cannot read, and a form field is exactly where unreadable input
arrives. Returning the input untouched means a bad value reaches your validator — which can report it
properly — rather than a 500 reaching your user.

## Storing it

```php
use Illuminate\Database\Schema\Blueprint;

Schema::create('contacts', function (Blueprint $table): void {
    $table->id();
    $table->phoneNumber();   // `phone` varchar(20) + `phone_country` char(2) + an index
    $table->timestamps();
});
```

```php
use Simtabi\Laranail\Phone\Casts\AsPhoneNumber;

class Contact extends Model
{
    protected function casts(): array
    {
        return [
            'phone' => AsPhoneNumber::class . ':phone_country',
        ];
    }
}
```

```php
$contact->phone = '0712 123456';
$contact->save();

// The column holds E.164; the attribute hands back a value object.
$contact->fresh()->phone->national;   // '0712 123456'
$contact->fresh()->phone_country;     // 'KE'
```

See [Store E.164 and a country column](recipes/store-e164-and-country.md) for what the second column
is and is not for.

## Displaying it

```php
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;

Phone::format($contact->phone, PhoneNumberFormat::International);   // '+254 712 123456'
Phone::format($contact->phone, PhoneNumberFormat::National);        // '0712 123456'

$contact->phone->telLink();       // 'tel:+254-712-123456'
$contact->phone->whatsAppLink();  // 'https://wa.me/254712123456'
$contact->phone->masked();        // '+254 ••• •••456'
```

## Validating it

Validation lives in `laranail/validation`, not here — one rule, one home:

```php
use Simtabi\Laranail\Validation\FluentRule;

FluentRule::phone()->required()->country('KE')->mobile();
```

See [Validation](validation.md).

## Where to go next

- [Formats](formats.md) — the four formats and when each is the right one
- [Countries](countries.md) — how a country is resolved, and the `+1` problem
- [Architecture](architecture.md) — why the package is shaped this way

---

[← Docs index](../README.md#documentation)
