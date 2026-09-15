import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { CloseRace, ElectionInfo, ResultsSummary, TurnoutCutoff, Winner } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { PageTitle, ProcessedBar, Stat } from '@/components/ui';

export function Home() {
    const { slug, election } = useElection();
    const t = useT();
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const turnout = useSnapshotList<TurnoutCutoff>('turnout', 'turnout-country.json');
    const info = useSnapshotData<ElectionInfo>('registry', 'election.json');
    const close = useSnapshotList<CloseRace>('results', 'close-races.json');
    const winners = useSnapshotList<Winner>('results', 'winners.json');

    const lastTurnout = turnout.list?.filter((c) => c.voters_voted !== null).at(-1);

    return (
        <div className="space-y-6">
            <PageTitle title={election.name} meta={summary.meta ?? info.meta}>
                {info.item && (
                    <>
                        {num(info.item.counts.stations)} {t('biračkih mesta')} · {num(info.item.counts.registered_voters)} {t('upisanih birača')} · {num(info.item.counts.lists)} {t('lista')}
                    </>
                )}
            </PageTitle>

            <WithFile state={summary} unavailable={t('Rezultati još nisu objavljeni. Do objave prvih zapisnika prikazuju se registar i izlaznost.')}>
                {(file) => {
                    const d = file.data;
                    const single = d.units.length === 1 ? d.units[0] : undefined;
                    return (
                        <>
                            <div className="card">
                                <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                    <h2>{t('Obrađeno biračkih mesta')}</h2>
                                    <span className="text-2xl font-semibold tabular-nums">{pct(d.processed)}</span>
                                </div>
                                <ProcessedBar value={d.processed} />
                                <p className="muted mt-2">
                                    {num(d.stations_verified)} {t('verifikovano')} · {num(d.stations_entered)} {t('uneto, čeka verifikaciju')} · {num(d.stations_flagged)} {t('sa odstupanjem')} · {num(d.stations_total)} {t('ukupno')}
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Stat label="Izlaznost (obrađena BM)" value={pct(d.turnout_pct)} sub={`${num(d.voters_voted)} / ${num(d.registered_voters)}`} />
                                <Stat label="Važećih listića" value={num(d.ballots_valid)} />
                                <Stat label="Nevažećih listića" value={num(d.ballots_invalid)} sub={pct(d.invalid_pct)} />
                                <Stat label={lastTurnout ? `Izlaznost u ${lastTurnout.cutoff}` : 'Izlaznost (preseci)'} value={lastTurnout ? pct(lastTurnout.turnout_pct) : '—'} sub={<Link className="link" to={`/${slug}/izlaznost`}>{t('svi preseci')}</Link>} />
                            </div>

                            {single ? (
                                <div className="card">
                                    <div className="mb-3 flex items-baseline justify-between">
                                        <h2>{t('Rezultati po listama')}</h2>
                                        <Link className="link text-sm" to={`/${slug}/skupstina`}>{t('sastav skupštine')} →</Link>
                                    </div>
                                    <ListResults rows={single.lists} slug={slug} showSeats={election.type !== 'presidential'} thresholdVotes={single.allocation.threshold_votes} />
                                    {single.allocation.notes.length > 0 && (
                                        <ul className="mt-3 list-disc pl-5 text-sm text-amber-800 dark:text-amber-300">
                                            {single.allocation.notes.map((n) => <li key={n}>{t(n)}</li>)}
                                        </ul>
                                    )}
                                </div>
                            ) : (
                                <div className="card">
                                    <h2 className="mb-3">{t('Izborne jedinice')}</h2>
                                    <div className="overflow-x-auto">
                                        <table className="data">
                                            <thead><tr><th>{t('Jedinica')}</th><th className="num">{t('Obrađeno')}</th><th>{t('Vodi')}</th><th className="num">%</th><th className="num">{t('Razlika')}</th></tr></thead>
                                            <tbody>
                                                {(winners.list ?? []).map((w) => (
                                                    <tr key={w.unit_code}>
                                                        <td><Link className="link" to={`/${slug}/mandati?unit=${w.unit_code}`}>{t(w.unit_name)}</Link></td>
                                                        <td className="num">{pct(w.processed)}</td>
                                                        <td>{w.leader ? t(w.leader.name) : '—'}</td>
                                                        <td className="num">{w.leader ? pct(w.leader.votes_pct) : '—'}</td>
                                                        <td className="num">{w.margin_pct === null ? '—' : pct(w.margin_pct)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {(close.list?.length ?? 0) > 0 && (
                                <div className="card border-amber-300 dark:border-amber-800">
                                    <h2 className="mb-2">{t('Tesne trke')}</h2>
                                    <ul className="space-y-1 text-sm">
                                        {close.list?.map((r, i) => (
                                            <li key={i}>
                                                {r.type === 'threshold'
                                                    ? <>{t(r.name)} — {r.margin_pct >= 0 ? t('iznad') : t('ispod')} {t('cenzusa za')} {pct(Math.abs(r.margin_pct))}</>
                                                    : <>{t(r.names[0] ?? '')} / {t(r.names[1] ?? '')} — {t('razlika')} {pct(r.margin_pct)} ({r.unit_code})</>}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </>
                    );
                }}
            </WithFile>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    ['skupstina', 'Sastav skupštine', 'Potkovica i lista izabranih poslanika'],
                    ['mandati', "D'Hondt matrica", 'Svaki količnik i svaki dodeljeni mandat'],
                    ['teritorija', 'Po teritoriji', 'Okrug → opština → biračko mesto'],
                    ['zapisnici', 'Zapisnici', 'Skenirani zapisnici i odstupanja'],
                ].map(([to, title, sub]) => (
                    <Link key={to} to={`/${slug}/${to}`} className="card no-underline hover:border-primary">
                        <div className="font-semibold">{t(title ?? '')}</div>
                        <div className="muted">{t(sub ?? '')}</div>
                    </Link>
                ))}
            </div>
        </div>
    );
}
