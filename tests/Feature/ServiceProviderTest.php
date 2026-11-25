<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Phone\Facades\Phone;
use Simtabi\Laranail\Phone\MaskGenerator;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNormalizer;
use Simtabi\Laranail\Phone\CountryReconciler;
use Simtabi\Laranail\Phone\PhoneNumberFactory;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;

it('namespaces its config under the vendor key', function (): void {
    // `config('phone')` would be a bare slug in a flat registry — a plausible collision with an
    // application's own config file, where the loser is replaced silently.
    expect(config('laranail.phone'))->toBeArray()
        ->and(config('laranail.phone.intel.enabled'))->toBeTrue()
        ->and(config('phone'))->toBeNull();
});

it('binds every service as a singleton', function (string $abstract): void {
    expect(app()->bound($abstract))->toBeTrue()
        ->and(app($abstract))->toBe(app($abstract));
})->with([
    PhoneFormatter::class,
    PhoneNormalizer::class,
    MaskGenerator::class,
    CountryReconciler::class,
    PhoneNumberFactory::class,
    ResolvesPhoneIntel::class,
]);

it('resolves the facade to the formatter', function (): void {
    expect(Phone::parse('+254712345678')->country)->toBe('KE');
});

it('honours the configured default country', function (): void {
    config()->set('laranail.phone.default_country', 'KE');
    app()->forgetInstance(PhoneFormatter::class);

    expect(app(PhoneFormatter::class)->toE164('0712 345678'))->toBe('+254712345678');
});

it('can disable the intel layer', function (): void {
    config()->set('laranail.phone.intel.enabled', false);
    app()->forgetInstance(PhoneFormatter::class);

    // The value object stays usable; only the three expensive lookups go quiet.
    $number = app(PhoneFormatter::class)->parse('+254712345678');

    expect($number->isValid)->toBeTrue()
        ->and($number->carrier())->toBeNull()
        ->and($number->timezones())->toBe([]);
});

it('registers a Blueprint macro that creates the column pair', function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->phoneNumber('phone');
    });

    expect(Schema::hasColumn('contacts', 'phone'))->toBeTrue()
        // The country column exists because E.164 alone cannot always name a country: `+1` is shared
        // by twenty-odd NANP members.
        ->and(Schema::hasColumn('contacts', 'phone_country'))->toBeTrue();
});

it('publishes translations under the vendor-scoped namespace', function (): void {
    expect(trans('laranail-phone::phone.invalid'))
        ->toBe('The :attribute field must be a valid phone number.');
});
