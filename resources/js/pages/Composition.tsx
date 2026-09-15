import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useT } from '@/app/hooks';
import type { Composition as CompositionData } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { Hemicycle } from '@/components/Hemicycle';
import { Band, Card, Processed, SectionHead, Swatch, Updated } from '@/components/ui';

export function Composition() {
    const { slug } = useElection();
    const t = useT();
    const file = useSnapshotData<CompositionData>('results', 'composition.json');
    const [filter, setFilter] = useState('');

    return (
        <div>
            {file.meta && <Updated meta={file.meta} />}
            <SectionHead title="Sastav Narodne skupštine" right={file.meta?.processed != null && <Processed value={file.meta.processed} />} />
            <WithFile state={file} unavailable={t('Rezultati još nisu objavljeni.')}>
                {({ data: c }) => (
                    <>
                        <div className="grid items-start gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                            <Hemicycle total={c.seats_total} byList={c.by_list} empty={c.seats_empty} />
                            <div className="overflow-x-auto">
                                <table className="data min-w-[460px]">
                                    <thead><tr><th>{t('Izborna lista')}</th><th className="num">{t('Mandata')}</th><th className="num">{t('Udeo')}</th><th className="num">{t('Glasova')}</th></tr></thead>
                                    <tbody>
                                        {c.by_list.map((l, i) => (
                                            <tr key={`${l.name}-${i}`}>
                                                <td><span className="flex items-center gap-2.5"><Swatch color={l.color} index={i} />{l.list_ids[0] !== undefined ? <Link className="link" to={`/${slug}/liste/${l.list_ids[0]}`}>{t(l.name)}</Link> : t(l.name)}{l.is_minority && <span className="badge badge-blue">{t('manjinska')}</span>}</span></td>
                                                <td className="num strong">{l.seats}</td>
                                                <td className="num">{pct(l.seats_pct)}</td>
                                                <td className="num">{num(l.votes)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <p className="muted mt-2">{num(c.seats_allocated)} {t('od')} {num(c.seats_total)} {t('mandata raspodeljeno')}{c.seats_empty > 0 && <>, {num(c.seats_empty)} {t('nepopunjeno')}</>}.</p>
                            </div>
                        </div>

                        <Band className="mt-10">
                            <Card>
                                <SectionHead title="Izabrani poslanici" right={
                                    <label className="flex items-center gap-2 text-sm">
                                        <span className="text-ink-2">{t('Lista')}:</span>
                                        <select className="select" value={filter} onChange={(e) => setFilter(e.target.value)}>
                                            <option value="">{t('sve liste')}</option>
                                            {c.by_list.filter((l) => l.seats > 0).map((l, i) => <option key={i} value={l.short_name ?? l.name}>{t(l.short_name ?? l.name)}</option>)}
                                        </select>
                                    </label>
                                } />
                                <div className="overflow-x-auto">
                                    <table className="data min-w-[640px]">
                                        <thead><tr><th className="num">{t('Mandat')}</th><th>{t('Poslanik')}</th><th>{t('Lista')}</th><th className="num">{t('Mesto na listi')}</th><th className="num">{t('Količnik')}</th></tr></thead>
                                        <tbody>
                                            {c.seats.filter((s) => !filter || s.list_short_name === filter).map((s) => (
                                                <tr key={`${s.unit_code}-${s.seat_no}`}>
                                                    <td className="num">{s.seat_no}</td>
                                                    <td>{s.candidate ? <><span className="font-medium">{t(s.candidate.full_name)}</span><div className="muted">{[s.candidate.birth_year, t(s.candidate.occupation ?? ''), t(s.candidate.residence ?? '')].filter(Boolean).join(', ')}</div></> : <span className="muted">{t('lista nema dovoljno kandidata')}</span>}</td>
                                                    <td><span className="flex items-center gap-2"><Swatch color={s.color} index={s.list_number - 1} />{t(s.list_short_name)}{c.seats.some((x) => x.unit_code !== s.unit_code) && <span className="muted">({s.unit_code})</span>}</span></td>
                                                    <td className="num">{s.candidate?.position ?? '-'}</td>
                                                    <td className="num">{num(Math.round(s.quotient))}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Card>
                        </Band>
                    </>
                )}
            </WithFile>
        </div>
    );
}
