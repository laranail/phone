<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberToCarrierMapper;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use Simtabi\Laranail\Phone\Contracts\ResolvesPhoneIntel;

/**
 * Carrier, geography and timezone, resolved on demand.
 *
 * Each of the three mappers loads its own prefix-keyed metadata on first use, so all three are
 * constructed lazily and none is touched unless something asks. A value object that nobody
 * interrogates costs nothing.
 *
 * The number is re-parsed from its E.164 form rather than carried through from
 * {@see PhoneFormatter}. That is a deliberate trade: parsing an already-canonical E.164 string is
 * cheap and metadata-only, and paying it here is what lets {@see PhoneNumberValue} stay a pure,
 * serialisable data class instead of dragging a libphonenumber object through every cache,
 * queue payload and JSON response it lands in.
 */
final class PhoneIntel implements ResolvesPhoneIntel
{
    private readonly PhoneNumberUtil $util;

    private ?PhoneNumberOfflineGeocoder $geocoder = null;

    private ?PhoneNumberToCarrierMapper $carrier = null;

    private ?PhoneNumberToTimeZonesMapper $timezones = null;

    public function __construct(
        private readonly string $fallbackLocale = 'en',
        ?PhoneNumberUtil $util = null,
    ) {
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    public function carrier(PhoneNumberValue $number, ?string $locale = null): ?string
    {
        $parsed = $this->reparse($number);

        if ($parsed === null) {
            return null;
        }

        $this->carrier ??= PhoneNumberToCarrierMapper::getInstance();

        // getNameForNumber, not getNameForValidNumber: the latter returns an empty string for
        // anything libphonenumber has not yet marked valid, which silently hides the carrier for
        // recently allocated ranges.
        $name = $this->carrier->getNameForNumber($parsed, $locale ?? $this->fallbackLocale);

        return $name === '' ? null : $name;
    }

    public function description(PhoneNumberValue $number, ?string $locale = null): ?string
    {
        $parsed = $this->reparse($number);

        if ($parsed === null) {
            return null;
        }

        $this->geocoder ??= PhoneNumberOfflineGeocoder::getInstance();

        $description = $this->geocoder->getDescriptionForNumber($parsed, $locale ?? $this->fallbackLocale);

        return $description === '' ? null : $description;
    }

    /**
     * @return list<string>
     */
    public function timezones(PhoneNumberValue $number): array
    {
        $parsed = $this->reparse($number);

        if ($parsed === null) {
            return [];
        }

        $this->timezones ??= PhoneNumberToTimeZonesMapper::getInstance();

        $zones = $this->timezones->getTimeZonesForNumber($parsed);

        // The mapper answers `Etc/Unknown` rather than nothing when it has no data. That is a
        // sentinel, not a timezone, and letting it reach a caller means someone eventually passes it
        // to a DateTimeZone constructor.
        return array_values(array_filter(
            $zones,
            static fn (string $zone): bool => $zone !== 'Etc/Unknown',
        ));
    }

    private function reparse(PhoneNumberValue $number): ?LibPhoneNumber
    {
        if ($number->e164 === null) {
            return null;
        }

        try {
            return $this->util->parse($number->e164);
        } catch (NumberParseException) {
            return null;
        }
    }
}
