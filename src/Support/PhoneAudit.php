<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Simtabi\Laranail\Phone\PhoneBatch;
use Traversable;

/**
 * The verdict on a whole list of numbers.
 *
 * Two questions get asked of a batch and they are not the same question. *What is each of these*
 * produces one answer per input and scales with it. *What is wrong with this list* produces a report
 * whose size does not depend on the list's length at all — counts, breakdowns, the duplicate groups,
 * the failure reasons. This object answers both, from one pass, so the two can never disagree about
 * the same input.
 *
 * ## Memory
 *
 * Entries are held, so this is O(n) in the input. That is the right trade for the case it is for —
 * an import you are about to act on row by row — and the wrong one for a file larger than memory.
 * {@see PhoneBatch::each()} exists for that case: it yields entries and
 * accumulates nothing.
 *
 * @implements IteratorAggregate<int, PhoneAuditEntry>
 */
final readonly class PhoneAudit implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param  list<PhoneAuditEntry>  $entries
     */
    public function __construct(public array $entries) {}

    /**
     * @return list<PhoneAuditEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<PhoneAuditEntry>
     */
    public function valid(): array
    {
        return array_values(array_filter($this->entries, static fn (PhoneAuditEntry $e): bool => $e->isValid()));
    }

    /**
     * Everything that did not parse to a valid number, in input order.
     *
     * @return list<PhoneAuditEntry>
     */
    public function invalid(): array
    {
        return array_values(array_filter($this->entries, static fn (PhoneAuditEntry $e): bool => ! $e->isValid()));
    }

    /**
     * The rows that repeat an earlier row.
     *
     * @return list<PhoneAuditEntry>
     */
    public function duplicates(): array
    {
        return array_values(array_filter($this->entries, static fn (PhoneAuditEntry $e): bool => $e->isDuplicate()));
    }

    /**
     * The rows to keep when de-duplicating: everything that is not a repeat of something earlier.
     *
     * The survivor of a duplicate group is deterministically the **earliest** row, which is the one
     * a person is most likely to have meant — the original, not the re-entry.
     *
     * @return list<PhoneAuditEntry>
     */
    public function distinct(): array
    {
        return array_values(array_filter($this->entries, static fn (PhoneAuditEntry $e): bool => ! $e->isDuplicate()));
    }

    /**
     * Duplicate groups: E.164 => the input indexes that produced it, first one first.
     *
     * @return array<string, list<int>>
     */
    public function duplicateGroups(): array
    {
        $groups = [];

        foreach ($this->entries as $entry) {
            $e164 = $entry->e164();

            if ($e164 === null) {
                continue;
            }

            $groups[$e164][] = $entry->index;
        }

        return array_filter($groups, static fn (array $indexes): bool => count($indexes) > 1);
    }

    /**
     * Every distinct E.164 the list resolved to, in first-seen order.
     *
     * @return list<string>
     */
    public function unique(): array
    {
        $seen = [];

        foreach ($this->entries as $entry) {
            $e164 = $entry->e164();

            if ($e164 !== null) {
                $seen[$e164] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * ISO 3166-1 alpha-2 => how many rows resolved to it, commonest first.
     *
     * @return array<string, int>
     */
    public function countries(): array
    {
        return $this->tally(static fn (PhoneAuditEntry $e): ?string => $e->country());
    }

    /**
     * Number type => count, commonest first. Unparseable rows land in `UNKNOWN`.
     *
     * @return array<string, int>
     */
    public function types(): array
    {
        return $this->tally(static fn (PhoneAuditEntry $e): string => $e->type()->value);
    }

    /**
     * Why the invalid rows are invalid => count.
     *
     * This is the one to render. "42 invalid" tells an operator nothing; "38 too short, 4 with an
     * unrecognised calling code" tells them the column was truncated on export.
     *
     * @return array<string, int>
     */
    public function reasons(): array
    {
        $invalid = array_filter($this->entries, static fn (PhoneAuditEntry $e): bool => ! $e->isValid());

        return $this->tally(static fn (PhoneAuditEntry $e): string => $e->reason()->value, $invalid);
    }

    /**
     * The fixed-size headline, whatever the input length.
     *
     * @return array{total: int, valid: int, invalid: int, possible: int, duplicates: int, distinct: int, countries: int}
     */
    public function summary(): array
    {
        $valid = count($this->valid());
        $duplicates = count($this->duplicates());

        return [
            'total' => count($this->entries),
            'valid' => $valid,
            'invalid' => count($this->entries) - $valid,
            'possible' => count(array_filter($this->entries, static fn (PhoneAuditEntry $e): bool => $e->isPossible())),
            'duplicates' => $duplicates,
            'distinct' => count($this->entries) - $duplicates,
            'countries' => count($this->countries()),
        ];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    /**
     * The fixed-size verdict.
     *
     * Delegates to {@see PhoneAuditReport} rather than computing its own, so this path and the
     * streaming one cannot drift into disagreeing about what a summary means — the kind of
     * divergence nobody notices until two dashboards built on the same data show different numbers.
     *
     * @return array{summary: array<string, int>, countries: array<string, int>, types: array<string, int>, reasons: array<string, int>, duplicates: array<string, list<int>>}
     */
    public function report(): array
    {
        $report = new PhoneAuditReport;

        foreach ($this->entries as $entry) {
            $report->add($entry);
        }

        return $report->toArray();
    }

    /**
     * @return array{summary: array<string, int>, countries: array<string, int>, types: array<string, int>, reasons: array<string, int>, duplicates: array<string, list<int>>, entries: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            ...$this->report(),
            'entries' => array_map(static fn (PhoneAuditEntry $e): array => $e->toArray(), $this->entries),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  callable(PhoneAuditEntry): ?string  $key
     * @param  list<PhoneAuditEntry>|array<int, PhoneAuditEntry>|null  $over
     * @return array<string, int>
     */
    private function tally(callable $key, ?array $over = null): array
    {
        $counts = [];

        foreach ($over ?? $this->entries as $entry) {
            $value = $key($entry);

            if ($value === null) {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
