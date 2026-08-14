<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNumberFactory;

beforeEach(function (): void {
    $this->factory = new PhoneNumberFactory;
    $this->formatter = new PhoneFormatter;
});

it('produces numbers that are actually valid for their country', function (string $country): void {
    $number = $this->factory->make($country);

    expect($number?->isValid)->toBeTrue()
        ->and($number?->country)->toBe($country);
})->with(['KE', 'GB', 'DE', 'NG', 'US', 'ZA', 'IN', 'BR']);

it('offers E.164 and national renderings', function (): void {
    expect($this->factory->e164('KE'))->toStartWith('+254')
        ->and($this->factory->national('KE'))->toStartWith('0');
});

/*
| Possible-but-invalid is the case real validation has to catch, and it is different from junk: junk
| fails to parse at all and exercises a different branch entirely. Both are needed to test a rule.
*/
it('produces a number that parses but is not valid', function (): void {
    $number = $this->formatter->parse($this->factory->invalid('KE'));

    expect($number->isValid)->toBeFalse()
        ->and($number->isPossible)->toBeTrue();
});

it('produces junk that does not parse at all', function (): void {
    expect($this->formatter->parse($this->factory->junk())->e164)->toBeNull();
});

it('spreads numbers across countries for a seeder', function (): void {
    $spread = $this->factory->spread(['KE', 'NG', 'ZA']);

    expect($spread)->toHaveKeys(['KE', 'NG', 'ZA'])
        ->and($spread['KE'])->toStartWith('+254')
        ->and($spread['NG'])->toStartWith('+234')
        ->and($spread['ZA'])->toStartWith('+27');
});

it('skips countries it cannot produce a number for', function (): void {
    expect($this->factory->spread(['KE', 'ZZ']))->toHaveKey('KE')
        ->and($this->factory->spread(['KE', 'ZZ']))->not->toHaveKey('ZZ');
});
