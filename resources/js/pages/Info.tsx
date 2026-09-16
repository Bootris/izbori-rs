import { useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { District, Incident, InfoLinkKey, IncidentSeverity, IncidentStatus, Municipality } from '@/types';
import { INCIDENT_CATEGORY_LABEL, INCIDENT_SEVERITY_LABEL, INCIDENT_STATUS_LABEL, dateTimeSec, num, pct, plural, searchKey, timeSec } from '@/lib/format';
import { WithFile } from '@/components/state';
import { CsvButton } from '@/components/Csv';
import { Band, Breadcrumbs, Card, PageTitle, SectionHead, Segmented, Stat } from '@/components/ui';

/* ------------------------------------------------------------------ hub */

interface InfoLink {
    label: string;
    /** internal route (relative to the election) or an external URL */
    to?: string;
    href?: string | null;
    note?: string;
}

function IconExternal() {
    return (
        <svg className="h-4 w-4 shrink-0 text-ink-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M7 17 17 7M9 7h8v8" />
        </svg>
    );
}

function LinkList({ title, items }: { title: string; items: InfoLink[] }) {
    const { slug } = useElection();
    const t = useT();
    const visible = items.filter((i) => i.to !== undefined || i.href);
    if (visible.length === 0) return null;
    return (
        <Card>
            <h2 className="eyebrow mb-3">{t(title)}</h2>
            <ul className="divide-y divide-line-2">
                {visible.map((item) => (
                    <li key={item.label} className="py-2.5">
                        {item.to !== undefined ? (
                            <Link className="link t-data flex items-start justify-between gap-3" to={`/${slug}${item.to ? `/${item.to}` : ''}`}>
                                <span>{t(item.label)}</span>
                            </Link>
                        ) : (
                            <a className="link t-data flex items-start justify-between gap-3" href={item.href ?? undefined} target="_blank" rel="noreferrer">
                                <span>{t(item.label)}</span>
                                <IconExternal />
                            </a>
                        )}
                        {item.note && <div className="muted mt-0.5">{t(item.note)}</div>}
                    </li>
                ))}
            </ul>
        </Card>
    );
}

/** The "Informacije" landing page: everything that is not a number, grouped like the Hungarian VTR menu. */
export function InfoHub() {
    const { site, election } = useElection();
    const t = useT();
    const incidents = useSnapshotList<Incident>('incidents', 'incidents.json');
    const link = (key: InfoLinkKey): string | null => site?.links?.[key] ?? null;
    const openCount = (incidents.list ?? []).filter((i) => i.status === 'open' || i.status === 'in_review').length;
    const isProportional = election.type !== 'presidential';

    return (
        <div>
            <PageTitle title="Informacije" sub={t('Podaci o izborima, pravila, rokovi i vanredni događaji na biračkim mestima.')} />
            <Band>
                <div className="grid gap-6 md:grid-cols-3">
                    <LinkList title="Opšte informacije" items={[
                        { label: 'Vanredni događaji na biračkim mestima', to: 'vanredni-dogadjaji', note: incidents.available ? (openCount > 0 ? `${openCount} ${plural(openCount, ['otvorena prijava', 'otvorene prijave', 'otvorenih prijava'])}` : 'Objavljene prijave sa biračkih mesta i šta je preduzeto.') : 'Prijave još nisu objavljene.' },
                        { label: 'Broj birača', to: 'biraci', note: 'Upisani birači po okruzima i opštinama.' },
                        { label: 'O podacima i metodologija', to: 'o-podacima', note: 'Kako nastaju objavljeni brojevi, otvoreni podaci.' },
                        ...(isProportional ? [{ label: 'Raspodela mandata', to: 'mandati', note: 'D\'Hondtov metod, cenzus, količnici.' }] : []),
                        { label: 'Saopštenja i vesti', href: link('news_url') },
                        { label: 'Zakoni i propisi', href: link('legislation_url') },
                        { label: 'Posmatrači', href: link('observers_url') },
                    ]} />
                    <LinkList title="Za podnosioce i kandidate" items={[
                        { label: 'Izborne liste', to: 'liste' },
                        { label: 'Podnosioci lista', to: 'podnosioci' },
                        { label: 'Izborni rokovi', to: 'rokovi' },
                        { label: 'Informacije za podnosioce lista', href: link('nominators_url') },
                        { label: 'Obrasci', href: link('forms_url') },
                    ]} />
                    <LinkList title="Izborni organi" items={[
                        { label: 'Republička izborna komisija', href: link('commission_url') },
                        { label: 'Zapisnici biračkih odbora', to: 'zapisnici', note: 'Svaki objavljeni zapisnik, sa skenom.' },
                        { label: 'Biračka mesta po teritoriji', to: 'teritorija' },
                        { label: 'Kontakt', href: site?.contact_email ? `mailto:${site.contact_email}` : null, note: site?.contact_email ?? undefined },
                    ]} />
                </div>
            </Band>
        </div>
    );
}

/* ------------------------------------------------------------ incidents */

type IncidentTab = 'sve' | 'otvorene' | 'zatvorene';

const SEVERITY_CLASS: Record<IncidentSeverity, string> = {
    low: 'badge-gray',
    medium: 'badge-blue',
    high: 'badge-amber',
    critical: 'badge-red',
};

const STATUS_CLASS: Record<IncidentStatus, string> = {
    open: 'badge-red',
    in_review: 'badge-amber',
    resolved: 'badge-green',
    dismissed: 'badge-gray',
};

const isOpen = (i: Incident) => i.status === 'open' || i.status === 'in_review';

function IncidentRow({ incident: i }: { incident: Incident }) {
    const { slug } = useElection();
    const t = useT();
    return (
        <li className="py-4">
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <span className="t-data font-semibold tabular-nums text-ink" title={t('Vreme prijave (server)')}>{dateTimeSec(i.reported_at)}</span>
                <span className={`badge ${SEVERITY_CLASS[i.severity]}`}>{t(INCIDENT_SEVERITY_LABEL[i.severity] ?? i.severity)}</span>
                <span className={`badge ${STATUS_CLASS[i.status]}`}>{t(INCIDENT_STATUS_LABEL[i.status] ?? i.status)}</span>
            </div>
            <div className="t-lead mt-1.5 font-semibold">
                {t(INCIDENT_CATEGORY_LABEL[i.category] ?? i.category)}
                <span className="font-normal text-ink-2">: </span>
                <Link className="link" to={`/${slug}/biracko-mesto/${i.station_id}`}>BM {i.station_number} {t(i.station_name)}</Link>
                <span className="font-normal text-ink-2">, {t(i.municipality_name)}</span>
            </div>
            <p className="t-data mt-1.5 text-ink-2">{t(i.description)}</p>
            <p className="muted mt-1.5">{t('Događaj')}: {timeSec(i.occurred_at)}{i.resolved_at && <>, {t('zatvoreno')}: {timeSec(i.resolved_at)}</>}</p>
            {i.resolution && (
                <p className="t-data mt-2 rounded-lg bg-line-2 px-3.5 py-2.5"><b>{t('Šta je preduzeto')}:</b> {t(i.resolution)}</p>
            )}
        </li>
    );
}

/** Public log of election-day reports. Only what the commission published; the reporter never leaves the admin. */
export function Incidents() {
    const { slug } = useElection();
    const t = useT();
    const incidents = useSnapshotList<Incident>('incidents', 'incidents.json');
    const [tab, setTab] = useState<IncidentTab>('sve');
    const [query, setQuery] = useState('');
    const q = searchKey(query.trim());
    const all = incidents.list ?? [];
    const rows = all
        .filter((i) => tab === 'sve' || (tab === 'otvorene' ? isOpen(i) : !isOpen(i)))
        .filter((i) => !q || searchKey(`${i.municipality_name} ${i.station_number} ${i.station_name}`).includes(q));
    const open = all.filter(isOpen).length;
    const serious = all.filter((i) => i.severity === 'high' || i.severity === 'critical').length;

    return (
        <div>
            <Breadcrumbs items={[{ label: 'Informacije', to: `/${slug}/informacije` }, { label: 'Vanredni događaji' }]} />
            <PageTitle
                title="Vanredni događaji na biračkim mestima"
                meta={incidents.meta}
                showProcessed={false}
                sub={t('Prijave koje su članovi biračkih odbora i izbornih komisija uneli u sistem u trenutku događaja. Vreme prijave beleži server. Objavljuju se samo prijave koje je izborna komisija označila za objavu.')}
            />
            <Band>
                <WithFile state={incidents} unavailable={t('Prijave sa biračkih mesta još nisu objavljene.')}>
                    {({ list }) => (
                        <>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <Stat label="Objavljenih prijava" value={num(list.length)} />
                                <Stat label="Otvorenih" value={num(open)} sub={t('u obradi ili čeka reakciju')} />
                                <Stat label="Zatvorenih" value={num(list.length - open)} sub={t('rešene i odbačene')} />
                                <Stat label="Visoke i kritične" value={num(serious)} />
                            </div>
                            <Card>
                                <SectionHead title="Prijave" right={
                                    <CsvButton
                                        filename={`vanredni-dogadjaji-${slug}`}
                                        rows={rows}
                                        columns={[
                                            { label: 'Prijavljeno', value: (i) => i.reported_at },
                                            { label: 'Događaj', value: (i) => i.occurred_at },
                                            { label: 'Biračko mesto', value: (i) => i.station_id },
                                            { label: 'Naziv', value: (i) => i.station_name },
                                            { label: 'Opština', value: (i) => i.municipality_name },
                                            { label: 'Vrsta', value: (i) => INCIDENT_CATEGORY_LABEL[i.category] ?? i.category },
                                            { label: 'Ozbiljnost', value: (i) => INCIDENT_SEVERITY_LABEL[i.severity] ?? i.severity },
                                            { label: 'Status', value: (i) => INCIDENT_STATUS_LABEL[i.status] ?? i.status },
                                            { label: 'Opis', value: (i) => i.description },
                                            { label: 'Preduzeto', value: (i) => i.resolution ?? '' },
                                        ]}
                                    />
                                } />
                                <div className="flex flex-wrap items-center gap-3">
                                    <Segmented<IncidentTab> label="Status prijave" value={tab} onChange={setTab} options={[{ value: 'sve', label: 'Sve' }, { value: 'otvorene', label: 'Otvorene' }, { value: 'zatvorene', label: 'Zatvorene' }]} />
                                    <input className="input sm:max-w-xs" placeholder={t('Opština ili biračko mesto')} value={query} onChange={(e) => setQuery(e.target.value)} aria-label={t('Pretraga prijava')} />
                                </div>
                                {list.length === 0 ? (
                                    <p className="muted mt-5">{t('Nema objavljenih vanrednih događaja.')}</p>
                                ) : rows.length === 0 ? (
                                    <p className="muted mt-5">{t('Nijedna prijava ne odgovara filteru.')}</p>
                                ) : (
                                    <ol className="mt-3 divide-y divide-line-2">
                                        {rows.map((i) => <IncidentRow key={i.id} incident={i} />)}
                                    </ol>
                                )}
                            </Card>
                        </>
                    )}
                </WithFile>
            </Band>
        </div>
    );
}

/** Compact list of the public reports of one polling station (station page). */
export function StationIncidents({ stationId }: { stationId: string }) {
    const t = useT();
    const incidents = useSnapshotList<Incident>('incidents', 'incidents.json');
    const rows = (incidents.list ?? []).filter((i) => i.station_id === stationId);
    if (rows.length === 0) return null;
    return (
        <Card>
            <h2 className="mb-1">{t('Vanredni događaji')}</h2>
            <p className="muted">{t('Objavljene prijave sa ovog biračkog mesta.')}</p>
            <ol className="divide-y divide-line-2">
                {rows.map((i) => <IncidentRow key={i.id} incident={i} />)}
            </ol>
        </Card>
    );
}

/* --------------------------------------------------------------- voters */

function VotersTable<T extends { code: string; name: string; stations: number; registered_voters: number }>({ rows, total, extra, linkTo }: { rows: T[]; total: number; extra?: (r: T) => ReactNode; linkTo: (r: T) => string }) {
    const t = useT();
    return (
        <div className="scroll-x">
            <table className="data stack min-w-full md:min-w-[560px]">
                <thead><tr><th>{t('Naziv')}</th><th className="num">{t('Biračkih mesta')}</th><th className="num">{t('Upisanih birača')}</th><th className="num">{t('Udeo')}</th></tr></thead>
                <tbody>
                    {rows.map((r) => (
                        <tr key={r.code}>
                            <td className="lead"><Link className="link" to={linkTo(r)}>{t(r.name)}</Link>{extra && <div className="muted font-normal">{extra(r)}</div>}</td>
                            <td className="num" data-label={t('Biračkih mesta')}>{num(r.stations)}</td>
                            <td className="num key" data-label={t('Upisanih birača')}>{num(r.registered_voters)}</td>
                            <td className="num" data-label={t('Udeo')}>{pct(total > 0 ? (r.registered_voters / total) * 100 : 0)}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="font-semibold">
                        <td className="lead">{t('Ukupno')}</td>
                        <td className="num" data-label={t('Biračkih mesta')}>{num(rows.reduce((s, r) => s + r.stations, 0))}</td>
                        <td className="num key" data-label={t('Upisanih birača')}>{num(rows.reduce((s, r) => s + r.registered_voters, 0))}</td>
                        <td className="num" data-label={t('Udeo')}>{pct(rows.length ? (rows.reduce((s, r) => s + r.registered_voters, 0) / total) * 100 : 0)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

/** Registered voters per district, with a drill-down into the municipalities of one district. */
export function Voters() {
    const { slug } = useElection();
    const t = useT();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    const [district, setDistrict] = useState('');
    const total = (districts.list ?? []).reduce((s, d) => s + d.registered_voters, 0);
    const chosen = districts.list?.find((d) => d.code === district);
    const munis = (municipalities.list ?? []).filter((m) => m.district_code === district);

    return (
        <div>
            <Breadcrumbs items={[{ label: 'Informacije', to: `/${slug}/informacije` }, { label: 'Broj birača' }]} />
            <PageTitle title="Broj birača" meta={districts.meta} sub={t('Birači upisani u izvode iz Jedinstvenog biračkog spiska, zbirno po biračkim mestima, po stanju na dan zaključenja spiska.')} />
            <Band>
                <WithFile state={districts} unavailable={t('Registar još nije objavljen.')}>
                    {({ list }) => (
                        <>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <Stat label="Upisanih birača" value={num(total)} wide />
                                <Stat label="Biračkih mesta" value={num(list.reduce((s, d) => s + d.stations, 0))} />
                                <Stat label="Okruga" value={num(list.length)} sub={`${num(list.reduce((s, d) => s + d.municipalities, 0))} ${t('opština')}`} />
                            </div>
                            <Card>
                                <SectionHead title="Po okruzima" right={
                                    <CsvButton filename={`biraci-okruzi-${slug}`} rows={list} columns={[
                                        { label: 'Šifra', value: (d) => d.code },
                                        { label: 'Okrug', value: (d) => d.name },
                                        { label: 'Opština', value: (d) => d.municipalities },
                                        { label: 'Biračkih mesta', value: (d) => d.stations },
                                        { label: 'Upisanih birača', value: (d) => d.registered_voters },
                                    ]} />
                                } />
                                <VotersTable rows={list} total={total} extra={(d) => `${num(d.municipalities)} ${t(plural(d.municipalities, ['opština', 'opštine', 'opština']))}`} linkTo={(d) => `/${slug}/teritorija/${d.code}`} />
                            </Card>
                            <Card>
                                <SectionHead title="Po opštinama" right={
                                    <select className="select" value={district} onChange={(e) => setDistrict(e.target.value)} aria-label={t('Okrug')}>
                                        <option value="">{t('Izaberite okrug')}</option>
                                        {list.map((d) => <option key={d.code} value={d.code}>{t(d.name)}</option>)}
                                    </select>
                                } />
                                {chosen ? (
                                    <VotersTable rows={munis} total={chosen.registered_voters} linkTo={(m) => `/${slug}/teritorija/${m.district_code}/${m.code}`} />
                                ) : (
                                    <p className="muted">{t('Izaberite okrug da vidite opštine.')}</p>
                                )}
                            </Card>
                        </>
                    )}
                </WithFile>
            </Band>
        </div>
    );
}
