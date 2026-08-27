# Audit a table in the background

A million-row column, on the queue, without holding it in memory.

## Dispatch it

```php
use Simtabi\Laranail\Phone\Jobs\AuditPhoneColumn;

AuditPhoneColumn::dispatch(
    model: User::class,
    column: 'phone',
    country: 'KE',
    key: 'users',
);
```

```php
$report = Cache::get('laranail.phone.audit.users');

$report['summary']['valid'];      // 987_412
$report['reasons']['TOO_SHORT'];  // 9_884
```

Nothing else is needed. The job reads the column in chunks, streams it through the report, and caches
the result under the key you chose.

## Watch it run

```php
$read = Cache::get('laranail.phone.audit.users.progress', 0);
$total = User::count();

$percent = $total === 0 ? 100 : (int) round($read / $total * 100);
```

Progress is written once per chunk rather than per row — a cache write per row would cost more than
the audit does, and a bar that moves a thousand rows at a time is not a worse bar.

## Narrow it

```php
AuditPhoneColumn::dispatch(User::class, 'phone', country: 'KE', key: 'active', scope: 'active');
```

The scope is a **named** query scope, applied without arguments. Anything richer would have to be a
closure, and a closure cannot be serialised into a queue payload — which is the same constraint that
makes the job take a model class rather than the rows themselves.

> A scope that does not return a builder throws. Ignoring it would audit the whole table while
> appearing to audit a subset, and the report would look entirely plausible.

## Tune the chunk

```php
AuditPhoneColumn::dispatch(User::class, 'phone', chunk: 5000, ttl: null);
```

`chunk` is rows per query. Larger means fewer round trips and more memory per query; the default
1,000 is a reasonable middle. `ttl: null` keeps the report until the cache evicts it, rather than for
a day.

## What the report costs

Memory is bounded, not proportional to the table. The accumulator holds the tallies and up to a
hundred example row indexes per repeated number — never the rows.

> The **counts** are exact. The row indexes under `duplicates` are a sample; `duplicate_counts` has
> the true totals. Keeping every index would make the structure grow with the table, which is the
> thing this whole path exists to avoid.

## Acting on the result

The report tells you what is wrong with the column. It deliberately cannot tell you which rows to
keep — that needs the rows, which is `Phone::audit()`'s job and does not scale to a million.

For a fix-up pass, use the duplicate groups to find the rows and go back to the database:

```php
foreach ($report['duplicate_counts'] as $e164 => $count) {
    if ($count < 2) {
        continue;
    }

    $rows = User::where('phone', $e164)->orderBy('id')->get();

    $rows->skip(1)->each->delete();   // keep the earliest, as the audit would
}
```

## When a job is the wrong tool

For anything that fits in memory — a CSV somebody just uploaded, a form submission, a few thousand
rows — call [`Phone::audit()`](../tools/batch.md) directly and get the entries as well as the report.
The queue buys you scale, and costs you per-row access and immediacy.

---

[← Docs index](../../README.md#documentation)
