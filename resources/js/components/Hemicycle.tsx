import { useMemo, useState } from 'react';
import type { CompositionByList } from '@/types';
import { useT } from '@/app/hooks';
import { listColor, num, pct, plural } from '@/lib/format';

interface Props {
    total: number;
    byList: CompositionByList[];
    empty: number;
    label?: string;
}

interface SeatPos { x: number; y: number; angle: number; size: number }

interface Block { index: number; name: string; color: string; seats: number; seatsPct: number }

const EMPTY_BLOCK = -1;

/**
 * Half-ring seat chart built from rotated squares; blocks are contiguous per
 * list in `byList` order (left to right). Hovering a seat lights up the whole
 * block of that list, dims the rest and opens a tooltip in the list's color.
 */
export function Hemicycle({ total, byList, empty, label = 'Broj poslaničkih mesta' }: Props) {
    const t = useT();
    const seats = useMemo(() => layout(total), [total]);
    const blocks = useMemo<Block[]>(
        () => byList.map((l, i) => ({ index: i, name: l.name, color: listColor(l.color, i), seats: l.seats, seatsPct: l.seats_pct })),
        [byList],
    );
    const owners = useMemo(() => {
        const out: number[] = [];
        blocks.forEach((b) => { for (let k = 0; k < b.seats; k++) out.push(b.index); });
        for (let k = 0; k < empty; k++) out.push(EMPTY_BLOCK);
        return out;
    }, [blocks, empty]);
    const [hover, setHover] = useState<{ block: number; x: number; y: number } | null>(null);

    if (total === 0) return null;

    const active = hover && hover.block !== EMPTY_BLOCK ? blocks[hover.block] : undefined;

    return (
        <div className="relative">
            <svg
                viewBox="0 0 200 108"
                className="mx-auto w-full max-w-xl"
                role="img"
                aria-label={`${t(label)}: ${total}`}
                onMouseLeave={() => setHover(null)}
            >
                {seats.map((s, i) => {
                    const owner = owners[i] ?? EMPTY_BLOCK;
                    const block = owner === EMPTY_BLOCK ? undefined : blocks[owner];
                    const dimmed = hover !== null && hover.block !== owner;
                    return (
                        <rect
                            key={i}
                            x={s.x - s.size / 2}
                            y={s.y - s.size / 2}
                            width={s.size}
                            height={s.size}
                            rx={0.6}
                            fill={block?.color ?? 'var(--color-track)'}
                            opacity={dimmed ? 0.3 : 1}
                            transform={`rotate(${90 - (s.angle * 180) / Math.PI} ${s.x} ${s.y})`}
                            className="transition-opacity duration-150"
                            onMouseMove={(e) => {
                                const box = e.currentTarget.ownerSVGElement?.getBoundingClientRect();
                                if (!box) return;
                                setHover({ block: owner, x: ((e.clientX - box.left) / box.width) * 100, y: ((e.clientY - box.top) / box.height) * 100 });
                            }}
                        >
                            <title>{block ? `${t(block.name)}: ${block.seats} ${t(plural(block.seats, ['mandat', 'mandata', 'mandata']))}` : t('nepopunjeno mesto')}</title>
                        </rect>
                    );
                })}
                <text x="100" y="86" textAnchor="middle" fontSize="6.2" fill="var(--color-ink-2)">{t(label)}</text>
                <text x="100" y="103" textAnchor="middle" fontSize="16" fontWeight="700" fill="var(--color-ink)">{num(total)}</text>
            </svg>
            {hover && active && (
                <div
                    className="pointer-events-none absolute z-10 max-w-xs rounded-lg px-4 py-2.5 text-center text-sm text-white shadow-lg"
                    style={{ background: active.color, left: `${hover.x}%`, top: `${hover.y}%`, transform: 'translate(-50%, calc(-100% - 14px))' }}
                    role="status"
                >
                    <div className="font-semibold leading-snug">{t(active.name)}</div>
                    <div className="mt-0.5 opacity-90">{num(active.seats)} {t(plural(active.seats, ['mandat', 'mandata', 'mandata']))} ({pct(active.seatsPct)})</div>
                </div>
            )}
            {hover && hover.block === EMPTY_BLOCK && (
                <div className="pointer-events-none absolute z-10 rounded-lg bg-ink px-3 py-2 text-sm text-white shadow-lg" style={{ left: `${hover.x}%`, top: `${hover.y}%`, transform: 'translate(-50%, calc(-100% - 14px))' }} role="status">
                    {t('Nepopunjeno mesto')}
                </div>
            )}
        </div>
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
