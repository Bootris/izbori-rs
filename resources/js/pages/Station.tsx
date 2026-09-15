import { Link, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { CodebookEntry, ElectoralList, StationProtocol } from '@/types';
import { dateTime, num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { Bar, Breadcrumbs, PageTitle, StatusBadge, Swatch } from '@/components/ui';

/** station_id is "{district}-{municipality}-{number}"; the number itself may contain dashes. */
function parseStationId(id: string): { district: string; municipality: string; number: string } | null {
    const [district, municipality, ...rest] = id.split('-');
    if (!district || !municipality || rest.length === 0) return null;
    return { district, municipality, number: rest.join('-') };
}

export function Station() {
    const { slug } = useElection();
    const { stationId = '' } = useParams();
    const t = useT();
    const ref = parseStationId(stationId);
    const path = ref ? `${ref.district}/protocols-${ref.district}-${ref.municipality}.json` : '';
    const protocols = useSnapshotList<StationProtocol>('results', path, { skip: !ref });
    const stations = useSnapshotList<StationProtocol>('registry', ref ? `${ref.district}/stations-${ref.district}-${ref.municipality}.json` : '', { skip: !ref });
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const codebook = useSnapshotList<CodebookEntry>('registry', 'codebooks.json');
    const controls = (codebook.list ?? []).filter((c) => c.table === 'CONTROL_SUM');

    if (!ref) return <p className="muted">{t('Nepoznato biračko mesto.')}</p>;

    const station = protocols.list?.find((s) => s.station_id === stationId) ?? stations.list?.find((s) => s.station_id === stationId);
    const listById = new Map((lists.list ?? []).map((l) => [l.id, l]));
    const source = protocols.available ? protocols : stations;

    return (
        <div className="space-y-6">
            <Breadcrumbs items={[{ label: 'Teritorija', to: `/${slug}/teritorija` }, { label: ref.district, to: `/${slug}/teritorija/${ref.district}` }, { label: ref.municipality, to: `/${slug}/teritorija/${ref.district}/${ref.municipality}` }, { label: `BM ${ref.number}` }]} />
            <WithFile state={source} unavailable={t('Podaci još nisu objavljeni.')}>
                {() => station ? (
                    <>
                        <PageTitle title={`Biračko mesto ${station.number} — ${station.name}`} meta={protocols.meta ?? stations.meta}>
                            {station.address && <>{t(station.address)} · </>}{num(station.registered_voters)} {t('upisanih birača')}{station.accessible && <> · {t('pristupačno')}</>}
                        </PageTitle>
                        <div className="flex flex-wrap items-center gap-3">
                            <StatusBadge status={station.status} />
                            {station.revision !== undefined && <span className="muted">{t('revizija')} {station.revision}</span>}
                            {station.verified_at && <span className="muted">{t('verifikovano')} {dateTime(station.verified_at)}</span>}
                            {station.recount_requested && <span className="badge badge-amber">{t('zatraženo ponovno brojanje')}</span>}
                        </div>
                        {station.status === undefined || station.status === null ? (
                            <p className="muted">{t('Zapisnik ovog biračkog mesta još nije unet.')}</p>
                        ) : (
                            <>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="card md:col-span-2">
                                        <h2 className="mb-3">{t('Zapisnik biračkog odbora')}</h2>
                                        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                                            {([
                                                ['Upisanih birača', station.registered_voters_protocol],
                                                ['Primljeno listića', station.ballots_received],
                                                ['Neupotrebljeno', station.ballots_unused],
                                                ['Glasalo (po izvodu)', station.voters_voted],
                                                ['Listića u kutiji', station.ballots_in_box],
                                                ['Važećih', station.ballots_valid],
                                                ['Nevažećih', station.ballots_invalid],
                                                ['Izlaznost', station.turnout_pct === undefined ? undefined : pct(station.turnout_pct)],
                                                ['Odstupanje (K4)', station.deviation],
                                            ] as Array<[string, number | string | undefined]>).map(([label, value]) => (
                                                <div key={label}><dt className="muted">{t(label)}</dt><dd className="font-semibold tabular-nums">{typeof value === 'number' ? num(value) : value ?? '—'}</dd></div>
                                            ))}
                                        </dl>
                                    </div>
                                    <div className="card">
                                        <h2 className="mb-3">{t('Kontrolne sume')}</h2>
                                        <ul className="space-y-1 text-sm">
                                            {controls.map((c) => {
                                                const err = station.errors?.[c.code];
                                                return <li key={c.code} className={err ? 'text-red-700 dark:text-red-400' : 'text-green-700 dark:text-green-400'}>{err ? '✘' : '✔'} <b>{c.code}</b> {t(err ?? c.label)}</li>;
                                            })}
                                        </ul>
                                    </div>
                                </div>
                                <div className="card overflow-x-auto">
                                    <h2 className="mb-3">{t('Glasovi po listama')}</h2>
                                    <table className="data">
                                        <thead><tr><th>#</th><th>{t('Lista')}</th><th className="w-1/3"></th><th className="num">{t('Glasova')}</th><th className="num">%</th></tr></thead>
                                        <tbody>
                                            {(station.items ?? []).map((it) => {
                                                const l = listById.get(it.list_id);
                                                const max = Math.max(...(station.items ?? []).map((x) => x.votes), 1);
                                                return (
                                                    <tr key={it.list_id}>
                                                        <td className="num">{l?.number ?? '—'}.</td>
                                                        <td><span className="flex items-center gap-2"><Swatch color={l?.color} index={(l?.number ?? 1) - 1} />{l ? <Link className="link" to={`/${slug}/liste/${l.id}`}>{t(l.name)}</Link> : `#${it.list_id}`}</span></td>
                                                        <td><Bar value={it.votes} max={max} color={l?.color} index={(l?.number ?? 1) - 1} /></td>
                                                        <td className="num">{num(it.votes)}</td>
                                                        <td className="num">{pct(it.votes_pct)}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="card">
                                    <h2 className="mb-2">{t('Skenirani zapisnik')}</h2>
                                    {(station.scans?.length ?? 0) === 0 ? <p className="muted">{t('Skenirani zapisnik još nije priložen.')}</p> : (
                                        <ul className="list-disc pl-5 text-sm">{station.scans?.map((u) => <li key={u}><a className="link" href={u} target="_blank" rel="noreferrer">{u.split('/').pop()}</a></li>)}</ul>
                                    )}
                                </div>
                            </>
                        )}
                    </>
                ) : <p className="muted">{t('Biračko mesto nije pronađeno.')}</p>}
            </WithFile>
        </div>
    );
}
