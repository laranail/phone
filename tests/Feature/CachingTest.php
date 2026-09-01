<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\Http\ApiRoutes;

/*
|--------------------------------------------------------------------------
| Deploy-time caching
|--------------------------------------------------------------------------
|
| `config:cache` and `route:cache` fail at deploy time rather than in
| development, which is the worst moment to discover a closure in a config
| file or a route that cannot be serialised.
|
*/

it('ships a config file with no closures in it', function (): void {
    $config = require dirname(__DIR__, 2).'/config/phone.php';

    $walk = function (array $values) use (&$walk): void {
        foreach ($values as $value) {
            if (is_array($value)) {
                $walk($value);

                continue;
            }

            expect($value)->not->toBeInstanceOf(Closure::class);
        }
    };

    $walk($config);
});

it('registers API routes to controller actions, not closures, so route:cache works', function (): void {
    // A route bound to a closure cannot be serialised, and `route:cache` fails on it — at deploy.
    config()->set('laranail.phone.api.enabled', true);

    ApiRoutes::register(config());

    $routes = array_filter(
        app('router')->getRoutes()->getRoutes(),
        static fn ($route): bool => str_starts_with((string) $route->getName(), ApiRoutes::NAME_PREFIX),
    );

    expect($routes)->toHaveCount(5);

    foreach ($routes as $route) {
        expect($route->getActionName())->not->toBe('Closure');
    }
});
