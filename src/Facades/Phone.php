<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNumberValue;

/**
 * The package's entry point, over {@see PhoneFormatter}.
 *
 * Deliberately **not** registered as a global alias. `Phone` is exactly the kind of short, plausible
 * name an application or another package would also claim, and Laravel's alias map is flat — the
 * second claimant replaces the first with no error, and it surfaces much later as a call to the wrong
 * class. Import it instead:
 *
 * ```php
 * use Simtabi\Laranail\Phone\Facades\Phone;
 *
 * Phone::parse('0712 345678', 'KE')->e164;   // '+254712345678'
 * ```
 *
 * @method static PhoneNumberValue parse(?string $input, ?string $country = null)
 * @method static string|null format(?string $input, PhoneNumberFormat $format = PhoneNumberFormat::E164, ?string $country = null)
 * @method static string|null toE164(?string $input, ?string $country = null)
 * @method static PhoneNumberValue|null example(string $country, PhoneNumberType $type = PhoneNumberType::Mobile)
 * @method static int|null callingCodeFor(string $country)
 *
 * @see PhoneFormatter
 */
final class Phone extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PhoneFormatter::class;
    }
}
