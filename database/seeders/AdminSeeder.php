<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    private const MINIMUM_PASSWORD_LENGTH = 8;

    public function run(): void
    {
        $email = config('hdtms.seed_admin.email');
        $password = config('hdtms.seed_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD in .env before seeding.');
        }

        if (strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must be at least 8 characters long.');
        }

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $admin = new User([
            'name' => 'System Administrator',
            'email' => $email,
            'password' => $password,
            'department' => 'ICT',
        ]);
        $admin->role = UserRole::Admin;
        $admin->is_active = true;
        $admin->save();
    }
}
