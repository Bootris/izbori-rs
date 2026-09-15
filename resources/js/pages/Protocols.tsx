import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { FlaggedStation, Municipality, StationProtocol } from '@/types';
import { num, pct, searchKey } from '@/lib/format';
import { WithFile } from '@/components/state';
import { Band, Card, PageTitle, StatusBadge } from '@/components/ui';

export function Protocols() {
    const { slug } = useElection();
    const t = useT();
    const flagged = useSnapshotList<FlaggedStation>('results', 'flagged.json');
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    const [code, setCode] = useState('');
    const [query, setQuery] = useState('');
    const muni = municipalities.list?.find((m) => m.code === code);
    const protocols = useSnapshotList<StationProtocol>('results', muni ? `${muni.district_code}/protocols-${muni.district_code}-${muni.code}.json` : '', { skip: !muni });
    const q = searchKey(query.trim());
    const rows = (protocols.list ?? []).filter((s) => !q || searchKey(s.number).includes(q) || searchKey(s.name).includes(q));

    return (
        <div>
            <PageTitle title="Zapisnici biračkih odbora" meta={flagged.meta} sub={t('Svaki objavljeni broj potiče iz zapisnika biračkog odbora. Zapisnici sa odstupanjem su vidljivi, ali ne ulaze u zbir dok ih izborna komisija ne ispravi.')} />
            <Band>
                <Card>
                    <h2 className="mb-4">{t('Pretraga zapisnika')}</h2>
                    <div className="flex flex-wrap gap-3">
                        <select className="select" value={code} onChange={(e) => setCode(e.target.value)}>
                            <option value="">{t('Izaberite opštinu')}</option>
                            {municipalities.list?.filter((m) => m.stations > 0).map((m) => <option key={m.code} value={m.code}>{t(m.name)}</option>)}
                        </select>
                        <input className="input max-w-xs" placeholder={t('Broj ili naziv biračkog mesta')} value={query} onChange={(e) => setQuery(e.target.value)} />
                    </div>
                    {muni && (
                        <div className="mt-4 overflow-x-auto">
                            <WithFile state={protocols} unavailable={t('Rezultati još nisu objavljeni.')}>
                                {() => (
                                    <table className="data min-w-[640px]">
                                        <thead><tr><th className="num">{t('BM')}</th><th>{t('Naziv')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th><th className="num">{t('Odstupanje')}</th><th>{t('Status')}</th><th>{t('Sken')}</th></tr></thead>
                                        <tbody>
                                            {rows.map((s) => (
                                                <tr key={s.station_id}>
                                                    <td className="num"><Link className="link" to={`/${slug}/biracko-mesto/${s.station_id}`}>{s.number}</Link></td>
                                                    <td>{t(s.name)}</td>
                                                    <td className="num">{num(s.voters_voted)}</td>
                                                    <td className="num">{s.turnout_pct === undefined ? '-' : pct(s.turnout_pct)}</td>
                                                    <td className={`num ${s.deviation ? 'font-semibold text-bad' : ''}`}>{s.deviation ?? '-'}</td>
                                                    <td><StatusBadge status={s.status} /></td>
                                                    <td>{(s.scans?.length ?? 0) > 0 ? <Link className="link" to={`/${slug}/biracko-mesto/${s.station_id}`}>{t('sken')}</Link> : ''}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </WithFile>
                        </div>
                    )}
                </Card>

                <Card>
                    <h2 className="mb-4">{t('Zapisnici sa odstupanjem')}</h2>
                    <WithFile state={flagged} unavailable={t('Rezultati još nisu objavljeni.')}>
                        {({ list }) => list.length === 0 ? <p className="muted">{t('Trenutno nema zapisnika sa odstupanjem.')}</p> : (
                            <div className="overflow-x-auto">
                                <table className="data min-w-[640px]">
                                    <thead><tr><th>{t('Biračko mesto')}</th><th>{t('Opština')}</th><th className="num">{t('Odstupanje')}</th><th>{t('Greške')}</th><th className="num">{t('Revizija')}</th></tr></thead>
                                    <tbody>
                                        {list.map((f) => (
                                            <tr key={f.station_id}>
                                                <td><Link className="link" to={`/${slug}/biracko-mesto/${f.station_id}`}>{f.station_id.split('-').pop()} {t(f.station_name)}</Link></td>
                                                <td>{t(f.municipality_name)}</td>
                                                <td className="num font-semibold text-bad">{f.deviation}</td>
                                                <td className="text-xs">{Object.entries(f.errors).map(([k, v]) => <div key={k}><b>{k}</b> {t(v)}</div>)}</td>
                                                <td className="num">{f.revision}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </WithFile>
                </Card>
            </Band>
        </div>
    );
}
