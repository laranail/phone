<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Phone\PhoneBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Phone\Facades\Phone;
use Simtabi\Laranail\Phone\Jobs\AuditPhoneColumn;
use Simtabi\Laranail\Phone\Support\PhoneAuditReport;

/*
|--------------------------------------------------------------------------
| The streaming report, and the job that uses it
|--------------------------------------------------------------------------
|
| The property that matters: the same numbers in produce the same report
| whether they were held or streamed. If those two ever disagree, two
| dashboards built on the same data show different figures and nobody can
| tell which is right.
|
*/

it('produces the same report streamed as held', function (): void {
    $rows = ['+254712123456', '0712 123456', '+12015550123', 'junk', '+2547'];

    expect(Phone::report($rows, 'KE')->toArray())->toBe(Phone::audit($rows, 'KE')->report());
});

it('never holds the entries', function (): void {
    // The whole reason it exists: memory is O(distinct), not O(rows). Ten thousand rows over three
    // distinct numbers costs three.
    $rows = (function (): Generator {
        for ($i = 0; $i < 10_000; $i++) {
            yield ['+254712123456', '+254733333333', '+12015550123'][$i % 3];
        }
    })();

    $before = memory_get_usage();
    $report = Phone::report($rows, 'KE');
    $growth = memory_get_usage() - $before;

    expect($report->summary())->toMatchArray(['total' => 10_000, 'valid' => 10_000, 'duplicates' => 9_997])
        ->and($report->unique())->toHaveCount(3)
        // Ten thousand held entries would be megabytes. The bound is loose on purpose — it is
        // asserting an order of magnitude, not a byte count.
        ->and($growth)->toBeLessThan(1_000_000);
});

it('breaks the failures down the same way the audit does', function (): void {
    $report = Phone::report(['+2547', '+25471', 'nonsense', '+254712123456'], 'KE');

    expect($report->reasons())->toHaveKey('TOO_SHORT')
        ->and($report->summary())->toMatchArray(['total' => 4, 'valid' => 1, 'invalid' => 3]);
});

it('merges two reports, and finds duplicates neither chunk could see alone', function (): void {
    // A number in chunk one and again in chunk two is a duplicate that neither chunk knows about,
    // which is why merge() recounts rather than adding the two totals.
    $first = Phone::report(['+254712123456', '+254733333333'], 'KE');
    $second = Phone::report(['+254712123456'], 'KE');

    $first->merge($second);

    expect($first->summary())->toMatchArray(['total' => 3, 'duplicates' => 1, 'distinct' => 2])
        ->and($first->countries())->toBe(['KE' => 3]);
});

it('samples duplicate row indexes but counts them exactly', function (): void {
    // The cap is what makes the memory claim true, so it is stated in the payload rather than left
    // as a surprise: `duplicates` is a sample, `duplicate_counts` is not.
    $rows = array_fill(0, PhoneAuditReport::SAMPLE_LIMIT + 50, '+254712123456');

    $report = Phone::report($rows, 'KE');

    expect($report->duplicateGroups()['+254712123456'])->toHaveCount(PhoneAuditReport::SAMPLE_LIMIT)
        ->and($report->duplicateCounts()['+254712123456'])->toBe(PhoneAuditReport::SAMPLE_LIMIT + 50)
        ->and($report->summary()['duplicates'])->toBe(PhoneAuditReport::SAMPLE_LIMIT + 49);
});

it('counts nothing as nothing', function (): void {
    expect((new PhoneAuditReport)->summary())->toMatchArray(['total' => 0, 'valid' => 0, 'invalid' => 0]);
});

describe('the queued job', function (): void {
    beforeEach(function (): void {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
        });
    });

    it('audits a whole column and caches the report', function (): void {
        AuditableContact::insert([
            ['phone' => '+254712123456', 'active' => true],
            ['phone' => '0712 123456', 'active' => true],
            ['phone' => 'junk', 'active' => true],
            ['phone' => null, 'active' => true],
        ]);

        new AuditPhoneColumn(AuditableContact::class, 'phone', country: 'KE', key: 'contacts')
            ->handle(app(PhoneBatch::class));

        $report = Cache::get('laranail.phone.audit.contacts');

        expect($report['summary'])->toMatchArray(['total' => 4, 'valid' => 2, 'duplicates' => 1])
            ->and($report['duplicates'])->toBe(['+254712123456' => [0, 1]]);
    });

    it('numbers rows across chunk boundaries, not within them', function (): void {
        // Chunked auditing that restarts indexes per chunk collides the duplicate groups and reports
        // rows as duplicates of each other that are nothing of the kind. One row per chunk is the
        // harshest version of that test.
        AuditableContact::insert([
            ['phone' => '+254712123456', 'active' => true],
            ['phone' => '+254733333333', 'active' => true],
            ['phone' => '+254712123456', 'active' => true],
        ]);

        new AuditPhoneColumn(AuditableContact::class, 'phone', country: 'KE', key: 'chunked', chunk: 1)
            ->handle(app(PhoneBatch::class));

        expect(Cache::get('laranail.phone.audit.chunked')['duplicates'])
            ->toBe(['+254712123456' => [0, 2]]);
    });

    it('honours a named scope', function (): void {
        AuditableContact::insert([
            ['phone' => '+254712123456', 'active' => true],
            ['phone' => '+254733333333', 'active' => false],
        ]);

        new AuditPhoneColumn(AuditableContact::class, 'phone', country: 'KE', key: 'scoped', scope: 'active')
            ->handle(app(PhoneBatch::class));

        expect(Cache::get('laranail.phone.audit.scoped')['summary']['total'])->toBe(1);
    });

    it('publishes progress as it goes', function (): void {
        AuditableContact::insert(array_map(
            static fn (int $i): array => ['phone' => '+2547121234' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'active' => true],
            range(1, 5),
        ));

        new AuditPhoneColumn(AuditableContact::class, 'phone', country: 'KE', key: 'progress', chunk: 2)
            ->handle(app(PhoneBatch::class));

        expect(Cache::get('laranail.phone.audit.progress.progress'))->toBe(5);
    });

    it('refuses a class that is not a model, by name', function (): void {
        // A wrong class name here would otherwise surface as a static call on a string, several
        // frames deeper and long after the dispatch that caused it.
        expect(fn () => new AuditPhoneColumn(stdClass::class, 'phone')
            ->handle(app(PhoneBatch::class)))
            ->toThrow(InvalidArgumentException::class, 'is not an Eloquent model');
    });

    it('refuses a scope that does not return a builder', function (): void {
        // Silently ignoring it would audit the whole table while appearing to audit a subset — the
        // worst kind of wrong, because the report looks plausible.
        expect(fn () => new AuditPhoneColumn(AuditableContact::class, 'phone', scope: 'notAScope')
            ->handle(app(PhoneBatch::class)))
            ->toThrow(InvalidArgumentException::class, 'did not return a query builder');
    });
});

class AuditableContact extends Model
{
    public $timestamps = false;

    protected $table = 'contacts';

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeNotAScope($query): string
    {
        return 'not a builder';
    }
}
