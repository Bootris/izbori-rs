const nf = new Intl.NumberFormat('sr-Latn-RS');
const pf = new Intl.NumberFormat('sr-Latn-RS', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const df = new Intl.DateTimeFormat('sr-Latn-RS', { day: '2-digit', month: '2-digit', year: 'numeric' });
const dtf = new Intl.DateTimeFormat('sr-Latn-RS', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

export const num = (v: number | null | undefined): string => (v == null ? '—' : nf.format(v));
export const pct = (v: number | null | undefined): string => (v == null ? '—' : `${pf.format(v)} %`);
export const date = (iso: string | null | undefined): string => (iso ? df.format(new Date(iso)) : '—');
export const dateTime = (iso: string | null | undefined): string => (iso ? dtf.format(new Date(iso)) : '—');

/** Fallback categorical slot for a list without its own color (validated default palette, fixed order). */
export const seriesColor = (index: number): string => `var(--series-${(index % 8) + 1})`;

export const listColor = (color: string | null | undefined, index: number): string => color || seriesColor(index);

export const ELECTION_TYPE_LABEL: Record<string, string> = {
    parliamentary: 'Parlamentarni izbori',
    provincial: 'Pokrajinski izbori',
    local: 'Lokalni izbori',
    presidential: 'Predsednički izbori',
};

export const STATUS_LABEL: Record<string, string> = {
    draft: 'Priprema',
    registry: 'Registar objavljen',
    voting: 'Glasanje u toku',
    counting: 'Brojanje u toku',
    final: 'Konačni rezultati',
};

export const PROTOCOL_STATUS_LABEL: Record<string, string> = {
    entered: 'Unet, čeka verifikaciju',
    flagged: 'Sa odstupanjem',
    verified: 'Verifikovan',
    annulled: 'Poništen',
};
