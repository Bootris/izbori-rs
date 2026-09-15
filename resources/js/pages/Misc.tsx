import { Link } from 'react-router-dom';
import { env } from '@/env';
import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { Deadline, Source } from '@/types';
import { date } from '@/lib/format';
import { WithFile } from '@/components/state';
import { PageTitle } from '@/components/ui';

export function Deadlines() {
    const t = useT();
    const deadlines = useSnapshotList<Deadline>('registry', 'deadlines.json');
    const today = new Date().toISOString().slice(0, 10);
    return (
        <div className="space-y-6">
            <PageTitle title="Izborni rokovi" meta={deadlines.meta} />
            <WithFile state={deadlines} unavailable={t('Registar još nije objavljen.')}>
                {({ list }) => (
                    <ol className="card divide-y divide-zinc-100 dark:divide-zinc-800">
                        {list.map((d) => (
                            <li key={`${d.date}-${d.title}`} className={`flex gap-4 py-3 ${d.date < today ? 'text-zinc-500' : ''}`}>
                                <span className="w-24 shrink-0 tabular-nums">{date(d.date)}</span>
                                <span><span className="font-medium">{t(d.title)}</span>{d.description && <div className="muted">{t(d.description)}</div>}{d.legal_basis && <div className="muted">{t(d.legal_basis)}</div>}</span>
                            </li>
                        ))}
                    </ol>
                )}
            </WithFile>
        </div>
    );
}

export function About() {
    const { slug, config, site } = useElection();
    const t = useT();
    const sources: Source[] = ['registry', 'turnout', 'results'];
    const base = `${env.dataUrl}/${slug}`;
    return (
        <div className="space-y-6">
            <PageTitle title="O podacima" />
            <div className="card space-y-3 text-sm leading-relaxed">
                <p>{t('Sve što ovaj sajt prikazuje su statički JSON fajlovi koje objavljuje sistem za unos. Svaka objava je nepromenljiva verzija sa sopstvenim manifestom (SHA-256 svakog fajla, vezan za prethodnu objavu), pa se u svakom trenutku može dokazati šta je bilo prikazano.')}</p>
                <p>{t('U zbir ulaze samo verifikovani zapisnici biračkih odbora koji prolaze kontrolne sume K1–K7. Zapisnici sa odstupanjem su javno vidljivi, ali se ne sabiraju dok ih izborna komisija ne ispravi. Procenat obrađenih biračkih mesta stoji uz svaki agregat.')}</p>
                <p>{t('Raspodela mandata: D\'Hondt (sistem najvećeg količnika) uz cenzus od 3 % birača koji su glasali; liste nacionalnih manjina učestvuju i ispod cenzusa, a njihovi količnici se uvećavaju za 35 %.')}</p>
                {site?.methodology_url && <p><a className="link" href={site.methodology_url}>{t('Metodologija i pravni osnov')}</a></p>}
                {site?.contact_email && <p>{t('Kontakt')}: <a className="link" href={`mailto:${site.contact_email}`}>{site.contact_email}</a></p>}
            </div>
            <div className="card text-sm">
                <h2 className="mb-2">{t('Otvoreni podaci')}</h2>
                <ul className="space-y-1">
                    <li><a className="link" href={`${env.dataUrl}/index.json`}>index.json</a> — {t('spisak izbora')}</li>
                    <li><a className="link" href={`${base}/config.json`}>config.json</a> — {t('tekuće verzije po izvoru')}</li>
                    {sources.map((s) => config[s] && (
                        <li key={s}>
                            <a className="link" href={`${base}/${config[s]}/${s}/manifest.json`}>{s}/manifest.json</a> — {t('verzija')} {config[s]}
                        </li>
                    ))}
                </ul>
                <p className="muted mt-3">{t('Oblik svih fajlova je dokumentovan u repozitorijumu (docs/DATA-CONTRACT.md). Fajlovi su UTF-8, latinica; ćirilica se dobija transliteracijom na klijentu.')}</p>
            </div>
        </div>
    );
}

export function NotFound() {
    const t = useT();
    const { slug } = useElection();
    return <div className="py-10 text-center"><h1>404</h1><p className="muted mt-2">{t('Stranica ne postoji.')}</p><Link className="link" to={`/${slug}`}>{t('Početna')}</Link></div>;
}
