# Store E.164 and a country column

Keep the canonical number in one column and the ISO code beside it, without letting the two drift.

The number column is the truth. The country column is a convenience — it makes a picker's selection
cheap to restore and lets you filter by country in SQL — and it must be **derived** from the number
rather than copied from whatever a form submitted.

## Migration

```php
Schema::create('contacts', function (Blueprint $table): void {
    $table->id();
    $table->phoneNumber();   // phone varchar(20) + phone_country char(2) + index
    $table->timestamps();
});
```

## Model

```php
use Simtabi\Laranail\Phone\Casts\AsPhoneNumber;

protected function casts(): array
{
    return [
        'phone' => AsPhoneNumber::class . ':phone_country',
    ];
}
```

The parameter names the sibling column. The cast parses on save and writes both attributes from that
one parse, so they cannot disagree.

```php
$contact->phone = '0712 123456';
$contact->save();

$contact->phone;           // PhoneNumberValue, e164 '+254712123456'
$contact->phone_country;   // 'KE'
```

## When the two disagree

They should not, but legacy data exists. `CountryReconciler` reports rather than rewrites:

```php
use Simtabi\Laranail\Phone\CountryReconciler;

$verdict = app(CountryReconciler::class)->reconcile($row->phone, $row->phone_country);

if ($verdict->conflicted) {
    logger()->warning('phone country drift', [
        'id' => $row->id,
        'stored' => $row->phone_country,
        'derived' => $verdict->country,
        'reason' => $verdict->reason,
    ]);
}
```

Reporting is the right default for a sweep. Silently correcting a column hides how many rows were
wrong, and therefore hides whatever wrote them that way.

## What not to do

**Do not store the national form** because you have a country column. This is the single most common
mistake, and it is a real bug in two of the Filament phone packages this design was built against:
one forces `NATIONAL` storage the moment a country column is configured — destroying the canonical
value — and a fork of it removes that line without replacing it, so the dial code is written twice.

E.164 stays canonical whether or not the second column exists.

**Do not make the country column authoritative.** If a user pastes `+254712123456` while a picker
still says Uganda, the number is Kenyan. Deriving the ISO code from the number being saved is what
prevents a contact quietly relocating.

## Querying

Always query the E.164 column, and normalise the search term first — a user typing `0712` will not
match a stored `+254712123456` otherwise, and has no way to know why:

```php
use Simtabi\Laranail\Phone\PhoneNormalizer;

$term = app(PhoneNormalizer::class)->digitsOnly($search);
$tail = ltrim(preg_replace('/^(00|0)/', '', ltrim($term, '+')) ?? '', '');

Contact::where('phone', 'like', "%{$tail}%")->get();
```

The tail is what the two forms share; the trunk prefix is exactly the part E.164 drops.

---

[← Docs index](../../README.md#documentation)
