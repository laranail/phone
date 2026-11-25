<?php

declare(strict_types=1);

// laranail-phone::phone.* — user-facing strings.
//
// Validation messages live here rather than in laranail/validation so that a project using this
// package without the rule builder still gets translated copy. The rule reads these keys.
return [

    // The number could not be parsed at all — not a shape problem, no digits to work with.
    'invalid' => 'The :attribute field must be a valid phone number.',

    // Parsed, correctly shaped for its country, but the range has not been allocated.
    'not_valid' => 'The :attribute field is not a phone number that is currently in use.',

    // Wrong shape for the country — wrong length, or leading digits that no plan uses.
    'not_possible' => 'The :attribute field is not a possible phone number for :country.',

    // Parsed fine, but belongs to a country the field does not accept.
    'wrong_country' => 'The :attribute field must be a phone number from :country.',

    // Parsed fine, but the wrong kind of line — a landline where a mobile is required.
    'wrong_type' => 'The :attribute field must be a :type number.',

    // A short code or emergency number where a full number is required.
    'short_number' => 'The :attribute field must be a full phone number, not a short code.',
    'emergency_number' => 'The :attribute field must not be an emergency number.',

    // The same number already exists, in any format.
    'unique' => 'This phone number is already registered.',

    // Field affordances.
    'country_code' => 'Country calling code',
    'placeholder' => 'Phone number',
    'select_country' => 'Select a country',
    'search_countries' => 'Search countries',
    'no_countries_found' => 'No countries found',
    'call' => 'Call :number',
    'message_on_whatsapp' => 'Message :number on WhatsApp',

    // Line types, for a badge or a filter.
    'types' => [
        'FIXED_LINE' => 'Landline',
        'MOBILE' => 'Mobile',
        'FIXED_LINE_OR_MOBILE' => 'Landline or mobile',
        'TOLL_FREE' => 'Toll free',
        'PREMIUM_RATE' => 'Premium rate',
        'SHARED_COST' => 'Shared cost',
        'VOIP' => 'VoIP',
        'PERSONAL_NUMBER' => 'Personal number',
        'PAGER' => 'Pager',
        'UAN' => 'Universal access number',
        'VOICEMAIL' => 'Voicemail',
        'EMERGENCY' => 'Emergency',
        'SHORT_CODE' => 'Short code',
        'STANDARD_RATE' => 'Standard rate',
        'UNKNOWN' => 'Unknown',
    ],

];
