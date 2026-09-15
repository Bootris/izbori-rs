import { Link, useParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { ElectoralList, ResultsSummary, Submitter as SubmitterRow, UnitListRow } from '@/types';
import { num, pct, plural } from '@/lib/format';
import { WithFile } from '@/components/state';
import { CsvButton } from '@/components/Csv';
import { Band, Breadcrumbs, Card, PageTitle, SectionHead, Stat, Swatch } from '@/components/ui';

const TYPE_LABEL: Record<string, string> = {
    party: 'Politička stranka',
    coalition: 'Koalicija',
    citizen_group: 'Grupa građana',
};

/** Votes and seats of every list, flattened across units (one unit for parliamentary). */
function useListResults(summary: ResultsSummary | undefined): Map<number, UnitListRow> {
    const rows = new Map<number, UnitListRow>();
    for (const unit of summary?.units ?? []) {
        for (const row of unit.lists) rows.set(row.list_id, row);
    }
    return rows;
}

export function Submitters() {
    const { slug } = useElection();
    const t = useT();
    const submitters = useSnapshotList<SubmitterRow>('registry', 'submitters.json');
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const results = useListResults(summary.item);

    const rows = (submitters.list ?? []).map((s) => {
        const own = (lists.list ?? []).filter((l) => l.submitter_id === s.id);
        const votes = own.reduce((sum, l) => sum + (results.get(l.id)?.votes ?? 0), 0);
        const seats = own.reduce((sum, l) => sum + (results.get(l.id)?.seats ?? 0), 0);
        const candidates = own.reduce((sum, l) => sum + l.candidates.length, 0);
        return { submitter: s, lists: own, votes, seats, candidates };
    }).sort((a, b) => b.seats - a.seats || b.votes - a.votes);

    return (
        <div>
            <PageTitle title="Podnosioci izbornih lista" meta={summary.meta ?? submitters.meta} sub={t('Stranke, koalicije i grupe građana koje su podnele izborne liste, sa osvojenim glasovima i mandatima.')} />
            <Band>
                <Card>
                    <SectionHead title={`${rows.length} ${t(plural(rows.length, ['podnosilac', 'podnosioca', 'podnosilaca']))}`} right={
                        <CsvButton
                            filename={`podnosioci-${slug}`}
                            rows={rows}
                            columns={[
                                { label: 'Podnosilac', value: (r) => r.submitter.name },
                                { label: 'Skraćeno', value: (r) => r.submitter.short_name },
                                { label: 'Tip', value: (r) => TYPE_LABEL[r.submitter.type] ?? r.submitter.type },
                                { label: 'Manjinski', value: (r) => (r.submitter.is_minority ? 'da' : 'ne') },
                                { label: 'Izbornih lista', value: (r) => r.lists.length },
                                { label: 'Kandidata', value: (r) => r.candidates },
                                { label: 'Glasova', value: (r) => r.votes },
                                { label: 'Mandata', value: (r) => r.seats },
                            ]}
                        />
                    } />
                    <WithFile state={submitters} unavailable={t('Registar još nije objavljen.')}>
                        {() => (
                            <div className="overflow-x-auto">
                                <table className="data min-w-[680px]">
                                    <thead><tr><th>{t('Podnosilac')}</th><th>{t('Tip')}</th><th className="num">{t('Izbornih lista')}</th><th className="num">{t('Kandidata')}</th><th className="num">{t('Glasova')}</th><th className="num">{t('Mandata')}</th></tr></thead>
                                    <tbody>
                                        {rows.map((r) => (
                                            <tr key={r.submitter.id}>
                                                <td>
                                                    <span className="flex items-center gap-2.5">
                                                        <Swatch color={r.submitter.color} index={r.submitter.id} />
                                                        <Link className="link" to={`/${slug}/podnosioci/${r.submitter.id}`}>{t(r.submitter.name)}</Link>
                                                        {r.submitter.is_minority && <span className="badge badge-blue">{t('nacionalna manjina')}</span>}
                                                    </span>
                                                </td>
                                                <td>{t(TYPE_LABEL[r.submitter.type] ?? r.submitter.type)}</td>
                                                <td className="num">{r.lists.length}</td>
                                                <td className="num">{num(r.candidates)}</td>
                                                <td className="num">{num(r.votes)}</td>
                                                <td className="num strong">{r.seats}</td>
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

export function Submitter() {
    const { slug } = useElection();
    const { submitterId = '' } = useParams();
    const t = useT();
    const submitters = useSnapshotList<SubmitterRow>('registry', 'submitters.json');
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const results = useListResults(summary.item);

    const id = Number(submitterId);
    const submitter = submitters.list?.find((s) => s.id === id);
    const own = (lists.list ?? []).filter((l) => l.submitter_id === id);
    const votes = own.reduce((sum, l) => sum + (results.get(l.id)?.votes ?? 0), 0);
    const seats = own.reduce((sum, l) => sum + (results.get(l.id)?.seats ?? 0), 0);
    const candidates = own.reduce((sum, l) => sum + l.candidates.length, 0);

    return (
        <div>
            <Breadcrumbs items={[{ label: 'Podnosioci', to: `/${slug}/podnosioci` }, { label: submitter?.name ?? `#${submitterId}` }]} />
            <WithFile state={submitters} unavailable={t('Registar još nije objavljen.')}>
                {() => submitter ? (
                    <>
                        <PageTitle
                            title={submitter.name}
                            meta={summary.meta ?? submitters.meta}
                            sub={<span className="flex flex-wrap items-center gap-2"><Swatch color={submitter.color} index={submitter.id} />{t(TYPE_LABEL[submitter.type] ?? submitter.type)}{submitter.is_minority && <span className="badge badge-blue">{t('podnosilac liste nacionalne manjine')}</span>}</span>}
                        />
                        <Band>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Stat label="Glasova" value={num(votes)} />
                                <Stat label="Mandata" value={seats} />
                                <Stat label="Kandidata na listama" value={num(candidates)} />
                            </div>
                            <Card>
                                <h2 className="mb-4">{own.length === 1 ? t('Izborna lista') : t('Izborne liste')}</h2>
                                <div className="overflow-x-auto">
                                    <table className="data min-w-[600px]">
                                        <thead><tr><th className="num">#</th><th>{t('Lista')}</th><th>{t('Nosilac')}</th><th className="num">{t('Kandidata')}</th><th className="num">{t('Glasova')}</th><th className="num">{t('Udeo')}</th><th className="num">{t('Mandata')}</th></tr></thead>
                                        <tbody>
                                            {own.map((l) => {
                                                const r = results.get(l.id);
                                                return (
                                                    <tr key={l.id}>
                                                        <td className="num">{l.number}.</td>
                                                        <td><Link className="link" to={`/${slug}/liste/${l.id}`}>{t(l.name)}</Link></td>
                                                        <td>{t(l.holder_name)}</td>
                                                        <td className="num">{l.candidates.length}</td>
                                                        <td className="num">{num(r?.votes)}</td>
                                                        <td className="num">{r ? pct(r.votes_pct) : '-'}</td>
                                                        <td className="num strong">{r?.seats ?? '-'}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </Card>
                        </Band>
                    </>
                ) : <p className="muted">{t('Podnosilac nije pronađen.')}</p>}
            </WithFile>
        </div>
    );
}
