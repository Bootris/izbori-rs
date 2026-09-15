import { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useElection } from '@/app/election-context';
import { useSnapshotData, useSnapshotList, useT } from '@/app/hooks';
import type { ResultsSummary, Unit, UnitResults } from '@/types';
import { num } from '@/lib/format';
import { WithFile } from '@/components/state';
import { CsvButton } from '@/components/Csv';
import { Band, Card, PageTitle, SectionHead, Swatch } from '@/components/ui';

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
        return <div><PageTitle title="Raspodela mandata" /><p className="muted">{t('Predsednički izbori nemaju raspodelu mandata: pobednik je kandidat sa više od polovine glasova, inače se održava drugi krug.')}</p></div>;
    }

    return (
        <div>
            <PageTitle title="Raspodela mandata po D'Hondtovom sistemu" meta={unit.meta} sub={t('Količnik je broj glasova podeljen deliocem. Mandate dobija prvih N najvećih količnika; obeleženi su oni koji su osvojili mandat.')} />
            {(units.list?.length ?? 0) > 1 && (
                <label className="t-data mb-4 flex items-center gap-2">
                    <span className="shrink-0 text-ink-2">{t('Izborna jedinica')}:</span>
                    <select className="select min-w-0 flex-1 sm:flex-none" value={code} onChange={(e) => setParams({ unit: e.target.value })}>
                        {units.list?.map((u) => <option key={u.code} value={u.code}>{t(u.name)}</option>)}
                    </select>
                </label>
            )}
            <Band>
                <WithFile state={unit} unavailable={t('Rezultati još nisu objavljeni.')}>
                    {({ data: u }) => {
                        const qualified = u.lists.filter((l) => l.qualified).sort((a, b) => a.number - b.number);
                        const winning = new Set(u.seat_order.map((s) => `${s.list_id}:${s.divisor}`));
                        const seatNo = new Map(u.seat_order.map((s) => [`${s.list_id}:${s.divisor}`, s.seat_no]));
                        const maxDivisor = Math.max(1, ...u.seat_order.map((s) => s.divisor));
                        const rows = showAll ? (u.seats ?? maxDivisor) : Math.min(u.seats ?? maxDivisor, Math.max(PREVIEW_ROWS, maxDivisor));
                        return (
                            <>
                                <Card>
                                    <p className="t-data mb-3 text-ink-2">{t('Cenzus')}: <b className="text-ink">{num(u.allocation.threshold_votes)}</b> {t('glasova')}. {t('Mandata')}: <b className="text-ink">{u.seats ?? '-'}</b>. {t('Lista u raspodeli')}: <b className="text-ink">{qualified.length}</b>.</p>
                                    {u.allocation.notes.map((n) => <p key={n} className="t-data mb-2 text-amber-900">{t(n)}</p>)}
                                    <p className="muted mb-2 md:hidden">{t('Matrica se pomera levo i desno; kolona delilaca ostaje na mestu.')}</p>
                                    <div className="scroll-x">
                                        <table className="data sticky-col">
                                            <thead>
                                                <tr><th className="num">{t('Delilac')}</th>{qualified.map((l) => <th key={l.list_id}><span className="flex items-center gap-1.5 whitespace-nowrap"><Swatch color={l.color} index={l.number - 1} />{t(l.short_name ?? l.name)}{l.is_minority && !((u.matrix[String(l.list_id)]?.['1'] ?? 0) === l.votes) && <span className="badge badge-blue">1,35</span>}</span></th>)}</tr>
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
                                                            return <td key={l.list_id} className={`num ${won ? 'bg-primary-soft font-semibold' : 'text-ink-3'}`}>{q === undefined ? '' : num(Math.round(q))}{won && <span className="muted"> #{seatNo.get(key)}</span>}</td>;
                                                        })}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {rows < (u.seats ?? 0) && <button type="button" className="btn-link mt-3" onClick={() => setShowAll(true)}>{t('Prikaži sve delioce')} ({u.seats})</button>}
                                </Card>
                                <Card>
                                    <SectionHead title="Redosled dodele mandata" right={
                                        <CsvButton
                                            filename={`redosled-mandata-${code}`}
                                            rows={u.seat_rows}
                                            columns={[
                                                { label: 'Mandat', value: (r) => r.seat_no },
                                                { label: 'Lista', value: (r) => r.list_short_name },
                                                { label: 'Delilac', value: (r) => r.divisor },
                                                { label: 'Količnik', value: (r) => Math.round(r.quotient) },
                                                { label: 'Kandidat', value: (r) => r.candidate?.full_name },
                                                { label: 'Mesto na listi', value: (r) => r.candidate?.position },
                                            ]}
                                        />
                                    } />
                                    <div className="scroll-x">
                                        <table className="data stack min-w-full md:min-w-[560px]">
                                            <thead><tr><th className="num">{t('Mandat')}</th><th>{t('Lista')}</th><th className="num">{t('Delilac')}</th><th className="num">{t('Količnik')}</th><th>{t('Kandidat')}</th></tr></thead>
                                            <tbody>
                                                {u.seat_rows.map((s) => (
                                                    <tr key={s.seat_no}>
                                                        <td className="num drop">{s.seat_no}</td>
                                                        <td className="lead">{s.candidate ? `${s.candidate.position}. ${t(s.candidate.full_name)}` : t('lista nema dovoljno kandidata')}</td>
                                                        <td className="num key" data-label={t('Mandat')}>{s.seat_no}</td>
                                                        <td className="wide"><span className="flex items-center gap-2"><Swatch color={s.color} index={s.list_number - 1} />{t(s.list_short_name)}</span></td>
                                                        <td className="num" data-label={t('Delilac')}>{s.divisor}</td>
                                                        <td className="num" data-label={t('Količnik')}>{num(Math.round(s.quotient))}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </Card>
                            </>
                        );
                    }}
                </WithFile>
            </Band>
        </div>
    );
}
