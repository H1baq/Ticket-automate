<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | First administrator
    |--------------------------------------------------------------------------
    |
    | The AdminSeeder creates this account on a fresh install. The password is
    | read from the environment and never stored in the repository.
    |
    */

    'seed_admin' => [
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Nothing in the code names a specific organization. The name and logo come
    | from here so the product can be rebranded for another customer.
    |
    */

    'organization' => [
        'name' => env('ORGANIZATION_NAME', 'Your organization'),
        'logo' => env('ORGANIZATION_LOGO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lists
    |--------------------------------------------------------------------------
    */

    'per_page' => 20,

];
