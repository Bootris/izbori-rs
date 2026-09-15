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
                <p className="t-label mb-3 text-ink-2">
                    {showSeats && seatsTotal != null && <>{t('Broj mandata')}: <b className="text-ink">{num(seatsTotal)}</b>. </>}
                    {thresholdVotes !== undefined && thresholdVotes > 0 && <>{t('Cenzus')}: <b className="text-ink">{num(thresholdVotes)}</b> {t('glasova (3 % birača koji su glasali). Liste nacionalnih manjina učestvuju u raspodeli i ispod cenzusa.')}</>}
                </p>
            )}
            <table className="data stack">
                <thead>
                    <tr>
                        <th>{t('Izborna lista')}</th>
                        {showSeats && <th className="num">{t('Mandata')}</th>}
                        <th className="num">{t('Glasova')}</th>
                        <th className="num">{t('Udeo')}</th>
                        <th className="w-[22%]"><span className="sr-only">{t('Udeo')}</span></th>
                    </tr>
                </thead>
                <tbody>
                    {sorted.map((r) => (
                        <tr key={r.list_id} className={r.qualified === false ? 'text-ink-3' : ''}>
                            <td className="lead">
                                <div className="flex items-start gap-2.5">
                                    <span className="mt-1"><Swatch color={r.color} index={r.number - 1} /></span>
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <Link className="link" to={`/${slug}/liste/${r.list_id}`}>{r.number}. {t(r.name)}</Link>
                                            {r.is_minority && <span className="badge badge-blue">{t('manjinska lista')}</span>}
                                            {r.qualified === false && <span className="badge badge-gray">{t('ispod cenzusa')}</span>}
                                        </div>
                                        {r.holder_name && <div className="muted font-normal">{t(r.holder_name)}</div>}
                                    </div>
                                </div>
                            </td>
                            {showSeats && <td className="num strong key" data-label={t('Mandata')}>{r.seats ?? 0}</td>}
                            <td className="num" data-label={t('Glasova')}>{num(r.votes)}</td>
                            <td className={`num font-semibold ${showSeats ? '' : 'key'}`} data-label={t('Udeo')}>{pct(r.votes_pct)}</td>
                            <td className="wide"><Bar value={r.votes} max={max} color={r.color} index={r.number - 1} /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
