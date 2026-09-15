import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { Composition, ElectoralList, ResultsSummary, UnitListRow } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { Breadcrumbs, PageTitle, Swatch } from '@/components/ui';

/** Picks one unit when an election has several (local elections); parliamentary has exactly one. */
export function useUnitSelector(summary: ResultsSummary | undefined) {
    const [unitCode, setUnitCode] = useState<string | null>(null);
    const units = summary?.units ?? [];
    const selected = units.find((u) => u.code === unitCode) ?? units[0];
    return { units, selected, setUnitCode };
}

export function Lists() {
    const { slug } = useElection();
    const t = useT();
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const { units, selected, setUnitCode } = useUnitSelector(summary.item);

    return (
        <div className="space-y-6">
            <PageTitle title="Izborne liste" meta={summary.meta ?? lists.meta} />
            {units.length > 1 && (
                <label className="text-sm">{t('Izborna jedinica')}:{' '}
                    <select className="rounded-md border border-zinc-300 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-800" value={selected?.code ?? ''} onChange={(e) => setUnitCode(e.target.value)}>
                        {units.map((u) => <option key={u.code} value={u.code}>{t(u.name)}</option>)}
                    </select>
                </label>
            )}
            {selected ? (
                <div className="card">
                    <ListResults rows={selected.lists} slug={slug} showSeats thresholdVotes={selected.allocation.threshold_votes} />
                </div>
            ) : (
                <WithFile state={lists} unavailable={t('Registar još nije objavljen.')}>
                    {({ list }) => (
                        <div className="card overflow-x-auto">
                            <p className="muted mb-3">{t('Rezultati još nisu objavljeni — prikazane su proglašene liste.')}</p>
                            <table className="data">
                                <thead><tr><th>#</th><th>{t('Lista')}</th><th>{t('Nosilac')}</th><th className="num">{t('Kandidata')}</th></tr></thead>
                                <tbody>
                                    {list.map((l) => (
                                        <tr key={l.id}>
                                            <td className="num">{l.number}.</td>
                                            <td><span className="flex items-center gap-2"><Swatch color={l.color} index={l.number - 1} /><Link className="link" to={`/${slug}/liste/${l.id}`}>{t(l.name)}</Link>{l.is_minority && <span className="badge badge-blue">{t('manjinska')}</span>}</span></td>
                                            <td>{t(l.holder_name)}</td>
                                            <td className="num">{l.candidates.length}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </WithFile>
            )}
        </div>
    );
}

export function ListDetail() {
    const { slug } = useElection();
    const { listId = '' } = useParams();
    const t = useT();
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const composition = useSnapshotData<Composition>('results', 'composition.json');

    const id = Number(listId);
    const list = lists.list?.find((l) => l.id === id);
    const unit = summary.item?.units.find((u) => u.lists.some((r) => r.list_id === id));
    const row: UnitListRow | undefined = unit?.lists.find((r) => r.list_id === id);
    const elected = new Set((composition.item?.seats ?? []).filter((s) => s.list_id === id).map((s) => s.candidate?.position));

    return (
        <div className="space-y-6">
            <Breadcrumbs items={[{ label: 'Liste', to: `/${slug}/liste` }, { label: list?.name ?? `#${listId}` }]} />
            <WithFile state={lists} unavailable={t('Registar još nije objavljen.')}>
                {() => list ? (
                    <>
                        <PageTitle title={`${list.number}. ${list.name}`} meta={summary.meta ?? lists.meta}>
                            {list.holder_name && <>{t('Nosilac liste')}: {t(list.holder_name)} · </>}{t('jedinica')} {list.unit_code}{list.is_minority && <> · <span className="badge badge-blue">{t('lista nacionalne manjine')}</span></>}
                        </PageTitle>
                        {row && (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="card"><div className="muted">{t('Glasova')}</div><div className="text-2xl font-semibold tabular-nums">{num(row.votes)}</div></div>
                                <div className="card"><div className="muted">{t('Udeo važećih glasova')}</div><div className="text-2xl font-semibold tabular-nums">{pct(row.votes_pct)}</div></div>
                                <div className="card"><div className="muted">{t('Mandata')}</div><div className="text-2xl font-semibold tabular-nums">{row.seats}</div>{!row.qualified && <div className="muted">{t('ispod cenzusa')}</div>}</div>
                            </div>
                        )}
                        <div className="card overflow-x-auto">
                            <h2 className="mb-3">{t('Kandidati')}</h2>
                            <table className="data">
                                <thead><tr><th className="num">#</th><th>{t('Ime i prezime')}</th><th className="num">{t('God.')}</th><th>{t('Zanimanje')}</th><th>{t('Prebivalište')}</th><th>{t('Status')}</th></tr></thead>
                                <tbody>
                                    {list.candidates.map((c) => (
                                        <tr key={c.position} className={elected.has(c.position) ? 'font-medium' : ''}>
                                            <td className="num">{c.position}</td>
                                            <td>{t(c.full_name)}</td>
                                            <td className="num">{c.birth_year ?? '—'}</td>
                                            <td>{t(c.occupation)}</td>
                                            <td>{t(c.residence)}</td>
                                            <td>{elected.has(c.position) ? <span className="badge badge-green">{t('izabran/a')}</span> : ''}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                ) : <p className="muted">{t('Lista nije pronađena.')}</p>}
            </WithFile>
        </div>
    );
}
