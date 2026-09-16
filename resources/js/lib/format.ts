const nf = new Intl.NumberFormat('sr-Latn-RS');
const pf = new Intl.NumberFormat('sr-Latn-RS', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pf1 = new Intl.NumberFormat('sr-Latn-RS', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const df = new Intl.DateTimeFormat('sr-Latn-RS', { day: 'numeric', month: 'long', year: 'numeric' });
const dfShort = new Intl.DateTimeFormat('sr-Latn-RS', { day: '2-digit', month: '2-digit', year: 'numeric' });
const dtf = new Intl.DateTimeFormat('sr-Latn-RS', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const dtfs = new Intl.DateTimeFormat('sr-Latn-RS', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
const tfs = new Intl.DateTimeFormat('sr-Latn-RS', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

/** Placeholder for a value that is not published yet. */
export const NA = '-';

export const num = (v: number | null | undefined): string => (v == null ? NA : nf.format(v));
export const pct = (v: number | null | undefined): string => (v == null ? NA : `${pf.format(v)} %`);
export const pct1 = (v: number | null | undefined): string => (v == null ? NA : `${pf1.format(v)} %`);
export const date = (iso: string | null | undefined): string => (iso ? dfShort.format(new Date(iso)) : NA);
export const dateLong = (iso: string | null | undefined): string => (iso ? df.format(new Date(iso)) : NA);
export const dateTime = (iso: string | null | undefined): string => (iso ? dtf.format(new Date(iso)) : NA);
export const dateTimeSec = (iso: string | null | undefined): string => (iso ? dtfs.format(new Date(iso)) : NA);
export const timeSec = (iso: string | null | undefined): string => (iso ? tfs.format(new Date(iso)) : NA);

/** Fallback categorical slot for a list without its own color (fixed order, never cycled past 8). */
export const seriesColor = (index: number): string => `var(--series-${(index % 8) + 1})`;

export const listColor = (color: string | null | undefined, index: number): string => color || seriesColor(index);

/** Short label for a list: its short name, else the first words of the full name. */
export const shortName = (name: string, short: string | null | undefined): string => {
    if (short) return short;
    const words = name.split(/\s+/);
    return words.length <= 3 ? name : `${words.slice(0, 3).join(' ')}...`;
};

/** Serbian plural: plural(3, ['opština', 'opštine', 'opština']) picks the form for 1 / 2-4 / 5+. */
export const plural = (n: number, forms: [string, string, string]): string => {
    const m10 = n % 10;
    const m100 = n % 100;
    if (m10 === 1 && m100 !== 11) return forms[0];
    if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return forms[1];
    return forms[2];
};

/** Case- and diacritic-insensitive search key (Č, Ć, Š, Ž, Đ fold to ASCII). */
export const searchKey = (s: string): string =>
    s.toLowerCase().replace(/đ/g, 'dj').normalize('NFD').replace(/[̀-ͯ]/g, '');

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

export const INCIDENT_SEVERITY_LABEL: Record<string, string> = {
    low: 'Niska',
    medium: 'Srednja',
    high: 'Visoka',
    critical: 'Kritična',
};

export const INCIDENT_STATUS_LABEL: Record<string, string> = {
    open: 'Otvorena',
    in_review: 'U obradi',
    resolved: 'Rešena',
    dismissed: 'Odbačena',
};

export const INCIDENT_CATEGORY_LABEL: Record<string, string> = {
    voting_interrupted: 'Prekid glasanja',
    materials: 'Izborni materijal',
    board_dispute: 'Spor u biračkom odboru',
    voter_roll: 'Birački spisak',
    intimidation: 'Pritisak na birače ili nasilje',
    observers: 'Posmatrači',
    facility: 'Prostorija biračkog mesta',
    other: 'Ostalo',
};
