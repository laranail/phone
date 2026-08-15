# Find numbers in text

Locate, link or redact phone numbers inside prose — support tickets, CVs, scraped pages, chat logs.

```php
use Simtabi\Laranail\Phone\Facades\Phone;

foreach (Phone::find($ticket->body, 'KE') as $match) {
    $match->raw;             // '0712 123456' — exactly as written
    $match->offset;          // where it starts
    $match->number->e164;    // '+254712123456'
}
```

## Why not a regex

```
Call me on 0712 123456 about invoice 2024-00123.
```

Two runs of digits with punctuation. A pattern cannot tell them apart; the scanner can, because it
checks each candidate against the numbering plan before accepting it.

## Linking them

```php
$linked = Phone::replaceIn($body, fn ($match): string => sprintf(
    '<a href="%s">%s</a>',
    $match->number->telLink(),
    e($match->raw),
), 'KE');
```

Replacement runs backwards through the matches so earlier offsets stay valid — going forwards shifts
every later one and the second replacement lands in the wrong place.

## Redacting them

```php
Phone::redact($body, 'KE');
// 'Reach me on +254•••••••56 any time.'
```

For a stricter redaction, tighten the leniency: `ExactGrouping` matches only numbers printed the way
the region prints them, so an invoice reference that happens to be a valid length is left alone.

```php
Phone::find($body, 'KE', MatchLeniency::ExactGrouping);
```

> In a redaction pipeline a false positive **destroys information**, so the trade runs the opposite
> way from a support inbox, where a miss is what costs you. Pick the leniency for the consequence,
> not for the recall.

## Extracting to a column

```php
$first = Phone::find($lead->notes, 'KE')[0] ?? null;

$lead->update([
    'phone' => $first?->number->e164,
    'phone_country' => $first?->number->country,
]);
```

## Scanning a lot of text

The scan is metadata-bound rather than CPU-bound: libphonenumber loads a region's numbering plan on
first use and caches it. The first call for a region is the slow one.

Set `laranail.phone.scanning.limit` where the input is user-supplied — an unbounded match count on an
unbounded input is a way to be handed a very large array.

---

[← Docs index](../../README.md#documentation)
