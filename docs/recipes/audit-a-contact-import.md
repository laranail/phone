# Audit a contact import

Judge a CSV before it reaches the database, and report what is wrong in terms someone can act on.

## Look before you import

```php
use Simtabi\Laranail\Phone\Facades\Phone;

$rows = array_column($csv, 'phone');

$audit = Phone::audit($rows, 'KE');

$audit->summary();
// ['total' => 1200, 'valid' => 1147, 'invalid' => 53,
//  'duplicates' => 38, 'distinct' => 1162, 'countries' => 6]
```

Two numbers in there decide whether to proceed, and neither is "valid":

- **`countries` => 6** on a list that should be one country means the default region is wrong for
  some rows, or the export carried numbers from a system you did not know about.
- **`duplicates` => 38** means the source is not what someone told you it was.

## Report the failures by cause, not by count

```php
$audit->reasons();
// ['TOO_SHORT' => 49, 'INVALID_COUNTRY_CODE' => 4]
```

"53 invalid" sends an operator through 53 rows one at a time. "49 too short" tells them the column
was truncated on export, and one fix clears 49 rows.

Hand back the addressable rows:

```php
foreach ($audit->invalid() as $entry) {
    $report[] = [
        'row' => $entry->index + 2,          // +2: header row, and humans count from one
        'value' => $entry->input,
        'problem' => $entry->reason()->label(),
        'fixable' => $entry->reason()->isCorrectable(),
    ];
}
```

`isCorrectable()` separates "one digit short" from "this was never a phone number". Only the first is
worth sending back to whoever produced the file.

## Import the survivors

```php
foreach ($audit->distinct() as $entry) {
    if (! $entry->isValid()) {
        continue;
    }

    Contact::create([
        'phone' => $entry->e164(),
        'phone_country' => $entry->country(),
        'source_row' => $entry->index,
    ]);
}
```

`distinct()` drops rows that repeat an earlier one, and the survivor is deterministically the
**earliest** — the original, not the re-entry.

> Comparison is on E.164, so `0712 123456`, `+254 712 123456` and `254712123456` are one number. A
> `SELECT DISTINCT` on the raw column keeps all three.

## When the file is bigger than memory

`audit()` holds every row. For a file that will not fit, stream:

```php
$handle = fopen($path, 'rb');

$numbers = (function () use ($handle): Generator {
    while (($row = fgetcsv($handle)) !== false) {
        yield $row[3];
    }
})();

foreach (Phone::each($numbers, 'KE') as $entry) {
    $entry->isValid()
        ? Contact::create(['phone' => $entry->e164(), 'phone_country' => $entry->country()])
        : fputcsv($rejects, [$entry->index, $entry->input, $entry->reason()->value]);
}
```

Duplicate detection still works. The report does not — nothing can total a list it has already
forgotten — so count as you go if you need one.

## Two things not to do

**Do not parse row by row in a loop of your own.** The batch memoises by input, and an import repeats
itself by definition, so it does strictly less work than the loop you would write.

**Do not treat the country column as authoritative.** E.164 carries its own country and a
hand-maintained ISO column drifts from it. See
[Store E.164 and a country column](store-e164-and-country.md).

---

[← Docs index](../../README.md#documentation)
