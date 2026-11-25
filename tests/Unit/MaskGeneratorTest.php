<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\MaskGenerator;

beforeEach(function (): void {
    $this->masks = new MaskGenerator;
});

it('builds a national mask where the numbering plan has one length', function (string $country, string $expected): void {
    expect($this->masks->national($country))->toBe($expected);
})->with([
    'Kenya' => ['KE', '9999 999999'],
    'United Kingdom' => ['GB', '99999 999999'],
    'Türkiye' => ['TR', '9999 999 99 99'],
    'Nigeria' => ['NG', '9999 999 9999'],
    'United States' => ['US', '(999) 999-9999'],
]);

it('builds an international mask including the calling code', function (): void {
    expect($this->masks->international('KE'))->toBe('+999 999 999999');
});

/*
|--------------------------------------------------------------------------
| The refusal is the feature
|--------------------------------------------------------------------------
|
| German mobile numbers are ten OR eleven digits. A ten-digit mask silently
| swallows the eleventh keystroke and the user cannot tell why — the worst
| failure mode an input field has. Where the plan allows more than one length,
| no mask is emitted and the field runs unmasked.
|
*/
it('refuses to build a mask for a variable-length plan', function (): void {
    expect($this->masks->national('DE'))->toBeNull()
        ->and($this->masks->international('DE'))->toBeNull();
});

it('still offers a placeholder where it refuses a mask', function (): void {
    // A placeholder only suggests; it never blocks a keystroke, so the variable-length problem
    // does not apply to it.
    expect($this->masks->placeholder('DE'))->not->toBeNull()
        ->and($this->masks->placeholder('KE'))->toBe('0712 123456');
});

it('falls back to the general descriptor when a type has no lengths of its own', function (): void {
    // The NANP publishes no separate mobile lengths because mobile and fixed-line share ranges.
    expect($this->masks->national('US', PhoneNumberType::FixedLine))->toBe('(999) 999-9999');
});

it('returns null for a region it does not know', function (): void {
    expect($this->masks->national('ZZ'))->toBeNull()
        ->and($this->masks->placeholder('ZZ'))->toBeNull();
});

it('memoises so repeated lookups do not re-read metadata', function (): void {
    expect($this->masks->national('KE'))->toBe($this->masks->national('KE'));
});
