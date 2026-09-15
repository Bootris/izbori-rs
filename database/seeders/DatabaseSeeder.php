<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Bootstraps any deployment: one admin + site settings. Idempotent. */
    public function run(): void
    {
        $admin = config('izbori.admin');

        User::updateOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password'], 'role' => UserRole::Admin],
        );

        foreach ([
            'site_name' => config('app.name'),
            'publisher' => 'Republička izborna komisija',
            'contact_email' => '',
            'methodology_url' => '',
        ] as $key => $value) {
            if (Setting::query()->where('key', $key)->doesntExist()) {
                Setting::set($key, $value);
            }
        }
    }
}
