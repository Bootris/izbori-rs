import { useMemo } from 'react';
import type { CompositionByList } from '@/types';
import { useT } from '@/app/hooks';
import { listColor, num } from '@/lib/format';

interface Props {
    total: number;
    byList: CompositionByList[];
    empty: number;
    label?: string;
}

interface SeatPos { x: number; y: number; angle: number; size: number }

/** Half-ring seat chart built from rotated squares; blocks are contiguous per list in `byList` order (left to right). */
export function Hemicycle({ total, byList, empty, label = 'Broj poslaničkih mesta' }: Props) {
    const t = useT();
    const seats = useMemo(() => layout(total), [total]);
    const owners = useMemo(() => {
        const out: Array<{ color: string; name: string }> = [];
        byList.forEach((l, i) => { for (let k = 0; k < l.seats; k++) out.push({ color: listColor(l.color, i), name: l.short_name ?? l.name }); });
        for (let k = 0; k < empty; k++) out.push({ color: 'var(--color-track)', name: 'nepopunjeno' });
        return out;
    }, [byList, empty]);

    if (total === 0) return null;

    return (
        <svg viewBox="0 0 200 108" className="mx-auto w-full max-w-xl" role="img" aria-label={`${t(label)}: ${total}`}>
            {seats.map((s, i) => {
                const owner = owners[i];
                return (
                    <rect
                        key={i}
                        x={s.x - s.size / 2}
                        y={s.y - s.size / 2}
                        width={s.size}
                        height={s.size}
                        rx={0.6}
                        fill={owner?.color ?? 'var(--color-track)'}
                        transform={`rotate(${90 - (s.angle * 180) / Math.PI} ${s.x} ${s.y})`}
                    >
                        <title>{owner ? `${t(owner.name)}, ${t('mandat')} ${i + 1}` : `${t('mandat')} ${i + 1}`}</title>
                    </rect>
                );
            })}
            <text x="100" y="86" textAnchor="middle" fontSize="6.2" fill="var(--color-ink-2)">{t(label)}</text>
            <text x="100" y="103" textAnchor="middle" fontSize="16" fontWeight="700" fill="var(--color-ink)">{num(total)}</text>
        </svg>
    );
}

/** Seats over concentric half-rings, proportional to ring length, ordered left to right by angle. */
function layout(n: number): SeatPos[] {
    const rows = Math.max(2, Math.round(Math.sqrt(n / 4)));
    const inner = 46;
    const outer = 96;
    const gap = (outer - inner) / (rows - 1);
    const radii = Array.from({ length: rows }, (_, i) => inner + i * gap);
    const totalLength = radii.reduce((s, r) => s + r, 0);
    const perRow = radii.map((r) => Math.floor((r / totalLength) * n));
    let rest = n - perRow.reduce((s, c) => s + c, 0);
    for (let i = rows - 1; rest > 0; i = (i - 1 + rows) % rows, rest--) perRow[i] = (perRow[i] ?? 0) + 1;

    const seats: SeatPos[] = [];
    radii.forEach((radius, row) => {
        const count = perRow[row] ?? 0;
        const step = count > 1 ? (Math.PI * radius) / (count - 1) : gap;
        const size = Math.min(gap * 0.72, step * 0.78);
        for (let k = 0; k < count; k++) {
            const angle = count === 1 ? Math.PI / 2 : Math.PI - (Math.PI * k) / (count - 1);
            seats.push({ x: 100 + radius * Math.cos(angle), y: 100 - radius * Math.sin(angle), angle, size });
        }
    });
    seats.sort((a, b) => b.angle - a.angle);
    return seats;
}
