import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { District, DistrictTurnout, MunicipalityTurnout, TurnoutCutoff } from '@/types';
import { num, pct } from '@/lib/format';
import { WithFile } from '@/components/state';
import { TurnoutChart } from '@/components/TurnoutChart';
import { PageTitle } from '@/components/ui';

export function Turnout() {
    const { config } = useElection();
    const t = useT();
    const country = useSnapshotList<TurnoutCutoff>('turnout', 'turnout-country.json');
    const districts = useSnapshotList<DistrictTurnout>('turnout', 'turnout-districts.json');
    const municipalities = useSnapshotList<MunicipalityTurnout>('turnout', 'turnout-municipalities.json');
    const registry = useSnapshotList<District>('registry', 'districts.json');
    const districtName = (code: string) => registry.list?.find((d) => d.code === code)?.name ?? code;
    const cutoffs = country.list?.map((c) => c.cutoff) ?? [];

    return (
        <div className="space-y-6">
            <PageTitle title="Izlaznost" meta={country.meta}>{config.turnout && <>{t('Kumulativno, po vremenskim presecima na dan glasanja.')}</>}</PageTitle>
            <WithFile state={country} unavailable={t('Izlaznost još nije objavljena.')}>
                {({ list }) => (
                    <>
                        <div className="card">
                            <TurnoutChart cutoffs={list} />
                            <div className="overflow-x-auto">
                                <table className="data mt-3">
                                    <thead><tr><th>{t('Presek')}</th><th className="num">{t('Glasalo')}</th><th className="num">{t('Upisano')}</th><th className="num">%</th><th className="num">{t('Opština javilo')}</th></tr></thead>
                                    <tbody>
                                        {list.map((c) => (
                                            <tr key={c.cutoff}><td>{c.cutoff}</td><td className="num">{num(c.voters_voted)}</td><td className="num">{num(c.registered_voters)}</td><td className="num font-semibold">{pct(c.turnout_pct)}</td><td className="num">{c.municipalities_reported}/{c.municipalities_total}</td></tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div className="card overflow-x-auto">
                            <h2 className="mb-3">{t('Po okruzima')}</h2>
                            <table className="data">
                                <thead><tr><th>{t('Okrug')}</th>{cutoffs.map((c) => <th key={c} className="num">{c}</th>)}</tr></thead>
                                <tbody>
                                    {(districts.list ?? []).map((d) => (
                                        <tr key={d.district_code}><td>{t(districtName(d.district_code))}</td>{d.cutoffs.map((c) => <td key={c.cutoff} className="num">{pct(c.turnout_pct)}</td>)}</tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="card overflow-x-auto">
                            <h2 className="mb-3">{t('Po opštinama')}</h2>
                            <table className="data">
                                <thead><tr><th>{t('Opština')}</th><th className="num">{t('Upisano')}</th>{cutoffs.map((c) => <th key={c} className="num">{c}</th>)}</tr></thead>
                                <tbody>
                                    {(municipalities.list ?? []).map((m) => (
                                        <tr key={m.code}><td>{t(m.name)} <span className="muted">({t(districtName(m.district_code))})</span></td><td className="num">{num(m.registered_voters)}</td>{m.cutoffs.map((c) => <td key={c.cutoff} className="num">{pct(c.turnout_pct)}</td>)}</tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </WithFile>
        </div>
    );
}
