import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, DistrictTurnout, MunicipalityTurnout, ResultsSummary, TurnoutCutoff } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { TurnoutChart } from '@/components/TurnoutChart';
import { DistrictMap, type MapEntry } from '@/components/DistrictMap';
import { CsvButton } from '@/components/Csv';
import { Accordion, Band, Card, Donut, PageTitle, SectionHead, Segmented } from '@/components/ui';

const FLOW = 'tok';

export function Turnout() {
    const { slug } = useElection();
    const t = useT();
    const navigate = useNavigate();
    const country = useSnapshotList<TurnoutCutoff>('turnout', 'turnout-country.json');
    const districts = useSnapshotList<DistrictTurnout>('turnout', 'turnout-districts.json');
    const municipalities = useSnapshotList<MunicipalityTurnout>('turnout', 'turnout-municipalities.json');
    const registry = useSnapshotList<District>('registry', 'districts.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const [tab, setTab] = useState<string>('');

    const reported = (country.list ?? []).filter((c) => c.voters_voted !== null);
    const last = reported.at(-1);
    const cutoff = tab === FLOW ? undefined : (reported.find((c) => c.cutoff === tab) ?? last);
    const districtName = (code: string) => registry.list?.find((d) => d.code === code)?.name ?? code;
    // A row published without its cutoffs must not take the whole page down with it.
    const at = (row: { cutoffs?: Array<{ cutoff: string; turnout_pct: number | null; voters_voted?: number | null }> }) =>
        row.cutoffs?.find((c) => c.cutoff === cutoff?.cutoff);

    const names: Record<string, string> = {};
    const entries: Record<string, MapEntry> = {};
    (registry.list ?? []).forEach((d) => { names[d.code] = d.name; });
    (districts.list ?? []).forEach((d) => { entries[String(d.district_code)] = { value: at(d)?.turnout_pct ?? null }; });

    const final = summary.item && summary.item.processed > 0 ? { label: t('zatvaranje'), value: summary.item.turnout_pct } : null;
    const options = [...reported.map((c) => ({ value: c.cutoff, label: c.cutoff })), { value: FLOW, label: 'Tok izlaznosti' }];

    return (
        <div>
            <PageTitle title="Izlaznost" meta={country.meta} sub={t('Preseci koje izborne komisije javljaju tokom dana glasanja i konačna izlaznost iz zapisnika.')} />
            <WithFile state={country} unavailable={t('Izlaznost još nije objavljena.')}>
                {({ list }) => (
                    <>
                        <div className="mb-5"><Segmented options={options} value={tab === '' ? (last?.cutoff ?? FLOW) : tab} onChange={setTab} label="Presek izlaznosti" /></div>
                        <Band>
                            {tab === FLOW ? (
                                <>
                                    <Card evidence={summary.item?.processed}>
                                        <h2 className="mb-1">{t('Republika Srbija')}</h2>
                                        <p className="muted mb-4">{t('Kumulativna izlaznost po presecima; poslednja tačka je konačna izlaznost iz verifikovanih zapisnika.')}</p>
                                        <TurnoutChart cutoffs={list} final={final} />
                                        <div className="mt-4 scroll-x">
                                            <table className="data stack">
                                                <thead><tr><th>{t('Presek')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Upisano')}</th><th className="num">{t('Izlaznost')}</th><th className="num">{t('Opština javilo')}</th></tr></thead>
                                                <tbody>
                                                    {list.map((c) => (
                                                        <tr key={c.cutoff}><td className="lead">{t('Presek u')} {c.cutoff}</td><td className="num strong key" data-label={t('Izlaznost')}>{pct(c.turnout_pct)}</td><td className="num" data-label={t('Glasalo')}>{num(c.voters_voted)}</td><td className="num" data-label={t('Upisano')}>{num(c.registered_voters)}</td><td className="num" data-label={t('Opština javilo')}>{c.municipalities_reported}/{c.municipalities_total}</td></tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Card>
                                    <Card>
                                        <SectionHead title="Okruzi po presecima" right={
                                            <CsvButton
                                                filename={`izlaznost-okruzi-${slug}`}
                                                rows={districts.list ?? []}
                                                columns={[
                                                    { label: 'Šifra', value: (d) => String(d.district_code) },
                                                    { label: 'Okrug', value: (d) => districtName(String(d.district_code)) },
                                                    { label: 'Upisanih birača', value: (d) => d.registered_voters },
                                                    ...list.map((c) => ({ label: `${c.cutoff} %`, value: (d: DistrictTurnout) => d.cutoffs.find((x) => x.cutoff === c.cutoff)?.turnout_pct ?? '' })),
                                                ]}
                                            />
                                        } />
                                        <p className="muted mb-2 md:hidden">{t('Tabela se pomera levo i desno.')}</p>
                                        <div className="scroll-x">
                                            <table className="data min-w-[640px]">
                                                <thead><tr><th>{t('Okrug')}</th>{list.map((c) => <th key={c.cutoff} className="num">{c.cutoff}</th>)}</tr></thead>
                                                <tbody>
                                                    {(districts.list ?? []).map((d) => (
                                                        <tr key={String(d.district_code)}><td>{t(districtName(String(d.district_code)))}</td>{(d.cutoffs ?? []).map((c) => <td key={c.cutoff} className="num">{pct(c.turnout_pct)}</td>)}</tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Card>
                                </>
                            ) : (
                                <>
                                    <Card>
                                        <div className="grid gap-6 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
                                            <div className="card-flat self-start">
                                                <div className="eyebrow">{t('Upisanih birača u opštinama koje su javile presek')}</div>
                                                <div className="figure mt-2">{num(cutoff?.registered_voters)}</div>
                                                <div className="mt-5"><Donut value={cutoff?.turnout_pct} label={cutoff ? `${t('Glasalo do')} ${cutoff.cutoff}` : 'Glasalo'} sub={<>{num(cutoff?.voters_voted)} {t('birača')}</>} /></div>
                                                {cutoff && cutoff.registered_voters > 0 && cutoff.voters_voted !== null && (
                                                    <div className="muted mt-3">{t('Nije glasalo')}: {pct(100 - (cutoff.turnout_pct ?? 0))} ({num(cutoff.registered_voters - cutoff.voters_voted)})</div>
                                                )}
                                                {cutoff && <div className="muted mt-3">{t('Javilo')}: {cutoff.municipalities_reported} {t('od')} {cutoff.municipalities_total} {t('opština')}</div>}
                                                {final && <div className="t-label mt-4 border-t border-line pt-3 text-ink-2">{t('Konačna izlaznost po zapisnicima')}: <b className="text-ink">{pct(final.value)}</b></div>}
                                            </div>
                                            <div>
                                                <DistrictMap entries={entries} names={names} valueLabel={cutoff ? `Izlaznost do ${cutoff.cutoff}` : 'Izlaznost'} onSelect={(code) => navigate(`/${slug}/teritorija/${code}`)} />
                                                <p className="muted mt-2 text-center">{t('Okruzi bez biračkih mesta u ovom skupu podataka su sivi.')} {t('Granice')}: geoBoundaries (ODbL).</p>
                                            </div>
                                        </div>
                                    </Card>
                                    <Card>
                                        <SectionHead title={cutoff ? `Okruzi, presek u ${cutoff.cutoff}` : 'Okruzi'} right={
                                            <CsvButton
                                                filename={`izlaznost-okruzi-${cutoff?.cutoff.replace(':', '') ?? 'presek'}-${slug}`}
                                                rows={districts.list ?? []}
                                                columns={[
                                                    { label: 'Okrug', value: (d) => districtName(String(d.district_code)) },
                                                    { label: 'Upisanih birača', value: (d) => d.registered_voters },
                                                    { label: 'Glasalo', value: (d) => at(d)?.voters_voted ?? '' },
                                                    { label: 'Izlaznost %', value: (d) => at(d)?.turnout_pct ?? '' },
                                                ]}
                                            />
                                        } />
                                        <div className="scroll-x">
                                            <table className="data stack">
                                                <thead><tr><th>{t('Okrug')}</th><th className="num">{t('Upisanih')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th></tr></thead>
                                                <tbody>
                                                    {[...(districts.list ?? [])].sort((a, b) => (at(b)?.turnout_pct ?? 0) - (at(a)?.turnout_pct ?? 0)).map((d) => (
                                                        <tr key={String(d.district_code)}>
                                                            <td className="lead"><button type="button" className="link" onClick={() => navigate(`/${slug}/teritorija/${String(d.district_code)}`)}>{t(districtName(String(d.district_code)))}</button></td>
                                                            <td className="num strong key" data-label={t('Izlaznost')}>{pct(at(d)?.turnout_pct)}</td>
                                                            <td className="num" data-label={t('Glasalo')}>{num(at(d)?.voters_voted)}</td>
                                                            <td className="num" data-label={t('Upisanih')}>{num(d.registered_voters)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Card>
                                    <Accordion title={`${t('Opštine')}${cutoff ? `, ${t('presek u')} ${cutoff.cutoff}` : ''}`} right={<span>{municipalities.list?.length ?? 0} {t('opština')}</span>}>
                                        <div className="rounded-lg bg-white px-3 md:px-0">
                                            <table className="data stack">
                                                <thead><tr><th className="pl-3">{t('Opština')}</th><th className="num">{t('Upisano')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th></tr></thead>
                                                <tbody>
                                                    {(municipalities.list ?? []).map((m) => (
                                                        <tr key={m.code}>
                                                            <td className="lead md:pl-3">{t(m.name)} <span className="muted font-normal">({t(districtName(String(m.district_code)))})</span></td>
                                                            <td className="num strong key" data-label={t('Izlaznost')}>{pct(at(m)?.turnout_pct)}</td>
                                                            <td className="num" data-label={t('Glasalo')}>{num(at(m)?.voters_voted)}</td>
                                                            <td className="num" data-label={t('Upisano')}>{num(m.registered_voters)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Accordion>
                                </>
                            )}
                        </Band>
                    </>
                )}
            </WithFile>
        </div>
    );
}
