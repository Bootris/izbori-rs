<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\District;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

/** Full registry of districts and municipalities (data/territory.php). Idempotent, keyed by code. */
final class TerritorySeeder extends Seeder
{
    public function run(): void
    {
        $territory = require database_path('seeders/data/territory.php');

        $districtSort = 0;
        foreach ($territory as $code => $district) {
            $model = District::query()->updateOrCreate(
                ['code' => (string) $code],
                ['name' => $district['name'], 'sort_order' => $districtSort++],
            );

            $sort = 0;
            foreach ($district['municipalities'] as $municipalityCode => [$name]) {
                Municipality::query()->updateOrCreate(
                    ['code' => (string) $municipalityCode],
                    ['district_id' => $model->id, 'name' => $name, 'sort_order' => $sort++],
                );
            }
        }
    }
}
