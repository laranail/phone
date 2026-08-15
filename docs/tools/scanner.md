# Scanner

`Phone::find()` — locate phone numbers inside free text.

```php
$text = 'Call me on 0712 123456 about invoice 2024-00123.';

Phone::find($text, 'KE');
// [ PhoneMatch{ raw: '0712 123456', offset: 11, number: PhoneNumberValue } ]
```

## What makes it different from a regex

It checks each candidate against the numbering plan before accepting it. The example above contains
one phone number and one invoice reference, both runs of digits with punctuation — a
`\d{3}[\s-]?\d{3}[\s-]?\d{4}` pattern cannot tell them apart, and this can.

That is the whole value. Parsing answers "is this string a number"; scanning answers "which parts of
this string are".

## The offset is the point

Every match carries the byte offset of its first character:

```php
$found = Phone::find('Ring 0712 123456 twice: 0712 123456.', 'KE');

$found[0]->offset;   // 5
$found[1]->offset;   // 24
$found[1]->end();    // one past the last character
```

Without it, a caller wanting to highlight or redact has to search the text again for the matched
string — which finds the *first* occurrence rather than *this* one. A message repeating a number gets
the wrong instance replaced.

## Replacing

```php
Phone::replaceIn($text, fn ($match) => '<a href="' . $match->number->telLink() . '">'
    . $match->number->national() . '</a>', 'KE');
```

Replacement runs **backwards through the matches**, and that is not a detail: going forwards shifts
every later offset by the difference in length, so the second replacement lands in the wrong place.

## Redacting

```php
Phone::redact('Reach me on 0712 123456 any time.', 'KE');
// 'Reach me on +254•••••••56 any time.'
```

The calling code survives on purpose — a redaction that removes the country usually removes the
reason the text was worth keeping.

## Leniency

How hard the scan tries to call something a number:

| | Finds | Right for |
|---|---|---|
| `Possible` | Anything that could be | A support inbox, where a miss costs more than a false positive |
| `Valid` *(default)* | Real numbers for some region | Most things |
| `StrictGrouping` | Valid, and grouped as that region groups | Structured documents |
| `ExactGrouping` | Valid, and grouped exactly as the region prints | Redaction, where a false positive destroys data |

```php
Phone::find($text, 'KE', MatchLeniency::Possible);
```

The default is `VALID` and configurable at `laranail.phone.scanning.leniency`.

## The country argument

Decides whether a bare **national** number is recognised at all. Numbers carrying their own calling
code are found regardless of it.

```php
Phone::find('Call 0712 123456', 'KE');   // one match
Phone::find('Call 0712 123456');          // none — nothing says which country
Phone::find('Call +254712123456');        // one match, no country needed
```

## Bounding the work

`laranail.phone.scanning.limit` caps matches per scan. The default is unbounded; set it where the
input is user-supplied and unbounded output would be a problem.

---

[← Docs index](../../README.md#documentation)
