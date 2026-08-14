<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone;

/**
 * Turns what people actually paste into something libphonenumber can parse.
 *
 * This runs **before** parsing, and it is deliberately dumb: it rewrites characters, it does not
 * interpret numbering plans. Every transformation here is one libphonenumber either does not do at
 * all, or does not do before it has already decided the input is unparseable.
 *
 * None of the three Filament phone packages surveyed does any of this, which is why each of them has
 * a variant of "the number my user pasted was rejected" in its issue tracker.
 */
final readonly class PhoneNormalizer
{
    /**
     * Digits that are not `0`-`9` but mean the same thing.
     *
     * Arabic-Indic (U+0660–0669) and Extended Arabic-Indic (U+06F0–06F9) reach a form whenever the
     * user's keyboard is set to Arabic, Persian or Urdu — which is precisely the audience one of the
     * surveyed packages was written for, and it does not handle them. A number typed as `٠٧١٢٣٤٥٦٧٨`
     * is a perfectly ordinary number; only its code points differ.
     *
     * @var array<string, string>
     */
    private const array DIGIT_MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * Space-like characters that are not U+0020.
     *
     * These arrive by copy-paste from Word, PDFs, contact cards and websites that format numbers for
     * display. A non-breaking space between the dial code and the number is enough for a naive
     * `preg_replace('/\s/')` to miss it and for parsing to fail on a number that looks correct on
     * screen — the worst class of bug to receive a report about.
     *
     * Folded to a **space**, not stripped. They occupy width on screen, so the user sees a separator
     * there; removing it silently joins two digit groups, which is a different string from the one
     * they think they pasted.
     *
     * @var list<string>
     */
    private const array SPACES = [
        "\u{00A0}", // no-break space
        "\u{202F}", // narrow no-break space
        "\u{2007}", // figure space
        "\u{2009}", // thin space
        "\u{2002}", // en space
        "\u{2003}", // em space
    ];

    /**
     * Characters that occupy no width at all.
     *
     * Stripped rather than folded, because they are invisible: the user never saw a separator, so
     * introducing one would be inventing input. The bidirectional marks in particular are inserted
     * automatically when a number is copied out of an RTL document, and they sit *inside* the digit
     * run rather than between groups.
     *
     * @var list<string>
     */
    private const array INVISIBLE = [
        "\u{200B}", // zero-width space
        "\u{200C}", // zero-width non-joiner
        "\u{200D}", // zero-width joiner
        "\u{FEFF}", // zero-width no-break space (BOM)
        "\u{200E}", // left-to-right mark
        "\u{200F}", // right-to-left mark
        "\u{061C}", // Arabic letter mark
        "\u{2066}", // left-to-right isolate
        "\u{2067}", // right-to-left isolate
        "\u{2068}", // first strong isolate
        "\u{2069}", // pop directional isolate
    ];

    /**
     * Dash-like characters that are not `-`.
     *
     * @var list<string>
     */
    private const array DASHES = [
        "\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}", "\u{2212}", "\u{FE63}", "\u{FF0D}",
    ];

    /**
     * Full-width and alternative plus signs.
     *
     * @var list<string>
     */
    private const array PLUSES = ["\u{FF0B}", "\u{2795}"];

    /**
     * The E.164 letter map, for vanity numbers.
     *
     * Off by default. `1-800-FLOWERS` is a real number people type, but so is a Nigerian number
     * written `080 ABC 1234` where the letters are an initialism and not a keypad instruction —
     * converting blindly would corrupt it. Opt in only where vanity numbers are actually expected.
     *
     * @var array<string, string>
     */
    private const array VANITY_MAP = [
        'A' => '2', 'B' => '2', 'C' => '2', 'D' => '3', 'E' => '3', 'F' => '3',
        'G' => '4', 'H' => '4', 'I' => '4', 'J' => '5', 'K' => '5', 'L' => '5',
        'M' => '6', 'N' => '6', 'O' => '6', 'P' => '7', 'Q' => '7', 'R' => '7',
        'S' => '7', 'T' => '8', 'U' => '8', 'V' => '8', 'W' => '9', 'X' => '9',
        'Y' => '9', 'Z' => '9',
    ];

    /**
     * International direct-dialling prefixes, longest first.
     *
     * A number written `00254712345678` or `011 254 712 345678` is the same number as
     * `+254712345678`; the prefix is how you *dial* internationally from a particular country, not
     * part of the number. libphonenumber understands `00` when it already knows the calling region,
     * but not when it does not — and a paste from a foreign contact list is exactly the case where
     * it does not.
     *
     * Order matters: `011` must be tested before `01`, or the longer prefix is never matched.
     *
     * @var list<string>
     */
    private const array IDD_PREFIXES = ['011', '810', '00', '010', '002', '005', '009', '119'];

    public function __construct(
        private bool $convertVanityLetters = false,
    ) {}

    /**
     * Normalise raw user input into something worth handing to a parser.
     *
     * Returns `null` for input that contains no digits at all, because there is nothing there to
     * parse and `''` and `'   '` and `'-'` should not be three different outcomes downstream.
     */
    public function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = $this->stripInvisible($input);
        $value = $this->foldDigits($value);
        $value = $this->foldPunctuation($value);

        if ($this->convertVanityLetters) {
            $value = $this->convertVanity($value);
        }

        $value = trim($value);
        $value = $this->promoteIddPrefix($value);

        // An extension is not part of the dialable number, but throwing it away silently loses data.
        // It is preserved verbatim and re-attached; only the number half is normalised further.
        [$number, $extension] = $this->splitExtension($value);

        if (! preg_match('/\d/', $number)) {
            return null;
        }

        return $extension === null ? $number : $number . ';ext=' . $extension;
    }

    /** Digits only, with a leading `+` if there was one. Useful for comparison, never for storage. */
    public function digitsOnly(?string $input): ?string
    {
        $value = $this->normalize($input);

        if ($value === null) {
            return null;
        }

        $plus = str_starts_with($value, '+') ? '+' : '';

        return $plus . preg_replace('/\D/', '', $value);
    }

    private function stripInvisible(string $value): string
    {
        $value = str_replace(self::SPACES, ' ', $value);

        return str_replace(self::INVISIBLE, '', $value);
    }

    private function foldDigits(string $value): string
    {
        return strtr($value, self::DIGIT_MAP);
    }

    private function foldPunctuation(string $value): string
    {
        $value = str_replace(self::DASHES, '-', $value);

        return str_replace(self::PLUSES, '+', $value);
    }

    private function convertVanity(string $value): string
    {
        return strtr(mb_strtoupper($value), self::VANITY_MAP);
    }

    /**
     * Rewrite a leading IDD prefix as `+`.
     *
     * Guarded three ways, because a false positive here silently corrupts a valid national number:
     * the prefix must be at the very start, must be followed by at least one non-zero digit (`000…`
     * is not an IDD call), and the remainder must be long enough to be an international number at
     * all. A UK national number like `020 7946 0018` starts `0`, not `00`, so it is untouched.
     */
    private function promoteIddPrefix(string $value): string
    {
        if (str_starts_with($value, '+')) {
            return $value;
        }

        $compact = preg_replace('/[^\d]/', '', $value) ?? '';

        foreach (self::IDD_PREFIXES as $prefix) {
            if (! str_starts_with($compact, $prefix)) {
                continue;
            }

            $remainder = substr($compact, strlen($prefix));

            // E.164 allows at most 15 digits and no plan is shorter than 7 once the country code is
            // included. Anything outside that is more likely a national number that happens to start
            // with these digits than an international one.
            if (strlen($remainder) < 7 || strlen($remainder) > 15 || str_starts_with($remainder, '0')) {
                continue;
            }

            return '+' . $remainder;
        }

        return $value;
    }

    /**
     * Split a trailing extension off the number.
     *
     * Recognises the RFC 3966 `;ext=` form and the shapes people type — `x123`, `ext 123`,
     * `ext. 123`, `extension 123`, `#123`. The extension is returned as digits only.
     *
     * @return array{string, string|null}
     */
    private function splitExtension(string $value): array
    {
        if (preg_match('/^(.*?)\s*;\s*ext\s*=\s*(\d+)\s*$/i', $value, $m) === 1) {
            return [trim($m[1]), $m[2]];
        }

        $pattern = '/^(.*?\d)\s*(?:'
            . 'x|ext|ext\.|extn|extension|#'
            . ')[\s.:-]*(\d{1,7})\s*$/i';

        if (preg_match($pattern, $value, $m) === 1) {
            return [trim($m[1]), $m[2]];
        }

        return [$value, null];
    }
}
