import { Link } from 'react-router-dom';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { District, Municipality, MunicipalityResults } from '@/types';
import { num, pct, plural, shortName } from '@/lib/format';
import { Accordion, RingIndicator, Swatch } from './ui';

/** District rows that open into their municipalities with processed share, turnout and the leading list. */
export function DistrictAccordion({ districts, slug }: { districts: District[]; slug: string }) {
    const t = useT();
    return (
        <div className="space-y-2">
            {districts.map((d) => (
                <Accordion
                    key={d.code}
                    title={<Link className="link text-ink hover:text-primary" to={`/${slug}/teritorija/${d.code}`} onClick={(e) => e.stopPropagation()}>{t(d.name)}</Link>}
                    right={<span>{d.municipalities} {t(plural(d.municipalities, ['opština', 'opštine', 'opština']))}</span>}
                >
                    <MunicipalityTable district={d} slug={slug} />
                </Accordion>
            ))}
        </div>
    );
}

/** Municipalities of one district with their results, usable on its own (district page) or inside the accordion. */
export function MunicipalityTable({ district, slug }: { district: District; slug: string }) {
    const t = useT();
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    if (district.stations === 0) return <p className="muted px-3 py-2">{t('Nema biračkih mesta u ovom skupu podataka.')}</p>;
    return (
        <div className="overflow-x-auto rounded-lg bg-white">
            <table className="data min-w-[560px]">
                <thead>
                    <tr><th className="pl-3">{t('Opština')}</th><th className="num">{t('Obrađeno')}</th><th className="num">{t('Izlaznost')}</th><th>{t('Vodeća lista')}</th></tr>
                </thead>
                <tbody>
                    {(municipalities.list ?? []).filter((m) => m.district_code === district.code).map((m) => <MunicipalityRow key={m.code} m={m} slug={slug} />)}
                </tbody>
            </table>
        </div>
    );
}

function MunicipalityRow({ m, slug }: { m: Municipality; slug: string }) {
    const t = useT();
    const results = useSnapshotData<MunicipalityResults>('results', `${m.district_code}/results-${m.district_code}-${m.code}.json`, { skip: m.stations === 0 });
    const r = results.item;
    const leader = r ? [...r.lists].sort((a, b) => b.votes - a.votes)[0] : undefined;
    return (
        <tr>
            <td className="pl-3"><Link className="link" to={`/${slug}/teritorija/${m.district_code}/${m.code}`}>{t(m.name)}</Link><div className="muted">{num(m.stations)} {t('biračkih mesta')}</div></td>
            <td className="num">{r ? <span className="inline-flex items-center gap-1.5">{pct(r.processed)}<RingIndicator value={r.processed} size={16} /></span> : '-'}</td>
            <td className="num">{r ? pct(r.turnout_pct) : '-'}</td>
            <td>{leader && leader.votes > 0 ? <span className="inline-flex items-center gap-2"><Swatch color={leader.color} index={leader.number - 1} />{t(shortName(leader.name, leader.short_name))} <span className="text-ink-3">{pct(leader.votes_pct)}</span></span> : <span className="muted">{r ? t('nema obrađenih zapisnika') : ''}</span>}</td>
        </tr>
    );
}
