import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useT } from '@/app/hooks';
import type { Composition as CompositionData } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { Hemicycle } from '@/components/Hemicycle';
import { PageTitle, Swatch } from '@/components/ui';

export function Composition() {
    const { slug } = useElection();
    const t = useT();
    const file = useSnapshotData<CompositionData>('results', 'composition.json');

    return (
        <div className="space-y-6">
            <PageTitle title="Sastav skupštine" meta={file.meta} />
            <WithFile state={file} unavailable={t('Rezultati još nisu objavljeni.')}>
                {({ data: c }) => (
                    <>
                        <div className="card">
                            <Hemicycle total={c.seats_total} byList={c.by_list} empty={c.seats_empty} />
                            <p className="muted text-center">
                                {num(c.seats_allocated)} / {num(c.seats_total)} {t('mandata raspodeljeno')}{c.seats_empty > 0 && <> · {num(c.seats_empty)} {t('nepopunjeno')}</>}
                            </p>
                        </div>
                        <div className="card overflow-x-auto">
                            <table className="data">
                                <thead><tr><th>{t('Lista')}</th><th className="num">{t('Mandata')}</th><th className="num">%</th><th className="num">{t('Glasova')}</th></tr></thead>
                                <tbody>
                                    {c.by_list.map((l, i) => (
                                        <tr key={`${l.name}-${i}`}>
                                            <td><span className="flex items-center gap-2"><Swatch color={l.color} index={i} />{l.list_ids[0] !== undefined ? <Link className="link" to={`/${slug}/liste/${l.list_ids[0]}`}>{t(l.name)}</Link> : t(l.name)}{l.is_minority && <span className="badge badge-blue">{t('manjinska')}</span>}</span></td>
                                            <td className="num font-semibold">{l.seats}</td>
                                            <td className="num">{pct(l.seats_pct)}</td>
                                            <td className="num">{num(l.votes)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="card overflow-x-auto">
                            <h2 className="mb-3">{t('Izabrani kandidati')}</h2>
                            <table className="data">
                                <thead><tr><th className="num">#</th><th>{t('Kandidat')}</th><th>{t('Lista')}</th><th className="num">{t('Pozicija')}</th><th className="num">{t('Količnik')}</th></tr></thead>
                                <tbody>
                                    {c.seats.map((s) => (
                                        <tr key={`${s.unit_code}-${s.seat_no}`}>
                                            <td className="num">{s.seat_no}</td>
                                            <td>{s.candidate ? <>{t(s.candidate.full_name)}<div className="muted">{[s.candidate.birth_year, t(s.candidate.occupation ?? ''), t(s.candidate.residence ?? '')].filter(Boolean).join(' · ')}</div></> : <span className="muted">{t('lista nema dovoljno kandidata')}</span>}</td>
                                            <td><span className="flex items-center gap-2"><Swatch color={s.color} index={s.list_number - 1} />{t(s.list_short_name)}{c.seats.some((x) => x.unit_code !== s.unit_code) && <span className="muted">({s.unit_code})</span>}</span></td>
                                            <td className="num">{s.candidate?.position ?? '—'}</td>
                                            <td className="num">{num(Math.round(s.quotient))}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </WithFile>
        </div>
    );
}
