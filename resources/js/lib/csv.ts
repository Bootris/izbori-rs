/*
| CSV izvoz radi u pregledaču, iz podataka koji su već učitani: nijedan
| dodatni zahtev ka serveru. Razdvajač je tačka-zarez, a brojevi idu sa
| decimalnom zapetom, jer Excel na srpskom podešavanju tako otvara fajl.
*/

export interface CsvColumn<T> {
    label: string;
    value: (row: T) => string | number | null | undefined;
}

const cell = (v: string | number | null | undefined): string => {
    if (v === null || v === undefined) return '';
    const text = typeof v === 'number' ? String(v).replace('.', ',') : v;
    return /[";\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
};

export function toCsv<T>(columns: Array<CsvColumn<T>>, rows: T[]): string {
    const head = columns.map((c) => cell(c.label)).join(';');
    const body = rows.map((row) => columns.map((c) => cell(c.value(row))).join(';'));
    return [head, ...body].join('\r\n');
}

/** Saves the table as a file; the BOM keeps Serbian letters readable in Excel. */
export function downloadCsv<T>(filename: string, columns: Array<CsvColumn<T>>, rows: T[]): void {
    const blob = new Blob(['﻿', toCsv(columns, rows)], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
    document.body.append(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}
