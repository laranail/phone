# The `phoneNumber` blueprint macro

One migration helper that creates the pair of columns this package expects —
registered on `Illuminate\Database\Schema\Blueprint` by the service provider.

```php
use Illuminate\Database\Schema\Blueprint;

Schema::create('contacts', function (Blueprint $table): void {
    $table->id();
    $table->phoneNumber();
    $table->timestamps();
});
```

## What it creates

| Column | Type | |
|---|---|---|
| `phone` | `varchar(20)` nullable | The E.164 value |
| `phone_country` | `char(2)` nullable | The ISO 3166-1 alpha-2 code |
| — | index on `phone` | |

## Signature

```php
$table->phoneNumber(
    string $column = 'phone',
    bool $nullable = true,
    bool $index = true,
): void
```

```php
$table->phoneNumber('mobile');                    // mobile + mobile_country
$table->phoneNumber('mobile', nullable: false);   // required
$table->phoneNumber('mobile', index: false);      // no index
```

## Why `varchar(20)`

E.164 caps at 15 digits plus the `+`, so 16 characters covers every number in existence. Twenty
leaves room for a short extension without a migration, and costs nothing on any modern database.

## Why the country column is nullable even when the number is not

The ISO code is derived from the number, and a number that could not be parsed has no country to
derive. Making it `NOT NULL` would mean a legacy import of unparseable rows cannot be written at all
— which turns a data-quality problem into an outage.

## Why there is an index

Phone numbers are looked up. Any "find the user by their number" flow, any deduplication check, and
`FluentRule::phone()->unique()` all query this column, and all of them do it on the E.164 form.

Pass `index: false` if you are adding your own composite index — a unique index across
`(tenant_id, phone)`, say.

## Registration is guarded

```php
if (Blueprint::hasMacro('phoneNumber')) {
    return;
}
```

Macros go into a global registry, so a second package claiming the same name would silently replace
this one. The guard means whoever registered first keeps it, and this package does not become the
cause of somebody else's missing column.

## Not using the macro

Nothing depends on it. The casts read whatever columns you name:

```php
$table->string('phone', 20)->nullable()->index();
$table->char('phone_country', 2)->nullable();
```

---

[← Docs index](../../README.md#documentation)
