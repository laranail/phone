<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNumberValue;

beforeEach(function (): void {
    $this->formatter = new PhoneFormatter;
    $this->mobile = $this->formatter->parse('+905301111111');
});

it('exposes a tel link', function (): void {
    expect($this->mobile->telLink())->toBe('tel:+90-530-111-11-11');
});

it('builds a WhatsApp link only for numbers that can receive a message', function (): void {
    expect($this->mobile->whatsAppLink())->toBe('https://wa.me/905301111111')
        ->and($this->mobile->whatsAppLink('Hi there'))->toBe('https://wa.me/905301111111?text=Hi%20there')
        // A landline cannot receive a WhatsApp message; returning a link would open to an error.
        ->and($this->formatter->parse('+902125111111')->whatsAppLink())->toBeNull();
});

it('masks the middle while keeping enough to identify the record', function (): void {
    // Calling code and last two digits survive: enough for a human to confirm the right customer,
    // not enough to dial.
    expect($this->mobile->masked('*'))->toBe('+90********11')
        ->and($this->formatter->parse('')->masked())->toBe('');
});

it('compares numbers by value, not by how they were written', function (): void {
    $national = $this->formatter->parse('0530 111 11 11', 'TR');

    expect($this->mobile->equals($national))->toBeTrue()
        ->and($this->mobile->equals($this->formatter->parse('+902125111111')))->toBeFalse()
        ->and($this->mobile->equals(null))->toBeFalse();
});

it('serialises to a stable array shape', function (): void {
    expect($this->mobile->toArray())->toMatchArray([
        'e164' => '+905301111111',
        'national' => '0530 111 11 11',
        'international' => '+90 530 111 11 11',
        'rfc3966' => 'tel:+90-530-111-11-11',
        'country' => 'TR',
        'calling_code' => 90,
        'type' => 'MOBILE',
        'is_valid' => true,
        'is_possible' => true,
    ]);

    expect(json_decode(json_encode($this->mobile, JSON_THROW_ON_ERROR), true))->toBe($this->mobile->toArray());
});

it('returns nothing from intel lookups when no resolver is supplied', function (): void {
    // The value object stays constructible and useful without the intel layer — which is what lets
    // a test build one directly.
    $bare = new PhoneNumberValue(raw: '+905301111111', e164: '+905301111111');

    expect($bare->carrier())->toBeNull()
        ->and($bare->description())->toBeNull()
        ->and($bare->timezones())->toBe([]);
});

it('treats an empty value as empty', function (): void {
    $empty = PhoneNumberValue::empty();

    expect($empty->isEmpty())->toBeTrue()
        ->and($empty->isValid)->toBeFalse()
        ->and((string) $empty)->toBe('');
});
