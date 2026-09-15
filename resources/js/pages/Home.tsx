import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { CloseRace, Composition, District, ElectionInfo, Municipality, MunicipalityResults, ResultsSummary, TurnoutCutoff, UnitSummary, Winner } from '@/types';
import { listColor, num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { ListResults } from '@/components/ListResults';
import { Hemicycle } from '@/components/Hemicycle';
import { DistrictAccordion } from '@/components/DistrictAccordion';
import { CsvButton } from '@/components/Csv';
import { Band, Card, Donut, IconArrow, Processed, ResultStatusNote, RingIndicator, SectionHead, Segmented, Swatch, Updated } from '@/components/ui';

type ListTab = 'sve' | 'manjinske' | 'inostranstvo';

export function Home() {
    const { slug, election } = useElection();
    const t = useT();
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const composition = useSnapshotData<Composition>('results', 'composition.json');
    const turnout = useSnapshotList<TurnoutCutoff>('turnout', 'turnout-country.json');
    const info = useSnapshotData<ElectionInfo>('registry', 'election.json');
    const close = useSnapshotList<CloseRace>('results', 'close-races.json');
    const winners = useSnapshotList<Winner>('results', 'winners.json');
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    const diaspora = municipalities.list?.find((m) => m.district_code === '99');
    const diasporaResults = useSnapshotData<MunicipalityResults>('results', diaspora ? `99/results-99-${diaspora.code}.json` : '', { skip: !diaspora });
    const [listTab, setListTab] = useState<ListTab>('sve');

    const lastTurnout = turnout.list?.filter((c) => c.voters_voted !== null).at(-1);
    const meta = summary.meta ?? info.meta;
    const d = summary.item;
    const single: UnitSummary | undefined = d && d.units.length === 1 ? d.units[0] : undefined;
    const isProportional = election.type !== 'presidential';

    const listTabs: Array<{ value: ListTab; label: string }> = [{ value: 'sve', label: 'Sve liste' }];
    if (single?.lists.some((l) => l.is_minority)) listTabs.push({ value: 'manjinske', label: 'Liste nacionalnih manjina' });
    if (diaspora && diasporaResults.available) listTabs.push({ value: 'inostranstvo', label: 'Glasanje u inostranstvu' });

    return (
        <div>
            {meta && <Updated meta={meta} />}

            <SectionHead title={isProportional ? 'Osvojeni mandati' : 'Rezultati'} right={d && <Processed value={d.processed} />} />

            {!summary.available && (
                <Card className="mb-6">
                    <p className="font-medium">{t('Rezultati još nisu objavljeni.')}</p>
                    <p className="muted mt-1">{t('Do objave prvih zapisnika prikazuju se registar biračkih mesta i izlaznost.')}</p>
                    {info.item && <p className="muted mt-2">{num(info.item.counts.stations)} {t('biračkih mesta')}, {num(info.item.counts.registered_voters)} {t('upisanih birača')}, {num(info.item.counts.lists)} {t('lista')}</p>}
                </Card>
            )}

            {isProportional && composition.available && (
                <WithFile state={composition} unavailable={t('Rezultati još nisu objavljeni.')}>
                    {({ data: c }) => {
                        const winning = c.by_list.filter((l) => l.seats > 0);
                        const maxSeats = winning[0]?.seats ?? 1;
                        return (
                            <div className="grid items-start gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                                <Hemicycle total={c.seats_total} byList={c.by_list} empty={c.seats_empty} />
                                <div className="overflow-x-auto">
                                    <table className="data md:min-w-[520px]">
                                        <thead><tr><th>{t('Izborna lista')}</th><th className="num">{t('Glasova')}</th><th className="num">{t('Mandata')}</th><th className="hidden w-[22%] md:table-cell"><span className="sr-only">{t('Udeo mandata')}</span></th><th className="hidden md:table-cell"><span className="sr-only">{t('Poslanici')}</span></th></tr></thead>
                                        <tbody>
                                            {winning.map((l, i) => (
                                                <tr key={`${l.name}-${i}`}>
                                                    <td>
                                                        <span className="flex items-center gap-2.5">
                                                            <Swatch color={l.color} index={i} />
                                                            {l.list_ids[0] !== undefined ? <Link className="link" to={`/${slug}/liste/${l.list_ids[0]}`}>{t(l.short_name ?? l.name)}</Link> : t(l.short_name ?? l.name)}
                                                            {l.is_minority && <span className="badge badge-blue">{t('manjinska')}</span>}
                                                        </span>
                                                    </td>
                                                    <td className="num">{num(l.votes)}</td>
                                                    <td className="num strong">{l.seats}</td>
                                                    <td className="hidden md:table-cell"><div className="bar-track"><div className="bar-fill" style={{ width: `${(l.seats / maxSeats) * 100}%`, background: listColor(l.color, i) }} /></div></td>
                                                    <td className="hidden md:table-cell"><Link className="link inline-flex items-center gap-1 whitespace-nowrap" to={`/${slug}/skupstina`}>{t('Poslanici')} <IconArrow /></Link></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {c.seats_empty > 0 && <p className="muted mt-2">{num(c.seats_empty)} {t('mandata još nije raspodeljeno.')}</p>}
                                </div>
                            </div>
                        );
                    }}
                </WithFile>
            )}

            {d && (
                <>
                    <SectionHead title="Izlaznost" className="mt-10" />
                    <div className="grid gap-6 sm:grid-cols-2">
                        <Donut value={d.turnout_pct} label="Izlaznost po obrađenim zapisnicima" sub={<>{num(d.voters_voted)} {t('od')} {num(d.registered_voters)} {t('upisanih birača')}</>} />
                        {lastTurnout && <Donut value={lastTurnout.turnout_pct} label={`Izlaznost u ${lastTurnout.cutoff} (preseci izbornih komisija)`} sub={<Link className="link font-normal" to={`/${slug}/izlaznost`}>{t('svi preseci i mapa')}</Link>} />}
                    </div>
                </>
            )}

            <Band className="mt-10">
                {single && (
                    <Card>
                        <SectionHead title="Rezultati glasanja po listama" right={
                            <span className="flex flex-wrap items-center gap-4">
                                <Processed value={single.processed} label="Obrađeno" />
                                <CsvButton
                                    filename={`rezultati-po-listama-${slug}`}
                                    rows={[...single.lists].sort((a, b) => b.votes - a.votes)}
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
                            </span>
                        } />
                        {listTabs.length > 1 && <div className="mb-4"><Segmented options={listTabs} value={listTab} onChange={setListTab} label="Prikaz lista" /></div>}
                        {listTab === 'inostranstvo' && diasporaResults.item ? (
                            <>
                                <p className="mb-3 text-sm text-ink-2">{t('Glasovi sa biračkih mesta u diplomatsko-konzularnim predstavništvima.')} {t('Obrađeno')}: <b className="text-ink">{pct(diasporaResults.item.processed)}</b>, {t('izlaznost')}: <b className="text-ink">{pct(diasporaResults.item.turnout_pct)}</b>.</p>
                                <ListResults rows={diasporaResults.item.lists} slug={slug} />
                            </>
                        ) : (
                            <ListResults
                                rows={listTab === 'manjinske' ? single.lists.filter((l) => l.is_minority) : single.lists}
                                slug={slug}
                                showSeats={isProportional}
                                thresholdVotes={single.allocation.threshold_votes}
                                seatsTotal={single.seats}
                            />
                        )}
                        {single.allocation.notes.length > 0 && (
                            <ul className="mt-3 list-disc pl-5 text-sm text-amber-900">{single.allocation.notes.map((n) => <li key={n}>{t(n)}</li>)}</ul>
                        )}
                        <ResultStatusNote status={election.status} verified={single.stations_verified} total={single.stations_total} />
                    </Card>
                )}

                {d && d.units.length > 1 && (
                    <Card>
                        <SectionHead title="Izborne jedinice" />
                        <div className="overflow-x-auto">
                            <table className="data">
                                <thead><tr><th>{t('Jedinica')}</th><th className="num">{t('Obrađeno')}</th><th>{t('Vodi')}</th><th className="num">%</th><th className="num">{t('Razlika')}</th></tr></thead>
                                <tbody>
                                    {(winners.list ?? []).map((w) => (
                                        <tr key={w.unit_code}>
                                            <td><Link className="link" to={`/${slug}/mandati?unit=${w.unit_code}`}>{t(w.unit_name)}</Link></td>
                                            <td className="num"><span className="inline-flex items-center gap-1.5">{pct(w.processed)}<RingIndicator value={w.processed} size={16} /></span></td>
                                            <td>{w.leader ? t(w.leader.name) : '-'}</td>
                                            <td className="num">{w.leader ? pct(w.leader.votes_pct) : '-'}</td>
                                            <td className="num">{w.margin_pct === null ? '-' : pct(w.margin_pct)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                <Card>
                    <SectionHead title="Rezultati po okruzima" right={<Link className="link inline-flex items-center gap-1" to={`/${slug}/teritorija`}>{t('Mapa rezultata')} <IconArrow /></Link>} />
                    <WithFile state={districts} unavailable={t('Registar još nije objavljen.')}>
                        {({ list }) => <DistrictAccordion districts={list.filter((x) => x.stations > 0)} slug={slug} />}
                    </WithFile>
                </Card>

                {(close.list?.length ?? 0) > 0 && (
                    <div className="card-flat border-amber-200 bg-warn-soft">
                        <h2 className="mb-2">{t('Tesne trke')}</h2>
                        <ul className="space-y-1 text-sm">
                            {close.list?.map((r, i) => (
                                <li key={i}>
                                    {r.type === 'threshold'
                                        ? <>{t(r.name)}: {r.margin_pct >= 0 ? t('iznad') : t('ispod')} {t('cenzusa za')} {pct(Math.abs(r.margin_pct))}</>
                                        : <>{t(r.names[0] ?? '')} / {t(r.names[1] ?? '')}: {t('razlika')} {pct(r.margin_pct)} ({r.unit_code})</>}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </Band>
        </div>
    );
}
