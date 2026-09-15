import type { ReactNode } from 'react';
import type { Meta } from '@/types';
import { useT } from '@/app/hooks';
import { dateTime, listColor, pct } from '@/lib/format';

export function PageTitle({ title, meta, children }: { title: string; meta?: Meta; children?: ReactNode }) {
    const t = useT();
    return (
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1>{t(title)}</h1>
                {children && <div className="muted mt-1">{children}</div>}
            </div>
            {meta && <ProcessedBadge meta={meta} />}
        </div>
    );
}

/** Every results view carries this: how much of the count is in, and when the file was generated. */
export function ProcessedBadge({ meta }: { meta: Meta }) {
    const t = useT();
    return (
        <div className="text-right text-sm">
            {meta.processed !== null && (
                <div className="font-semibold">
                    {t('Obrađeno')} {pct(meta.processed)} {t('biračkih mesta')}
                </div>
            )}
            <div className="muted">{t('Podaci ažurirani')} {dateTime(meta.generated)} · v{meta.version}</div>
        </div>
    );
}

export function ProcessedBar({ value }: { value: number }) {
    return (
        <div className="bar-track" aria-hidden="true">
            <div className="bar-fill bg-primary" style={{ width: `${Math.min(100, value)}%` }} />
        </div>
    );
}

export function Stat({ label, value, sub }: { label: string; value: ReactNode; sub?: ReactNode }) {
    const t = useT();
    return (
        <div className="card">
            <div className="muted">{t(label)}</div>
            <div className="mt-1 text-2xl font-semibold tabular-nums">{value}</div>
            {sub && <div className="muted mt-1">{sub}</div>}
        </div>
    );
}

export function Swatch({ color, index }: { color: string | null | undefined; index: number }) {
    return <span className="inline-block h-3 w-3 shrink-0 rounded-sm" style={{ background: listColor(color, index) }} aria-hidden="true" />;
}

export function Bar({ value, max, color, index }: { value: number; max: number; color: string | null | undefined; index: number }) {
    const width = max > 0 ? (value / max) * 100 : 0;
    return (
        <div className="bar-track">
            <div className="bar-fill" style={{ width: `${width}%`, background: listColor(color, index) }} />
        </div>
    );
}

export function StatusBadge({ status }: { status: string | null | undefined }) {
    const t = useT();
    const map: Record<string, [string, string]> = {
        verified: ['badge-green', 'Verifikovan'],
        entered: ['badge-gray', 'Unet'],
        flagged: ['badge-red', 'Sa odstupanjem'],
        annulled: ['badge-amber', 'Poništen'],
    };
    const [cls, label] = (status && map[status]) || ['badge-gray', 'Nije unet'];
    return <span className={`badge ${cls}`}>{t(label)}</span>;
}

export function Breadcrumbs({ items }: { items: Array<{ label: string; to?: string }> }) {
    const t = useT();
    return (
        <nav className="muted mb-3" aria-label="Putanja">
            {items.map((item, i) => (
                <span key={`${item.label}-${i}`}>
                    {i > 0 && ' › '}
                    {item.to ? <a className="link" href={item.to}>{t(item.label)}</a> : <span>{t(item.label)}</span>}
                </span>
            ))}
        </nav>
    );
}
