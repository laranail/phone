<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Phone\Enums\MatchLeniency;
use Simtabi\Laranail\Phone\Http\PhonePresenter;
use Simtabi\Laranail\Phone\PhoneBatch;
use Simtabi\Laranail\Phone\PhoneCatalogue;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneScanner;
use Simtabi\Laranail\Phone\Support\PhoneAuditEntry;

/**
 * The package over HTTP, for the callers that are not PHP.
 *
 * ## Why there is no `FormRequest` here
 *
 * `Illuminate\Foundation\Http\FormRequest` lives in `illuminate/foundation`, which is not published
 * as a standalone Composer package — depending on it means depending on `laravel/framework` in its
 * entirety, from a package that otherwise needs four Illuminate components. `Validator::make()`
 * throws the same {@see ValidationException}, which Laravel's handler renders into the same 422
 * body, so nothing is given up but the dependency.
 *
 * ## Two verbs that look like one
 *
 * `batch` returns a result per input and its size grows with the request. `audit` returns the report
 * only — counts, breakdowns, duplicate groups — and its size does not. A caller checking ten
 * thousand rows for import wants the second and would drown in the first.
 */
final readonly class PhoneApiController
{
    public function __construct(
        private PhoneFormatter $formatter,
        private PhoneBatch $batch,
        private PhoneScanner $scanner,
        private PhoneCatalogue $catalogue,
        private PhonePresenter $presenter,
    ) {}

    /** One number, everything known about it. */
    public function analyze(Request $request): JsonResponse
    {
        $input = $this->validate($request, [
            'number' => ['required', 'string', 'max:60'],
            'country' => ['nullable', 'string', 'size:2'],
            'intel' => ['sometimes', 'boolean'],
        ]);

        $number = $this->formatter->parse(
            is_string($input['number']) ? $input['number'] : null,
            $this->country($input),
        );

        return new JsonResponse([
            'data' => $this->presenter->number($number, $this->wantsIntel($input)),
        ]);
    }

    /** Many numbers, one result each, plus the report. */
    public function batch(Request $request): JsonResponse
    {
        $input = $this->validateBatch($request);

        $audit = $this->batch->audit($input['numbers'], $this->country($input));
        $intel = $this->wantsIntel($input);

        return new JsonResponse([
            'data' => array_map(
                fn (PhoneAuditEntry $entry): array => $this->presenter->entry($entry, $intel),
                $audit->entries(),
            ),
            'meta' => $audit->report(),
        ]);
    }

    /**
     * Many numbers, the report only.
     *
     * The same pass as `batch`, with the per-row payload dropped. For the caller who is asking "is
     * this list worth importing", which is a question about the list.
     */
    public function audit(Request $request): JsonResponse
    {
        $input = $this->validateBatch($request);

        $audit = $this->batch->audit($input['numbers'], $this->country($input));

        return new JsonResponse([
            'data' => [
                ...$audit->report(),
                'invalid' => array_map(
                    static fn (PhoneAuditEntry $entry): array => [
                        'index' => $entry->index,
                        'input' => $entry->input,
                        'reason' => $entry->reason()->value,
                    ],
                    $audit->invalid(),
                ),
            ],
        ]);
    }

    /** Free text in, the numbers it contains out. */
    public function scan(Request $request): JsonResponse
    {
        $input = $this->validate($request, [
            'text' => ['required', 'string', 'max:100000'],
            'country' => ['nullable', 'string', 'size:2'],
            'leniency' => ['nullable', Rule::in(array_column(MatchLeniency::cases(), 'value'))],
        ]);

        $leniency = is_string($input['leniency'] ?? null)
            ? MatchLeniency::tryFrom($input['leniency'])
            : null;

        $matches = $this->scanner->scan(is_string($input['text']) ? $input['text'] : null, $this->country($input), $leniency);

        return new JsonResponse([
            'data' => array_map($this->presenter->match(...), $matches),
            'meta' => ['count' => count($matches)],
        ]);
    }

    /**
     * The numbering plan: every region, its calling code, and an example number.
     *
     * Here because the alternative is every client shipping its own copy of the world's country
     * list, which is how those copies go stale.
     */
    public function countries(Request $request): JsonResponse
    {
        $input = $this->validate($request, [
            'calling_code' => ['nullable', 'integer', 'min:1', 'max:999'],
            'examples' => ['sometimes', 'boolean'],
        ]);

        $callingCode = $input['calling_code'] ?? null;

        $regions = is_numeric($callingCode)
            ? $this->catalogue->regionsForCallingCode((int) $callingCode)
            : $this->catalogue->regions();

        $withExamples = (bool) ($input['examples'] ?? false);

        return new JsonResponse([
            'data' => array_map(
                fn (string $region): array => $this->presenter->region($region, $withExamples),
                $regions,
            ),
            'meta' => ['count' => count($regions)],
        ]);
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<array-key, mixed>
     *
     * @throws ValidationException
     */
    private function validate(Request $request, array $rules): array
    {
        return Validator::make($request->all(), $rules)->validate();
    }

    /**
     * @return array{numbers: list<string|null>, country: string|null, intel: bool|null}
     *
     * @throws ValidationException
     */
    private function validateBatch(Request $request): array
    {
        $configured = config('laranail.phone.api.max_batch', 1000);
        $max = max(1, is_numeric($configured) ? (int) $configured : 1000);

        /** @var array{numbers: list<string|null>, country: string|null, intel: bool|null} $validated */
        $validated = $this->validate($request, [
            // `max` is enforced rather than applied: a caller that sent more than the cap gets a 422
            // naming the field, never a truncated answer it has no way to notice.
            'numbers' => ['required', 'array', 'min:1', "max:{$max}"],
            'numbers.*' => ['nullable', 'string', 'max:60'],
            'country' => ['nullable', 'string', 'size:2'],
            'intel' => ['sometimes', 'boolean'],
        ]);

        return $validated;
    }

    /**
     * @param array<array-key, mixed> $input
     */
    private function country(array $input): ?string
    {
        $country = $input['country'] ?? null;

        return is_string($country) && $country !== '' ? strtoupper($country) : null;
    }

    /**
     * @param array<array-key, mixed> $input
     */
    private function wantsIntel(array $input): bool
    {
        if (config('laranail.phone.api.allow_intel', true) !== true) {
            return false;
        }

        return (bool) ($input['intel'] ?? false);
    }
}
