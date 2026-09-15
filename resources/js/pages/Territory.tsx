import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, DistrictResults, ListRow, MunicipalityResults, StationProtocol } from '@/types';
import { listColor, num, pct, plural, shortName } from '@/lib/format';
import { leaderOf } from '@/lib/results';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { MunicipalityTable } from '@/components/DistrictAccordion';
import { DistrictMap, type MapEntry } from '@/components/DistrictMap';
import { CsvButton } from '@/components/Csv';
import { Band, Breadcrumbs, Card, Donut, PageTitle, Processed, RingIndicator, SectionHead, Segmented, StationTags, StatusBadge, Swatch } from '@/components/ui';

type Metric = 'lista' | 'izlaznost' | 'obradjeno';

export function Territory() {
    const { slug } = useElection();
    const t = useT();
    const navigate = useNavigate();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const results = useSnapshotList<DistrictResults>('results', 'results-districts.json');
    const [metric, setMetric] = useState<Metric>('lista');

    const byCode = new Map((results.list ?? []).map((d) => [String(d.district_code), d]));
    const names: Record<string, string> = {};
    const entries: Record<string, MapEntry> = {};
    const leaders = new Map<string, ListRow>();

    for (const d of districts.list ?? []) {
        names[d.code] = d.name;
        const r = byCode.get(d.code);
        const leader = r ? leaderOf(r.lists) : undefined;
        if (leader) leaders.set(d.code, leader);
        if (!r || d.stations === 0) {
            entries[d.code] = { value: null };
            continue;
        }
        entries[d.code] = metric === 'izlaznost'
            ? { value: r.turnout_pct }
            : metric === 'obradjeno'
                ? { value: r.processed }
                : { value: leader?.votes_pct ?? null, color: leader ? listColor(leader.color, leader.number - 1) : null, note: leader ? shortName(leader.name, leader.short_name) : undefined };
    }

    const legend = metric !== 'lista' ? undefined : [...new Map([...leaders.values()].map((l) => [l.list_id, l])).values()]
        .sort((a, b) => b.votes - a.votes)
        .map((l) => ({ label: shortName(l.name, l.short_name), color: listColor(l.color, l.number - 1) }));

    const metricLabel = metric === 'izlaznost' ? 'Izlaznost' : metric === 'obradjeno' ? 'Obrađeno biračkih mesta' : 'Udeo vodeće liste';

    return (
        <div>
            <PageTitle title="Rezultati po teritoriji" meta={results.meta ?? districts.meta} sub={t('Okrug, opština, biračko mesto, zapisnik. Kliknite okrug na mapi ili u tabeli.')} />
            <div className="mb-5"><Segmented options={[{ value: 'lista' as Metric, label: 'Vodeća lista' }, { value: 'izlaznost' as Metric, label: 'Izlaznost' }, { value: 'obradjeno' as Metric, label: 'Obrađenost' }]} value={metric} onChange={setMetric} label="Prikaz na mapi" /></div>
            <Band>
                <Card>
                    <SectionHead title={metricLabel} right={results.meta?.processed != null && <Processed value={results.meta.processed} />} />
                    <DistrictMap entries={entries} names={names} valueLabel={metricLabel} legend={legend} onSelect={(code) => navigate(`/${slug}/teritorija/${code}`)} />
                    <p className="muted mt-2 text-center">{t('Okruzi bez biračkih mesta u ovom skupu podataka su sivi.')} {t('Granice')}: geoBoundaries (ODbL).</p>
                </Card>

                <Card>
                    <SectionHead title="Okruzi" right={
                        <CsvButton
                            filename={`okruzi-${slug}`}
                            rows={districts.list ?? []}
                            columns={[
                                { label: 'Šifra', value: (d) => d.code },
                                { label: 'Okrug', value: (d) => d.name },
                                { label: 'Opština', value: (d) => d.municipalities },
                                { label: 'Biračkih mesta', value: (d) => d.stations },
                                { label: 'Upisanih birača', value: (d) => d.registered_voters },
                                { label: 'Obrađeno %', value: (d) => byCode.get(d.code)?.processed ?? '' },
                                { label: 'Izlaznost %', value: (d) => byCode.get(d.code)?.turnout_pct ?? '' },
                                { label: 'Vodeća lista', value: (d) => leaders.get(d.code)?.name ?? '' },
                                { label: 'Udeo vodeće liste %', value: (d) => leaders.get(d.code)?.votes_pct ?? '' },
                            ]}
                        />
                    } />
                    <WithFile state={districts} unavailable={t('Registar još nije objavljen.')}>
                        {({ list }) => (
                            <div className="overflow-x-auto">
                                <table className="data min-w-[720px]">
                                    <thead><tr><th>{t('Okrug')}</th><th className="num">{t('Opština')}</th><th className="num">{t('Biračkih mesta')}</th><th className="num">{t('Upisanih birača')}</th><th className="num">{t('Obrađeno')}</th><th className="num">{t('Izlaznost')}</th><th>{t('Vodeća lista')}</th></tr></thead>
                                    <tbody>
                                        {list.map((d) => {
                                            const r = byCode.get(d.code);
                                            const leader = leaders.get(d.code);
                                            return (
                                                <tr key={d.code}>
                                                    <td><Link className="link" to={`/${slug}/teritorija/${d.code}`}>{t(d.name)}</Link></td>
                                                    <td className="num">{d.municipalities}</td>
                                                    <td className="num">{num(d.stations)}</td>
                                                    <td className="num">{num(d.registered_voters)}</td>
                                                    <td className="num">{r ? <span className="inline-flex items-center gap-1.5">{pct(r.processed)}<RingIndicator value={r.processed} size={16} /></span> : '-'}</td>
                                                    <td className="num">{r ? pct(r.turnout_pct) : '-'}</td>
                                                    <td>{leader ? <span className="inline-flex items-center gap-2"><Swatch color={leader.color} index={leader.number - 1} />{t(shortName(leader.name, leader.short_name))} <span className="text-ink-3">{pct(leader.votes_pct)}</span></span> : <span className="muted">{d.stations === 0 ? t('nema biračkih mesta') : t('nema obrađenih zapisnika')}</span>}</td>
                                                </tr>
                                            );
                                        })}
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

export function TerritoryDistrict() {
    const { slug } = useElection();
    const { district = '' } = useParams();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const results = useSnapshotList<DistrictResults>('results', 'results-districts.json');
    const d = districts.list?.find((x) => x.code === district);
    const r = results.list?.find((x) => String(x.district_code) === district);

    return (
        <div>
            <Breadcrumbs items={[{ label: 'Po teritoriji', to: `/${slug}/teritorija` }, { label: d?.name ?? district }]} />
            <PageTitle title={d?.name ?? district} meta={results.meta ?? districts.meta} sub={d && <>{d.municipalities} {t(plural(d.municipalities, ['opština', 'opštine', 'opština']))}, {num(d.stations)} {t('biračkih mesta')}, {num(d.registered_voters)} {t('upisanih birača')}</>} />
            <Band>
                {r && r.stations_total > 0 && (
                    <>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="card-flat"><Donut value={r.turnout_pct} label="Izlaznost" sub={<>{num(r.voters_voted)} {t('od')} {num(r.registered_voters)}</>} size={72} /></div>
                            <div className="card-flat"><div className="text-sm text-ink-2">{t('Obrađeno biračkih mesta')}</div><div className="mt-1 text-2xl font-bold tabular-nums">{num(r.stations_verified)} / {num(r.stations_total)}</div><div className="mt-2"><Processed value={r.processed} label="Obrađeno" /></div></div>
                            <div className="card-flat"><div className="text-sm text-ink-2">{t('Važećih listića')}</div><div className="mt-1 text-2xl font-bold tabular-nums">{num(r.ballots_valid)}</div><div className="muted mt-1">{num(r.ballots_invalid)} {t('nevažećih')} ({pct(r.invalid_pct)})</div></div>
                        </div>
                        {r.lists.length > 0 && (
                            <Card>
                                <SectionHead title="Rezultati glasanja po listama" right={
                                    <CsvButton
                                        filename={`rezultati-okrug-${district}-${slug}`}
                                        rows={[...r.lists].sort((a, b) => b.votes - a.votes)}
                                        columns={[
                                            { label: 'Broj na listiću', value: (l) => l.number },
                                            { label: 'Izborna lista', value: (l) => l.name },
                                            { label: 'Glasova', value: (l) => l.votes },
                                            { label: 'Udeo %', value: (l) => l.votes_pct },
                                        ]}
                                    />
                                } />
                                <ListResults rows={r.lists} slug={slug} />
                            </Card>
                        )}
                    </>
                )}
                <Card>
                    <h2 className="mb-4">{t('Opštine')}</h2>
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
                            <div className="card-flat"><Donut value={r.turnout_pct} label="Izlaznost" sub={<>{num(r.voters_voted)} {t('od')} {num(r.registered_voters)}</>} size={72} /></div>
                            <div className="card-flat"><div className="text-sm text-ink-2">{t('Obrađeno biračkih mesta')}</div><div className="mt-1 text-2xl font-bold tabular-nums">{r.stations_verified} / {r.stations_total}</div><div className="mt-2"><Processed value={r.processed} label="Obrađeno" /></div></div>
                            <div className="card-flat"><div className="text-sm text-ink-2">{t('Važećih listića')}</div><div className="mt-1 text-2xl font-bold tabular-nums">{num(r.ballots_valid)}</div><div className="muted mt-1">{num(r.ballots_invalid)} {t('nevažećih')} ({pct(r.invalid_pct)})</div></div>
                        </div>
                        <Card>
                            <SectionHead title="Rezultati glasanja po listama" right={
                                <CsvButton
                                    filename={`rezultati-${district}-${municipality}-${slug}`}
                                    rows={[...r.lists].sort((a, b) => b.votes - a.votes)}
                                    columns={[
                                        { label: 'Broj na listiću', value: (l) => l.number },
                                        { label: 'Izborna lista', value: (l) => l.name },
                                        { label: 'Glasova', value: (l) => l.votes },
                                        { label: 'Udeo %', value: (l) => l.votes_pct },
                                    ]}
                                />
                            } />
                            <ListResults rows={r.lists} slug={slug} />
                        </Card>
                    </>
                )}
                <Card>
                    <SectionHead title="Biračka mesta" right={
                        <CsvButton
                            filename={`biracka-mesta-${district}-${municipality}-${slug}`}
                            rows={rows}
                            columns={[
                                { label: 'Biračko mesto', value: (s) => s.number },
                                { label: 'Naziv', value: (s) => s.name },
                                { label: 'Adresa', value: (s) => s.address },
                                { label: 'Upisanih birača', value: (s) => s.registered_voters },
                                { label: 'Glasalo', value: (s) => s.voters_voted },
                                { label: 'Izlaznost %', value: (s) => s.turnout_pct },
                                { label: 'Važećih', value: (s) => s.ballots_valid },
                                { label: 'Nevažećih', value: (s) => s.ballots_invalid },
                                { label: 'Odstupanje', value: (s) => s.deviation },
                                { label: 'Status', value: (s) => s.status ?? 'nije unet' },
                                { label: 'Pristupačno', value: (s) => (s.accessible ? 'da' : 'ne') },
                            ]}
                        />
                    } />
                    {rows.length === 0 ? <p className="muted">{t('Nema podataka.')}</p> : (
                        <div className="overflow-x-auto">
                            <table className="data min-w-[760px]">
                                <thead><tr><th className="num">{t('BM')}</th><th>{t('Naziv i adresa')}</th><th className="num">{t('Upisano')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th><th className="num">{t('Odstupanje')}</th><th>{t('Status')}</th></tr></thead>
                                <tbody>
                                    {rows.map((s) => (
                                        <tr key={s.station_id}>
                                            <td className="num"><Link className="link" to={`/${slug}/biracko-mesto/${s.station_id}`}>{s.number}</Link></td>
                                            <td>{t(s.name)}{s.address && <div className="muted">{t(s.address)}</div>}<StationTags station={s} /></td>
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
