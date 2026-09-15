import { Link, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, Municipality, MunicipalityResults, StationProtocol } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { Breadcrumbs, PageTitle, ProcessedBar, StatusBadge } from '@/components/ui';

export function Territory() {
    const { slug } = useElection();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    return (
        <div className="space-y-6">
            <PageTitle title="Po teritoriji" meta={districts.meta}>{t('Okrug → opština → biračko mesto → zapisnik')}</PageTitle>
            <WithFile state={districts} unavailable={t('Registar još nije objavljen.')}>
                {({ list }) => (
                    <div className="card overflow-x-auto">
                        <table className="data">
                            <thead><tr><th>{t('Okrug')}</th><th className="num">{t('Opština')}</th><th className="num">{t('Biračkih mesta')}</th><th className="num">{t('Upisanih birača')}</th></tr></thead>
                            <tbody>
                                {list.map((d) => (
                                    <tr key={d.code}><td><Link className="link" to={`/${slug}/teritorija/${d.code}`}>{t(d.name)}</Link></td><td className="num">{d.municipalities}</td><td className="num">{num(d.stations)}</td><td className="num">{num(d.registered_voters)}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </WithFile>
        </div>
    );
}

export function TerritoryDistrict() {
    const { slug } = useElection();
    const { district = '' } = useParams();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    const name = districts.list?.find((d) => d.code === district)?.name ?? district;
    return (
        <div className="space-y-6">
            <Breadcrumbs items={[{ label: 'Teritorija', to: `/${slug}/teritorija` }, { label: name }]} />
            <PageTitle title={name} meta={municipalities.meta} />
            <WithFile state={municipalities} unavailable={t('Registar još nije objavljen.')}>
                {({ list }) => (
                    <div className="card overflow-x-auto">
                        <table className="data">
                            <thead><tr><th>{t('Opština')}</th><th className="num">{t('Biračkih mesta')}</th><th className="num">{t('Upisanih birača')}</th><th>{t('Jedinica')}</th></tr></thead>
                            <tbody>
                                {list.filter((m) => m.district_code === district).map((m) => (
                                    <tr key={m.code}><td><Link className="link" to={`/${slug}/teritorija/${district}/${m.code}`}>{t(m.name)}</Link></td><td className="num">{num(m.stations)}</td><td className="num">{num(m.registered_voters)}</td><td>{m.unit_codes.join(', ')}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </WithFile>
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
    const districtName = districts.list?.find((d) => d.code === district)?.name ?? district;
    const title = results.item?.name ?? stations.list?.[0]?.municipality_code ?? municipality;
    const rows = protocols.list ?? stations.list ?? [];

    return (
        <div className="space-y-6">
            <Breadcrumbs items={[{ label: 'Teritorija', to: `/${slug}/teritorija` }, { label: districtName, to: `/${slug}/teritorija/${district}` }, { label: title }]} />
            <PageTitle title={title} meta={results.meta ?? protocols.meta ?? stations.meta} />
            {results.available && results.item && (
                <>
                    <div className="card">
                        <div className="mb-2 flex justify-between"><h2>{t('Obrađeno biračkih mesta')}</h2><span className="font-semibold tabular-nums">{results.item.stations_verified} / {results.item.stations_total} ({pct(results.item.processed)})</span></div>
                        <ProcessedBar value={results.item.processed} />
                        <p className="muted mt-2">{t('Izlaznost')} {pct(results.item.turnout_pct)} · {t('važećih')} {num(results.item.ballots_valid)} · {t('nevažećih')} {num(results.item.ballots_invalid)} ({pct(results.item.invalid_pct)})</p>
                    </div>
                    <div className="card"><ListResults rows={results.item.lists} slug={slug} /></div>
                </>
            )}
            <div className="card overflow-x-auto">
                <h2 className="mb-3">{t('Biračka mesta')}</h2>
                {rows.length === 0 ? <p className="muted">{t('Nema podataka.')}</p> : (
                    <table className="data">
                        <thead><tr><th>{t('BM')}</th><th>{t('Naziv')}</th><th className="num">{t('Upisano')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th><th className="num">{t('Odstupanje')}</th><th>{t('Status')}</th></tr></thead>
                        <tbody>
                            {rows.map((s) => (
                                <tr key={s.station_id}>
                                    <td className="num"><Link className="link" to={`/${slug}/biracko-mesto/${s.station_id}`}>{s.number}</Link></td>
                                    <td>{t(s.name)}{s.address && <div className="muted">{t(s.address)}</div>}{s.is_diaspora && s.country && <div className="muted">{t(s.country)}</div>}</td>
                                    <td className="num">{num(s.registered_voters)}</td>
                                    <td className="num">{num(s.voters_voted)}</td>
                                    <td className="num">{s.turnout_pct === undefined ? '—' : pct(s.turnout_pct)}</td>
                                    <td className={`num ${s.deviation ? 'font-semibold text-red-700 dark:text-red-400' : ''}`}>{s.deviation === undefined ? '—' : s.deviation}</td>
                                    <td><StatusBadge status={s.status} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
