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
}

/** Votes per list with proportional bars; sorted by votes, ballot number kept visible. */
export function ListResults({ rows, slug, showSeats = false, thresholdVotes }: Props) {
    const t = useT();
    const sorted = [...rows].sort((a, b) => b.votes - a.votes);
    const max = sorted[0]?.votes ?? 0;
    return (
        <div className="overflow-x-auto">
            <table className="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{t('Izborna lista')}</th>
                        <th className="w-1/3">{t('Glasova')}</th>
                        <th className="num">{t('Broj')}</th>
                        <th className="num">%</th>
                        {showSeats && <th className="num">{t('Mandata')}</th>}
                    </tr>
                </thead>
                <tbody>
                    {sorted.map((r) => (
                        <tr key={r.list_id} className={r.qualified === false ? 'text-zinc-500' : ''}>
                            <td className="num">{r.number}.</td>
                            <td>
                                <div className="flex items-center gap-2">
                                    <Swatch color={r.color} index={r.number - 1} />
                                    <Link className="link no-underline hover:underline" to={`/${slug}/liste/${r.list_id}`}>{t(r.name)}</Link>
                                    {r.is_minority && <span className="badge badge-blue">{t('manjinska')}</span>}
                                    {r.qualified === false && <span className="badge badge-gray">{t('ispod cenzusa')}</span>}
                                </div>
                                {r.holder_name && <div className="muted">{t(r.holder_name)}</div>}
                            </td>
                            <td><Bar value={r.votes} max={max} color={r.color} index={r.number - 1} /></td>
                            <td className="num">{num(r.votes)}</td>
                            <td className="num">{pct(r.votes_pct)}</td>
                            {showSeats && <td className="num font-semibold">{r.seats ?? 0}</td>}
                        </tr>
                    ))}
                </tbody>
            </table>
            {thresholdVotes !== undefined && thresholdVotes > 0 && (
                <p className="muted mt-2">{t('Cenzus')}: {num(thresholdVotes)} {t('glasova (3 % birača koji su glasali). Manjinske liste učestvuju i ispod cenzusa; njihovi količnici se uvećavaju za 35 %.')}</p>
            )}
        </div>
    );
}
