<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\Enums\MatchLeniency;
use Simtabi\Laranail\Phone\Enums\PhoneNumberFormat;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\Enums\PossibilityReason;
use Simtabi\Laranail\Phone\Enums\ShortNumberCost;
use Simtabi\Laranail\Phone\Facades\Phone;

// =========================================================================
// The fluent chain
// =========================================================================

it('parses through a chain', function (): void {
    expect(Phone::of('0712 123456')->country('KE')->e164())->toBe('+254712123456')
        ->and(Phone::of('0712 123456')->country('KE')->national())->toBe('0712 123456')
        ->and(Phone::of('0712 123456')->country('KE')->international())->toBe('+254 712 123456')
        ->and(Phone::of('0712 123456')->country('KE')->format(PhoneNumberFormat::Rfc3966))->toBe('tel:+254-712-123456');
});

/**
 * Narrowing returns a new instance, so a builder held on a model or passed to a view cannot be
 * changed underneath whoever is holding it.
 */
it('never mutates the builder it was called on', function (): void {
    $base = Phone::of('0712 123456');
    $kenyan = $base->country('KE');

    expect($kenyan->e164())->toBe('+254712123456')
        // The original still has no country and so still cannot resolve one.
        ->and($base->e164())->toBeNull();
});

it('parses once however long the chain', function (): void {
    $number = Phone::of('+254712123456');

    // Same object identity on every read proves the parse is memoised rather than repeated.
    expect($number->value())->toBe($number->value());
});

it('hands back the value object for anything the chain does not cover', function (): void {
    expect(Phone::of('+254712123456')->value()->callingCode)->toBe(254);
});

// =========================================================================
// Why, rather than whether
// =========================================================================

it('says how a number failed, not just that it did', function (): void {
    expect(Phone::of('0712')->country('KE')->why())->toBe(PossibilityReason::TooShort)
        ->and(Phone::of('0712')->country('KE')->why()->isCorrectable())->toBeTrue()
        ->and(Phone::of('+9999999999999')->why()->isCorrectable())->toBeFalse()
        ->and(Phone::of('+254712123456')->why())->toBe(PossibilityReason::IsPossible);
});

// =========================================================================
// Line types
// =========================================================================

it('counts the ambiguous NANP type as both mobile and fixed line', function (): void {
    // libphonenumber reports FIXED_LINE_OR_MOBILE for every US number, because the plan does not
    // distinguish them. Treating that as "not mobile" rejects every valid American mobile.
    expect(Phone::of('+12015550123')->isType(PhoneNumberType::Mobile))->toBeTrue()
        ->and(Phone::of('+12015550123')->isType(PhoneNumberType::FixedLine))->toBeTrue()
        ->and(Phone::of('+254712123456')->isType(PhoneNumberType::Mobile))->toBeTrue();
});

// =========================================================================
// Dialling
// =========================================================================

it('gives the digits to dial from a given country', function (): void {
    expect(Phone::of('+254712123456')->dialFrom('GB'))->toBe('00 254 712 123456')
        // Domestic: no international prefix at all.
        ->and(Phone::of('+254712123456')->dialFrom('KE'))->toBe('0712 123456');
});

it('knows what cannot be reached from abroad', function (): void {
    expect(Phone::of('+254712123456')->isInternationallyDiallable())->toBeTrue();
});

// =========================================================================
// Structure
// =========================================================================

it('extracts the area code only where the plan has one', function (): void {
    expect(Phone::of('+12015550123')->areaCode())->toBe('201')
        ->and(Phone::of('+442071838750')->areaCode())->toBe('20')
        // A Kenyan mobile is not geographic, so there is no area code to extract.
        ->and(Phone::of('+254712123456')->areaCode())->toBeNull()
        ->and(Phone::of('+254712123456')->nationalDestinationCode())->toBe('712');
});

it('grades a comparison rather than answering yes or no', function (): void {
    expect(Phone::of('+254712123456')->is('+254712123456'))->toBeTrue()
        // Same national number, country supplied by only one side.
        ->and(Phone::of('+254712123456')->is('0712123456'))->toBeTrue()
        ->and(Phone::of('+254712123456')->is('+254722999999'))->toBeFalse();
});

// =========================================================================
// Masking
// =========================================================================

it('masks by count and by proportion', function (): void {
    expect(Phone::of('+254712123456')->masked())->toBe('+254•••••••56')
        ->and(Phone::of('+254712123456')->masked('*', 4))->toBe('+254*****3456')
        ->and(Phone::of('+254712123456')->maskedByPercent(100))->toBe('+254•••••••••');
});

// =========================================================================
// Free text
// =========================================================================

/**
 * The distinctive capability: a digit-run pattern cannot tell a phone number from an invoice
 * reference, and this can, because each candidate is checked against the numbering plan.
 */
it('finds numbers in prose and ignores digits that are not numbers', function (): void {
    $text = 'Call me on 0712 123456 about invoice 2024-00123, or try +44 20 7183 8750.';

    $found = Phone::find($text, 'KE');

    expect($found)->toHaveCount(2)
        ->and($found[0]->number->e164)->toBe('+254712123456')
        ->and($found[1]->number->e164)->toBe('+442071838750');
});

