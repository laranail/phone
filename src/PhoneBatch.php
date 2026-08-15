<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use Generator;
use Simtabi\Laranail\Phone\Support\PhoneAudit;
use Simtabi\Laranail\Phone\Support\PhoneAuditEntry;
use Simtabi\Laranail\Phone\Support\PhoneAuditReport;
use Stringable;

/**
 * Judges a list of numbers in one pass.
 *
 * The package could already answer every question about one number and had no notion of *more than
 * one*, which is the shape almost every real job arrives in: a CSV column, a contacts import, a
 * marketing list someone is about to send to, a table you inherited.
 *
 * ## Duplicate inputs are parsed once
 *
 * The reason to audit a list is that it is dirty, and a dirty list repeats itself. Parsing is the
 * expensive step here — libphonenumber loads a region's metadata on first contact with it — so the
 * pass memoises by raw input string. A column with 40 % repeats costs 60 % of the parses, and the
 * saving grows with exactly the mess that made the audit necessary.
 *
 * The cache lives for the pass and is discarded with it. It is deliberately **not** a process-wide
 * cache: keying arbitrary user input for the lifetime of a worker is an unbounded map, and the
 * locality that makes it pay off is inside a single list anyway.
 *
 * ## Two entry points, for two sizes of problem
 *
 * {@see audit()} holds every entry, so the result can be filtered, grouped and reported on. That is
 * right for an import you are about to act on row by row, and wrong for a file larger than memory.
 * {@see each()} yields entries and accumulates nothing, for that case.
 */
final readonly class PhoneBatch
{
    public function __construct(private PhoneFormatter $formatter) {}

    /**
     * Parse every input and return the whole verdict.
     *
     * @param iterable<mixed, string|null> $inputs
     * @param string|null $country Region for bare national input; ignored for anything carrying a `+`
     */
    public function audit(iterable $inputs, ?string $country = null): PhoneAudit
    {
        return new PhoneAudit(iterator_to_array($this->each($inputs, $country), preserve_keys: false));
    }

    /**
     * The same pass, yielded one entry at a time and never accumulated.
     *
     * Duplicate detection still works — it needs only the first index seen per E.164, which is
     * O(distinct), not O(n). What is given up is the report: nothing here can tell you the totals
     * until the generator is exhausted, and by then the entries are gone.
     *
     * @param iterable<mixed, string|null> $inputs
     * @return Generator<int, PhoneAuditEntry>
     */
    public function each(iterable $inputs, ?string $country = null): Generator
    {
        $parsed = [];
        $firstSeen = [];
        $index = 0;

        foreach ($inputs as $input) {
            $input = $this->normaliseInput($input);

            // Keyed on the raw input *and* the country, because the same digits parse differently
            // against different regions and a batch may be given only one of them — but a caller
            // narrowing per row would otherwise get the first row's answer for all of them.
            $key = $country . "\0" . $input;

            $number = $parsed[$key] ??= $this->formatter->parse($input, $country);

            $duplicateOf = null;
            $e164 = $number->e164;

            if ($e164 !== null) {
                if (isset($firstSeen[$e164])) {
                    $duplicateOf = $firstSeen[$e164];
                } else {
                    $firstSeen[$e164] = $index;
                }
            }

            yield new PhoneAuditEntry(
                index: $index,
                input: $input,
                number: $number,
                duplicateOf: $duplicateOf,
            );

            $index++;
        }
    }

    /**
     * The report, without holding the entries.
     *
     * The third option, and usually the right one for anything large: `audit()` holds every entry at
     * O(n), `each()` holds nothing and gives up the report, and this holds only the tallies and the
     * first index per number — **O(distinct)**. A million rows over a few thousand distinct numbers
     * costs the thousands.
     *
     * What is given up compared to `audit()` is per-row access: you get the verdict on the list and
     * cannot afterwards ask which rows to keep. Use `each()` alongside it if you need both, or the
     * duplicate groups, which carry the indexes.
     *
     * @param iterable<mixed, string|null> $inputs
     */
    public function report(iterable $inputs, ?string $country = null): PhoneAuditReport
    {
        $report = new PhoneAuditReport;

        foreach ($this->each($inputs, $country) as $entry) {
            $report->add($entry);
        }

        return $report;
    }

    /**
     * Just the E.164 values, de-duplicated, in first-seen order.
     *
     * The shortest useful thing to do with a list, and the one most callers want: turn a column of
     * whatever people typed into the set of distinct numbers it actually contains. Unparseable rows
     * are dropped rather than passed through — this method's contract is that everything it returns
     * is a real number.
     *
     * @param iterable<mixed, string|null> $inputs
     * @return list<string>
     */
    public function e164(iterable $inputs, ?string $country = null, bool $validOnly = true): array
    {
        $out = [];

        foreach ($this->each($inputs, $country) as $entry) {
            if ($entry->isDuplicate() || $entry->e164() === null) {
                continue;
            }

            if ($validOnly && ! $entry->isValid()) {
                continue;
            }

            $out[] = $entry->e164();
        }

        return $out;
    }

    private function normaliseInput(mixed $input): ?string
    {
        if ($input === null) {
            return null;
        }

        return is_scalar($input) || $input instanceof Stringable ? (string) $input : null;
    }
}
