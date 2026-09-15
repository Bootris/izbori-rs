import { useMemo } from 'react';
import type { CompositionByList } from '@/types';
import { listColor } from '@/lib/format';

interface Props {
    total: number;
    byList: CompositionByList[];
    empty: number;
}

/** Half-circle seat chart: blocks are contiguous per list, ordered as `byList` (largest first). */
export function Hemicycle({ total, byList, empty }: Props) {
    const dots = useMemo(() => layout(total), [total]);
    const colors = useMemo(() => {
        const out: string[] = [];
        byList.forEach((l, i) => { for (let k = 0; k < l.seats; k++) out.push(listColor(l.color, i)); });
        for (let k = 0; k < empty; k++) out.push('#d4d4d8');
        return out;
    }, [byList, empty]);

    if (total === 0) return null;

    return (
        <svg viewBox="0 0 200 105" className="mx-auto w-full max-w-2xl" role="img" aria-label="Raspored mandata">
            {dots.map((d, i) => (
                <circle key={i} cx={d.x} cy={d.y} r={d.r} fill={colors[i] ?? '#d4d4d8'}>
                    <title>{`Mandat ${i + 1}`}</title>
                </circle>
            ))}
        </svg>
    );
}

/** Distribute `n` seats over concentric half-rings, proportionally to ring length, left → right. */
function layout(n: number): Array<{ x: number; y: number; r: number }> {
    const rows = Math.max(1, Math.round(Math.sqrt(n / 4)));
    const inner = 40;
    const outer = 96;
    const gap = rows > 1 ? (outer - inner) / (rows - 1) : 0;
    const radii = Array.from({ length: rows }, (_, i) => inner + i * gap);
    const totalLength = radii.reduce((s, r) => s + r, 0);
    const perRow = radii.map((r) => Math.floor((r / totalLength) * n));
    let rest = n - perRow.reduce((s, c) => s + c, 0);
    for (let i = rows - 1; rest > 0; i = (i - 1 + rows) % rows, rest--) perRow[i] = (perRow[i] ?? 0) + 1;

    // walk all rows simultaneously by angle so seat i (in list order) fills left→right
    const seats: Array<{ x: number; y: number; r: number; angle: number }> = [];
    radii.forEach((radius, row) => {
        const count = perRow[row] ?? 0;
        for (let k = 0; k < count; k++) {
            const angle = count === 1 ? Math.PI / 2 : Math.PI - (Math.PI * k) / (count - 1);
            seats.push({ x: 100 + radius * Math.cos(angle), y: 100 - radius * Math.sin(angle), r: Math.min(3.2, gap * 0.42 || 3), angle });
        }
    });
    seats.sort((a, b) => b.angle - a.angle);
    return seats;
}
