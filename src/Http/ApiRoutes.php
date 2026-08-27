<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Phone\Http\Controllers\PhoneApiController;

/**
 * Registers the HTTP API, if it has been turned on.
 *
 * Kept out of the service provider because the decision it encodes is worth reading on its own: this
 * package adds **no routes at all** unless an application asks for them. A package that publishes
 * endpoints by installing it is a package that changes an application's attack surface as a side
 * effect of `composer require`, and the person who notices is rarely the person who ran it.
 */
final class ApiRoutes
{
    /**
     * The route names, so `route()` works and nothing has to write the URI down twice.
     */
    public const string NAME_PREFIX = 'laranail.phone.api.';

    public static function register(Repository $config): void
    {
        if ($config->get('laranail.phone.api.enabled', false) !== true) {
            return;
        }

        $configured = $config->get('laranail.phone.api.prefix', 'api/laranail/phone');
        $prefix = trim(is_string($configured) ? $configured : 'api/laranail/phone', '/');

        Route::prefix($prefix)
            ->middleware(self::middleware($config))
            ->name(self::NAME_PREFIX)
            ->group(static function (): void {
                Route::post('/analyze', [PhoneApiController::class, 'analyze'])->name('analyze');
                Route::post('/batch', [PhoneApiController::class, 'batch'])->name('batch');
                Route::post('/audit', [PhoneApiController::class, 'audit'])->name('audit');
                Route::post('/scan', [PhoneApiController::class, 'scan'])->name('scan');
                Route::get('/countries', [PhoneApiController::class, 'countries'])->name('countries');
            });
    }

    /**
     * The configured middleware, with a throttle appended unless one is already there.
     *
     * Appended rather than prepended so an application's own authentication runs first — rejecting
     * an unauthenticated request should not consume its rate-limit budget, or an unauthenticated
     * caller could exhaust the bucket for everyone sharing the limiter's key.
     *
     * Checking for an existing `throttle` matters more than it looks: an application that has
     * already put `throttle:10,1` in the list means it, and silently adding a second limiter would
     * give it two buckets with different keys and a rate that is neither of the two numbers written
     * down.
     *
     * @return list<string>
     */
    public static function middleware(Repository $config): array
    {
        /** @var list<string> $middleware */
        $middleware = array_values((array) $config->get('laranail.phone.api.middleware', ['api']));

        $throttle = $config->get('laranail.phone.api.throttle');

        if (! is_string($throttle) || $throttle === '') {
            return $middleware;
        }

        foreach ($middleware as $entry) {
            if (str_starts_with($entry, 'throttle')) {
                return $middleware;
            }
        }

        $middleware[] = "throttle:{$throttle}";

        return $middleware;
    }
}
