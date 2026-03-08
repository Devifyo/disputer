<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Roles
    |--------------------------------------------------------------------------
    |
    | Defined roles for the system. Using constants/keys helps prevent 
    | typos in your controllers and middleware.
    |
    */

    'admin' => [
        'label' => 'Administrator',
        'name'  => 'admin',
    ],

    'user' => [
        'label' => 'Standard User',
        'name'  => 'user',
    ],
];