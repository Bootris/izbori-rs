import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { District, ElectoralList, Municipality, Submitter } from '@/types';
import { searchKey } from '@/lib/format';
import { IconSearch } from './ui';

interface Hit { key: string; label: string; sub: string; group: string; to: string }

const LIMIT = 8;

/** Header search over places, lists, submitters and candidates; Enter opens the first hit. */
export function Search() {
    const { slug } = useElection();
    const t = useT();
    const navigate = useNavigate();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
    const lists = useSnapshotList<ElectoralList>('registry', 'lists.json');
    const submitters = useSnapshotList<Submitter>('registry', 'submitters.json');
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const close = (e: MouseEvent) => { if (!box.current?.contains(e.target as Node)) setOpen(false); };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const key = searchKey(q.trim());

    const hits = useMemo<Hit[]>(() => {
        if (key.length < 2) return [];
        const districtName = (code: string) => districts.list?.find((d) => d.code === code)?.name ?? code;
        const places: Hit[] = [
            ...(districts.list ?? []).filter((d) => searchKey(d.name).includes(key)).map((d) => ({ key: `d-${d.code}`, label: d.name, sub: 'okrug', group: 'Teritorija', to: `/${slug}/teritorija/${d.code}` })),
            ...(municipalities.list ?? []).filter((m) => searchKey(m.name).includes(key)).map((m) => ({ key: `m-${m.code}`, label: m.name, sub: districtName(String(m.district_code)), group: 'Teritorija', to: `/${slug}/teritorija/${m.district_code}/${m.code}` })),
        ];
        const listHits: Hit[] = (lists.list ?? [])
            .filter((l) => searchKey(l.name).includes(key) || searchKey(l.short_name ?? '').includes(key))
            .map((l) => ({ key: `l-${l.id}`, label: `${l.number}. ${l.name}`, sub: 'izborna lista', group: 'Liste', to: `/${slug}/liste/${l.id}` }));
        const submitterHits: Hit[] = (submitters.list ?? [])
            .filter((s) => searchKey(s.name).includes(key) || searchKey(s.short_name ?? '').includes(key))
            .map((s) => ({ key: `s-${s.id}`, label: s.name, sub: 'podnosilac', group: 'Podnosioci', to: `/${slug}/podnosioci/${s.id}` }));
        const candidateHits: Hit[] = [];
        for (const l of lists.list ?? []) {
            for (const c of l.candidates) {
                if (candidateHits.length >= LIMIT) break;
                if (searchKey(c.full_name).includes(key)) {
                    candidateHits.push({ key: `c-${l.id}-${c.position}`, label: c.full_name, sub: `${c.position}. ${t(l.short_name ?? l.name)}`, group: 'Kandidati', to: `/${slug}/liste/${l.id}` });
                }
            }
        }
        return [...places.slice(0, LIMIT), ...listHits.slice(0, 4), ...submitterHits.slice(0, 3), ...candidateHits.slice(0, 5)].slice(0, 14);
    }, [key, districts.list, municipalities.list, lists.list, submitters.list, slug, t]);

    const go = (hit: Hit | undefined) => {
        if (!hit) return;
        setQ('');
        setOpen(false);
        navigate(hit.to);
    };

    let lastGroup = '';

    return (
        <div ref={box} className="relative w-full md:w-72">
            <label className="sr-only" htmlFor="site-search">{t('Pretraga opštine, liste ili kandidata')}</label>
            <input
                id="site-search"
                className="input pr-11"
                placeholder={t('Pretraga')}
                value={q}
                autoComplete="off"
                onChange={(e) => { setQ(e.target.value); setOpen(true); setActive(0); }}
                onFocus={() => setOpen(true)}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(hits.length - 1, a + 1)); }
                    if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(0, a - 1)); }
                    if (e.key === 'Enter') { e.preventDefault(); go(hits[active]); }
                    if (e.key === 'Escape') setOpen(false);
                }}
                role="combobox"
                aria-expanded={open && hits.length > 0}
                aria-controls="site-search-list"
                aria-autocomplete="list"
            />
            <span className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-primary"><IconSearch /></span>
            {open && hits.length > 0 && (
                <ul id="site-search-list" role="listbox" className="absolute left-0 right-0 top-full z-40 mt-1 max-h-96 overflow-auto rounded-xl border border-line bg-white py-1 shadow-lg">
                    {hits.map((h, i) => {
                        const header = h.group !== lastGroup ? h.group : null;
                        lastGroup = h.group;
                        return (
                            <li key={h.key} role="option" aria-selected={i === active}>
                                {header && <div className="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-ink-3">{t(header)}</div>}
                                <button type="button" className={`flex w-full items-baseline justify-between gap-3 px-4 py-2 text-left text-sm ${i === active ? 'bg-primary-soft' : 'hover:bg-line-2'}`} onMouseEnter={() => setActive(i)} onClick={() => go(h)}>
                                    <span className="min-w-0 truncate font-medium">{t(h.label)}</span>
                                    <span className="muted shrink-0">{t(h.sub)}</span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
            {open && key.length >= 2 && hits.length === 0 && (
                <div className="absolute left-0 right-0 top-full z-40 mt-1 rounded-xl border border-line bg-white px-4 py-3 text-sm text-ink-3 shadow-lg">{t('Nema rezultata za')} „{q}"</div>
            )}
        </div>
    );
}
