<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Support;

use Simtabi\Laranail\Phone\PhoneNumberValue;

/**
 * One number found inside a body of text.
 *
 * The offset is the point. Without it a caller wanting to highlight or redact has to search the text
 * again for the matched string, which finds the *first* occurrence rather than *this* one — and a
 * message that repeats a number gets the wrong instance replaced.
 */
final readonly class PhoneMatch
{
    public function __construct(
        /** Exactly as it appeared, punctuation and all. */
        public string $raw,
        /** Byte offset of the first character of {@see $raw} in the scanned text. */
        public int $offset,
        public PhoneNumberValue $number,
    ) {}

    /** The offset one past the end of the match. */
    public function end(): int
    {
        return $this->offset + strlen($this->raw);
    }

    /**
     * @return array{raw: string, offset: int, end: int, e164: string|null, country: string|null}
     */
    public function toArray(): array
    {
        return [
            'raw'     => $this->raw,
            'offset'  => $this->offset,
            'end'     => $this->end(),
            'e164'    => $this->number->e164,
            'country' => $this->number->country,
        ];
    }
}
