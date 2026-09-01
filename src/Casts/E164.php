<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Phone\Facades\Phone;

/**
 * Normalises a column to E.164 on write, and leaves it a plain string on read.
 *
 * The lightweight alternative to {@see AsPhoneNumber} for columns that are only ever stored and
 * displayed verbatim — a notification destination, an SMS recipient — where a value object would be
 * unwrapped on every access anyway.
 *
 * ```php
 * 'sms_to' => E164::class,
 * 'sms_to' => E164::class . ':KE',   // parse bare national input against Kenya
 * ```
 *
 * Its whole job is making `unique` and `where` work. Without it a column accumulates
 * `+15551234567`, `(555) 123-4567` and `555-1234` as three different strings for one number, and
 * every duplicate check silently passes.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final readonly class E164 implements CastsAttributes
{
    public function __construct(
        private ?string $country = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        return $value === '' ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = Phone::parse((string) $value, $this->country);

        // Unparseable input is kept as typed. See AsPhoneNumber::set() — the alternative is throwing
        // away something the user can see on their screen.
        return $number->e164 ?? $number->raw;
    }
}
