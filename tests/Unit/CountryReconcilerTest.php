<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\CountryReconciler;

beforeEach(function (): void {
    $this->reconciler = new CountryReconciler;
});

/*
| The number wins. An E.164 string carries its own calling code, written by whoever knew the number;
| the country column is a convenience copy that goes stale whenever the number is edited and the
| column is not. Trusting the column would reformat a Kenyan number as American — corrupting data
| that was correct.
*/
it('believes the number over a contradicting country column', function (): void {
    $verdict = $this->reconciler->reconcile('+254712345678', 'US');

    expect($verdict->country)->toBe('KE')
        ->and($verdict->conflicted)->toBeTrue()
        ->and($verdict->reason)->toContain('contradicts');
});

it('reports no conflict when the two agree', function (): void {
    $verdict = $this->reconciler->reconcile('+254712345678', 'KE');

    expect($verdict->country)->toBe('KE')
        ->and($verdict->conflicted)->toBeFalse();
});

it('derives the country when none is stored', function (): void {
    $verdict = $this->reconciler->reconcile('+254712345678', null);

    expect($verdict->country)->toBe('KE')
        ->and($verdict->conflicted)->toBeFalse();
});

/*
| The one case where the column genuinely adds information. `+1` is shared by twenty-odd NANP
| members, and numbers with no distinguishing prefix resolve to US by default — so a stored `CA` is
| at least as good an answer as the derived one, and more likely to be what the user chose.
*/
it('keeps a stored country that shares the calling code', function (): void {
    $verdict = $this->reconciler->reconcile('+12125551234', 'CA');

    expect($verdict->country)->toBe('CA')
        ->and($verdict->conflicted)->toBeFalse()
        ->and($verdict->reason)->toContain('shared calling code');
});

it('leaves the column standing when there is no parseable number to contradict it', function (): void {
    $verdict = $this->reconciler->reconcile('nonsense', 'KE');

    expect($verdict->country)->toBe('KE')
        ->and($verdict->conflicted)->toBeFalse();
});

it('normalises a lowercase or padded country code', function (): void {
    expect($this->reconciler->reconcile('+254712345678', ' ke ')->conflicted)->toBeFalse();
});
