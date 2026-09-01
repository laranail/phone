<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Support;

use JsonSerializable;
use Simtabi\Laranail\Phone\PhoneBatch;

/**
 * The fixed-size verdict on a list, accumulated one entry at a time.
 *
 * ## The gap this closes
 *
 * {@see PhoneAudit} holds every entry, so it can report *and* be filtered — at O(n) memory.
 * {@see PhoneBatch::each()} holds nothing, so it scales to any input — and
 * gives up the report, because nothing can total a list it has already forgotten.
 *
 * That was a false choice. The report is fixed-size by construction: counts, tallies, and a bounded
 * sample of the row indexes per number. None of that needs the entries kept, and none of it grows
 * with the input — so a million rows over a few thousand distinct numbers costs the few thousand.
 *
 * That last part took a correction. An earlier version kept **every** index per number, which is
 * O(rows) wearing an O(distinct) description: ten thousand rows over three numbers cost 1.2 MB, and
 * a million would have been proportionally worse. The counts are exact and the indexes are sampled;
 * see {@see $samples}.
 *
 * ## One definition of the report
 *
 * `PhoneAudit::report()` delegates here rather than computing its own. The two paths through the
 * package — hold everything, or stream — therefore cannot drift into disagreeing about what a
 * summary means, which is exactly the kind of divergence nobody notices until two dashboards built
 * on the same data show different numbers.
 *
 * Mutable, deliberately and alone in this package: it is an accumulator, and an immutable one would
 * allocate a new object per row.
 */
final class PhoneAuditReport implements JsonSerializable
{
    /** How many example row indexes are kept per duplicate group. */
    public const int SAMPLE_LIMIT = 100;

    private int $total = 0;

    private int $valid = 0;

    private int $possible = 0;

    private int $duplicates = 0;

    /** @var array<string, int> */
    private array $countries = [];

    /** @var array<string, int> */
    private array $types = [];

    /** @var array<string, int> */
    private array $reasons = [];

    /**
     * E.164 => how many rows produced it. Exact, and O(distinct).
     *
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * E.164 => up to {@see SAMPLE_LIMIT} of the input indexes that produced it.
     *
     * **Capped, and the cap is the difference between this class working and not.** Keeping every
     * index makes the structure O(rows) rather than O(distinct) — a million-row column over three
     * distinct numbers would hold a million integers, which is precisely the cost the streaming
     * report exists to avoid. Measured before the cap: ten thousand rows over three numbers cost
     * 1.2 MB, and the test that caught it is still there.
     *
     * The counts above stay exact, so no total is ever wrong. What is bounded is how many example
     * rows a reader can be pointed at, and a hundred is both more than anyone reads and enough to
     * see the pattern.
     *
     * @var array<string, list<int>>
     */
    private array $samples = [];

    public function add(PhoneAuditEntry $entry): void
    {
        $this->total++;

        if ($entry->isValid()) {
            $this->valid++;
        } else {
            $reason = $entry->reason()->value;
            $this->reasons[$reason] = ($this->reasons[$reason] ?? 0) + 1;
        }

        if ($entry->isPossible()) {
            $this->possible++;
        }

        if ($entry->isDuplicate()) {
            $this->duplicates++;
        }

        $country = $entry->country();

        if ($country !== null) {
            $this->countries[$country] = ($this->countries[$country] ?? 0) + 1;
        }

        $type = $entry->type()->value;
        $this->types[$type] = ($this->types[$type] ?? 0) + 1;

        $e164 = $entry->e164();

        if ($e164 !== null) {
            $this->record($e164, $entry->index);
        }
    }

