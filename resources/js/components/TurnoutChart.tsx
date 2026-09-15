import { useState } from 'react';
import type { TurnoutCutoff } from '@/types';
import { useT } from '@/app/hooks';
import { pct } from '@/lib/format';

interface Point { label: string; hour: number; value: number | null }

interface Props {
    cutoffs: Array<Pick<TurnoutCutoff, 'cutoff' | 'turnout_pct'>>;
    /** Optional closing figure from the protocols, drawn as the last point. */
    final?: { label: string; value: number } | null;
}

const W = 640;
const H = 250;
const ML = 44;
const MR = 70;
const MT = 20;
const MB = 34;

/** Cumulative turnout over election day: area line with one readout on hover, endpoint labeled. */
export function TurnoutChart({ cutoffs, final }: Props) {
    const t = useT();
    const [hover, setHover] = useState<number | null>(null);
    const points: Point[] = cutoffs.map((c) => ({ label: c.cutoff, hour: parseInt(c.cutoff, 10) + (parseInt(c.cutoff.slice(3, 5), 10) || 0) / 60, value: c.turnout_pct }));
    if (final) points.push({ label: final.label, hour: (points.at(-1)?.hour ?? 19) + 1, value: final.value });
    const known = points.filter((p) => p.value !== null);
    if (known.length === 0) return null;

    const h0 = points[0]?.hour ?? 7;
    const h1 = points.at(-1)?.hour ?? 20;
    const yMax = Math.max(20, Math.ceil((Math.max(...known.map((p) => p.value ?? 0)) + 5) / 10) * 10);
    const X = (h: number) => ML + ((h - h0) / Math.max(1, h1 - h0)) * (W - ML - MR);
    const Y = (v: number) => MT + (1 - v / yMax) * (H - MT - MB);
    const path = known.map((p, i) => `${i ? 'L' : 'M'}${X(p.hour).toFixed(1)} ${Y(p.value ?? 0).toFixed(1)}`).join(' ');
    const last = known[known.length - 1] as Point;
    const ticks = Array.from({ length: yMax / 10 + 1 }, (_, i) => i * 10);
    const active = hover === null ? null : known[hover] ?? null;

    return (
        <div className="relative">
            <svg
                viewBox={`0 0 ${W} ${H}`}
                className="w-full"
                role="img"
                aria-label={`${t('Izlaznost po presecima')}, ${t('poslednji')}: ${pct(last.value)}`}
                onMouseMove={(e) => {
                    const box = e.currentTarget.getBoundingClientRect();
                    const x = ((e.clientX - box.left) / box.width) * W;
                    let best = 0;
                    known.forEach((p, i) => { if (Math.abs(X(p.hour) - x) < Math.abs(X((known[best] as Point).hour) - x)) best = i; });
                    setHover(best);
                }}
                onMouseLeave={() => setHover(null)}
            >
                {ticks.map((v) => (
                    <g key={v}>
                        <line x1={ML} x2={W - MR} y1={Y(v)} y2={Y(v)} stroke="var(--color-line)" />
                        <text x={ML - 8} y={Y(v) + 4} textAnchor="end" fontSize="11" fill="var(--color-ink-3)">{v} %</text>
                    </g>
                ))}
                {points.map((p) => (
                    <text key={p.label} x={X(p.hour)} y={H - 10} textAnchor="middle" fontSize="11" fill="var(--color-ink-3)">{p.label}</text>
                ))}
                <path d={`${path} L${X(last.hour).toFixed(1)} ${Y(0)} L${X((known[0] as Point).hour).toFixed(1)} ${Y(0)} Z`} fill="var(--color-ok)" opacity="0.10" />
                <path d={path} fill="none" stroke="var(--color-ok)" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
                {active && <line x1={X(active.hour)} x2={X(active.hour)} y1={MT} y2={H - MB} stroke="var(--color-ink-3)" strokeDasharray="0" opacity="0.5" />}
                {active && <circle cx={X(active.hour)} cy={Y(active.value ?? 0)} r="5" fill="var(--color-ok)" stroke="#fff" strokeWidth="2" />}
                <circle cx={X(last.hour)} cy={Y(last.value ?? 0)} r="4.5" fill="var(--color-ok)" stroke="#fff" strokeWidth="2" />
                <text x={X(last.hour) + 10} y={Y(last.value ?? 0) + 4} fontSize="12" fontWeight="700" fill="var(--color-ink)">{pct(last.value)}</text>
            </svg>
            {active && (
                <div className="pointer-events-none absolute rounded-md bg-ink px-2.5 py-1.5 text-xs text-white shadow" style={{ left: `${(X(active.hour) / W) * 100}%`, top: `${(Y(active.value ?? 0) / H) * 100}%`, transform: 'translate(-50%, calc(-100% - 12px))' }}>
                    <b className="text-sm">{pct(active.value)}</b> {t('do')} {active.label}
                </div>
            )}
        </div>
    );
}
