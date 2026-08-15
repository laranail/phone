<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Http;

use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\MaskGenerator;
use Simtabi\Laranail\Phone\PhoneCatalogue;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNumberValue;
use Simtabi\Laranail\Phone\Support\PhoneAuditEntry;
use Simtabi\Laranail\Phone\Support\PhoneMatch;

/**
 * Turns the package's objects into the JSON the API returns.
 *
 * Separate from the controller because the wire format is a contract and the controller is not: a
 * caller written against `country` and `national` should keep working across a refactor of how the
 * routes are wired, and the only way to make that true is to have exactly one place that decides
 * what a number looks like on the wire.
 *
 * ## Intel is opt-in, per request
 *
 * Carrier, geographic description and timezone each load their own prefix-keyed metadata on first
 * use. One number is free; a thousand-number batch with intel on is a different cost class, and
 * making that the default would mean every caller paying for data most of them do not read.
 */
final readonly class PhonePresenter
{
    public function __construct(
        private PhoneFormatter $formatter,
        private MaskGenerator $masks,
        private PhoneCatalogue $catalogue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function number(PhoneNumberValue $number, bool $withIntel = false): array
    {
        $payload = [
            'input' => $number->raw,
            'valid' => $number->isValid,
            'possible' => $number->isPossible,
            'reason' => $number->possibility()->value,
            'reason_label' => $number->possibility()->label(),
            'e164' => $number->e164,
            'national' => $number->national,
            'international' => $number->international,
            'rfc3966' => $number->rfc3966,
            'country' => $number->country,
            'calling_code' => $number->callingCode,
            'extension' => $number->extension,
            'type' => $number->type->value,
            'type_label' => $number->type->label(),
            'area_code' => $number->areaCode(),
            'geographic' => $number->isGeographic(),
            'tel_link' => $number->telLink(),
        ];

        if (! $withIntel) {
            return $payload;
        }

        return [
            ...$payload,
            'carrier' => $number->carrier(),
            'description' => $number->description(),
            'timezones' => $number->timezones(),
        ];
    }

    /**
     * A batch row: the number's payload plus where it sat in the request.
     *
     * @return array<string, mixed>
     */
    public function entry(PhoneAuditEntry $entry, bool $withIntel = false): array
    {
        return [
            'index' => $entry->index,
            'duplicate_of' => $entry->duplicateOf,
            ...$this->number($entry->number, $withIntel),
        ];
    }

    /**
     * @return array{raw: string, offset: int, end: int, e164: string|null, country: string|null, valid: bool, type: string}
     */
    public function match(PhoneMatch $match): array
    {
        return [
            ...$match->toArray(),
            'valid' => $match->number->isValid,
            'type' => $match->number->type->value,
        ];
    }

    /**
     * One region of the numbering plan.
     *
     * `examples` is off by default because an example number is one metadata load per region, and
     * the full catalogue is 245 of them — the thing that makes the mask table in the UI package cost
     * half a second cold.
     *
     * @return array<string, mixed>
     */
    public function region(string $region, bool $withExamples = false): array
    {
        $payload = [
            'country' => $region,
            'calling_code' => $this->catalogue->callingCodeFor($region),
            'nanp' => $this->catalogue->isNanp($region),
            'portable' => $this->catalogue->isPortable($region),
            'national_prefix' => $this->catalogue->nationalPrefix($region),
        ];

        if (! $withExamples) {
            return $payload;
        }

        $example = $this->formatter->example($region, PhoneNumberType::Mobile);

        return [
            ...$payload,
            'example' => $example?->e164,
            'example_national' => $example?->national,
            'mask' => $this->masks->national($region),
            'placeholder' => $this->masks->placeholder($region),
        ];
    }
}
