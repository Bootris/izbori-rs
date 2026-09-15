import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { Composition, ElectoralList, ResultsSummary, UnitListRow } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { CsvButton } from '@/components/Csv';
import { Band, Breadcrumbs, Card, PageTitle, SectionHead, Stat, Swatch } from '@/components/ui';

/** Picks one unit when an election has several (local elections); parliamentary has exactly one. */
export function useUnitSelector(summary: ResultsSummary | undefined) {
    const [unitCode, setUnitCode] = useState<string | null>(null);
    const units = summary?.units ?? [];
    const selected = units.find((u) => u.code === unitCode) ?? units[0];
    return { units, selected, setUnitCode };
}

export function Lists() {
    const { slug, election } = useElection();
    const t = useT();
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const { units, selected, setUnitCode } = useUnitSelector(summary.item);

    return (
        <div>
            <PageTitle title="Izborne liste" meta={summary.meta ?? lists.meta} sub={t('Proglašene izborne liste po redosledu na glasačkom listiću, sa osvojenim glasovima i mandatima.')} />
            <Band>
                {units.length > 1 && (
                    <label className="flex items-center gap-2 text-sm">
                        <span className="text-ink-2">{t('Izborna jedinica')}:</span>
                        <select className="select" value={selected?.code ?? ''} onChange={(e) => setUnitCode(e.target.value)}>
                            {units.map((u) => <option key={u.code} value={u.code}>{t(u.name)}</option>)}
                        </select>
                    </label>
                )}
                {selected ? (
                    <Card>
                        <SectionHead title="Rezultati po listama" right={
                            <CsvButton
                                filename={`izborne-liste-${slug}`}
                                rows={[...selected.lists].sort((a, b) => b.votes - a.votes)}
                                columns={[
                                    { label: 'Broj na listiću', value: (l) => l.number },
                                    { label: 'Izborna lista', value: (l) => l.name },
                                    { label: 'Nosilac liste', value: (l) => l.holder_name },
                                    { label: 'Manjinska', value: (l) => (l.is_minority ? 'da' : 'ne') },
                                    { label: 'Glasova', value: (l) => l.votes },
                                    { label: 'Udeo %', value: (l) => l.votes_pct },
                                    { label: 'Mandata', value: (l) => l.seats },
                                ]}
                            />
                        } />
                        <ListResults rows={selected.lists} slug={slug} showSeats={election.type !== 'presidential'} thresholdVotes={selected.allocation.threshold_votes} seatsTotal={selected.seats} />
                    </Card>
                ) : (
                    <WithFile state={lists} unavailable={t('Registar još nije objavljen.')}>
                        {({ list }) => (
                            <Card>
                                <p className="muted mb-3">{t('Rezultati još nisu objavljeni. Prikazane su proglašene liste.')}</p>
                                <div className="overflow-x-auto">
                                    <table className="data">
                                        <thead><tr><th className="num">#</th><th>{t('Lista')}</th><th>{t('Nosilac')}</th><th className="num">{t('Kandidata')}</th></tr></thead>
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
                            </Card>
                        )}
                    </WithFile>
                )}
            </Band>
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
        <div>
            <Breadcrumbs items={[{ label: 'Izborne liste', to: `/${slug}/liste` }, { label: list?.name ?? `#${listId}` }]} />
            <WithFile state={lists} unavailable={t('Registar još nije objavljen.')}>
                {() => list ? (
                    <>
                        <PageTitle title={`${list.number}. ${list.name}`} meta={summary.meta ?? lists.meta} sub={<span className="flex flex-wrap items-center gap-2"><Swatch color={list.color} index={list.number - 1} />{list.holder_name && <>{t('Nosilac liste')}: {t(list.holder_name)}</>}{list.is_minority && <span className="badge badge-blue">{t('lista nacionalne manjine')}</span>}</span>} />
                        <Band>
                            {row && (
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                                    <Stat label="Mandata" value={row.seats} sub={row.qualified ? undefined : t('ispod cenzusa')} wide />
                                    <Stat label="Glasova" value={num(row.votes)} />
                                    <Stat label="Udeo važećih glasova" value={pct(row.votes_pct)} />
                                </div>
                            )}
                            <Card>
                                <h2 className="mb-4">{t('Kandidati')}</h2>
                                <div className="scroll-x">
                                    <table className="data stack min-w-full md:min-w-[600px]">
                                        <thead><tr><th className="num">#</th><th>{t('Ime i prezime')}</th><th className="num">{t('Godište')}</th><th>{t('Zanimanje')}</th><th>{t('Prebivalište')}</th><th>{t('Status')}</th></tr></thead>
                                        <tbody>
                                            {list.candidates.map((c) => (
                                                <tr key={c.position} className={elected.has(c.position) ? 'font-medium' : ''}>
                                                    <td className="num drop">{c.position}</td>
                                                    <td className="lead">{c.position}. {t(c.full_name)}</td>
                                                    <td className="num" data-label={t('Godište')}>{c.birth_year ?? '-'}</td>
                                                    <td data-label={t('Zanimanje')}>{t(c.occupation)}</td>
                                                    <td data-label={t('Prebivalište')}>{t(c.residence)}</td>
                                                    <td className="wide">{elected.has(c.position) ? <span className="badge badge-green">{t('izabran/a')}</span> : ''}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Card>
                        </Band>
                    </>
                ) : <p className="muted">{t('Lista nije pronađena.')}</p>}
            </WithFile>
        </div>
    );
}
