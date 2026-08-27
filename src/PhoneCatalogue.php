<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use libphonenumber\PhoneNumberUtil;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;

/**
 * What libphonenumber knows about regions and calling codes, rather than about a number.
 *
 * The method that earns this class its place is {@see regionsForCallingCode()}. A calling code does
 * not identify a country: `+1` is the North American Numbering Plan, shared by twenty-odd countries
 * and territories, and every design that assumes otherwise files Trinidad under the United States.
 * Anything mapping dial codes to countries needs the plural answer.
 */
final readonly class PhoneCatalogue
{
    private PhoneNumberUtil $util;

    public function __construct(?PhoneNumberUtil $util = null)
    {
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    /**
     * Every region libphonenumber has metadata for.
     *
     * Not the same as the ISO 3166-1 list: it omits places with no numbering plan of their own and
     * includes a few that are not countries.
     *
     * @return list<string>
     */
    public function regions(): array
    {
        $regions = array_values($this->util->getSupportedRegions());
        sort($regions);

        return $regions;
    }

    /**
     * Every region sharing a calling code, most-populous first as libphonenumber orders them.
     *
     * @return list<string>
     */
    public function regionsForCallingCode(int $callingCode): array
    {
        return array_values($this->util->getRegionCodesForCountryCode($callingCode));
    }

    /** The single region a calling code is *primarily* assigned to, or null where it is shared. */
    public function primaryRegionForCallingCode(int $callingCode): ?string
    {
        $region = $this->util->getRegionCodeForCountryCode($callingCode);

        return ($region === '' || $region === 'ZZ') ? null : $region;
    }

    public function callingCodeFor(string $region): ?int
    {
        $code = $this->util->getCountryCodeForRegion(strtoupper($region));

        return $code === 0 ? null : $code;
    }

    /** Whether a region is part of the North American Numbering Plan, and therefore shares `+1`. */
    public function isNanp(string $region): bool
    {
        return $this->util->isNANPACountry(strtoupper($region));
    }

    /**
     * The line types a region actually allocates.
     *
     * Worth consulting before offering a "mobile only" toggle: in the NANP there is no such
     * distinction to make, so the toggle would reject every valid number.
     *
     * @return list<PhoneNumberType>
     */
    public function typesFor(string $region): array
    {
        $types = [];

        foreach ($this->util->getSupportedTypesForRegion(strtoupper($region)) as $type) {
            $types[] = PhoneNumberType::fromLibPhoneNumber($type);
        }

        return $types;
    }

    /**
     * Whether numbers in this region can be ported between carriers.
     *
     * The caveat that makes carrier lookups untrustworthy, expressed as data: where this is true, a
     * carrier name is the network the number was *issued* on and may not be the network it is on
     * now.
     */
    public function isPortable(string $region): bool
    {
        return $this->util->isMobileNumberPortableRegion(strtoupper($region));
    }

    /** The national dialling prefix a region prepends, e.g. `0` for Kenya and nothing for Italy. */
    public function nationalPrefix(string $region): ?string
    {
        $prefix = $this->util->getNddPrefixForRegion(strtoupper($region), true);

        return ($prefix === null || $prefix === '') ? null : $prefix;
    }
}
