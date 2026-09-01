<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Phone\CountryReconciler;
use Simtabi\Laranail\Phone\Facades\Phone;
use Simtabi\Laranail\Phone\PhoneNumberValue;

/**
 * Casts a column to a {@see PhoneNumberValue}, keeping a sibling country column in sync.
 *
 * ```php
 * protected function casts(): array
 * {
 *     return [
 *         'phone' => AsPhoneNumber::class . ':phone_country',
 *     ];
 * }
 * ```
 *
 * **What is stored is always E.164.** The country column is written alongside it as a convenience for
 * querying and for pre-selecting a picker, never as the source of truth — see
 * {@see CountryReconciler}. This is the point the surveyed packages get wrong in opposite directions:
 * one forces national format whenever a country column exists, which makes the number unreadable
 * without the column; a fork of it removes that and stores the dial code twice. Storing E.164 plus a
 * derived country is neither.
 *
 * Reading is total: an unparseable legacy value comes back as a `PhoneNumberValue` whose `isValid`
 * is false and whose `raw` holds the original. Nothing throws, and nothing is silently dropped.
 *
 * ### Why there is a saving hook
 *
 * Eloquent applies casts in the order attributes are assigned, so
 * `Contact::create(['phone' => '0712 345678', 'phone_country' => 'KE'])` reaches `set()` for `phone`
 * **before** `phone_country` exists — and a bare national number cannot be canonicalised without a
 * country. Reversing the two array keys would work, which is precisely what makes it a bad API: the
 * behaviour depends on something invisible at the call site, and it fails silently by storing the
 * national form.
 *
 * `propaganistas/laravel-phone` documents this ordering requirement and leaves it to the caller. This
 * cast instead registers a one-off `saving` listener per model class, which re-canonicalises once
 * every attribute is present. Assignment order stops mattering.
 *
 * @implements CastsAttributes<PhoneNumberValue|null, PhoneNumberValue|string|null>
 */
final class AsPhoneNumber implements CastsAttributes
{
    /**
     * Which listeners are already attached, keyed `Class::column@dispatcherId`.
     *
     * The dispatcher's object id is part of the key on purpose. Eloquent's listeners live on whatever
     * dispatcher is current, and that instance is replaced whenever the container is rebuilt — between
     * test cases, and between requests under Octane. Keying on the class alone would leave this cache
     * claiming a listener is attached after the dispatcher holding it had been thrown away, and the
     * hook would silently stop running.
     *
     * @var array<string, true>
     */
    private static array $hooked = [];

    public function __construct(
        private readonly ?string $countryColumn = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PhoneNumberValue
    {
        // A column holding something other than a string is not a phone number that needs casting —
        // it is a schema mistake, and turning an array into `"Array"` would hide it behind a value
        // object that looks parsed.
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        if ($value === '') {
            return null;
        }

        $country = $this->countryColumn === null
            ? null
            : ($attributes[$this->countryColumn] ?? null);

        return Phone::parse((string) $value, is_string($country) ? $country : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $this->hookSaving($model, $key);

        if ($value === null || $value === '') {
            return $this->countryColumn === null
                ? [$key => null]
                : [$key => null, $this->countryColumn => null];
        }

        $number = $value instanceof PhoneNumberValue
            ? $value
            : Phone::parse((string) $value, $this->countryFrom($attributes));

        // An unparseable value is written back as the user typed it rather than as null. Discarding
        // input because a parser disagreed with it loses data the user can see on their screen.
        $stored = $number->e164 ?? $number->raw;

        if ($this->countryColumn === null) {
            return [$key => $stored];
        }

        return [
            $key => $stored,
            $this->countryColumn => $number->country ?? $this->countryFrom($attributes),
        ];
    }

    /**
     * Attach a one-off listener that canonicalises the pair once every attribute has landed.
     *
     * Registered per model class per column, so a model with two phone columns gets two listeners and
     * a model saved a thousand times gets one. `saving` rather than `saved`, so the canonical value is
     * what reaches the database rather than a correction applied afterwards.
     */
    private function hookSaving(Model $model, string $key): void
    {
        if ($this->countryColumn === null) {
            return;
        }

        $dispatcher = $model::getEventDispatcher();

        if ($dispatcher === null) {
            return;
        }

        $token = $model::class.'::'.$key.'@'.spl_object_id($dispatcher);

        if (isset(self::$hooked[$token])) {
            return;
        }

        self::$hooked[$token] = true;

        $countryColumn = $this->countryColumn;

        $model::saving(static function (Model $saving) use ($key, $countryColumn): void {
            $attributes = $saving->getAttributes();
            $raw = $attributes[$key] ?? null;

            // Already canonical, or nothing to work with.
            if (! is_string($raw) || $raw === '' || str_starts_with($raw, '+')) {
                return;
            }

            $country = $attributes[$countryColumn] ?? null;
            $number = Phone::parse($raw, is_string($country) ? $country : null);

            if ($number->e164 === null) {
                return;
            }

            // setRawAttributes without syncing, so dirty tracking still sees the change and the
            // write is not skipped as a no-op.
            $saving->setRawAttributes([
                ...$attributes,
                $key => $number->e164,
                $countryColumn => $number->country ?? $country,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function countryFrom(array $attributes): ?string
    {
        if ($this->countryColumn === null) {
            return null;
        }

        $country = $attributes[$this->countryColumn] ?? null;

        return is_string($country) && $country !== '' ? $country : null;
    }
}
