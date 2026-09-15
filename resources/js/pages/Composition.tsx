import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useT } from '@/app/hooks';
import type { Composition as CompositionData } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { Hemicycle } from '@/components/Hemicycle';
import { CsvButton } from '@/components/Csv';
import { Band, Card, Processed, SectionHead, Swatch } from '@/components/ui';

export function Composition() {
    const { slug } = useElection();
    const t = useT();
    const file = useSnapshotData<CompositionData>('results', 'composition.json');
    const [filter, setFilter] = useState('');

    return (
        <div>
            <SectionHead title="Sastav Narodne skupštine" right={file.meta?.processed != null && <Processed value={file.meta.processed} />} />
            <WithFile state={file} unavailable={t('Rezultati još nisu objavljeni.')}>
                {({ data: c }) => (
                    <>
                        <div className="grid items-start gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                            <Hemicycle total={c.seats_total} byList={c.by_list} empty={c.seats_empty} />
                            <div>
                                <table className="data stack">
                                    <thead><tr><th>{t('Izborna lista')}</th><th className="num">{t('Mandata')}</th><th className="num">{t('Udeo')}</th><th className="num">{t('Glasova')}</th></tr></thead>
                                    <tbody>
                                        {c.by_list.map((l, i) => (
                                            <tr key={`${l.name}-${i}`}>
                                                <td className="lead"><span className="flex items-start gap-2.5"><span className="mt-1"><Swatch color={l.color} index={i} /></span><span className="min-w-0">{l.list_ids[0] !== undefined ? <Link className="link" to={`/${slug}/liste/${l.list_ids[0]}`}>{t(l.name)}</Link> : t(l.name)}{l.is_minority && <span className="badge badge-blue ml-2 align-middle">{t('manjinska')}</span>}</span></span></td>
                                                <td className="num strong key" data-label={t('Mandata')}>{l.seats}</td>
                                                <td className="num" data-label={t('Udeo mandata')}>{pct(l.seats_pct)}</td>
                                                <td className="num" data-label={t('Glasova')}>{num(l.votes)}</td>
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
                                    <span className="flex flex-wrap items-center gap-4">
                                    <CsvButton
                                        filename={`izabrani-poslanici-${slug}`}
                                        rows={c.seats.filter((x) => !filter || x.list_short_name === filter)}
                                        columns={[
                                            { label: 'Mandat', value: (x) => x.seat_no },
                                            { label: 'Poslanik', value: (x) => x.candidate?.full_name },
                                            { label: 'Godište', value: (x) => x.candidate?.birth_year },
                                            { label: 'Zanimanje', value: (x) => x.candidate?.occupation },
                                            { label: 'Prebivalište', value: (x) => x.candidate?.residence },
                                            { label: 'Lista', value: (x) => x.list_short_name },
                                            { label: 'Mesto na listi', value: (x) => x.candidate?.position },
                                            { label: 'Delilac', value: (x) => x.divisor },
                                            { label: 'Količnik', value: (x) => Math.round(x.quotient) },
                                        ]}
                                    />
                                    <label className="t-data flex items-center gap-2">
                                        <span className="text-ink-2">{t('Lista')}:</span>
                                        <select className="select" value={filter} onChange={(e) => setFilter(e.target.value)}>
                                            <option value="">{t('sve liste')}</option>
                                            {c.by_list.filter((l) => l.seats > 0).map((l, i) => <option key={i} value={l.short_name ?? l.name}>{t(l.short_name ?? l.name)}</option>)}
                                        </select>
                                    </label>
                                    </span>
                                } />
                                <div className="scroll-x">
                                    <table className="data stack min-w-full md:min-w-[640px]">
                                        <thead><tr><th className="num">{t('Mandat')}</th><th>{t('Poslanik')}</th><th>{t('Lista')}</th><th className="num">{t('Mesto na listi')}</th><th className="num">{t('Količnik')}</th></tr></thead>
                                        <tbody>
                                            {c.seats.filter((s) => !filter || s.list_short_name === filter).map((s) => (
                                                <tr key={`${s.unit_code}-${s.seat_no}`}>
                                                    <td className="num drop">{s.seat_no}</td>
                                                    <td className="lead">{s.candidate ? <><span>{t(s.candidate.full_name)}</span><div className="muted font-normal">{[s.candidate.birth_year, t(s.candidate.occupation ?? ''), t(s.candidate.residence ?? '')].filter(Boolean).join(', ')}</div></> : <span className="muted">{t('lista nema dovoljno kandidata')}</span>}</td>
                                                    <td className="num key" data-label={t('Mandat')}>{s.seat_no}</td>
                                                    <td className="wide"><span className="flex items-center gap-2"><Swatch color={s.color} index={s.list_number - 1} />{t(s.list_short_name)}{c.seats.some((x) => x.unit_code !== s.unit_code) && <span className="muted">({s.unit_code})</span>}</span></td>
                                                    <td className="num" data-label={t('Mesto na listi')}>{s.candidate?.position ?? '-'}</td>
                                                    <td className="num" data-label={t('Količnik')}>{num(Math.round(s.quotient))}</td>
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
