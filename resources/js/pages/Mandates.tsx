import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { ResultsSummary, Unit, UnitResults } from '@/types';
import { num } from '@/lib/format';
import { WithFile } from '@/components/state';
import { PageTitle, Swatch } from '@/components/ui';

const PREVIEW_ROWS = 40;

export function Mandates() {
    const { election } = useElection();
    const t = useT();
    const [params, setParams] = useSearchParams();
    const units = useSnapshotList<Unit>('registry', 'units.json');
    const summary = useSnapshotData<ResultsSummary>('results', 'results-summary.json');
    const code = params.get('unit') ?? units.list?.[0]?.code ?? summary.item?.units[0]?.code ?? '';
    const unit = useSnapshotData<UnitResults>('results', `results-unit-${code}.json`, { skip: !code });
    const [showAll, setShowAll] = useState(false);

    if (election.type === 'presidential') {
        return <div className="space-y-6"><PageTitle title="Mandati" />{t('Predsednički izbori nemaju raspodelu mandata — pobednik je kandidat sa više od polovine glasova, inače drugi krug.')}</div>;
    }

    return (
        <div className="space-y-6">
            <PageTitle title="Raspodela mandata — D'Hondt" meta={unit.meta}>{t('Količnik = glasovi ÷ delilac. Mandate dobija prvih N najvećih količnika; obeleženi su oni koji su osvojili mandat.')}</PageTitle>
            {(units.list?.length ?? 0) > 1 && (
                <label className="text-sm">{t('Izborna jedinica')}:{' '}
                    <select className="rounded-md border border-zinc-300 px-2 py-1 dark:border-zinc-700 dark:bg-zinc-800" value={code} onChange={(e) => setParams({ unit: e.target.value })}>
                        {units.list?.map((u) => <option key={u.code} value={u.code}>{t(u.name)}</option>)}
                    </select>
                </label>
            )}
            <WithFile state={unit} unavailable={t('Rezultati još nisu objavljeni.')}>
                {({ data: u }) => {
                    const qualified = u.lists.filter((l) => l.qualified).sort((a, b) => a.number - b.number);
                    const winning = new Set(u.seat_order.map((s) => `${s.list_id}:${s.divisor}`));
                    const seatNo = new Map(u.seat_order.map((s) => [`${s.list_id}:${s.divisor}`, s.seat_no]));
                    const maxDivisor = Math.max(1, ...u.seat_order.map((s) => s.divisor));
                    const rows = showAll ? (u.seats ?? maxDivisor) : Math.min(u.seats ?? maxDivisor, Math.max(PREVIEW_ROWS, maxDivisor));
                    return (
                        <>
                            <p className="muted">{t('Cenzus')}: {num(u.allocation.threshold_votes)} {t('glasova')} · {t('mandata')}: {u.seats ?? '—'} · {t('kvalifikovanih lista')}: {qualified.length}</p>
                            {u.allocation.notes.map((n) => <p key={n} className="text-sm text-amber-800 dark:text-amber-300">{t(n)}</p>)}
                            <div className="card overflow-x-auto">
                                <table className="data">
                                    <thead>
                                        <tr><th className="num">{t('Delilac')}</th>{qualified.map((l) => <th key={l.list_id}><span className="flex items-center gap-1"><Swatch color={l.color} index={l.number - 1} />{t(l.short_name ?? l.name)}{l.is_minority && !((u.matrix[String(l.list_id)]?.['1'] ?? 0) === l.votes) && <span className="badge badge-blue">×1,35</span>}</span></th>)}</tr>
                                        <tr><th className="num">{t('glasova')}</th>{qualified.map((l) => <th key={l.list_id} className="num">{num(l.votes)}</th>)}</tr>
                                    </thead>
                                    <tbody>
                                        {Array.from({ length: rows }, (_, i) => i + 1).map((d) => (
                                            <tr key={d}>
                                                <td className="num">{d}</td>
                                                {qualified.map((l) => {
                                                    const key = `${l.list_id}:${d}`;
                                                    const q = u.matrix[String(l.list_id)]?.[String(d)];
                                                    const won = winning.has(key);
                                                    return <td key={l.list_id} className={`num ${won ? 'bg-primary-soft font-semibold dark:bg-blue-950' : 'text-zinc-500'}`}>{q === undefined ? '' : num(Math.round(q))}{won && <span className="muted"> #{seatNo.get(key)}</span>}</td>;
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {rows < (u.seats ?? 0) && <button type="button" className="link mt-3 text-sm" onClick={() => setShowAll(true)}>{t('Prikaži sve delioce')} ({u.seats})</button>}
                            </div>
                            <div className="card overflow-x-auto">
                                <h2 className="mb-3">{t('Redosled dodele mandata')}</h2>
                                <table className="data">
                                    <thead><tr><th className="num">#</th><th>{t('Lista')}</th><th className="num">{t('Delilac')}</th><th className="num">{t('Količnik')}</th><th>{t('Kandidat')}</th></tr></thead>
                                    <tbody>
                                        {u.seat_rows.map((s) => (
                                            <tr key={s.seat_no}><td className="num">{s.seat_no}</td><td><span className="flex items-center gap-2"><Swatch color={s.color} index={s.list_number - 1} />{t(s.list_short_name)}</span></td><td className="num">{s.divisor}</td><td className="num">{num(Math.round(s.quotient))}</td><td>{s.candidate ? `${s.candidate.position}. ${t(s.candidate.full_name)}` : '—'}</td></tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    );
                }}
            </WithFile>
        </div>
    );
}
