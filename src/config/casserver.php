<?php
return [
    'route' => [
        'domain' => null,

        'prefix' => 'cas',

        'middleware' => 'web'
    ],

    /**
     * enable cas sso module
     */
    'enable' => false,

    'user' => [
        'model' => App\User::class,

        'table' => 'users',

        'id' => 'id',

        /**
         * sso logout control by cas server
         */
        'cas_slo' => false,

        /**
         * client return from cas
         */
        'user_info' => [
            'email', 'name',
        ],
    ],

    /**
     * ticket effect time, the unit is second
     */
    'ticket_expire' => 60 * 5,

    'verify_ssl' => env('CAS_VERIFY_SSL', false),
];
