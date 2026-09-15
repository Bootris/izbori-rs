<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

/** Prints the number of elections — lets start.sh decide whether to seed demo data. */
class CountElections extends Command
{
    protected $signature = 'izbori:count';

    protected $description = 'Print how many elections exist (used by start.sh)';

    public function handle(): int
    {
        $this->output->write((string) Election::query()->count());

        return self::SUCCESS;
    }
}
