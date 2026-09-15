import type { TurnoutCutoff } from '@/types';
import { pct } from '@/lib/format';

/** Cumulative turnout per cut-off as a simple bar chart (inline SVG, no library). */
export function TurnoutChart({ cutoffs }: { cutoffs: Array<Pick<TurnoutCutoff, 'cutoff' | 'turnout_pct'>> }) {
    const max = Math.max(10, ...cutoffs.map((c) => c.turnout_pct ?? 0));
    const w = 60;
    const width = cutoffs.length * w + 20;
    return (
        <svg viewBox={`0 0 ${width} 130`} className="w-full max-w-xl" role="img" aria-label="Izlaznost po presecima">
            {cutoffs.map((c, i) => {
                const h = c.turnout_pct == null ? 0 : (c.turnout_pct / max) * 90;
                const x = 10 + i * w;
                return (
                    <g key={c.cutoff}>
                        <rect x={x + 10} y={100 - h} width={w - 20} height={h} rx="2" fill={c.turnout_pct == null ? '#d4d4d8' : '#1d4ed8'} />
                        <text x={x + w / 2} y={96 - h} textAnchor="middle" fontSize="8" fill="currentColor">{c.turnout_pct == null ? '—' : pct(c.turnout_pct)}</text>
                        <text x={x + w / 2} y="118" textAnchor="middle" fontSize="9" fill="currentColor">{c.cutoff}</text>
                    </g>
                );
            })}
        </svg>
    );
}
