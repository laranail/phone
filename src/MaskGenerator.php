<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use libphonenumber\PhoneNumberUtil;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;

/**
 * Builds an input-mask template for a country, from libphonenumber's own example numbers.
 *
 * The output is Alpine's `x-mask` vocabulary — `9` for a digit, everything else literal — so a mask
 * can be handed straight to a field with no client-side phone library at all. That is the whole point:
 * as-you-type formatting for the price of a server-rendered attribute, instead of the 80–145 kB of
 * metadata a JavaScript phone library costs.
 *
 * ### It refuses more often than you would expect, and that is the feature
 *
 * A mask is a promise that the number has exactly this many digits in exactly these groups. That
 * promise is only true where the numbering plan says so. libphonenumber publishes the possible
 * lengths per country and per type, and this class returns **null** unless there is exactly one:
 *
 * | Country | Mobile lengths | Mask? |
 * |---------|----------------|-------|
 * | `KE`    | 9              | yes   |
 * | `NG`    | 10             | yes   |
 * | `GB`    | 10             | yes   |
 * | `DE`    | 10, 11         | **no** |
 *
 * German mobile numbers are ten *or* eleven digits. A ten-digit mask silently blocks every
 * eleven-digit one, and the user cannot tell why their keystroke did nothing — the worst possible
 * failure for an input field. Where this returns null, render the field unmasked and let the server
 * format on blur.
 */
final class MaskGenerator
{
    private readonly PhoneNumberUtil $util;

    /** @var array<string, string|null> */
    private array $memo = [];

    public function __construct(
        private readonly PhoneFormatter $formatter = new PhoneFormatter,
        ?PhoneNumberUtil $util = null,
    ) {
        $this->util = $util ?? PhoneNumberUtil::getInstance();
    }

    /**
     * A national-format mask for a country, or null when one would be a lie.
     *
     * @param string $country ISO 3166-1 alpha-2
     */
    public function national(string $country, PhoneNumberType $type = PhoneNumberType::Mobile): ?string
    {
        return $this->build($country, $type, PhoneNumberFormat::National);
    }

    /**
     * An international-format mask, including the `+` and the calling code.
     *
     * Use this when the field holds the whole number rather than sitting beside a country picker.
     */
    public function international(string $country, PhoneNumberType $type = PhoneNumberType::Mobile): ?string
    {
        return $this->build($country, $type, PhoneNumberFormat::International);
    }

    /**
     * A placeholder showing what a real number looks like, e.g. `0712 345678`.
     *
     * Unlike a mask this is always available — showing an example never constrains what can be
     * typed, so the variable-length problem does not apply.
     *
     * Memoised on the same table as the masks. `example()` loads a region's metadata, which is the
     * expensive part of this class: without the memo, a caller building a table for every country
     * pays that cost again on every pass — measured at 210 ms for 245 regions on a pass where the
     * masks themselves were already cached and free.
     */
    public function placeholder(string $country, PhoneNumberType $type = PhoneNumberType::Mobile): ?string
    {
        $country = strtoupper($country);
        $key = $country . '|' . $type->value . '|placeholder';

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->formatter->example($country, $type)?->national;
    }

    private function build(string $country, PhoneNumberType $type, PhoneNumberFormat $format): ?string
    {
        $country = strtoupper($country);
        $key = $country . '|' . $type->value . '|' . $format->value;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->compute($country, $type, $format);
    }

    private function compute(string $country, PhoneNumberType $type, PhoneNumberFormat $format): ?string
    {
        if (! $this->hasSingleLength($country, $type)) {
            return null;
        }

        $example = $this->formatter->example($country, $type);

        if ($example === null) {
            return null;
        }

        $rendered = $example->format($format);

        // Every digit becomes a mask token; punctuation and the `+` stay as they are.
        $mask = preg_replace('/\d/', '9', $rendered);

        return ($mask === null || $mask === '') ? null : $mask;
    }

    /**
     * Whether the numbering plan allows exactly one length for this type.
     *
     * Falls back to the general descriptor when the type has none of its own — which is the normal
     * case in the NANP, where mobile and fixed-line share ranges and libphonenumber therefore
     * publishes no separate mobile lengths.
     */
    private function hasSingleLength(string $country, PhoneNumberType $type): bool
    {
        $metadata = $this->util->getMetadataForRegion($country);

        if ($metadata === null) {
            return false;
        }

        $descriptor = match ($type) {
            PhoneNumberType::Mobile => $metadata->getMobile(),
            PhoneNumberType::FixedLine => $metadata->getFixedLine(),
            PhoneNumberType::TollFree => $metadata->getTollFree(),
            default => null,
        };

        $lengths = $descriptor?->getPossibleLength() ?? [];

        if ($lengths === []) {
            $lengths = $metadata->getGeneralDesc()?->getPossibleLength() ?? [];
        }

        return count($lengths) === 1;
    }
}
