<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fortify
|--------------------------------------------------------------------------
|
| HDTMS uses Fortify's sign-in pipeline, sign-out and password reset with its
| own Blade views and its own routes (routes/auth.php), so every route can carry
| our rate limits and middleware. Two-factor, passkeys, email verification and
| profile features are not used.
|
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/dashboard',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
    ],

    'views' => true,

    'features' => [],

];
