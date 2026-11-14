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
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Self-registration is off unless switched on in .env. When it is on, only
    | email addresses ending in one of the allowed domains can register; leave the
    | list empty to allow any address.
    |
    */

    'auth' => [
        'self_registration' => (bool) env('SELF_REGISTRATION_ENABLED', false),
        'allowed_email_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ALLOWED_EMAIL_DOMAINS', '')),
        ))),
        'max_failed_logins' => 5,
        'lockout_minutes' => 15,
        'password_min_length' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tickets
    |--------------------------------------------------------------------------
    |
    | One attachment per ticket. The type is checked from the file's content, not its name.
    |
    */

    'tickets' => [
        'attachment_max_kb' => 5120,
        'attachment_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
        'attachment_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalation
    |--------------------------------------------------------------------------
    |
    | A ticket that is unresolved past its due time is escalated again every
    | interval until it is resolved. The level cannot pass 255, which is the
    | most the database column can hold.
    |
    */

    'escalation' => [
        'interval_minutes' => 15,
        'max_level' => 255,
        'batch_size' => (int) env('ESCALATION_BATCH_SIZE', 500),
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
