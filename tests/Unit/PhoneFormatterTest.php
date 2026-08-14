<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\PhoneFormatter;

beforeEach(function (): void {
    $this->formatter = new PhoneFormatter;
});

it('renders every format for a known number', function (PhoneNumberFormat $format, string $expected): void {
    expect($this->formatter->format('+905301111111', $format))->toBe($expected);
})->with([
    'E.164' => [PhoneNumberFormat::E164, '+905301111111'],
    'international' => [PhoneNumberFormat::International, '+90 530 111 11 11'],
    'national' => [PhoneNumberFormat::National, '0530 111 11 11'],
    'RFC 3966' => [PhoneNumberFormat::Rfc3966, 'tel:+90-530-111-11-11'],
]);

it('populates the whole value object', function (): void {
    $number = $this->formatter->parse('+905301111111');

    expect($number->e164)->toBe('+905301111111')
        ->and($number->country)->toBe('TR')
        ->and($number->callingCode)->toBe(90)
        ->and($number->type)->toBe(PhoneNumberType::Mobile)
        ->and($number->isValid)->toBeTrue()
        ->and($number->isPossible)->toBeTrue()
        ->and((string) $number)->toBe('+905301111111');
});

/*
| The country hint is only consulted when the input does not carry its own calling code. This is what
| makes it safe to pass a user's last-selected country as a hint: it can help a bare national number
| and it cannot corrupt one already written in E.164.
*/
it('ignores the country hint for input that carries its own calling code', function (): void {
    expect($this->formatter->toE164('+905301111111', 'US'))->toBe('+905301111111');
});

it('uses the country hint for bare national input', function (): void {
    expect($this->formatter->toE164('0530 111 11 11', 'TR'))->toBe('+905301111111');
});

it('falls back to a configured default country', function (): void {
    $formatter = new PhoneFormatter(defaultCountry: 'TR');

    expect($formatter->toE164('0530 111 11 11'))->toBe('+905301111111');
});

/*
| Parsing must never throw. `NumberParseException` is the single most reported exception across all
| three surveyed packages, and it is reported because they let it escape into request handling — a
| user typing `+` into an empty field is enough to raise it.
*/
it('never throws on input it cannot parse', function (string $input): void {
    $number = $this->formatter->parse($input);

    expect($number->isValid)->toBeFalse()
        ->and($number->e164)->toBeNull()
        // The raw input survives so nothing the user can see on screen is silently discarded.
        ->and($number->format(PhoneNumberFormat::E164))->toBe($input)
        ->and((string) $number)->toBe($input);
})->with([
    'prose' => ['not a number at all 42'],
    'lone plus' => ['+'],
    'letters' => ['abcdef'],
]);

it('returns an empty value for blank input', function (?string $input): void {
    expect($this->formatter->parse($input)->isEmpty())->toBeTrue()
        ->and($this->formatter->toE164($input))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
]);

it('carries an extension through parsing', function (): void {
    expect($this->formatter->parse('+1 555 123 4567 x890')->extension)->toBe('890');
});

it('normalises before parsing', function (): void {
    // The full chain: Arabic-Indic digits, through the IDD prefix rule, out as E.164.
    expect($this->formatter->toE164('٠٠٩٠٥٣٠١١١١١١١'))->toBe('+905301111111');
});

it('classifies line types', function (string $number, PhoneNumberType $expected): void {
    expect($this->formatter->parse($number)->type)->toBe($expected);
})->with([
    'Turkish mobile' => ['+905301111111', PhoneNumberType::Mobile],
    'Turkish landline' => ['+902125111111', PhoneNumberType::FixedLine],
    // In the NANP mobile and fixed-line share ranges, so "both" is the honest answer. Code that
    // treats this as "not mobile" rejects valid North American mobiles.
    'US number is ambiguous' => ['+12125551234', PhoneNumberType::FixedLineOrMobile],
]);

it('supplies example numbers and calling codes', function (): void {
    $example = $this->formatter->example('KE');

    expect($example?->isValid)->toBeTrue()
        ->and($example?->country)->toBe('KE')
        ->and($this->formatter->callingCodeFor('KE'))->toBe(254)
        // Null rather than libphonenumber's `0` sentinel, so absence is never mistaken for a code.
        ->and($this->formatter->callingCodeFor('ZZ'))->toBeNull();
});
