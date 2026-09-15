import { useT } from '@/app/hooks';
import { downloadCsv, type CsvColumn } from '@/lib/csv';

interface Props<T> {
    filename: string;
    columns: Array<CsvColumn<T>>;
    rows: T[];
    label?: string;
}

/** "Preuzmi tabelu (.csv)" next to a table; builds the file from the rows already on screen. */
export function CsvButton<T>({ filename, columns, rows, label = 'Preuzmi tabelu (.csv)' }: Props<T>) {
    const t = useT();
    if (rows.length === 0) return null;
    return (
        <button type="button" className="btn-csv" onClick={() => downloadCsv(filename, columns, rows)}>
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d="M12 3v12m0 0-4-4m4 4 4-4" /><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
            </svg>
            {t(label)}
        </button>
    );
}
