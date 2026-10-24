<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default GEDCOM Start Person
    |--------------------------------------------------------------------------
    |
    | The default root individual ID or name used when viewing the family tree
    | if no specific individual is requested.
    |
    */
    'start_person' => env('GEDCOM_START_PERSON') ?:
        env('GEDCOM_ROOT_PERSON') ?:
        env('GEDCOM_DEFAULT_PERSON') ?:
        env('GEDCOM_DEFAULT_PERSON_ID') ?:
        env('GEDCOM_DEFAULT_PERSON_NAME') ?:
        env('GEDCOM_PERSON_ID') ?:
        env('GEDCOM_PERSON_NAME') ?:
        env('GEDCOM_PERSON') ?: '',
];
