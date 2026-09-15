<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Election;
use App\Models\Municipality;
use App\Models\PollingStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Registry import from CSV (the ~8,200-row national file). Idempotent: rows are
 * upserted on (election, municipality, number). Expected header:
 *
 *   district_code,district_name,municipality_code,municipality_name,number,name,
 *   address,registered_voters,accessible,is_diaspora,country,lat,lng
 */
class ImportPollingStations extends Command
{
    protected $signature = 'izbori:import-stations {election : Election slug} {file : Path to CSV}';

    protected $description = 'Import districts, municipalities and polling stations from a CSV file';

    private const REQUIRED = ['district_code', 'district_name', 'municipality_code', 'municipality_name', 'number', 'name', 'registered_voters'];

    public function handle(): int
    {
        $election = Election::query()->where('slug', $this->argument('election'))->first();
        if ($election === null) {
            $this->error("Nepoznat izbor: {$this->argument('election')}");

            return self::FAILURE;
        }

        $path = (string) $this->argument('file');
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            $this->error("Ne mogu da otvorim fajl: {$path}");

            return self::FAILURE;
        }

        $header = array_map('trim', (array) fgetcsv($handle));
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing !== []) {
            $this->error('Nedostaju kolone: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $count = 0;
        DB::transaction(function () use ($handle, $header, $election, &$count) {
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $r = array_combine($header, array_map('trim', $row));

                $district = District::firstOrCreate(['code' => $r['district_code']], ['name' => $r['district_name']]);
                $municipality = Municipality::firstOrCreate(
                    ['code' => $r['municipality_code']],
                    ['district_id' => $district->id, 'name' => $r['municipality_name']],
                );

                PollingStation::updateOrCreate(
                    ['election_id' => $election->id, 'municipality_id' => $municipality->id, 'number' => $r['number']],
                    [
                        'name' => $r['name'],
                        'address' => $r['address'] ?? null,
                        'registered_voters' => (int) $r['registered_voters'],
                        'accessible' => filter_var($r['accessible'] ?? false, FILTER_VALIDATE_BOOL),
                        'is_diaspora' => filter_var($r['is_diaspora'] ?? false, FILTER_VALIDATE_BOOL),
                        'country' => ($r['country'] ?? '') ?: null,
                        'lat' => ($r['lat'] ?? '') !== '' ? (float) $r['lat'] : null,
                        'lng' => ($r['lng'] ?? '') !== '' ? (float) $r['lng'] : null,
                    ],
                );
                $count++;
            }
        });
        fclose($handle);

        $this->info("Uvezeno/ažurirano biračkih mesta: {$count}");

        return self::SUCCESS;
    }
}
