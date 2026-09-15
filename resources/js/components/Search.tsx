import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotList, useT } from '@/app/hooks';
import type { District, Municipality } from '@/types';
import { searchKey } from '@/lib/format';
import { IconSearch } from './ui';

interface Hit { key: string; label: string; sub: string; to: string }

/** Header search over districts and municipalities; picking a hit opens its territory page. */
export function Search() {
    const { slug } = useElection();
    const t = useT();
    const navigate = useNavigate();
    const districts = useSnapshotList<District>('registry', 'districts.json');
    const municipalities = useSnapshotList<Municipality>('registry', 'municipalities.json');
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
    const districtName = (code: string) => districts.list?.find((d) => d.code === code)?.name ?? code;
    const hits: Hit[] = key.length < 2 ? [] : [
        ...(districts.list ?? []).filter((d) => searchKey(d.name).includes(key)).map((d) => ({ key: `d-${d.code}`, label: d.name, sub: 'okrug', to: `/${slug}/teritorija/${d.code}` })),
        ...(municipalities.list ?? []).filter((m) => searchKey(m.name).includes(key)).map((m) => ({ key: `m-${m.code}`, label: m.name, sub: districtName(m.district_code), to: `/${slug}/teritorija/${m.district_code}/${m.code}` })),
    ].slice(0, 8);

    const go = (hit: Hit | undefined) => {
        if (!hit) return;
        setQ('');
        setOpen(false);
        navigate(hit.to);
    };

    return (
        <div ref={box} className="relative w-full md:w-72">
            <label className="sr-only" htmlFor="site-search">{t('Pretraga opštine ili okruga')}</label>
            <input
                id="site-search"
                className="input pr-11"
                placeholder={t('Pretraga opštine')}
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
                <ul id="site-search-list" role="listbox" className="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
                    {hits.map((h, i) => (
                        <li key={h.key} role="option" aria-selected={i === active}>
                            <button type="button" className={`flex w-full items-baseline justify-between gap-3 px-4 py-2 text-left text-sm ${i === active ? 'bg-primary-soft' : 'hover:bg-line-2'}`} onMouseEnter={() => setActive(i)} onClick={() => go(h)}>
                                <span className="font-medium">{t(h.label)}</span>
                                <span className="muted">{t(h.sub)}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
