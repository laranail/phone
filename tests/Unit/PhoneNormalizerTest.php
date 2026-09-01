<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\PhoneNormalizer;

/*
|--------------------------------------------------------------------------
| PhoneNormalizer
|--------------------------------------------------------------------------
|
| These are the inputs users actually produce. Every case here is one that a
| naive `preg_replace('/[^\d+]/', '')` gets wrong, which is what the three
| surveyed Filament packages each do.
|
*/

beforeEach(function (): void {
    $this->normalizer = new PhoneNormalizer;
});

it('folds non-ASCII digits to ASCII', function (string $input, string $expected): void {
    expect($this->normalizer->normalize($input))->toBe($expected);
})->with([
    'Arabic-Indic' => ['٠٠٩٠٥٣٠١١١١١١١', '+905301111111'],
    'Extended Arabic-Indic (Persian)' => ['۰۰۹۰۵۳۰۱۱۱۱۱۱۱', '+905301111111'],
    'mixed with ASCII' => ['+٩٠ 530 ١١١ 11 ١١', '+90 530 111 11 11'],
]);

it('promotes an international dialling prefix to a plus', function (string $input, string $expected): void {
    expect($this->normalizer->normalize($input))->toBe($expected);
})->with([
    '00 prefix' => ['00905301111111', '+905301111111'],
    '011 prefix, spaced' => ['011 90 530 111 11 11', '+905301111111'],
]);

it('leaves national numbers that merely start with zero alone', function (string $input): void {
    expect($this->normalizer->normalize($input))->toBe($input);
})->with([
    // A single leading zero is a trunk prefix, not an IDD prefix.
    'UK national' => ['020 7946 0018'],
    // 000… is not a country calling code.
    'triple zero' => ['0001234567890'],
    // Too short after the prefix to be an international number at all.
    'short 00' => ['00123456'],
]);

it('folds space-like characters to a space and strips zero-width ones', function (): void {
    // Visible separators stay visible; invisible marks disappear. Stripping a no-break space would
    // join two digit groups into a string the user never typed.
    expect($this->normalizer->normalize("+90\u{00A0}530\u{202F}111 11 11"))->toBe('+90 530 111 11 11')
        ->and($this->normalizer->normalize("+90\u{200B}530111\u{200F}1111"))->toBe('+905301111111');
});

it('folds typographic dashes and plus signs', function (): void {
    expect($this->normalizer->normalize('+90–530—111-11-11'))->toBe('+90-530-111-11-11')
        ->and($this->normalizer->normalize('＋905301111111'))->toBe('+905301111111');
});

it('returns null when there is nothing to parse', function (?string $input): void {
    expect($this->normalizer->normalize($input))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'punctuation only' => ['-- () --'],
]);

it('splits an extension off in every shape people write it', function (string $input, string $expected): void {
    expect($this->normalizer->normalize($input))->toBe($expected);
})->with([
    'RFC 3966' => ['+15551234567;ext=890', '+15551234567;ext=890'],
    'x890' => ['+1 555 123 4567 x890', '+1 555 123 4567;ext=890'],
    'ext. 890' => ['+1 555 123 4567 ext. 890', '+1 555 123 4567;ext=890'],
    'hash' => ['+1 555 123 4567 #890', '+1 555 123 4567;ext=890'],
]);

it('converts vanity letters only when asked', function (): void {
    $vanity = new PhoneNormalizer(convertVanityLetters: true);

    expect($vanity->digitsOnly('1-800-FLOWERS'))->toBe('18003569377')
        // Off by default, because letters in a phone field are not always keypad instructions.
        ->and($this->normalizer->digitsOnly('1-800-FLOWERS'))->toBe('1800');
});

it('reduces to digits while keeping a leading plus', function (): void {
    expect($this->normalizer->digitsOnly('+90 (530) 111-11-11'))->toBe('+905301111111');
});
