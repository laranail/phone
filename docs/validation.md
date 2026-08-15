# Validation

There are no validation rules in this package. They live in `laranail/validation`, and this page
explains why and shows the seam.

## Why not here

One rule, one home. `laranail/validation` already owns every rule in this family — the fluent
builder, the translated messages, the array and wildcard handling, the `RuleSet` container. A second
set of phone rules here would mean two message files to keep in step, two places to look, and the
usual drift.

So `laranail/validation` requires `laranail/phone` and builds the rule on top of it. The dependency
points that way round because a rule needs a parser and a parser does not need a rule.

## The rule

```php
use Simtabi\Laranail\Validation\FluentRule;

public function rules(): array
{
    return [
        'phone' => FluentRule::phone()->required()->country('KE')->mobile(),
    ];
}
```

```php
->country(string|array|Closure)   // restrict to one or more ISO codes
->countryFrom(string $field)      // read the country from a sibling field
->international()                 // accept any country, but require a calling code
->type(PhoneNumberType|array)     // restrict by number type
->mobile() ->fixedLine() ->tollFree() ->voip()
->possible()                      // accept anything correctly shaped, allocated or not
->strict()                        // back to requiring an allocated range (the default)
->withoutExtension()              // reject `;ext=` and friends, which are accepted by default
->rejectShortNumbers()
->rejectEmergency()
->unique(string $table, ?string $column = null)
```

Full reference: [`laranail/validation` → Phone rule](https://opensource.simtabi.com/documentation/laranail/validation/tools/phone-rule).

## `->unique()` matters more than it looks

A plain `unique` on a phone column does not work. With a row holding `+254712123456`, a user typing
`0712 123456` passes — the strings differ, so the query finds nothing, and you get a duplicate
contact that no amount of squinting at the table will explain.

`->unique()` on a phone rule is not Laravel's — it normalises to E.164 first and queries the
canonical form, so both spellings collide the way they should. Unparseable input is not reported as a
duplicate: it is not a number, so it cannot collide with one.

## Valid versus possible

Two different questions, and the distinction is the reason `->possible()` exists:

| | Asks | Fails when |
|---|---|---|
| **valid** | Is this number allocated? | The range exists in the plan but has not been issued |
| **possible** | Is this number correctly shaped? | The length is wrong for the plan |

Strict validity is the right default. Use `->possible()` for numbering plans that move faster than
libphonenumber's release cadence — the cost is letting through numbers that look right and do not
exist yet.

## Messages

`laranail/validation` ships translated messages for every distinct failure — wrong country, wrong
type, not possible, short number, emergency number — rather than one generic "invalid phone number".

That is worth calling out because none of the phone packages surveyed for this design ships any, and
neither does `propaganistas/laravel-phone`: its README tells you to add the key yourself.

## Validating without `laranail/validation`

You can, and sometimes you should — a package that requires this one but not the rule library:

```php
$number = Phone::parse($input, 'KE');

if (! $number->isValid || ! $number->type->isMobile()) {
    // reject
}
```

That is all the rule does underneath. You just do not get the messages.

---

[← Docs index](../README.md#documentation)
