import { NavLink, Link, Outlet, useNavigate } from 'react-router-dom';
import { env } from '@/env';
import { useElection } from '@/app/election-context';
import { useAppDispatch, useAppSelector, useT } from '@/app/hooks';
import { setScript } from '@/app/ui-slice';
import { ELECTION_TYPE_LABEL, STATUS_LABEL, date, dateTime } from '@/lib/format';

const NAV: Array<{ to: string; label: string; end?: boolean }> = [
    { to: '', label: 'Pregled', end: true },
    { to: 'skupstina', label: 'Skupština' },
    { to: 'liste', label: 'Liste' },
    { to: 'mandati', label: 'Mandati' },
    { to: 'izlaznost', label: 'Izlaznost' },
    { to: 'teritorija', label: 'Teritorija' },
    { to: 'zapisnici', label: 'Zapisnici' },
    { to: 'rokovi', label: 'Rokovi' },
    { to: 'o-podacima', label: 'O podacima' },
];

export function Layout() {
    const { slug, election, index, config, site } = useElection();
    const t = useT();
    const script = useAppSelector((s) => s.ui.script);
    const dispatch = useAppDispatch();
    const navigate = useNavigate();
    const siteName = site?.name ?? env.siteName;

    return (
        <div className="min-h-screen">
            <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
                    <Link to={`/${slug}`} className="text-lg font-bold tracking-tight text-primary no-underline dark:text-blue-400">
                        {t(siteName)}
                    </Link>
                    <div className="min-w-0 flex-1 text-sm">
                        <div className="truncate font-medium">{t(election.name)}</div>
                        <div className="muted truncate">
                            {t(ELECTION_TYPE_LABEL[election.type] ?? election.type)} · {date(election.election_date)} ·{' '}
                            <span className={election.status === 'final' ? 'badge badge-green' : election.status === 'counting' ? 'badge badge-blue' : 'badge badge-gray'}>
                                {t(STATUS_LABEL[election.status] ?? election.status)}
                            </span>
                        </div>
                    </div>
                    {index.elections.length > 1 && (
                        <label className="text-sm">
                            <span className="sr-only">Izbori</span>
                            <select
                                className="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                                value={slug}
                                onChange={(e) => navigate(`/${e.target.value}`)}
                            >
                                {index.elections.map((e) => (
                                    <option key={e.slug} value={e.slug}>{t(e.name)}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <div className="inline-flex overflow-hidden rounded-md border border-zinc-300 text-xs dark:border-zinc-700" role="group" aria-label="Pismo">
                        <button type="button" onClick={() => dispatch(setScript('lat'))} className={`px-2 py-1 ${script === 'lat' ? 'bg-primary text-white' : 'bg-white dark:bg-zinc-800'}`} aria-pressed={script === 'lat'}>Lat</button>
                        <button type="button" onClick={() => dispatch(setScript('cyr'))} className={`px-2 py-1 ${script === 'cyr' ? 'bg-primary text-white' : 'bg-white dark:bg-zinc-800'}`} aria-pressed={script === 'cyr'}>Ћир</button>
                    </div>
                </div>
                <nav className="mx-auto max-w-6xl overflow-x-auto px-4">
                    <ul className="flex gap-1 whitespace-nowrap pb-2 text-sm">
                        {NAV.map((item) => (
                            <li key={item.to}>
                                <NavLink
                                    to={item.to === '' ? `/${slug}` : `/${slug}/${item.to}`}
                                    end={item.end}
                                    className={({ isActive }) => `inline-block rounded-md px-3 py-1.5 no-underline ${isActive ? 'bg-primary-soft font-semibold text-primary dark:bg-blue-950 dark:text-blue-300' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800'}`}
                                >
                                    {t(item.label)}
                                </NavLink>
                            </li>
                        ))}
                    </ul>
                </nav>
            </header>

            {site?.notice && (
                <div className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">{t(site.notice)}</div>
            )}

            <main className="mx-auto max-w-6xl px-4 py-6">
                <Outlet />
            </main>

            <footer className="mx-auto max-w-6xl px-4 py-8 text-xs text-zinc-500 dark:text-zinc-400">
                <p>
                    {t(site?.publisher ?? env.publisher)}{(site?.publisher ?? env.publisher) ? ' · ' : ''}
                    {t('Verzije podataka')}: registar {config.registry ?? '—'} · izlaznost {config.turnout ?? '—'} · rezultati {config.results ?? '—'} · {t('ažurirano')} {dateTime(config.updated)}
                </p>
                <p className="mt-1">
                    <a className="link" href={`${env.dataUrl}/index.json`}>index.json</a> · <a className="link" href={`${env.dataUrl}/${slug}/config.json`}>config.json</a>
                    {' · '}<Link className="link" to={`/${slug}/o-podacima`}>{t('otvoreni podaci i metodologija')}</Link>
                </p>
            </footer>
        </div>
    );
}
