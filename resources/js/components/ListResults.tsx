import { Link } from 'react-router-dom';
import type { ListRow, UnitListRow } from '@/types';
import { useT } from '@/app/hooks';
import { num, pct } from '@/lib/format';
import { Bar, Swatch } from './ui';

type Row = ListRow & Partial<Pick<UnitListRow, 'seats' | 'qualified'>>;

interface Props {
    rows: Row[];
    slug: string;
    showSeats?: boolean;
    thresholdVotes?: number;
    seatsTotal?: number | null;
}

/** Votes per list, sorted by votes: name and holder, mandates, share with the count, proportional bar. */
export function ListResults({ rows, slug, showSeats = false, thresholdVotes, seatsTotal }: Props) {
    const t = useT();
    const sorted = [...rows].sort((a, b) => b.votes - a.votes);
    const max = sorted[0]?.votes ?? 0;
    return (
        <div>
            {(showSeats || thresholdVotes) && (
                <p className="mb-3 text-sm text-ink-2">
                    {showSeats && seatsTotal != null && <>{t('Broj mandata')}: <b className="text-ink">{num(seatsTotal)}</b>. </>}
                    {thresholdVotes !== undefined && thresholdVotes > 0 && <>{t('Cenzus')}: <b className="text-ink">{num(thresholdVotes)}</b> {t('glasova (3 % birača koji su glasali). Liste nacionalnih manjina učestvuju u raspodeli i ispod cenzusa.')}</>}
                </p>
            )}
            <div className="overflow-x-auto">
                <table className="data md:min-w-[640px]">
                    <thead>
                        <tr>
                            <th>{t('Izborna lista')}</th>
                            {showSeats && <th className="num">{t('Mandata')}</th>}
                            <th className="num">{t('Glasova')}</th>
                            <th className="hidden w-[26%] md:table-cell"><span className="sr-only">{t('Udeo')}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((r) => (
                            <tr key={r.list_id} className={r.qualified === false ? 'text-ink-3' : ''}>
                                <td>
                                    <div className="flex items-start gap-2.5">
                                        <span className="mt-1"><Swatch color={r.color} index={r.number - 1} /></span>
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <Link className="link" to={`/${slug}/liste/${r.list_id}`}>{r.number}. {t(r.name)}</Link>
                                                {r.is_minority && <span className="badge badge-blue">{t('manjinska lista')}</span>}
                                                {r.qualified === false && <span className="badge badge-gray">{t('ispod cenzusa')}</span>}
                                            </div>
                                            {r.holder_name && <div className="muted">{t(r.holder_name)}</div>}
                                        </div>
                                    </div>
                                </td>
                                {showSeats && <td className="num strong">{r.seats ?? 0}</td>}
                                <td className="num !whitespace-normal md:!whitespace-nowrap"><b>{pct(r.votes_pct)}</b> <span className="block text-ink-3 md:inline">({num(r.votes)})</span></td>
                                <td className="hidden md:table-cell"><Bar value={r.votes} max={max} color={r.color} index={r.number - 1} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
