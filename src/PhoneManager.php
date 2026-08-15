<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use Simtabi\Laranail\Phone\Enums\MatchLeniency;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\Support\PhoneMatch;

/**
 * What the `Phone` facade resolves to.
 *
 * A thin front over four collaborators — the formatter, the dialler, the scanner, the catalogue and
 * the short-number reader — rather than a fifth implementation. It exists so that the facade has one
 * accessor and callers have one place to start, without {@see PhoneFormatter} growing into a god
 * object and losing the property that makes it worth having: that it is the *only* code in the
 * package touching libphonenumber's parser.
 *
 * The five original methods forward unchanged, so the facade's surface is a superset of what it was.
 */
final readonly class PhoneManager
{
    public function __construct(
        private PhoneFormatter $formatter,
        private PhoneDialler $dialler,
        private PhoneScanner $scanner,
        private PhoneCatalogue $catalogue,
        private ShortNumbers $shortNumbers,
    ) {}

    // ---------------------------------------------------------------- fluent

    /**
     * Start a chain.
     *
     * ```php
     * Phone::of('0712 123456')->country('KE')->e164();
     * ```
     */
    public function of(?string $input): PhoneBuilder
    {
        return new PhoneBuilder($input);
    }

    // ---------------------------------------------------------------- forwarded

    public function parse(?string $input, ?string $country = null): PhoneNumberValue
    {
        return $this->formatter->parse($input, $country);
    }

    public function format(?string $input, PhoneNumberFormat $format = PhoneNumberFormat::E164, ?string $country = null): ?string
    {
        return $this->formatter->format($input, $format, $country);
    }

    public function toE164(?string $input, ?string $country = null): ?string
    {
        return $this->formatter->toE164($input, $country);
    }

    public function example(string $country, PhoneNumberType $type = PhoneNumberType::Mobile): ?PhoneNumberValue
    {
        return $this->formatter->example($country, $type);
    }

    public function callingCodeFor(string $country): ?int
    {
        return $this->formatter->callingCodeFor($country);
    }

    // ---------------------------------------------------------------- free text

    /**
     * Find every phone number inside a body of text.
     *
     * @return list<PhoneMatch>
     */
    public function find(?string $text, ?string $country = null, ?MatchLeniency $leniency = null): array
    {
        return $this->scanner->scan($text, $country, $leniency);
    }

    /** Replace every number found, offsets handled. */
    public function replaceIn(?string $text, callable $replace, ?string $country = null): ?string
    {
        return $this->scanner->replace($text, $replace, $country);
    }

    /** Mask every number found, keeping the calling code. */
    public function redact(?string $text, ?string $country = null, string $maskChar = '•'): ?string
    {
        return $this->scanner->redact($text, $country, $maskChar);
    }

    // ---------------------------------------------------------------- collaborators

    public function dialler(): PhoneDialler
    {
        return $this->dialler;
    }

    public function scanner(): PhoneScanner
    {
        return $this->scanner;
    }

    public function catalogue(): PhoneCatalogue
    {
        return $this->catalogue;
    }

    public function shortNumbers(): ShortNumbers
    {
        return $this->shortNumbers;
    }
}
