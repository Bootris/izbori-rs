import { useState } from 'react';
import { useT } from '@/app/hooks';
import { pct } from '@/lib/format';
import { OKRUZI, OKRUZI_VIEWBOX } from '@/data/okruzi';

export interface DistrictMapProps {
    /** district code => turnout (null when not reported) */
    values: Record<string, number | null | undefined>;
    names: Record<string, string>;
    onSelect?: (code: string) => void;
    valueLabel?: string;
}

const STEPS = ['#dcefe0', '#a8d5b3', '#6fb87f', '#3f9655', '#1f6b33'];

/** Choropleth of districts (KiM excluded from the shapes), sequential green like the reference site. */
export default function DistrictMapInner({ values, names, onSelect, valueLabel = 'Izlaznost' }: DistrictMapProps) {
    const t = useT();
    const [hover, setHover] = useState<{ code: string; x: number; y: number } | null>(null);
    const known = Object.values(values).filter((v): v is number => typeof v === 'number');
    const min = known.length ? Math.min(...known) : 0;
    const max = known.length ? Math.max(...known) : 100;
    const fill = (v: number | null | undefined) => {
        if (v == null) return 'var(--color-track)';
        const i = max === min ? STEPS.length - 1 : Math.min(STEPS.length - 1, Math.floor(((v - min) / (max - min)) * STEPS.length));
        return STEPS[i] ?? STEPS[0];
    };
    const hovered = hover ? { name: names[hover.code] ?? hover.code, value: values[hover.code] } : null;

    return (
        <div className="relative">
            <svg
                viewBox={OKRUZI_VIEWBOX}
                className="mx-auto w-full max-w-md"
                role="img"
                aria-label={t('Mapa okruga')}
                onMouseLeave={() => setHover(null)}
            >
                {OKRUZI.map((d) => (
                    <path
                        key={d.code}
                        d={d.d}
                        fill={fill(values[d.code])}
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
                        <title>{`${t(names[d.code] ?? d.code)}: ${pct(values[d.code])}`}</title>
                    </path>
                ))}
                {OKRUZI.map((d) => values[d.code] != null && (
                    <g key={`l-${d.code}`} className="pointer-events-none hidden sm:block">
                        <rect x={d.cx - 17} y={d.cy - 6} width="34" height="12" rx="2" fill="#fff" opacity="0.92" />
                        <text x={d.cx} y={d.cy + 3.2} textAnchor="middle" fontSize="8.5" fontWeight="600" fill="var(--color-ink)">{pct(values[d.code]).replace(' %', '%')}</text>
                    </g>
                ))}
            </svg>
            {hover && hovered && (
                <div className="pointer-events-none absolute z-10 rounded-lg bg-white px-3 py-2 text-sm shadow-lg ring-1 ring-line" style={{ left: `${hover.x}%`, top: `${hover.y}%`, transform: 'translate(-50%, calc(-100% - 14px))' }}>
                    <div className="font-semibold">{t(hovered.name)}</div>
                    <div className="text-ink-2">{t(valueLabel)}: <b className="text-ink">{pct(hovered.value)}</b></div>
                </div>
            )}
            <div className="mt-3 flex flex-wrap items-center justify-center gap-3 text-xs text-ink-2">
                <span>{pct(min)}</span>
                <span className="flex overflow-hidden rounded">{STEPS.map((c) => <i key={c} className="block h-3 w-8" style={{ background: c }} />)}</span>
                <span>{pct(max)}</span>
            </div>
        </div>
    );
}
