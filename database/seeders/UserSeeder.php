<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One OIK verifier and one data-entry operator per municipality that has
 * voters (oik.{opština}@example.com / operater.{opština}@example.com,
 * password "password"). The RIK admin comes from DatabaseSeeder.
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $territory = require database_path('seeders/data/territory.php');
        $municipalityIds = Municipality::query()->pluck('id', 'code');
        $password = Hash::make('password');
        $now = now();

        $rows = [];
        foreach ($territory as $district) {
            foreach ($district['municipalities'] as $code => [$name, $voters]) {
                $municipalityId = $municipalityIds[(string) $code] ?? null;
                if ($voters <= 0 || $municipalityId === null) {
                    continue;
                }

                $slug = Str::slug($name);
                foreach ([
                    [UserRole::Verifier, "OIK {$name}", "oik.{$slug}@example.com"],
                    [UserRole::Operator, "Operater {$name}", "operater.{$slug}@example.com"],
                ] as [$role, $displayName, $email]) {
                    $rows[] = [
                        'name' => $displayName,
                        'email' => $email,
                        'email_verified_at' => $now,
                        'password' => $password,
                        'role' => $role->value,
                        'municipality_id' => $municipalityId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('users')->upsert($chunk, ['email'], ['name', 'role', 'municipality_id']);
        }
    }
}
