<?php

return [

    /*
     * By default, the package will only preview mail in a non-production environment.
     */
    'enabled' => env('MAIL_PREVIEW_ENABLED', env('APP_DEBUG', true)),

    /*
     * All generated previews will be stored in this directory.
     */
    'storage_path' => storage_path('email-previews'),

    /*
     * Maximum age in minutes of preview files before they get deleted.
     */
    'maximum_lifetime_in_minutes' => 60,

    /*
     * The URL path where email previews can be viewed.
     */
    'prefix' => 'spatie-mail-preview',

    /*
     * The middleware applied to the mail preview routes.
     */
    'middleware' => ['web'],

];
