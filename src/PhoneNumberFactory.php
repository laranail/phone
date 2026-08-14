<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use Simtabi\Laranail\Phone\Enums\PhoneNumberType;

/**
 * Valid, country-correct phone numbers for factories, seeders and tests.
 *
 * None of the surveyed packages ships anything like this, which is why test suites around them are
 * full of `'+15551234567'` — a number that is *possible* but not *valid*, so any test that asserts
 * validity has to be written around it.
 *
 * Every number here comes from libphonenumber's own example metadata, which means two things worth
 * relying on: it is correctly shaped for its country, and it belongs to a reserved range, so a
 * seeder that accidentally reaches production cannot text a real person.
 */
final readonly class PhoneNumberFactory
{
    public function __construct(
        private PhoneFormatter $formatter = new PhoneFormatter,
        private string $defaultCountry = 'US',
    ) {}

    /** A valid number for a country, as a value object. */
    public function make(?string $country = null, PhoneNumberType $type = PhoneNumberType::Mobile): ?PhoneNumberValue
    {
        return $this->formatter->example($country ?? $this->defaultCountry, $type);
    }

    /** A valid number in E.164, ready to seed a column. */
    public function e164(?string $country = null, PhoneNumberType $type = PhoneNumberType::Mobile): ?string
    {
        return $this->make($country, $type)?->e164;
    }

    /** A valid number in national format, as a user would type it into a form. */
    public function national(?string $country = null, PhoneNumberType $type = PhoneNumberType::Mobile): ?string
    {
        return $this->make($country, $type)?->national;
    }

    /**
     * A number that parses but is **not** valid, for testing the rejection path.
     *
     * Distinct from junk: junk fails to parse at all and exercises a different branch. This returns
     * something correctly shaped whose range has not been allocated, which is the case real
     * validation actually has to catch.
     */
    public function invalid(?string $country = null): ?string
    {
        $example = $this->make($country, PhoneNumberType::Mobile);

        if ($example?->e164 === null) {
            return null;
        }

        // Push the subscriber digits into an unallocated range while keeping the length and the
        // calling code intact, so the result is possible-but-invalid rather than merely malformed.
        $prefix = '+' . $example->callingCode;
        $rest = substr($example->e164, strlen($prefix));

        return $prefix . '0' . substr(str_repeat('1', strlen($rest)), 1);
    }

    /** Input that cannot be parsed at all, for testing the fallback path. */
    public function junk(): string
    {
        return 'not a phone number';
    }

    /**
     * One valid number per country, keyed by ISO code.
     *
     * For seeding a table that needs geographic spread rather than one country repeated.
     *
     * @param list<string> $countries
     * @return array<string, string>
     */
    public function spread(array $countries, PhoneNumberType $type = PhoneNumberType::Mobile): array
    {
        $out = [];

        foreach ($countries as $country) {
            $number = $this->e164($country, $type);

            if ($number !== null) {
                $out[strtoupper($country)] = $number;
            }
        }

        return $out;
    }
}
