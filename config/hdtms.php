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

];
