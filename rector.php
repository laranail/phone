<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

/**
 * Pinned to the **php84** set, matching this package's `^8.4.1 || ^8.5` floor — the same choice
 * `laranail/atlas` makes, and for the same reason: the 8.5 set would rewrite code into syntax that
 * parses on the newer CI leg and fails on the older one, which is the quietest way to break a
 * supported version.
 */
return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withSkip([
        __DIR__.'/vendor',

        // Rewrites `$x === null` into `! $x instanceof \Fully\Qualified\Name`, inlining an FQCN into
        // a condition that was already clear. For a `?self` parameter the null check *is* the
        // intent, and the instanceof form reads as a type guard that is not what the code means.
        FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(removeUnusedImports: true);
