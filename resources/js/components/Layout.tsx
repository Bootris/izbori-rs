import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { env } from '@/env';
import { useElection } from '@/app/election-context';
import { useAppDispatch, useAppSelector, useT } from '@/app/hooks';
import { setScript } from '@/app/ui-slice';
import { STATUS_LABEL, dateLong, dateTime } from '@/lib/format';
import { Search } from './Search';
import { LiveStrip } from './LiveStrip';

const NAV: Array<{ to: string; label: string; end?: boolean }> = [
    { to: '', label: 'Početna', end: true },
    { to: 'skupstina', label: 'Sastav skupštine' },
    { to: 'liste', label: 'Izborne liste' },
    { to: 'podnosioci', label: 'Podnosioci' },
    { to: 'teritorija', label: 'Po teritoriji' },
    { to: 'izlaznost', label: 'Izlaznost' },
    { to: 'zapisnici', label: 'Zapisnici' },
    { to: 'mandati', label: 'Mandati' },
    { to: 'rokovi', label: 'Rokovi' },
    { to: 'informacije', label: 'Informacije' },
];

/** Ballot-box mark used as the site logo (no external image). */
function BrandMark() {
    return (
        <svg className="h-10 w-10 shrink-0 md:h-11 md:w-11" viewBox="0 0 44 44" aria-hidden="true">
            <rect width="44" height="44" rx="8" fill="var(--color-primary-soft)" />
            <path d="M11 22h22v11a2 2 0 0 1-2 2H13a2 2 0 0 1-2-2V22Z" fill="var(--color-primary)" />
            <path d="M9 18h26v4H9z" fill="var(--color-primary-dark)" />
            <path d="M18 8h12l2 10H16l2-10Z" fill="#fff" stroke="var(--color-primary-dark)" strokeWidth="1.5" />
            <path d="m21 13 2 2 4-4" fill="none" stroke="var(--color-primary)" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

export function Layout() {
    const { slug, election, index, config, site } = useElection();
    const t = useT();
    const script = useAppSelector((s) => s.ui.script);
    const dispatch = useAppDispatch();
    const navigate = useNavigate();
    const siteName = site?.name ?? env.siteName;
    const statusClass = election.status === 'final' ? 'badge-green' : election.status === 'counting' ? 'badge-blue' : 'badge-gray';

    return (
        <div className="min-h-screen bg-white">
            <a className="skip" href="#sadrzaj">{t('Pređi na sadržaj')}</a>
            <a className="skip" href="#navigacija">{t('Pređi na navigaciju')}</a>

            <header className="border-b border-line bg-white">
                <div className="container-x flex flex-wrap items-center gap-x-6 gap-y-3 pt-3">
                    <Link to={`/${slug}`} className="flex w-full min-w-0 items-start gap-3 text-ink no-underline md:w-auto">
                        <BrandMark />
                        <span className="min-w-0 flex-1">
                            <span className="block text-[clamp(1rem,4vw,1.25rem)] font-bold leading-tight">{t(election.name)}</span>
                            <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span className="muted">{dateLong(election.election_date)}</span>
                                <span className={`badge ${statusClass}`}>{t(STATUS_LABEL[election.status] ?? election.status)}</span>
                            </span>
                        </span>
                    </Link>
                    <div className="flex w-full items-center gap-3 md:ml-auto md:w-auto">
                        <div className="min-w-0 flex-1 md:w-72 md:flex-none"><Search /></div>
                        <div className="inline-flex shrink-0 rounded-lg bg-line-2 p-1 text-sm font-semibold" role="group" aria-label={t('Pismo')}>
                            <button type="button" onClick={() => dispatch(setScript('lat'))} className={`rounded-md px-2.5 py-1.5 ${script === 'lat' ? 'bg-primary text-white' : 'text-ink-2'}`} aria-pressed={script === 'lat'}>LAT</button>
                            <button type="button" onClick={() => dispatch(setScript('cyr'))} className={`rounded-md px-2.5 py-1.5 ${script === 'cyr' ? 'bg-primary text-white' : 'text-ink-2'}`} aria-pressed={script === 'cyr'}>ЋИР</button>
                        </div>
                    </div>
                </div>

                <nav id="navigacija" className="container-x navfade mt-2 overflow-x-auto" aria-label={t('Glavna navigacija')}>
                    <ul className="navscroll flex gap-1 whitespace-nowrap pb-2">
                        {NAV.map((item) => (
                            <li key={item.to}>
                                <NavLink to={item.to === '' ? `/${slug}` : `/${slug}/${item.to}`} end={item.end} className={({ isActive }) => `tab ${isActive ? 'tab-active' : ''}`}>
                                    {t(item.label)}
                                </NavLink>
                            </li>
                        ))}
                    </ul>
                </nav>
            </header>

            <LiveStrip />

            {(index.elections.length > 1 || site?.notice) && (
                <div className="border-b border-line bg-line-2">
                    <div className="container-x flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2">
                        {site?.notice ? <span className="t-label text-amber-900">{t(site.notice)}</span> : <span className="hidden md:block" />}
                        {index.elections.length > 1 && (
                            <label className="flex min-w-0 max-w-full flex-1 items-center gap-2 md:flex-none">
                                <span className="muted shrink-0">{t('Izbori')}:</span>
                                <select className="select min-w-0 flex-1 md:flex-none" value={slug} onChange={(e) => navigate(`/${e.target.value}`)}>
                                    {index.elections.map((e) => <option key={e.slug} value={e.slug}>{t(e.name)}</option>)}
                                </select>
                            </label>
                        )}
                    </div>
                </div>
            )}

            <main id="sadrzaj" className="container-x py-5 md:py-8">
                <Outlet />
            </main>

            <footer className="border-t border-line bg-white">
                <div className="container-x t-label py-6">
                    <p className="font-semibold text-ink-2">{t(siteName)}{(site?.publisher ?? env.publisher) ? `, ${t(site?.publisher ?? env.publisher)}` : ''}</p>
                    <p className="mt-1 text-ink-3">
                        {t('Verzije podataka')}: registar {config.registry ?? '-'}, izlaznost {config.turnout ?? '-'}, rezultati {config.results ?? '-'}, prijave {config.incidents ?? '-'}. {t('Ažurirano')} {dateTime(config.updated)}.
                    </p>
                    <p className="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        <a className="link font-normal" href={`${env.dataUrl}/index.json`}>index.json</a>
                        <a className="link font-normal" href={`${env.dataUrl}/${slug}/config.json`}>config.json</a>
                        <Link className="link font-normal" to={`/${slug}/o-podacima`}>{t('otvoreni podaci i metodologija')}</Link>
                    </p>
                </div>
            </footer>
        </div>
    );
}
