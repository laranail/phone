<?php

declare(strict_types=1);

// |--------------------------------------------------------------------------
// | laranail/phone
// |--------------------------------------------------------------------------
// | Resolves under the vendor-namespaced key `config('laranail.phone.*')`.
// |
// | No closures anywhere in this file — a closure in config breaks `config:cache`,
// | and the failure appears at deploy time rather than in development.

return [

    // |----------------------------------------------------------------------
    // | Default country
    // |----------------------------------------------------------------------
    // | The region bare national input is parsed against when no country is
    // | supplied at the call site. Only ever consulted for input that does not
    // | already carry its own calling code, so it cannot corrupt E.164 values.
    // |
    // | Leave null to require an explicit country. That is the safer default for
    // | an international audience: a wrong guess here silently turns one
    // | country's numbers into another's.
    'default_country' => env('PHONE_DEFAULT_COUNTRY'),

    // |----------------------------------------------------------------------
    // | Vanity letters
    // |----------------------------------------------------------------------
    // | Convert `1-800-FLOWERS` into digits on input.
    // |
    // | Off by default, and it should stay off unless vanity numbers are genuinely
    // | expected. Letters in a phone field are not always keypad instructions —
    // | a Nigerian number written `080 ABC 1234` uses them as an initialism, and
    // | converting blindly corrupts it.
    'convert_vanity_letters' => env('PHONE_VANITY_LETTERS', false),

    // |----------------------------------------------------------------------
    // | Intel lookups
    // |----------------------------------------------------------------------
    // | Carrier, geographic description and timezone. Each loads its own
    // | prefix-keyed metadata on first use, so leaving this enabled costs nothing
    // | until something actually asks.
    // |
    // | `locale` is the language those answers come back in. Null follows the
    // | application locale, falling back to English where a translation is
    // | missing — libphonenumber ships carrier names in nine languages and
    // | geocoding in about forty.
    'intel' => [
        'enabled' => env('PHONE_INTEL', true),
        'locale' => null,
    ],

    // |----------------------------------------------------------------------
    // | Input masks
    // |----------------------------------------------------------------------
    // | Per-country mask templates, generated from libphonenumber's example
    // | numbers and cached because the derivation reads numbering-plan metadata.
    // |
    // | A mask is only emitted where the plan allows exactly one length; where it
    // | does not — Germany's mobile numbers are 10 *or* 11 digits — the generator
    // | returns null and the field runs unmasked. Setting `ttl` to null caches
    // | forever, which is correct: the answer only changes when libphonenumber
    // | itself is upgraded.
    'masks' => [
        'cache_store' => env('PHONE_MASK_CACHE_STORE'),
        'ttl' => null,
    ],

];
