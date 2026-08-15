# Batch and audit

`Phone::audit()` — one pass over a whole list, answering both what each row is and what is wrong with
the list.

```php
use Simtabi\Laranail\Phone\Facades\Phone;

$audit = Phone::audit($rows, 'KE');

$audit->summary();   // ['total' => 1200, 'valid' => 1147, 'duplicates' => 38, …]
$audit->reasons();   // ['TOO_SHORT' => 49, 'INVALID_COUNTRY_CODE' => 4]
$audit->distinct();  // the rows to keep
```

Everything else in this package answers a question about **one** number. Almost every real job
arrives as a list: a CSV column, a contacts import, a marketing list someone is about to send to, a
users table nobody has looked at in three years.

In this section: [Two questions](#two-questions-not-one) · [Entries](#entries) ·
[The report](#the-report) · [Streaming](#streaming-a-list-larger-than-memory) ·
[Cost](#what-it-costs) · [Method reference](#method-reference)

## Two questions, not one

They look like the same operation and they are not.

| | Answers | Output size |
|---|---|---|
| `Phone::audit()` → entries | *What is each of these?* | Grows with the input |
| `Phone::audit()` → report | *What is wrong with this list?* | Fixed, whatever the length |

A caller checking ten thousand rows before an import wants the second. Both come from the same pass
over the same entries, so the two can never disagree about the same input.

## Entries

```php
foreach ($audit as $entry) {
    $entry->index;         // position in the INPUT, and it survives filtering
    $entry->input;         // exactly what was supplied
    $entry->number;        // the PhoneNumberValue
    $entry->isValid();
    $entry->reason();      // PossibilityReason — why not, when not
    $entry->duplicateOf;   // the first index that produced the same E.164, or null
}
```

`index` is the part that makes a report actionable. "42 invalid numbers" is not something anyone can
fix; "rows 7, 19 and 104" is.

### Duplicates point backwards

`duplicateOf` names the **first** row that produced the same E.164, so de-duplicating is a filter and
the survivor is deterministic — the earliest row wins, which is the one a person is most likely to
have meant.

```php
Phone::audit(['+254712123456', '+254733333333', '0712 123456'], 'KE');
// index 2 → duplicateOf 0    ('0712 123456' and '+254712123456' are one number)

$audit->distinct();          // indexes 0 and 1
$audit->duplicateGroups();   // ['+254712123456' => [0, 2]]
```

The comparison is on E.164, not on the string. That is the whole point: `0712 123456`,
`+254 712 123456` and `254712123456` are one number written three ways, and a `SELECT DISTINCT`
keeps all three.

## The report

```php
$audit->summary();
// ['total' => 1200, 'valid' => 1147, 'invalid' => 53, 'possible' => 1151,
//  'duplicates' => 38, 'distinct' => 1162, 'countries' => 6]

$audit->reasons();     // ['TOO_SHORT' => 49, 'INVALID_COUNTRY_CODE' => 4]
$audit->countries();   // ['KE' => 980, 'UG' => 141, 'TZ' => 26, …]  commonest first
$audit->types();       // ['MOBILE' => 1102, 'FIXED_LINE' => 45, 'UNKNOWN' => 53]
```

**`reasons()` is the one to render.** "53 invalid" sends an operator looking at 53 rows one at a
time. "49 too short" tells them the column was truncated on export, and they fix it once.

> The reasons are honest about a case that used to be reported wrongly: a string the parser refused
> outright now comes back as `NOT_A_NUMBER` rather than being guessed at as `INVALID_COUNTRY_CODE`.
> See [`PhoneNumberValue`](value-objects.md).

## Streaming a list larger than memory

`audit()` holds every entry. `each()` yields them and accumulates nothing:

```php
foreach (Phone::each($millionRowIterator, 'KE') as $entry) {
    if (! $entry->isValid()) {
        fputcsv($rejects, [$entry->index, $entry->input, $entry->reason()->value]);
    }
}
```

Duplicate detection survives — it needs only the first index seen per E.164, which is O(distinct)
rather than O(n). What is given up is the report: nothing can tell you the totals until the generator
is exhausted, and by then the entries are gone.

And the shortest useful thing, when all you want is the set of numbers a column contains:

```php
Phone::e164List($column, 'KE');   // ['+254712123456', '+12015550123']
```

Unparseable rows are dropped rather than passed through, so everything it returns is a real number.

## What it costs

**Each distinct input is parsed once.** The reason to audit a list is that it is dirty, and a dirty
list repeats itself — so the pass memoises by raw input, and the saving grows with exactly the mess
that made the audit necessary. A column with 40 % repeats costs 60 % of the parses.

The memoisation key carries the country, so narrowing per call cannot hand one row the answer another
got for the same digits against a different region.

The cache lives for the pass and is discarded with it. It is deliberately **not** process-wide:
keying arbitrary user input for the lifetime of a worker is an unbounded map, and the locality that
makes it pay off is inside one list anyway.

## Method reference

| | |
|---|---|
| `Phone::audit(iterable, ?string)` | The whole verdict |
| `Phone::each(iterable, ?string)` | The same pass, streamed |
| `Phone::e164List(iterable, ?string, bool)` | Just the distinct numbers |
| `Phone::batch()` | The `PhoneBatch` service, for injection |

On the audit:

| | |
|---|---|
| `entries()` · `valid()` · `invalid()` | The rows |
| `distinct()` · `duplicates()` · `duplicateGroups()` · `unique()` | De-duplication |
| `summary()` · `reasons()` · `countries()` · `types()` | The report |
| `report()` · `toArray()` · `jsonSerialize()` | Output |
| `count()` · `isEmpty()` · iteration | It is `Countable` and `IteratorAggregate` |

The same shapes are reachable over HTTP — see [HTTP API](api.md).

---

[← Docs index](../../README.md#documentation)
