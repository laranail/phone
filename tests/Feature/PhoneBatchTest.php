<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\Facades\Phone;
use Simtabi\Laranail\Phone\Support\PhoneAuditEntry;

/*
|--------------------------------------------------------------------------
| Batch and audit
|--------------------------------------------------------------------------
|
| Two questions, one pass: what is each of these, and what is wrong with the
| list. The tests that matter here are the ones about the *list* — duplicate
| detection, the reason breakdown, and the memoisation that makes a dirty
| list cheaper to audit rather than more expensive.
|
*/

it('answers per-row and in aggregate from one pass', function (): void {
    $audit = Phone::audit([
        '+254712123456',
        '0712 123456',      // the same number, written nationally
        '+12015550123',
        'not a number',
        '+2547',            // too short
    ], 'KE');

    expect($audit)->toHaveCount(5)
        ->and($audit->summary())->toMatchArray([
            'total' => 5,
            'valid' => 3,
            'invalid' => 2,
            'duplicates' => 1,
            'distinct' => 4,
        ])
        ->and($audit->unique())->toBe(['+254712123456', '+12015550123'])
        ->and(array_keys($audit->countries()))->toEqualCanonicalizing(['KE', 'US']);
});

it('points a duplicate at the first row that produced it, not the last', function (): void {
    $audit = Phone::audit(['+254712123456', '+254733333333', '0712 123456', '0712123456'], 'KE');

    $duplicates = array_map(
        static fn (PhoneAuditEntry $e): array => [$e->index, $e->duplicateOf],
        $audit->duplicates(),
    );

    expect($duplicates)->toBe([[2, 0], [3, 0]])
        // The survivor of a group is deterministically the earliest row — the original, not the
        // re-entry, which is the one a person is most likely to have meant.
        ->and(array_map(static fn (PhoneAuditEntry $e): int => $e->index, $audit->distinct()))->toBe([0, 1]);
});

it('groups duplicates by the number they resolved to', function (): void {
    $audit = Phone::audit(['+254712123456', '0712 123456', '+12015550123'], 'KE');

    expect($audit->duplicateGroups())->toBe(['+254712123456' => [0, 1]]);
});

it('breaks the failures down by reason rather than just counting them', function (): void {
    // "42 invalid" tells an operator nothing they can act on. "38 too short" tells them the column
    // was truncated on export.
    $audit = Phone::audit(['+2547', '+25471', '+9991234567890', '+254712123456'], 'KE');

    expect($audit->reasons())->toHaveKey('TOO_SHORT')
        ->and($audit->reasons()['TOO_SHORT'])->toBe(2)
        ->and($audit->reasons())->toHaveKey('INVALID_COUNTRY_CODE');
});

it('parses each distinct input once however often it repeats', function (): void {
    // The reason to audit a list is that it is dirty, and a dirty list repeats itself. This is the
    // property that makes the saving grow with exactly the mess that made the audit necessary.
    //
    // Asserted by object *identity* rather than through a counting double, because PhoneFormatter is
    // final by design — it is the only code in the package touching libphonenumber's parser and that
    // is worth keeping true. A memoised parse hands back the same instance; a repeated one cannot.
    $entries = Phone::audit(['+254712123456', '+12015550123', '+254712123456'])->entries();

    expect($entries[0]->number)->toBe($entries[2]->number)
        ->and($entries[0]->number)->not->toBe($entries[1]->number);
});

it('does not let one row of a batch answer for another parsed against a different region', function (): void {
    // The memoisation key carries the country, so a batch narrowed per call cannot hand row two the
    // answer row one got for the same digits against a different region.
    $ke = Phone::audit(['0712 123456'], 'KE')->entries()[0];
    $ug = Phone::audit(['0712 123456'], 'UG')->entries()[0];

    expect($ke->e164())->toBe('+254712123456')
        ->and($ug->e164())->not->toBe($ke->e164());
});

it('streams without accumulating, and still detects duplicates', function (): void {
    $seen = [];

    foreach (Phone::each(['+254712123456', '0712 123456', '+12015550123'], 'KE') as $entry) {
        $seen[] = [$entry->index, $entry->duplicateOf];
    }

    expect($seen)->toBe([[0, null], [1, 0], [2, null]]);
});

it('turns a column of whatever people typed into the distinct numbers it contains', function (): void {
    $e164 = Phone::e164List([
        '0712 123456',
        '+254712123456',
        '  ',
        null,
        'garbage',
        '+12015550123',
    ], 'KE');

    expect($e164)->toBe(['+254712123456', '+12015550123']);
});

it('keeps unparseable rows out of the E.164 list rather than passing them through', function (): void {
    expect(Phone::e164List(['garbage', '+2547'], 'KE'))->toBe([]);
});

it('counts nothing as nothing', function (): void {
    $audit = Phone::audit([]);

    expect($audit->isEmpty())->toBeTrue()
        ->and($audit->summary())->toMatchArray(['total' => 0, 'valid' => 0, 'invalid' => 0]);
});

it('reconciles: valid plus invalid is always the total', function (): void {
    $audit = Phone::audit(['+254712123456', 'junk', null, '', '+12015550123'], 'KE');

    $summary = $audit->summary();

    expect($summary['valid'] + $summary['invalid'])->toBe($summary['total'])
        ->and($summary['distinct'] + $summary['duplicates'])->toBe($summary['total']);
});

it('serialises the whole verdict, entries and all', function (): void {
    $json = json_decode(json_encode(Phone::audit(['+254712123456', 'junk'], 'KE'), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    expect($json)->toHaveKeys(['summary', 'countries', 'types', 'reasons', 'duplicates', 'entries'])
        ->and($json['entries'][0])->toMatchArray([
            'index' => 0,
            'input' => '+254712123456',
            'valid' => true,
            'country' => 'KE',
            'duplicate_of' => null,
        ]);
});
