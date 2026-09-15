<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ElectionStatus;
use App\Enums\SnapshotSource;
use App\Models\Election;
use App\Services\Results\ResultsAggregator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Election-night glance: how much is counted, what is stuck, what is live. */
class ElectionOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Whatever is being counted right now comes first, then whatever is voting, then the newest.
        $election = Election::query()
            ->where('status', '!=', ElectionStatus::Draft)
            ->orderByRaw('case when status = ? then 0 when status = ? then 1 else 2 end', [ElectionStatus::Counting->value, ElectionStatus::Voting->value])
            ->orderByDesc('election_date')
            ->first();

        if ($election === null) {
            return [Stat::make('Izbori', 'Nema aktivnih izbora')->description('Kreiraj izbore i promeni status iz „Priprema".')];
        }

        $totals = app(ResultsAggregator::class)->forElection($election, $election->round);
        $latest = $election->snapshots()->where('source', SnapshotSource::Results)->latest('id')->first();

        return [
            Stat::make('Verifikovano biračkih mesta', "{$totals['stations_verified']} / {$totals['stations_total']}")
                ->description("{$totals['processed']} % obrađeno · {$election->name}")
                ->color('success'),
            Stat::make('Uneto, čeka verifikaciju', (string) $totals['stations_entered'])
                ->color('gray'),
            Stat::make('Sa odstupanjem', (string) $totals['stations_flagged'])
                ->description('ne ulaze u zbir dok OIK ne ispravi')
                ->color($totals['stations_flagged'] > 0 ? 'danger' : 'success'),
            Stat::make('Poslednja objava rezultata', $latest?->version ?? '—')
                ->description($latest ? $latest->generated_at->diffForHumans() : 'još nije objavljeno')
                ->color('primary'),
        ];
    }
}
