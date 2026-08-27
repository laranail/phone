<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Facades;

use Generator;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Phone\Enums\MatchLeniency;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\PhoneBatch;
use Simtabi\Laranail\Phone\PhoneBuilder;
use Simtabi\Laranail\Phone\PhoneCatalogue;
use Simtabi\Laranail\Phone\PhoneDialler;
use Simtabi\Laranail\Phone\PhoneManager;
use Simtabi\Laranail\Phone\PhoneNumberValue;
use Simtabi\Laranail\Phone\PhoneScanner;
use Simtabi\Laranail\Phone\ShortNumbers;
use Simtabi\Laranail\Phone\Support\PhoneAudit;
use Simtabi\Laranail\Phone\Support\PhoneAuditEntry;
use Simtabi\Laranail\Phone\Support\PhoneAuditReport;
use Simtabi\Laranail\Phone\Support\PhoneMatch;

/**
 * The package's entry point, over {@see PhoneManager}.
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
 * Phone::of('0712 345678')->country('KE')->e164();
 * ```
 *
 * @method static PhoneNumberValue parse(?string $input, ?string $country = null)
 * @method static string|null format(?string $input, PhoneNumberFormat $format = PhoneNumberFormat::E164, ?string $country = null)
 * @method static string|null toE164(?string $input, ?string $country = null)
 * @method static PhoneNumberValue|null example(string $country, PhoneNumberType $type = PhoneNumberType::Mobile)
 * @method static int|null callingCodeFor(string $country)
 * @method static PhoneBuilder of(?string $input)
 * @method static PhoneAudit audit(iterable<mixed, string|null> $inputs, ?string $country = null)
 * @method static Generator<int, PhoneAuditEntry> each(iterable<mixed, string|null> $inputs, ?string $country = null)
 * @method static PhoneAuditReport report(iterable<mixed, string|null> $inputs, ?string $country = null)
 * @method static list<string> e164List(iterable<mixed, string|null> $inputs, ?string $country = null, bool $validOnly = true)
 * @method static PhoneBatch batch()
 * @method static list<PhoneMatch> find(?string $text, ?string $country = null, ?MatchLeniency $leniency = null)
 * @method static string|null replaceIn(?string $text, callable $replace, ?string $country = null)
 * @method static string|null redact(?string $text, ?string $country = null, string $maskChar = '•')
 * @method static PhoneDialler dialler()
 * @method static PhoneScanner scanner()
 * @method static PhoneCatalogue catalogue()
 * @method static ShortNumbers shortNumbers()
 *
 * @see PhoneManager
 */
final class Phone extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PhoneManager::class;
    }
}
