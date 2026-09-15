import { useId, useState, type CSSProperties, type ReactNode } from 'react';
import type { Meta } from '@/types';
import { useT } from '@/app/hooks';
import { dateTime, listColor, num, pct } from '@/lib/format';

/* ---------- icons (inline SVG, no icon font) ---------- */

export function IconSearch({ className = 'h-5 w-5' }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
        </svg>
    );
}

export function IconChevron({ open = false, className = 'h-5 w-5' }: { open?: boolean; className?: string }) {
    return (
        <svg className={`${className} shrink-0 text-primary transition-transform ${open ? 'rotate-180' : ''}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    );
}

export function IconArrow({ className = 'h-4 w-4' }: { className?: string }) {
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M5 12h14M13 6l6 6-6 6" />
        </svg>
    );
}

/** Ring that reads as "how much of the count is in": full green at 100 %, blue arc otherwise. */
export function RingIndicator({ value, size = 20 }: { value: number; size?: number }) {
    const r = 8;
    const c = 2 * Math.PI * r;
    const done = value >= 100;
    return (
        <svg width={size} height={size} viewBox="0 0 20 20" aria-hidden="true">
            <circle cx="10" cy="10" r={r} fill="none" stroke={done ? 'var(--color-ok)' : 'var(--color-track)'} strokeWidth="4" />
            {!done && <circle cx="10" cy="10" r={r} fill="none" stroke="var(--color-primary)" strokeWidth="4" strokeDasharray={`${(Math.max(0, Math.min(100, value)) / 100) * c} ${c}`} transform="rotate(-90 10 10)" />}
        </svg>
    );
}

/* ---------- page chrome ---------- */

export function PageTitle({ title, sub, meta, showProcessed = true, children }: { title: string; sub?: ReactNode; meta?: Meta; showProcessed?: boolean; children?: ReactNode }) {
    const t = useT();
    return (
        <div className="mb-5">
            {meta && <Updated meta={meta} />}
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1>{t(title)}</h1>
                    {sub && <div className="muted mt-1">{sub}</div>}
                </div>
                {showProcessed && meta?.processed != null && <Processed value={meta.processed} />}
            </div>
            {children}
        </div>
    );
}

export function Updated({ meta }: { meta: Meta }) {
    const t = useT();
    return <p className="muted mb-3">{t('Podaci ažurirani')}: {dateTime(meta.generated)}</p>;
}

/** "Obrađeno: 100 %" with the ring, the trust signal next to every aggregate. */
export function Processed({ value, label = 'Obrađeno biračkih mesta' }: { value: number; label?: string }) {
    const t = useT();
    return (
        <div className="t-data flex items-center gap-2 text-ink-2">
            <RingIndicator value={value} size={18} />
            <span>{t(label)}: <b className="tabular-nums text-ink">{pct(value)}</b></span>
        </div>
    );
}

export function SectionHead({ title, right, className = '' }: { title: string; right?: ReactNode; className?: string }) {
    const t = useT();
    return (
        <div className={`mb-4 flex flex-wrap items-center justify-between gap-3 ${className}`}>
            <h2>{t(title)}</h2>
            {right}
        </div>
    );
}

/**
 * White card. `evidence` draws the share of polling stations this card's numbers
 * rest on as a hairline across its top edge: blue while counting, green at 100 %.
 */
export function Card({ children, className = '', evidence }: { children: ReactNode; className?: string; evidence?: number | null }) {
    const style = evidence == null
        ? undefined
        : {
            '--evidence': `${Math.max(0, Math.min(100, evidence))}%`,
            '--evidence-color': evidence >= 100 ? 'var(--color-ok)' : 'var(--color-primary)',
        } as CSSProperties;
    return <div className={`card ${evidence == null ? '' : 'evidence'} ${className}`} style={style}>{children}</div>;
}

/** Gray full-bleed band that holds the white cards. */
export function Band({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`band py-6 md:py-8 ${className}`}><div className="space-y-6">{children}</div></div>;
}

/* ---------- controls ---------- */

export function Segmented<T extends string>({ options, value, onChange, label }: { options: Array<{ value: T; label: string }>; value: T; onChange: (v: T) => void; label: string }) {
    const t = useT();
    return (
        <div className="seg seg-wide" role="tablist" aria-label={t(label)}>
            {options.map((o) => (
                <button key={o.value} type="button" role="tab" aria-selected={o.value === value} className={`seg-btn ${o.value === value ? 'seg-btn-active' : ''}`} onClick={() => onChange(o.value)}>
                    {t(o.label)}
                </button>
            ))}
        </div>
    );
}

export function Accordion({ title, right, children, defaultOpen = false }: { title: ReactNode; right?: ReactNode; children: ReactNode; defaultOpen?: boolean }) {
    const [open, setOpen] = useState(defaultOpen);
    const id = useId();
    return (
        <div className="acc">
            <button type="button" className="acc-btn" aria-expanded={open} aria-controls={id} onClick={() => setOpen((v) => !v)}>
                <span>{title}</span>
                <span className="flex items-center gap-3 text-sm font-medium text-ink-2">{right}<IconChevron open={open} /></span>
            </button>
            {open && <div id={id} className="acc-body">{children}</div>}
        </div>
    );
}

/* ---------- figures ---------- */

/** Green ring with the value beside it, the turnout figure of every results page. */
export function Donut({ value, label, sub, size = 84 }: { value: number | null | undefined; label: string; sub?: ReactNode; size?: number }) {
    const t = useT();
    const r = 15.5;
    const c = 2 * Math.PI * r;
    const v = Math.max(0, Math.min(100, value ?? 0));
    return (
        <div className="flex items-center gap-4">
            <svg width={size} height={size} viewBox="0 0 40 40" className="shrink-0" aria-hidden="true">
                <circle cx="20" cy="20" r={r} fill="none" stroke="var(--color-track)" strokeWidth="7" />
                <circle cx="20" cy="20" r={r} fill="none" stroke="var(--color-ok)" strokeWidth="7" strokeDasharray={`${(v / 100) * c} ${c}`} transform="rotate(-90 20 20)" />
            </svg>
            <div className="min-w-0">
                <div className="t-label text-ink-2">{t(label)}</div>
                <div className="figure mt-1">{pct(value)}</div>
                {sub && <div className="muted mt-1">{sub}</div>}
            </div>
        </div>
    );
}

/** `wide` makes the tile span the full row on a phone, for the headline number. */
export function Stat({ label, value, sub, wide = false }: { label: string; value: ReactNode; sub?: ReactNode; wide?: boolean }) {
    const t = useT();
    return (
        <div className={`card-flat ${wide ? 'col-span-2 sm:col-span-1' : ''}`}>
            <div className="eyebrow">{t(label)}</div>
            <div className="figure mt-2">{value}</div>
            {sub && <div className="muted mt-1.5">{sub}</div>}
        </div>
    );
}

export function Swatch({ color, index }: { color: string | null | undefined; index: number }) {
    return <span className="inline-block h-4 w-4 shrink-0 rounded-sm" style={{ background: listColor(color, index) }} aria-hidden="true" />;
}

export function Bar({ value, max, color, index, className = '' }: { value: number; max: number; color: string | null | undefined; index: number; className?: string }) {
    const width = max > 0 ? Math.min(100, (value / max) * 100) : 0;
    return (
        <div className={`bar-track ${className}`}>
            <div className="bar-fill" style={{ width: `${width}%`, background: listColor(color, index) }} />
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

/**
 * Says out loud whether the numbers are still provisional. A share of the count
 * is not the same statement as "these are the final results", and the reader
 * should not have to infer which one they are looking at.
 */
export function ResultStatusNote({ status, verified, total }: { status: string; verified: number; total: number }) {
    const t = useT();
    if (status === 'final') {
        return (
            <p className="mt-4 rounded-lg bg-ok-soft px-4 py-2.5 text-sm text-green-900">
                <b>{t('Konačni rezultati')}.</b> {t('Svi zapisnici biračkih odbora su verifikovani i uračunati')} ({num(verified)} {t('od')} {num(total)}).
            </p>
        );
    }
    if (status === 'counting') {
        return (
            <p className="mt-4 rounded-lg bg-warn-soft px-4 py-2.5 text-sm text-amber-900">
                <b>{t('Prethodni rezultati')}.</b> {t('Brojanje je u toku. U zbir ulaze samo verifikovani zapisnici')} ({num(verified)} {t('od')} {num(total)} {t('biračkih mesta')}), {t('pa se brojevi menjaju do utvrđivanja konačnih rezultata.')}
            </p>
        );
    }
    return null;
}

/** Badges a voter cares about on a polling station: step-free access, voting abroad. */
export function StationTags({ station }: { station: { accessible?: boolean; is_diaspora?: boolean; country?: string | null } }) {
    const t = useT();
    if (!station.accessible && !station.is_diaspora) return null;
    return (
        <span className="mt-1 flex flex-wrap gap-1.5">
            {station.accessible && (
                <span className="badge badge-green" title={t('Biračko mesto je pristupačno osobama sa invaliditetom')}>
                    <svg className="mr-1 h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <circle cx="12" cy="4.5" r="2" /><path d="M9 9h6M12 9v6h5M8.5 12a5.5 5.5 0 1 0 7 7" />
                    </svg>
                    {t('pristupačno')}
                </span>
            )}
            {station.is_diaspora && <span className="badge badge-blue">{t('inostranstvo')}{station.country ? `: ${t(station.country)}` : ''}</span>}
        </span>
    );
}

export function Breadcrumbs({ items }: { items: Array<{ label: string; to?: string }> }) {
    const t = useT();
    return (
        <nav className="muted mb-3 flex flex-wrap items-center gap-1" aria-label="Putanja">
            {items.map((item, i) => (
                <span key={`${item.label}-${i}`} className="flex items-center gap-1">
                    {i > 0 && <span aria-hidden="true">/</span>}
                    {item.to ? <a className="link font-normal" href={item.to}>{t(item.label)}</a> : <span className="text-ink">{t(item.label)}</span>}
                </span>
            ))}
        </nav>
    );
}
