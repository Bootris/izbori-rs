import { useState } from 'react';
import { useT } from '@/app/hooks';
import { pct } from '@/lib/format';
import { OKRUZI, OKRUZI_VIEWBOX } from '@/data/okruzi';

export interface MapEntry {
    /** Number the label and the sequential ramp use; null means "not reported". */
    value: number | null;
    /** Explicit fill, used when the map shows categories (leading list) instead of a scale. */
    color?: string | null;
    /** Second line of the tooltip, for example the name of the leading list. */
    note?: string;
}

export interface DistrictMapProps {
    entries: Record<string, MapEntry>;
    names: Record<string, string>;
    valueLabel?: string;
    /** Present for a category map; its entries are drawn as the legend and fills come from `color`. */
    legend?: Array<{ label: string; color: string }>;
    onSelect?: (code: string) => void;
}

const STEPS = ['#dcefe0', '#a8d5b3', '#6fb87f', '#3f9655', '#1f6b33'];

/** Districts of Serbia (KiM has no shapes here), either as a green scale or colored by category. */
export default function DistrictMapInner({ entries, names, onSelect, valueLabel = 'Izlaznost', legend }: DistrictMapProps) {
    const t = useT();
    const [hover, setHover] = useState<{ code: string; x: number; y: number } | null>(null);
    const known = Object.values(entries).map((e) => e.value).filter((v): v is number => typeof v === 'number');
    const min = known.length ? Math.min(...known) : 0;
    const max = known.length ? Math.max(...known) : 100;

    const fill = (code: string) => {
        const entry = entries[code];
        if (!entry || entry.value === null) return 'var(--color-track)';
        if (legend) return entry.color || 'var(--color-track)';
        const i = max === min ? STEPS.length - 1 : Math.min(STEPS.length - 1, Math.floor(((entry.value - min) / (max - min)) * STEPS.length));
        return STEPS[i] ?? STEPS[0];
    };

    /** On a category map every district wears its winner's colour, so the strength of the win carries the shade. */
    const fillOpacity = (code: string): number => {
        const value = entries[code]?.value;
        if (!legend || value === null || value === undefined || max === min) return 1;
        return 0.45 + 0.55 * ((value - min) / (max - min));
    };

    const hovered = hover ? { name: names[hover.code] ?? hover.code, entry: entries[hover.code] } : null;

    return (
        <div className="relative">
            <svg viewBox={OKRUZI_VIEWBOX} className="mx-auto w-full max-w-md" role="img" aria-label={t('Mapa okruga')} onMouseLeave={() => setHover(null)}>
                {OKRUZI.map((d) => (
                    <path
                        key={d.code}
                        d={d.d}
                        fill={fill(d.code)}
                        fillOpacity={fillOpacity(d.code)}
                        stroke="#fff"
                        strokeWidth="1.2"
                        strokeLinejoin="round"
                        className={onSelect ? 'cursor-pointer hover:opacity-80' : ''}
                        onMouseMove={(e) => {
                            const box = e.currentTarget.ownerSVGElement?.getBoundingClientRect();
                            if (box) setHover({ code: d.code, x: ((e.clientX - box.left) / box.width) * 100, y: ((e.clientY - box.top) / box.height) * 100 });
                        }}
                        onClick={() => onSelect?.(d.code)}
                        tabIndex={onSelect ? 0 : -1}
                        onKeyDown={(e) => { if (onSelect && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); onSelect(d.code); } }}
                    >
                        <title>{`${t(names[d.code] ?? d.code)}: ${entries[d.code]?.note ? `${t(entries[d.code]?.note ?? '')}, ` : ''}${pct(entries[d.code]?.value ?? null)}`}</title>
                    </path>
                ))}
                {OKRUZI.map((d) => entries[d.code]?.value != null && (
                    <g key={`l-${d.code}`} className="pointer-events-none hidden sm:block">
                        <rect x={d.cx - 17} y={d.cy - 6} width="34" height="12" rx="2" fill="#fff" opacity="0.92" />
                        <text x={d.cx} y={d.cy + 3.2} textAnchor="middle" fontSize="8.5" fontWeight="600" fill="var(--color-ink)">{pct(entries[d.code]?.value ?? null).replace(' %', '%')}</text>
                    </g>
                ))}
            </svg>

            {hover && hovered && (
                <div className="pointer-events-none absolute z-10 rounded-lg bg-white px-3 py-2 text-sm shadow-lg ring-1 ring-line" style={{ left: `${hover.x}%`, top: `${hover.y}%`, transform: 'translate(-50%, calc(-100% - 14px))' }}>
                    <div className="font-semibold">{t(hovered.name)}</div>
                    {hovered.entry?.note && (
                        <div className="flex items-center gap-2 text-ink-2">
                            {hovered.entry.color && <i className="inline-block h-2.5 w-2.5 rounded-sm" style={{ background: hovered.entry.color }} />}
                            {t(hovered.entry.note)}
                        </div>
                    )}
                    <div className="text-ink-2">{t(valueLabel)}: <b className="text-ink">{pct(hovered.entry?.value ?? null)}</b></div>
                </div>
            )}

            {legend ? (
                <div className="mt-3 space-y-1.5 text-xs text-ink-2">
                    <ul className="flex flex-wrap justify-center gap-x-4 gap-y-1.5">
                        {legend.map((l) => (
                            <li key={l.label} className="flex items-center gap-1.5">
                                <i className="inline-block h-3 w-3 rounded-sm" style={{ background: l.color }} />
                                {t(l.label)}
                            </li>
                        ))}
                    </ul>
                    <p className="text-center">{t('Tamnija nijansa znači ubedljiviju pobedu')}: {pct(min)} do {pct(max)}</p>
                </div>
            ) : (
                <div className="mt-3 flex flex-wrap items-center justify-center gap-3 text-xs text-ink-2">
                    <span>{pct(min)}</span>
                    <span className="flex overflow-hidden rounded">{STEPS.map((c) => <i key={c} className="block h-3 w-8" style={{ background: c }} />)}</span>
                    <span>{pct(max)}</span>
                </div>
            )}
        </div>
    );
}
