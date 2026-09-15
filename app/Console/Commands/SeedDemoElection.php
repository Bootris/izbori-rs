<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Election;
use Database\Seeders\DemoElectionSeeder;
use Illuminate\Console\Command;

class SeedDemoElection extends Command
{
    protected $signature = 'izbori:demo {--publish : Publish registry, turnout and results after seeding}';

    protected $description = 'Seed a fictional parliamentary election (districts, stations, lists, protocols) for local development';

    public function handle(): int
    {
        if (Election::query()->where('slug', DemoElectionSeeder::SLUG)->exists()) {
            $this->warn('Demo izbor već postoji ('.DemoElectionSeeder::SLUG.'). Za ponovno seed-ovanje pokreni migrate:fresh --seed pa ovu komandu.');

            return self::FAILURE;
        }

        $this->info('Seed-ujem demo izbor…');
        (new DemoElectionSeeder())->setCommand($this)->run();

        if ($this->option('publish')) {
            return $this->call('izbori:publish', ['election' => DemoElectionSeeder::SLUG, '--all' => true]);
        }

        return self::SUCCESS;
    }
}
