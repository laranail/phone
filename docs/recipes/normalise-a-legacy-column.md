# Normalise a legacy column

Convert a column of whatever people typed over the years into E.164, without losing the rows that
cannot be converted.

## The shape of the job

A legacy phone column typically holds four kinds of value:

1. Numbers already in E.164 — nothing to do.
2. National numbers whose country you know from context.
3. Numbers with IDD prefixes, punctuation, non-ASCII digits or invisible characters.
4. Things that are not phone numbers at all.

The normaliser handles (3) transparently. The work is (2) and, more importantly, **not destroying**
(4).

## A backfill command

```php
use Illuminate\Console\Command;
use Simtabi\Laranail\Phone\Facades\Phone;

class NormalisePhones extends Command
{
    protected $signature = 'laranail::phone.backfill {--country=} {--dry-run}';

    public function handle(): int
    {
        $country = $this->option('country');
        $dry = (bool) $this->option('dry-run');
        $unparseable = [];

        Contact::query()
            ->whereNotNull('phone')
            ->chunkById(500, function ($rows) use ($country, $dry, &$unparseable): void {
                foreach ($rows as $row) {
                    $number = Phone::parse($row->phone, $country);

                    if ($number->isEmpty()) {
                        $unparseable[] = [$row->id, $row->phone];

                        continue;   // leave the row exactly as it is
                    }

                    if ($dry) {
                        continue;
                    }

                    $row->forceFill([
                        'phone' => $number->e164,
                        'phone_country' => $number->country,
                    ])->saveQuietly();
                }
            });

        foreach ($unparseable as [$id, $value]) {
            $this->warn("#{$id} could not be parsed: {$value}");
        }

        $this->info(count($unparseable) . ' rows left untouched.');

        return self::SUCCESS;
    }
}
```

Three things that matter more than the loop:

- **`--dry-run` first, always.** It reports the unparseable rows without writing anything, which is
  the number you actually want before you start.
- **Unparseable rows are skipped, not blanked.** A row holding `ask reception` is a data-quality
  problem for a human; overwriting it with null destroys the only evidence of what it was.
- **`saveQuietly()`** so the backfill does not fire model events across a million rows.

## Countries you do not know

If the column spans several countries with no other signal, do not guess. Parse without a hint:
numbers carrying their own calling code convert, and the rest are reported.

```php
$number = Phone::parse($row->phone);   // no hint at all
```

Then handle the remainder per segment — by tenant, by locale column, by whatever your data actually
knows — rather than applying one country to everything and quietly relocating half the table.

## Checking the result

```php
$total = Contact::whereNotNull('phone')->count();
$e164 = Contact::where('phone', 'like', '+%')->count();

$this->info("{$e164}/{$total} in E.164");
```

## Finding drift afterwards

If the table already had a country column, reconcile rather than trust it:

```php
use Simtabi\Laranail\Phone\CountryReconciler;

$verdict = app(CountryReconciler::class)->reconcile($row->phone, $row->phone_country);

if ($verdict->conflicted) {
    // report; the number is the truth
}
```

---

[← Docs index](../../README.md#documentation)
