<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Existing categories are left alone so edits made by an administrator survive a re-seed.
     */
    public function run(): void
    {
        $categories = [
            'Hardware' => 'Computers, laptops, monitors, keyboards and other equipment.',
            'Software' => 'Installing, updating or fixing applications.',
            'Network' => 'Wi-Fi, internet access, network points and VPN.',
            'Email and Accounts' => 'Email, passwords and logins to systems.',
            'Printing' => 'Printers, scanners and photocopiers.',
            'Other' => 'Anything that does not fit the categories above.',
        ];

        foreach ($categories as $name => $description) {
            Category::query()->firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true],
            );
        }
    }
}
