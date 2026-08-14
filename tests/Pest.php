<?php

declare(strict_types=1);

use Simtabi\Laranail\Phone\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case bindings
|--------------------------------------------------------------------------
|
| Only Feature tests boot Laravel. Unit tests exercise the parsing, formatting
| and normalisation layer directly with no container — the cheapest proof that
| the layer stayed framework-free.
|
*/

uses(TestCase::class)->in('Feature');
