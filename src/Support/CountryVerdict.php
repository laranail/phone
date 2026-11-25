<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Support;

use Simtabi\Laranail\Phone\CountryReconciler;
use Simtabi\Laranail\Phone\PhoneNumberValue;

/**
 * The outcome of {@see CountryReconciler::reconcile()}.
 *
 * `$conflicted` is separate from `$country` on purpose. A caller that only wants the right answer
 * reads `$country` and ignores the rest; a caller running a data-quality sweep reads `$conflicted`
 * and `$reason` to decide what to report. Collapsing the two would force the second caller to
 * re-derive the comparison the reconciler already made.
 */
final readonly class CountryVerdict
{
    public function __construct(
        public PhoneNumberValue $number,
        public ?string $country,
        public bool $conflicted,
        public string $reason,
    ) {}
}
