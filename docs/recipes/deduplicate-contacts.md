# Deduplicate contacts

Merge records that hold the same number written differently.

## The problem

```
+254712123456
0712 123456
254712123456
(0712) 123-456
```

Four rows, one person. `SELECT DISTINCT phone` keeps all four.

## The fix, in one line

```php
use Simtabi\Laranail\Phone\Facades\Phone;

$key = Phone::of($row->phone)->country($row->country)->e164();
```

E.164 is the only format that carries its own country, which is what makes it a usable key. Everything
else needs context you may not have on the row.

## When you cannot normalise

Some rows will not parse — junk, extensions, free text. Those are not duplicates of anything and
should keep their own identity rather than collapsing into a shared null:

```php
$groups = [];

foreach ($rows as $row) {
    $key = Phone::of($row->phone)->country($row->country)->e164() ?? 'raw:' . $row->id;
    $groups[$key][] = $row;
}
```

## Comparing without normalising

Where the two values come from different sources and neither is canonical:

```php
Phone::of($a)->is($b);        // true or false
Phone::of($a)->matches($b);   // the grade behind it
```

| Grade | Same number? | |
|---|:---:|---|
| `ExactMatch` | yes | Same country code, same national number |
| `NsnMatch` | yes | National numbers agree; only one side carried a country |
| `ShortNsnMatch` | **no** | One is a suffix of the other — an area code may be missing |
| `NoMatch` | no | |

> `ShortNsnMatch` is the one to be careful with. `123456` is a suffix of `0201123456`, and treating
> that as a match merges two different subscribers in two different cities. `is()` excludes it
> deliberately.

## Deduplicating across a country column

If rows carry a country column, reconcile rather than trust it:

```php
use Simtabi\Laranail\Phone\CountryReconciler;

$verdict = app(CountryReconciler::class)->reconcile($row->phone, $row->country);

if ($verdict->conflicted) {
    // The number and the column disagree. The number wins — but the disagreement is
    // worth reporting, because something wrote it that way.
}
```

---

[← Docs index](../../README.md#documentation)
