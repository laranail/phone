<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Phone\Http\ApiRoutes;

/*
|--------------------------------------------------------------------------
| The HTTP API
|--------------------------------------------------------------------------
|
| Two things are being guarded here and only one of them is the JSON. The
| other is that installing this package adds nothing to an application's
| attack surface: no routes exist until config says so, and when they do
| they are rate limited whether or not anyone remembered to ask.
|
*/

it('registers no routes at all until an application asks for them', function (): void {
    // A package that publishes endpoints by being installed changes an application's attack surface
    // as a side effect of `composer require`, and the person who notices is rarely the one who ran it.
    $names = array_keys(Route::getRoutes()->getRoutesByName());

    expect(array_filter($names, static fn (string $n): bool => str_starts_with($n, ApiRoutes::NAME_PREFIX)))->toBe([]);
});

describe('with the API enabled', function (): void {
    beforeEach(function (): void {
        config()->set('laranail.phone.api.enabled', true);

        ApiRoutes::register(config());
    });

    it('answers everything known about one number', function (): void {
        $this->postJson('api/laranail/phone/analyze', ['number' => '0712 123456', 'country' => 'KE'])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.e164', '+254712123456')
            ->assertJsonPath('data.national', '0712 123456')
            ->assertJsonPath('data.country', 'KE')
            ->assertJsonPath('data.calling_code', 254)
            ->assertJsonPath('data.type', 'MOBILE')
            ->assertJsonPath('data.tel_link', 'tel:+254-712-123456');
    });

    it('hands an unparseable number back with a reason rather than an error', function (): void {
        // A 500 for bad input would be wrong: the request was well formed and the answer is simply
        // "no". The reason is the part a caller can show to whoever typed it.
        $this->postJson('api/laranail/phone/analyze', ['number' => 'nonsense'])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', 'NOT_A_NUMBER')
            ->assertJsonPath('data.reason_label', 'Not a phone number');
    });

    it('leaves intel out unless it is asked for', function (): void {
        // Carrier, geocoding and timezone each load their own metadata. Free for one number, a
        // different cost class for a thousand — so it is opt-in rather than merely documented.
        $this->postJson('api/laranail/phone/analyze', ['number' => '+254712123456'])
            ->assertOk()
            ->assertJsonMissingPath('data.carrier');

        $this->postJson('api/laranail/phone/analyze', ['number' => '+254712123456', 'intel' => true])
            ->assertOk()
            ->assertJsonStructure(['data' => ['carrier', 'description', 'timezones']]);
    });

    it('refuses intel when the application has switched it off', function (): void {
        config()->set('laranail.phone.api.allow_intel', false);

        $this->postJson('api/laranail/phone/analyze', ['number' => '+254712123456', 'intel' => true])
            ->assertOk()
            ->assertJsonMissingPath('data.carrier');
    });

    it('returns one result per input plus the report', function (): void {
        $this->postJson('api/laranail/phone/batch', [
            'numbers' => ['+254712123456', '0712 123456', 'junk'],
            'country' => 'KE',
        ])
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.1.duplicate_of', 0)
            ->assertJsonPath('meta.summary.total', 3)
            ->assertJsonPath('meta.summary.valid', 2)
            ->assertJsonPath('meta.summary.duplicates', 1)
            ->assertJsonPath('meta.duplicates', ['+254712123456' => [0, 1]]);
    });

    it('drops the per-row payload for an audit but keeps the failures addressable', function (): void {
        // The whole point of the second verb: a caller checking ten thousand rows for import wants
        // the verdict on the list, not ten thousand rows back. The invalid ones still carry their
        // index, because "42 invalid" is not something anyone can act on.
        $response = $this->postJson('api/laranail/phone/audit', [
            'numbers' => ['+254712123456', 'junk', '+2547'],
            'country' => 'KE',
        ])->assertOk();

        $response->assertJsonPath('data.summary.total', 3)
            ->assertJsonPath('data.invalid.0.index', 1)
            ->assertJsonPath('data.invalid.0.reason', 'NOT_A_NUMBER');

        expect($response->json('data'))->not->toHaveKey('entries');
    });

    it('rejects an over-sized batch instead of silently truncating it', function (): void {
        // A caller that sent 5,000 and got 1,000 back has a bug it cannot see.
        config()->set('laranail.phone.api.max_batch', 2);

        $this->postJson('api/laranail/phone/batch', ['numbers' => ['+254712123456', '+254712123456', '+254712123456']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numbers');
    });

    it('rejects an empty or malformed payload', function (): void {
        $this->postJson('api/laranail/phone/batch', ['numbers' => []])->assertStatus(422);
        $this->postJson('api/laranail/phone/batch', ['numbers' => 'not an array'])->assertStatus(422);
        $this->postJson('api/laranail/phone/analyze', [])->assertJsonValidationErrors('number');
        $this->postJson('api/laranail/phone/analyze', ['number' => '+254712123456', 'country' => 'KEN'])
            ->assertJsonValidationErrors('country');
    });

    it('finds numbers in free text with their offsets', function (): void {
        $this->postJson('api/laranail/phone/scan', [
            'text'    => 'Call me on 0712 123456 or 0733 111222 after six.',
            'country' => 'KE',
        ])
            ->assertOk()
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('data.0.e164', '+254712123456')
            ->assertJsonPath('data.0.offset', 11);
    });

    it('rejects a leniency it does not know', function (): void {
        $this->postJson('api/laranail/phone/scan', ['text' => 'hello', 'leniency' => 'WHATEVER'])
            ->assertJsonValidationErrors('leniency');
    });

    it('serves the numbering plan, and the NANP as a set rather than as US', function (): void {
        $this->getJson('api/laranail/phone/countries?calling_code=1')
            ->assertOk()
            ->assertJsonPath('data.0.calling_code', 1)
            ->assertJsonPath('data.0.nanp', true)
            // Twenty-odd regions share `+1`. A package that answered `US` here would be wrong for
            // every Caribbean number it ever saw.
            ->assertJson(fn ($json) => $json->where('meta.count', fn (int $c): bool => $c > 20)->etc());
    });

    it('leaves examples out of the catalogue unless asked', function (): void {
        // One example number is one metadata load per region, and the full catalogue is 245 of them.
        $this->getJson('api/laranail/phone/countries')
            ->assertOk()
            ->assertJsonMissingPath('data.0.example');

        $this->getJson('api/laranail/phone/countries?calling_code=254&examples=1')
            ->assertOk()
            ->assertJsonPath('data.0.country', 'KE')
            ->assertJsonStructure(['data' => [['example', 'example_national', 'mask', 'placeholder']]]);
    });
});

describe('middleware', function (): void {
    it('adds a throttle to whatever the application configured', function (): void {
        config()->set('laranail.phone.api.middleware', ['api']);
        config()->set('laranail.phone.api.throttle', '60,1');

        expect(ApiRoutes::middleware(config()))->toBe(['api', 'throttle:60,1']);
    });

    it('appends rather than prepends, so authentication runs first', function (): void {
        // Rejecting an unauthenticated request should not consume its rate-limit budget, or an
        // unauthenticated caller could exhaust the bucket for everyone sharing the limiter's key.
        config()->set('laranail.phone.api.middleware', ['api', 'auth:sanctum']);

        expect(ApiRoutes::middleware(config()))->toBe(['api', 'auth:sanctum', 'throttle:60,1']);
    });

    it('leaves a throttle the application wrote down alone', function (): void {
        // Two limiters with different keys give a rate that is neither of the two numbers written
        // down, and the one that was written down is the one that was meant.
        config()->set('laranail.phone.api.middleware', ['api', 'throttle:10,1']);

        expect(ApiRoutes::middleware(config()))->toBe(['api', 'throttle:10,1']);
    });

    it('takes null as a deliberate opt-out', function (): void {
        config()->set('laranail.phone.api.middleware', ['api']);
        config()->set('laranail.phone.api.throttle');

        expect(ApiRoutes::middleware(config()))->toBe(['api']);
    });

    it('actually attaches the middleware to the registered routes', function (): void {
        config()->set('laranail.phone.api.enabled', true);
        config()->set('laranail.phone.api.middleware', ['api', 'auth:sanctum']);

        ApiRoutes::register(config());

        Route::getRoutes()->refreshNameLookups();

        $route = Route::getRoutes()->getByName(ApiRoutes::NAME_PREFIX . 'batch');

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'throttle:60,1');
    });
});

it('honours a custom prefix', function (): void {
    config()->set('laranail.phone.api.enabled', true);
    config()->set('laranail.phone.api.prefix', 'internal/phone');

    ApiRoutes::register(config());

    $this->postJson('internal/phone/analyze', ['number' => '+254712123456'])
        ->assertOk()
        ->assertJsonPath('data.e164', '+254712123456');
});
