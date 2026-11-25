<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Phone\Tests;

use Simtabi\Laranail\Atlas\Providers\AtlasServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;
use Simtabi\Laranail\Phone\Providers\PhoneServiceProvider;

/**
 * Base case for tests that need a booted Laravel application.
 *
 * Unit tests under `tests/Unit` deliberately do **not** use this. Parsing, formatting and
 * normalisation are container-free by design, and exercising them without an application is the
 * cheapest proof that they stayed that way — a stray `config()` or `app()` call fails the run.
 */
abstract class TestCase extends IsolatedTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AtlasServiceProvider::class,
            PhoneServiceProvider::class,
        ];
    }
}
