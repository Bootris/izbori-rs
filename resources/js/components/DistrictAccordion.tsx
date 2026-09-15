import { Link } from 'react-router-dom';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, DistrictResults, Municipality, MunicipalityResults } from '@/types';
import { num, pct, plural, shortName } from '@/lib/format';
import { leaderOf } from '@/lib/results';
import { Accordion, RingIndicator, Swatch } from './ui';

/** District rows that already carry their own numbers and open into their municipalities. */
export function DistrictAccordion({ districts, slug }: { districts: District[]; slug: string }) {
    const t = useT();
    const results = useSnapshotList<DistrictResults>('results', 'results-districts.json');
    const byCode = new Map((results.list ?? []).map((d) => [String(d.district_code), d]));

    return (
        <div className="space-y-2">
            {districts.map((d) => {
                const r = byCode.get(d.code);
                const leader = r ? leaderOf(r.lists) : undefined;
                return (
                    <Accordion
                        key={d.code}
                        title={<Link className="link text-ink hover:text-primary" to={`/${slug}/teritorija/${d.code}`} onClick={(e) => e.stopPropagation()}>{t(d.name)}</Link>}
                        right={
                            <span className="flex flex-wrap items-center justify-end gap-x-4 gap-y-1">
                                {leader && <span className="flex items-center gap-1.5"><Swatch color={leader.color} index={leader.number - 1} />{t(shortName(leader.name, leader.short_name))} <span className="text-ink-3">{pct(leader.votes_pct)}</span></span>}
                                {r && r.stations_total > 0 && <span className="hidden text-ink-3 md:inline">{t('izlaznost')} {pct(r.turnout_pct)}</span>}
                                {r && r.stations_total > 0
                                    ? <span className="inline-flex items-center gap-1.5">{pct(r.processed)}<RingIndicator value={r.processed} size={16} /></span>
                                    : <span>{d.municipalities} {t(plural(d.municipalities, ['opština', 'opštine', 'opština']))}</span>}
                            </span>
                        }
                    >
                        <MunicipalityTable district={d} slug={slug} />
                    </Accordion>
                );
            })}
        </div>
    );
}

/** Municipalities of one district, on the district page and inside the accordion. */
export function MunicipalityTable({ district, slug }: { district: District; slug: string }) {
    const t = useT();
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    if (district.stations === 0) return <p className="muted px-3 py-2">{t('Nema biračkih mesta u ovom skupu podataka.')}</p>;
    return (
        <div className="rounded-lg bg-white px-3 md:px-0">
            <table className="data stack">
                <thead>
                    <tr><th className="pl-3">{t('Opština')}</th><th className="num">{t('Obrađeno')}</th><th className="num">{t('Izlaznost')}</th><th>{t('Vodeća lista')}</th></tr>
                </thead>
                <tbody>
                    {(municipalities.list ?? []).filter((m) => String(m.district_code) === district.code).map((m) => <MunicipalityRow key={m.code} m={m} slug={slug} />)}
                </tbody>
            </table>
        </div>
    );
}

function MunicipalityRow({ m, slug }: { m: Municipality; slug: string }) {
    const t = useT();
    const results = useSnapshotData<MunicipalityResults>('results', `${m.district_code}/results-${m.district_code}-${m.code}.json`, { skip: m.stations === 0 });
    const r = results.item;
    const leader = r ? leaderOf(r.lists) : undefined;
    return (
        <tr>
            <td className="lead md:pl-3"><Link className="link" to={`/${slug}/teritorija/${m.district_code}/${m.code}`}>{t(m.name)}</Link><div className="muted font-normal">{num(m.stations)} {t('biračkih mesta')}</div></td>
            <td className="num" data-label={t('Obrađeno')}>{r ? <span className="inline-flex items-center gap-1.5">{pct(r.processed)}<RingIndicator value={r.processed} size={16} /></span> : '-'}</td>
            <td className="num" data-label={t('Izlaznost')}>{r ? pct(r.turnout_pct) : '-'}</td>
            <td data-label={t('Vodeća lista')}>{leader ? <span className="inline-flex items-center gap-2"><Swatch color={leader.color} index={leader.number - 1} />{t(shortName(leader.name, leader.short_name))} <span className="text-ink-3">{pct(leader.votes_pct)}</span></span> : <span className="muted">{r ? t('nema obrađenih zapisnika') : ''}</span>}</td>
        </tr>
    );
}
