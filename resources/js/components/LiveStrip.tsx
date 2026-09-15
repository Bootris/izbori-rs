import { useElection } from '@/app/election-context';
import { useSnapshotData, useT } from '@/app/hooks';
import type { ResultsSummary } from '@/types';
import { dateTime, num, pct } from '@/lib/format';

/**
 * Election-night strip: how much of the count is in, and when the page last
 * saw a new publication. Sticks to the top, because that is the number people
 * re-check all evening.
 */
export function LiveStrip() {
    const { election, config } = useElection();
    const t = useT();
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const d = summary.item;
    if (!d) return null;

    const counting = election.status === 'counting';

    return (
        <div className="livestrip">
            <div className="container-x flex flex-wrap items-center gap-x-5 gap-y-1.5 py-2 text-sm">
                <span className="flex items-center gap-2 font-semibold">
                    {counting && <span className="pulse-dot" aria-hidden="true" />}
                    {t('Obrađeno')}: <span className="tabular-nums">{pct(d.processed)}</span>
                </span>
                <span className="hidden h-2 w-32 overflow-hidden rounded-full bg-track sm:block" aria-hidden="true">
                    <span className="block h-full rounded-full bg-primary" style={{ width: `${Math.min(100, d.processed)}%` }} />
                </span>
                <span className="muted">{num(d.stations_verified)} {t('od')} {num(d.stations_total)} {t('biračkih mesta')}</span>
                <span className="muted ml-auto">
                    {t('Ažurirano')}: {dateTime(config.updated)}
                    {counting && <> · {t('osvežava se automatski')}</>}
                </span>
            </div>
        </div>
    );
}
