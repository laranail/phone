<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Support;

use JsonSerializable;
use Simtabi\Laranail\Phone\PhoneNumberValue;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\Enums\PossibilityReason;

/**
 * One row of a batch: the input, what it parsed to, and where it sits in the list.
 *
 * `index` is the position in the *input*, not in the output, and it survives filtering. An audit
 * whose caller cannot map a verdict back to the row it came from is a report nobody can act on —
 * "42 invalid numbers" is not something you can fix, and "rows 7, 19 and 104" is.
 */
final readonly class PhoneAuditEntry implements JsonSerializable
{
    /**
     * @param int $index Position in the input list
     * @param string|null $input Exactly what was supplied, unmodified
     * @param int|null $duplicateOf The index of the first row that produced the same E.164, if any
     */
    public function __construct(
        public int $index,
        public ?string $input,
        public PhoneNumberValue $number,
        public ?int $duplicateOf = null,
    ) {}

    public function isValid(): bool
    {
        return $this->number->isValid;
    }

    public function isPossible(): bool
    {
        return $this->number->isPossible;
    }

    /** Why it is not valid, in a form that can be shown to a person. */
    public function reason(): PossibilityReason
    {
        return $this->number->possibility();
    }

    public function isDuplicate(): bool
    {
        return $this->duplicateOf !== null;
    }

    public function e164(): ?string
    {
        return $this->number->e164;
    }

    public function country(): ?string
    {
        return $this->number->country;
    }

    public function type(): PhoneNumberType
    {
        return $this->number->type;
    }

    /**
     * @return array{
     *     index: int,
     *     input: string|null,
     *     valid: bool,
     *     possible: bool,
     *     reason: string,
     *     e164: string|null,
     *     national: string|null,
     *     international: string|null,
     *     country: string|null,
     *     type: string,
     *     duplicate_of: int|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'index'         => $this->index,
            'input'         => $this->input,
            'valid'         => $this->number->isValid,
            'possible'      => $this->number->isPossible,
            'reason'        => $this->reason()->value,
            'e164'          => $this->number->e164,
            'national'      => $this->number->national,
            'international' => $this->number->international,
            'country'       => $this->number->country,
            'type'          => $this->number->type->value,
            'duplicate_of'  => $this->duplicateOf,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
