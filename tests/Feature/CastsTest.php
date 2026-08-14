<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Phone\Casts\AsPhoneNumber;
use Simtabi\Laranail\Phone\Casts\E164;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNumberValue;

beforeEach(function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->phoneNumber('phone');
        $table->string('sms_to')->nullable();
    });
});

/** @property-read PhoneNumberValue|null $phone */
final class Contact extends Model
{
    protected $table = 'contacts';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'phone' => AsPhoneNumber::class . ':phone_country',
            'sms_to' => E164::class . ':KE',
        ];
    }
}

it('stores E.164 and derives the country column', function (): void {
    $contact = Contact::create(['phone' => '0712 345678', 'phone_country' => 'KE']);

    expect($contact->fresh()->getRawOriginal('phone'))->toBe('+254712345678')
        ->and($contact->fresh()->getRawOriginal('phone_country'))->toBe('KE');
});

/*
| The regression the surveyed packages get wrong in opposite directions. One forces NATIONAL storage
| whenever a country column exists, which makes the number unreadable without the column; a fork of
| it removed that line and started storing the dial code twice. E.164 plus a derived country is
| neither: the number stays canonical and the column stays a convenience.
*/
it('keeps E.164 in the number column rather than duplicating the dial code', function (): void {
    $contact = Contact::create(['phone' => '+15551234567', 'phone_country' => 'US']);
    $stored = $contact->fresh();

    expect($stored->getRawOriginal('phone'))->toBe('+15551234567')
        ->and($stored->getRawOriginal('phone_country'))->toBe('US')
        // Not '+1+15551234567', and not '(555) 123-4567'.
        ->and($stored->getRawOriginal('phone'))->not->toContain('+1+');
});

/*
| Eloquent applies casts in attribute-assignment order, so with `phone` listed first the cast runs
| before `phone_country` exists. `propaganistas/laravel-phone` documents this as a caller
| requirement; a saving hook removes it instead. Both orders must produce the same row, or the
| behaviour depends on something invisible at the call site.
*/
it('canonicalises regardless of attribute assignment order', function (array $input): void {
    $stored = Contact::create($input)->fresh();

    expect($stored->getRawOriginal('phone'))->toBe('+254712345678')
        ->and($stored->getRawOriginal('phone_country'))->toBe('KE');
})->with([
    'number first' => [['phone' => '0712 345678', 'phone_country' => 'KE']],
    'country first' => [['phone_country' => 'KE', 'phone' => '0712 345678']],
]);

it('canonicalises on update as well as create', function (): void {
    $contact = Contact::create(['phone' => '+254712345678', 'phone_country' => 'KE']);

    $contact->update(['phone' => '0722 111222', 'phone_country' => 'KE']);

    expect($contact->fresh()->getRawOriginal('phone'))->toBe('+254722111222');
});

it('round-trips through the cast as a value object', function (): void {
    Contact::create(['phone' => '0712 345678', 'phone_country' => 'KE']);
    $phone = Contact::first()?->phone;

    expect($phone)->toBeInstanceOf(PhoneNumberValue::class)
        ->and($phone?->e164)->toBe('+254712345678')
        ->and($phone?->national)->toBe('0712 345678')
        ->and($phone?->country)->toBe('KE')
        ->and($phone?->isValid)->toBeTrue();
});

it('accepts a value object on assignment', function (): void {
    $contact = new Contact;
    $contact->phone = app(PhoneFormatter::class)->parse('+254712345678');
    $contact->save();

    expect($contact->fresh()->getRawOriginal('phone'))->toBe('+254712345678');
});

it('keeps unparseable input rather than discarding it', function (): void {
    // Throwing away something the user can see on their screen is worse than storing it.
    $contact = Contact::create(['phone' => 'call reception']);
    $stored = $contact->fresh();

    expect($stored->getRawOriginal('phone'))->toBe('call reception')
        ->and($stored->phone?->isValid)->toBeFalse()
        ->and($stored->phone?->raw)->toBe('call reception');
});

it('nulls both columns for an empty value', function (): void {
    $contact = Contact::create(['phone' => null]);

    expect($contact->fresh()->getRawOriginal('phone'))->toBeNull()
        ->and($contact->fresh()->getRawOriginal('phone_country'))->toBeNull()
        ->and($contact->fresh()->phone)->toBeNull();
});

/*
| The whole reason the lightweight cast exists. Without it a column accumulates `+254712345678`,
| `0712 345678` and `0712-345678` as three different strings for one number, and every `unique`
| check and every `where` silently passes.
*/
it('normalises the lightweight cast to E.164 so lookups match', function (): void {
    Contact::create(['sms_to' => '0712 345678']);
    Contact::create(['sms_to' => '+254 712 345678']);

    expect(Contact::where('sms_to', '+254712345678')->count())->toBe(2);
});
