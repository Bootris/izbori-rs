import { Link, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, MunicipalityResults, StationProtocol } from '@/types';
import { num, pct, plural } from '@/lib/format';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { DistrictAccordion, MunicipalityTable } from '@/components/DistrictAccordion';
import { Band, Breadcrumbs, Card, Donut, PageTitle, Processed, StatusBadge } from '@/components/ui';

export function Territory() {
    const { slug } = useElection();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    return (
        <div>
            <PageTitle title="Rezultati po teritoriji" meta={districts.meta} sub={t('Okrug, opština, biračko mesto, zapisnik. Otvorite okrug da vidite opštine.')} />
            <Band>
                <Card>
                    <WithFile state={districts} unavailable={t('Registar još nije objavljen.')}>
                        {({ list }) => <DistrictAccordion districts={list} slug={slug} />}
                    </WithFile>
                </Card>
            </Band>
        </div>
    );
}

export function TerritoryDistrict() {
    const { slug } = useElection();
    const { district = '' } = useParams();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const d = districts.list?.find((x) => x.code === district);
    return (
        <div>
            <Breadcrumbs items={[{ label: 'Po teritoriji', to: `/${slug}/teritorija` }, { label: d?.name ?? district }]} />
            <PageTitle title={d?.name ?? district} meta={districts.meta} sub={d && <>{d.municipalities} {t(plural(d.municipalities, ['opština', 'opštine', 'opština']))}, {num(d.stations)} {t('biračkih mesta')}, {num(d.registered_voters)} {t('upisanih birača')}</>} />
            <Band>
                <Card>
                    <WithFile state={districts} unavailable={t('Registar još nije objavljen.')}>
                        {() => d ? <MunicipalityTable district={d} slug={slug} /> : <p className="muted">{t('Okrug nije pronađen.')}</p>}
                    </WithFile>
                </Card>
            </Band>
        </div>
    );
}

export function TerritoryMunicipality() {
    const { slug } = useElection();
    const { district = '', municipality = '' } = useParams();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const results = useSnapshotData<MunicipalityResults>('results', `${district}/results-${district}-${municipality}.json`);
    const protocols = useSnapshotList<StationProtocol>('results', `${district}/protocols-${district}-${municipality}.json`);
    const stations = useSnapshotList<StationProtocol>('registry', `${district}/stations-${district}-${municipality}.json`);
    const districtName = districts.list?.find((x) => x.code === district)?.name ?? district;
    const title = results.item?.name ?? municipality;
    const rows = protocols.list ?? stations.list ?? [];
    const r = results.item;

    return (
        <div>
            <Breadcrumbs items={[{ label: 'Po teritoriji', to: `/${slug}/teritorija` }, { label: districtName, to: `/${slug}/teritorija/${district}` }, { label: title }]} />
            <PageTitle title={title} meta={results.meta ?? protocols.meta ?? stations.meta} sub={<>{t(districtName)}, {num(rows.length)} {t('biračkih mesta')}</>} />
            <Band>
                {results.available && r && (
                    <>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="card-flat md:col-span-1"><Donut value={r.turnout_pct} label="Izlaznost" sub={<>{num(r.voters_voted)} {t('od')} {num(r.registered_voters)}</>} size={72} /></div>
                            <div className="card-flat"><div className="text-sm text-ink-2">{t('Obrađeno biračkih mesta')}</div><div className="mt-1 text-2xl font-bold tabular-nums">{r.stations_verified} / {r.stations_total}</div><div className="mt-2"><Processed value={r.processed} label="Obrađeno" /></div></div>
                            <div className="card-flat"><div className="text-sm text-ink-2">{t('Važećih i nevažećih listića')}</div><div className="mt-1 text-2xl font-bold tabular-nums">{num(r.ballots_valid)}</div><div className="muted mt-1">{num(r.ballots_invalid)} {t('nevažećih')} ({pct(r.invalid_pct)})</div></div>
                        </div>
                        <Card>
                            <h2 className="mb-4">{t('Rezultati glasanja po listama')}</h2>
                            <ListResults rows={r.lists} slug={slug} />
                        </Card>
                    </>
                )}
                <Card>
                    <h2 className="mb-4">{t('Biračka mesta')}</h2>
                    {rows.length === 0 ? <p className="muted">{t('Nema podataka.')}</p> : (
                        <div className="overflow-x-auto">
                            <table className="data min-w-[720px]">
                                <thead><tr><th className="num">{t('BM')}</th><th>{t('Naziv i adresa')}</th><th className="num">{t('Upisano')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th><th className="num">{t('Odstupanje')}</th><th>{t('Status')}</th></tr></thead>
                                <tbody>
                                    {rows.map((s) => (
                                        <tr key={s.station_id}>
                                            <td className="num"><Link className="link" to={`/${slug}/biracko-mesto/${s.station_id}`}>{s.number}</Link></td>
                                            <td>{t(s.name)}{s.address && <div className="muted">{t(s.address)}</div>}{s.is_diaspora && s.country && <div className="muted">{t(s.country)}</div>}</td>
                                            <td className="num">{num(s.registered_voters)}</td>
                                            <td className="num">{num(s.voters_voted)}</td>
                                            <td className="num">{s.turnout_pct === undefined ? '-' : pct(s.turnout_pct)}</td>
                                            <td className={`num ${s.deviation ? 'font-semibold text-bad' : ''}`}>{s.deviation === undefined ? '-' : s.deviation}</td>
                                            <td><StatusBadge status={s.status} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </Band>
        </div>
    );
}
