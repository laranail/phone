# `PhoneNormalizer`

Two methods that clean input before it is parsed —
`Simtabi\Laranail\Phone\PhoneNormalizer`, resolved as a singleton and called by
[`PhoneFormatter`](formatter.md) on every parse.

You rarely call it directly. It is documented because what it does is the difference between a user
being told their perfectly good number is invalid and it simply working.

## `normalize(?string $input): ?string`

Runs four passes, in order.

### 1. Non-ASCII digits

Arabic-Indic (`٠١٢٣٤٥٦٧٨٩`) and Eastern Arabic / Persian (`۰۱۲۳۴۵۶۷۸۹`) become ASCII:

```php
$normalizer->normalize('٠٠٩٠٥٣٠١١١١١١١');   // '+905301111111'
```

A user typing on an Arabic or Persian keyboard produces these. Without this pass, every one of their
numbers is rejected.

### 2. Invisible characters

Non-breaking spaces, zero-width spaces and joiners, byte-order marks, ideographic spaces. Stripped
entirely rather than folded to a space, because they carry no information and are invisible in every
error message the user will ever see.

A number copied out of a spreadsheet, a PDF or a chat message routinely arrives wrapped in these.

Ordinary separators — spaces, hyphens, dots, parentheses — are folded to a space rather than removed,
so grouping is preserved for the parser.

### 3. IDD prefixes

`00`, `011`, `0011` and the other international dialling prefixes become `+`:

```php
$normalizer->normalize('00254712123456');   // '+254712123456'
```

This is how people actually write international numbers outside the E.164 convention, and how phones
themselves display them in a call log.

### 4. Vanity letters — off by default

`1-800-FLOWERS` becomes digits only when `config('laranail.phone.convert_vanity_letters')` is true.

It is off because letters in a phone field are not always keypad instructions. A Nigerian number
written `080 ABC 1234` uses them as an initialism, and converting blindly produces digits that dial
somewhere else. Turn it on for a US-facing marketing form; leave it off for a contact book.

## `digitsOnly(?string $input): ?string`

Everything above, then strip to digits and a leading `+`. For comparison and search rather than
storage.

```php
$normalizer->digitsOnly('+254 (712) 123-456');   // '+254712123456'
```

## Constructing it

```php
use Simtabi\Laranail\Phone\PhoneNormalizer;

new PhoneNormalizer(convertVanityLetters: true);
```

## What it does not do

It does not decide what the input means. No parsing, no country resolution, no validity check — it
only removes the noise that would stop a parser recognising a number it would otherwise accept.

That separation is deliberate: normalisation is safe to run on anything, including a value you have
not decided is a phone number yet.

---

[← Docs index](../../README.md#documentation)
