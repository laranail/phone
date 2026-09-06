<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

use Simtabi\Laranail\Phone\Support\CountryVerdict;

/**
 * Decides what to believe when a stored country code and a stored number disagree.
 *
 * The situation arises constantly and no surveyed package has a rule for it. A row holds
 * `('+254712345678', 'US')` because the number was edited and the country column was not, or because
 * a country picker defaulted before the paste landed, or because an import mapped the wrong column.
 *
 * **The number wins, always.** An E.164 string carries its own calling code, and that code was
 * written by whoever knew the number; the country column is a convenience copy. Trusting the column
 * would mean reformatting a Kenyan number as American, which corrupts data that was correct. Trusting
 * the number costs nothing, because the column can be recomputed from it.
 *
 * The one case where the column genuinely adds information is `+1`: the NANP spreads twenty-odd
 * countries across a single calling code, so `+1 555…` alone cannot say whether it is US or Canadian.
 * There the column is kept when it is consistent with the number's calling code.
 */
final readonly class CountryReconciler
{
    public function __construct(
        private PhoneFormatter $formatter = new PhoneFormatter,
    ) {}

    /**
     * @param string|null $number The stored number, in any format
     * @param string|null $country The stored ISO 3166-1 alpha-2 code, if any
     */
    public function reconcile(?string $number, ?string $country): CountryVerdict
    {
        $country = $country === null ? null : strtoupper(trim($country));
        $country = ($country === '' ? null : $country);

        $parsed = $this->formatter->parse($number, $country);

        if ($parsed->isEmpty()) {
            // Nothing parseable: there is no number to contradict the column, so the column stands.
            return new CountryVerdict($parsed, $country, false, 'no parseable number');
        }

        $derived = $parsed->country;

        if ($derived === null) {
            return new CountryVerdict($parsed, $country, false, 'number carries no resolvable region');
        }

        if ($country === null) {
            return new CountryVerdict($parsed, $derived, false, 'country derived from the number');
        }

        if ($country === $derived) {
            return new CountryVerdict($parsed, $derived, false, 'agreed');
        }

        // The shared-calling-code case. `+1 604…` resolves to CA, `+1 212…` to US, but many NANP
        // members have no distinguishing prefix in the metadata and resolve to US by default. If the
        // stored country shares the number's calling code, it is at least as good an answer as the
        // derived one and is more likely to reflect what the user actually chose.
        if ($this->sharesCallingCode($country, $parsed->callingCode)) {
            return new CountryVerdict($parsed, $country, false, 'shared calling code; stored country kept');
        }

        return new CountryVerdict(
            $parsed,
            $derived,
            true,
            sprintf('stored country %s contradicts the number, which is %s', $country, $derived),
        );
    }

    private function sharesCallingCode(string $country, ?int $callingCode): bool
    {
        if ($callingCode === null) {
            return false;
        }

        return $this->formatter->callingCodeFor($country) === $callingCode;
    }
}