it('reports where each match was, so it can be replaced in place', function (): void {
    $text = 'Ring 0712 123456 twice: 0712 123456.';

    $found = Phone::find($text, 'KE');

    expect($found)->toHaveCount(2)
        // Two occurrences of one number are two matches at different offsets — which is the whole
        // reason the offset is carried rather than the caller searching for the string again.
        ->and($found[0]->offset)->toBeLessThan($found[1]->offset)
        ->and(substr($text, $found[1]->offset, strlen($found[1]->raw)))->toBe($found[1]->raw);
});

it('redacts every number, keeping the calling code', function (): void {
    $redacted = Phone::redact('Reach me on 0712 123456 any time.', 'KE');

    expect($redacted)->toContain('+254')
        ->and($redacted)->not->toContain('123456')
        ->and($redacted)->toStartWith('Reach me on ');
});

it('replaces backwards, so later offsets stay valid', function (): void {
    // Replacing forwards shifts every later offset by the length difference and the second
    // replacement lands in the wrong place. Two numbers with a long replacement proves the order.
    $out = Phone::replaceIn(
        'a 0712 123456 b 0722 123456 c',
        static fn ($match): string => '[' . $match->number->e164 . ']',
        'KE',
    );

    expect($out)->toBe('a [+254712123456] b [+254722123456] c');
});

it('finds more with looser leniency', function (): void {
    $text = 'Maybe 0712 123 456 is a number.';

    expect(count(Phone::find($text, 'KE', MatchLeniency::Possible)))
        ->toBeGreaterThanOrEqual(count(Phone::find($text, 'KE', MatchLeniency::ExactGrouping)));
});

// =========================================================================
// Short codes
// =========================================================================

it('reads short codes, which need a region because they carry no calling code', function (): void {
    expect(Phone::of('999')->country('GB')->isShortCode())->toBeTrue()
        ->and(Phone::of('999')->country('GB')->connectsToEmergency())->toBeTrue()
        ->and(Phone::of('999')->country('GB')->shortCodeCost())->toBe(ShortNumberCost::TollFree)
        ->and(Phone::of('0712123456')->country('KE')->connectsToEmergency())->toBeFalse();
});

it('refuses a short-code question with no region rather than guessing', function (): void {
    Phone::of('999')->isShortCode();
})->throws(InvalidArgumentException::class, 'need a region');

// =========================================================================
// The catalogue
// =========================================================================

/**
 * A calling code does not identify a country. Every design that assumes it does files Trinidad
 * under the United States.
 */
it('answers the +1 question in the plural', function (): void {
    $regions = Phone::catalogue()->regionsForCallingCode(1);

    expect($regions)->toContain('US')
        ->and($regions)->toContain('TT')
        ->and(count($regions))->toBeGreaterThan(20)
        ->and(Phone::catalogue()->primaryRegionForCallingCode(1))->toBe('US');
});

it('knows which regions share the NANP and which numbers can be ported', function (): void {
    expect(Phone::catalogue()->isNanp('TT'))->toBeTrue()
        ->and(Phone::catalogue()->isNanp('KE'))->toBeFalse()
        ->and(Phone::catalogue()->callingCodeFor('KE'))->toBe(254)
        // The caveat behind every carrier lookup, as data rather than prose.
        ->and(Phone::catalogue()->isPortable('KE'))->toBeTrue();
});

it('reports the national prefix a region prepends', function (): void {
    expect(Phone::catalogue()->nationalPrefix('KE'))->toBe('0')
        // Italy prepends nothing, which is why a blanket trunk-zero rule corrupts Italian numbers.
        ->and(Phone::catalogue()->nationalPrefix('IT'))->toBeNull();
});

it('lists the line types a region actually allocates', function (): void {
    expect(Phone::catalogue()->typesFor('KE'))->not->toBeEmpty()
        ->and(Phone::catalogue()->regions())->toHaveCount(245);
});

// =========================================================================
// Per-country helpers, which is what the type narrowing is for
// =========================================================================

it('produces an example, a mask and a placeholder for the configured country and type', function (): void {
    $kenya = Phone::of(null)->country('KE')->mobile();

    expect($kenya->example()?->e164)->toBe('+254712123456')
        ->and($kenya->mask())->toBe('9999 999999')
        ->and($kenya->placeholder())->toBe('0712 123456');
});

/**
 * The refusal is the feature. German mobile numbers are ten *or* eleven digits, so a ten-digit mask
 * swallows the eleventh keystroke and the user cannot tell why.
 */
it('refuses a mask where the plan allows more than one length, but still offers a placeholder', function (): void {
    $germany = Phone::of(null)->country('DE')->mobile();

    expect($germany->mask())->toBeNull()
        ->and($germany->placeholder())->not->toBeNull();
});

it('changes the answer when the type is narrowed', function (): void {
    $fixed = Phone::of(null)->country('KE')->fixedLine()->example()?->e164;
    $mobile = Phone::of(null)->country('KE')->mobile()->example()?->e164;

    expect($fixed)->not->toBe($mobile);
});

it('returns null for a country it was never given', function (): void {
    expect(Phone::of(null)->example())->toBeNull()
        ->and(Phone::of(null)->mask())->toBeNull();
});