    /**
     * Fold another report into this one.
     *
     * For the case where chunks were audited separately — different workers, or a resumed job — and
     * the totals have to come back together. Duplicate groups merge correctly **only because indexes
     * are positions in the whole input rather than in the chunk**, which is why the chunked job
     * offsets them rather than restarting at zero per chunk.
     */
    public function merge(self $other): void
    {
        $this->total += $other->total;
        $this->valid += $other->valid;
        $this->possible += $other->possible;

        // Written out rather than looped over property names: a dynamic `$this->{$name}` is
        // untypeable, so a static analyser cannot tell that only int-keyed tallies are being added
        // together — and a typo in the list would be a runtime error rather than a compile-time one.
        $this->countries = $this->addTallies($this->countries, $other->countries);
        $this->types = $this->addTallies($this->types, $other->types);
        $this->reasons = $this->addTallies($this->reasons, $other->reasons);

        foreach ($other->counts as $e164 => $count) {
            $this->counts[$e164] = ($this->counts[$e164] ?? 0) + $count;
        }

        foreach ($other->samples as $e164 => $indexes) {
            foreach ($indexes as $index) {
                $this->sample($e164, $index);
            }
        }

        // Recounted rather than added, because a number first seen in chunk one and again in chunk
        // two is a duplicate that neither chunk could see on its own.
        $this->duplicates = 0;

        foreach ($this->counts as $count) {
            $this->duplicates += max(0, $count - 1);
        }
    }

    /**
     * @return array{total: int, valid: int, invalid: int, possible: int, duplicates: int, distinct: int, countries: int}
     */
    public function summary(): array
    {
        return [
            'total' => $this->total,
            'valid' => $this->valid,
            'invalid' => $this->total - $this->valid,
            'possible' => $this->possible,
            'duplicates' => $this->duplicates,
            'distinct' => $this->total - $this->duplicates,
            'countries' => count($this->countries),
        ];
    }

    /**
     * E.164 => exactly how many rows produced it, for the ones that repeat.
     *
     * Unlike {@see duplicateGroups()} these counts are never truncated, so a total taken from here
     * is right however large the input was.
     *
     * @return array<string, int>
     */
    public function duplicateCounts(): array
    {
        return array_filter($this->counts, static fn (int $count): bool => $count > 1);
    }

    /**
     * @return array<string, int>
     */
    public function countries(): array
    {
        return $this->sorted($this->countries);
    }

    /**
     * @return array<string, int>
     */
    public function types(): array
    {
        return $this->sorted($this->types);
    }

    /**
     * @return array<string, int>
     */
    public function reasons(): array
    {
        return $this->sorted($this->reasons);
    }

    /**
     * E.164 => example input indexes, for the ones that repeat.
     *
     * A **sample**, capped at {@see SAMPLE_LIMIT} per group. {@see duplicateCounts()} carries the
     * exact totals; this carries enough rows to go and look at.
     *
     * @return array<string, list<int>>
     */
    public function duplicateGroups(): array
    {
        $duplicated = $this->duplicateCounts();

        return array_intersect_key($this->samples, $duplicated);
    }

    /**
     * Every distinct E.164, in first-seen order.
     *
     * @return list<string>
     */
    public function unique(): array
    {
        return array_keys($this->counts);
    }

    /**
     * @return array{summary: array<string, int>, countries: array<string, int>, types: array<string, int>, reasons: array<string, int>, duplicates: array<string, list<int>>, duplicate_counts: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary(),
            'countries' => $this->countries(),
            'types' => $this->types(),
            'reasons' => $this->reasons(),
            'duplicates' => $this->duplicateGroups(),
            'duplicate_counts' => $this->duplicateCounts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function record(string $key, int $index): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

        $this->sample($key, $index);
    }

    private function sample(string $key, int $index): void
    {
        $kept = $this->samples[$key] ?? [];

        if (count($kept) >= self::SAMPLE_LIMIT) {
            return;
        }

        $kept[] = $index;
        $this->samples[$key] = $kept;
    }

    /**
     * @param  array<string, int>  $into
     * @param  array<string, int>  $from
     * @return array<string, int>
     */
    private function addTallies(array $into, array $from): array
    {
        foreach ($from as $key => $count) {
            $into[$key] = ($into[$key] ?? 0) + $count;
        }

        return $into;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function sorted(array $counts): array
    {
        arsort($counts);

        return $counts;
    }
}
