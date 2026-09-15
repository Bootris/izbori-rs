import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, DistrictTurnout, MunicipalityTurnout, ResultsSummary, TurnoutCutoff } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { TurnoutChart } from '@/components/TurnoutChart';
import { DistrictMap } from '@/components/DistrictMap';
import { Accordion, Band, Card, Donut, PageTitle, Segmented } from '@/components/ui';

type Tab = 'mapa' | 'tok';

export function Turnout() {
    const { slug } = useElection();
    const t = useT();
    const navigate = useNavigate();
    const country = useSnapshotList<TurnoutCutoff>('turnout', 'turnout-country.json');
    const districts = useSnapshotList<DistrictTurnout>('turnout', 'turnout-districts.json');
    const municipalities = useSnapshotList<MunicipalityTurnout>('turnout', 'turnout-municipalities.json');
    const registry = useSnapshotList<District>('registry', 'districts.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const [tab, setTab] = useState<Tab>('mapa');

    const districtName = (code: string) => registry.list?.find((d) => d.code === code)?.name ?? code;
    const cutoffs = country.list?.map((c) => c.cutoff) ?? [];
    const last = country.list?.filter((c) => c.voters_voted !== null).at(-1);
    const lastOf = (d: DistrictTurnout) => d.cutoffs.filter((c) => c.voters_voted !== null).at(-1);
    const names: Record<string, string> = {};
    const values: Record<string, number | null> = {};
    (registry.list ?? []).forEach((d) => { names[d.code] = d.name; });
    // district_code arrives as a number for codes without a leading zero; compare as text
    (districts.list ?? []).forEach((d) => { values[String(d.district_code)] = lastOf(d)?.turnout_pct ?? null; });
    const final = summary.item && summary.item.processed > 0 ? { label: t('zatvaranje'), value: summary.item.turnout_pct } : null;

    return (
        <div>
            <PageTitle title="Izlaznost" meta={country.meta} sub={t('Preseci koje izborne komisije javljaju tokom dana glasanja i konačna izlaznost iz zapisnika.')} />
            <WithFile state={country} unavailable={t('Izlaznost još nije objavljena.')}>
                {({ list }) => (
                    <>
                        <div className="mb-5"><Segmented options={[{ value: 'mapa', label: last ? `Presek u ${last.cutoff}` : 'Poslednji presek' }, { value: 'tok', label: 'Tok izlaznosti' }]} value={tab} onChange={setTab} label="Prikaz izlaznosti" /></div>
                        <Band>
                            {tab === 'mapa' ? (
                                <>
                                    <Card>
                                        <div className="grid gap-6 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
                                            <div className="card-flat self-start">
                                                <div className="text-sm text-ink-2">{t('Upisanih birača u opštinama koje su javile presek')}</div>
                                                <div className="mt-1 text-2xl font-bold tabular-nums">{num(last?.registered_voters)}</div>
                                                <div className="mt-5"><Donut value={last?.turnout_pct} label={last ? `${t('Glasalo do')} ${last.cutoff}` : 'Glasalo'} sub={<>{num(last?.voters_voted)} {t('birača')}</>} /></div>
                                                {last && last.registered_voters > 0 && last.voters_voted !== null && (
                                                    <div className="muted mt-3">{t('Nije glasalo')}: {pct(100 - (last.turnout_pct ?? 0))} ({num(last.registered_voters - last.voters_voted)})</div>
                                                )}
                                                {last && <div className="muted mt-3">{t('Javilo')}: {last.municipalities_reported} {t('od')} {last.municipalities_total} {t('opština')}</div>}
                                                {final && <div className="mt-4 border-t border-line pt-3 text-sm text-ink-2">{t('Konačna izlaznost po zapisnicima')}: <b className="text-ink">{pct(final.value)}</b></div>}
                                            </div>
                                            <div>
                                                <DistrictMap values={values} names={names} valueLabel={last ? `Izlaznost do ${last.cutoff}` : 'Izlaznost'} onSelect={(code) => navigate(`/${slug}/teritorija/${code}`)} />
                                                <p className="muted mt-2 text-center">{t('Okruzi na Kosovu i Metohiji nemaju biračka mesta u ovom skupu podataka.')} {t('Granice')}: geoBoundaries (ODbL).</p>
                                            </div>
                                        </div>
                                    </Card>
                                    <Card>
                                        <h2 className="mb-4">{t('Po okruzima')}</h2>
                                        <div className="overflow-x-auto">
                                            <table className="data">
                                                <thead><tr><th>{t('Okrug')}</th><th className="num">{t('Upisanih')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Izlaznost')}</th></tr></thead>
                                                <tbody>
                                                    {[...(districts.list ?? [])].sort((a, b) => (lastOf(b)?.turnout_pct ?? 0) - (lastOf(a)?.turnout_pct ?? 0)).map((d) => {
                                                        const c = lastOf(d);
                                                        return (
                                                            <tr key={d.district_code}>
                                                                <td><button type="button" className="link" onClick={() => navigate(`/${slug}/teritorija/${String(d.district_code)}`)}>{t(districtName(String(d.district_code)))}</button></td>
                                                                <td className="num">{num(d.registered_voters)}</td>
                                                                <td className="num">{num(c?.voters_voted)}</td>
                                                                <td className="num strong">{pct(c?.turnout_pct)}</td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Card>
                                </>
                            ) : (
                                <>
                                    <Card>
                                        <h2 className="mb-1">{t('Republika Srbija')}</h2>
                                        <p className="muted mb-4">{t('Kumulativna izlaznost po presecima; poslednja tačka je konačna izlaznost iz verifikovanih zapisnika.')}</p>
                                        <TurnoutChart cutoffs={list} final={final} />
                                        <div className="mt-4 overflow-x-auto">
                                            <table className="data">
                                                <thead><tr><th>{t('Presek')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Upisano')}</th><th className="num">{t('Izlaznost')}</th><th className="num">{t('Opština javilo')}</th></tr></thead>
                                                <tbody>
                                                    {list.map((c) => (
                                                        <tr key={c.cutoff}><td>{c.cutoff}</td><td className="num">{num(c.voters_voted)}</td><td className="num">{num(c.registered_voters)}</td><td className="num strong">{pct(c.turnout_pct)}</td><td className="num">{c.municipalities_reported}/{c.municipalities_total}</td></tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Card>
                                    <Card>
                                        <h2 className="mb-4">{t('Po okruzima')}</h2>
                                        <div className="overflow-x-auto">
                                            <table className="data min-w-[640px]">
                                                <thead><tr><th>{t('Okrug')}</th>{cutoffs.map((c) => <th key={c} className="num">{c}</th>)}</tr></thead>
                                                <tbody>
                                                    {(districts.list ?? []).map((d) => (
                                                        <tr key={d.district_code}><td>{t(districtName(String(d.district_code)))}</td>{d.cutoffs.map((c) => <td key={c.cutoff} className="num">{pct(c.turnout_pct)}</td>)}</tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </Card>
                                    <Accordion title={t('Po opštinama')} right={<span>{municipalities.list?.length ?? 0} {t('opština')}</span>}>
                                        <div className="overflow-x-auto rounded-lg bg-white">
                                            <table className="data min-w-[720px]">
                                                <thead><tr><th className="pl-3">{t('Opština')}</th><th className="num">{t('Upisano')}</th>{cutoffs.map((c) => <th key={c} className="num">{c}</th>)}</tr></thead>
                                                <tbody>
                                                    {(municipalities.list ?? []).map((m) => (
                                                        <tr key={m.code}><td className="pl-3">{t(m.name)} <span className="muted">({t(districtName(String(m.district_code)))})</span></td><td className="num">{num(m.registered_voters)}</td>{m.cutoffs.map((c) => <td key={c.cutoff} className="num">{pct(c.turnout_pct)}</td>)}</tr>
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
