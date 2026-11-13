import { Fragment, useEffect, useState } from 'react';
import { ChevronDown, Download } from 'lucide-react';
import api from '../../lib/api';
import { Badge, Button, Callout, Card, PageHeader, Spinner } from '../../components/ui';
import { cx, fmtMoney } from '../../lib/format';

export default function Reports() {
    const [data, setData] = useState(null);
    const [open, setOpen] = useState(null);
    useEffect(() => { api.get('/reports/programs').then(({ data }) => setData(data)); }, []);
    if (!data) return <Spinner />;

    const totals = data.data.reduce((a, r) => ({ total: a.total + r.total, fin: a.fin + r.finalizado, rev: a.rev + r.revenue, own: a.own + r.own_procedures }), { total: 0, fin: 0, rev: 0, own: 0 });

    const exportCsv = () => {
        const head = ['Código', 'Carrera', 'Nivel', 'Facultad', 'Estudiantes', 'Trámites exclusivos', 'Trámites disponibles', 'Solicitudes', 'En proceso', 'Observadas', 'Finalizadas', 'Recaudado'];
        const rows = data.data.map((r) => [r.code, r.name, r.level, r.faculty, r.students, r.own_procedures, r.available_procedures, r.total, r.en_proceso, r.observado, r.finalizado, r.revenue.toFixed(2)]);
        const csv = '﻿' + [head, ...rows].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(';')).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'tramites_por_carrera.csv';
        a.click();
    };

    return (
        <div>
            <PageHeader kicker="Reportes" title="Trámites por carrera" description="Cuántos trámites tiene disponibles cada carrera (pregrado, segunda especialización, maestrías y doctorados) y cuántas solicitudes ha registrado."
                actions={<Button variant="secondary" icon={Download} onClick={exportCsv}>Exportar CSV</Button>} />
            <Callout className="mb-5">Cada carrera tiene disponibles los trámites que figuran para ella en el reporte del sistema académico. <b>Exclusivos</b> = trámites que solo tiene esa carrera ({totals.own} en total). Haz clic en una fila para ver el detalle.</Callout>
            <Card className="divide-y divide-line md:hidden">
                {data.data.map((r) => (
                    <div key={r.id} className="px-4 py-3.5">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0"><p className="font-bold text-ink">{r.name}</p><p className="text-xs text-muted">{r.level} · {r.available_procedures} trámites disponibles</p></div>
                            <p className="shrink-0 text-xl font-extrabold text-ink">{r.total}</p>
                        </div>
                        <div className="mt-2 flex h-2 overflow-hidden rounded-full bg-surface-2">
                            {r.total > 0 && [['finalizado', 'bg-teal-udh'], ['en_proceso', 'bg-sky-500'], ['observado', 'bg-amber-500'], ['rechazado', 'bg-red-500']].map(([k, c]) => <div key={k} className={c} style={{ width: `${(r[k] / r.total) * 100}%` }} />)}
                        </div>
                        <p className="mt-1.5 text-xs text-muted">{r.finalizado} finalizadas · {r.en_proceso} en proceso · {fmtMoney(r.revenue)}</p>
                    </div>
                ))}
            </Card>
            <Card className="hidden overflow-x-auto md:block">
                <table className="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr className="border-b border-line text-left text-xs tracking-wider text-muted uppercase">
                            <th className="px-5 py-3.5">Carrera</th>
                            <th className="px-3 py-3.5 text-right">Estudiantes</th>
                            <th className="px-3 py-3.5">Nivel</th>
                            <th className="px-3 py-3.5 text-right">Exclusivos</th>
                            <th className="px-3 py-3.5 text-right">Disponibles</th>
                            <th className="px-3 py-3.5 text-right">Solicitudes</th>
                            <th className="px-3 py-3.5">Estado</th>
                            <th className="px-3 py-3.5 text-right">Recaudado</th>
                            <th className="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        {data.data.map((r) => (
                            <Fragment key={r.id}>
                                <tr className="cursor-pointer border-b border-line hover:bg-surface-2" onClick={() => setOpen(open === r.id ? null : r.id)}>
                                    <td className="px-5 py-3.5"><p className="font-bold text-ink">{r.name}</p><p className="text-xs text-muted">{r.code} · {r.faculty}{r.modalities ? ` · ${r.modalities}` : ''}</p></td>
                                    <td className="px-3 text-xs font-semibold text-muted">{r.level}</td>
                                    <td className="px-3 text-right">{r.students}</td>
                                    <td className="px-3 text-right">{r.own_procedures > 0 ? <Badge tone="navy">{r.own_procedures}</Badge> : '—'}</td>
                                    <td className="px-3 text-right font-semibold">{r.available_procedures}</td>
                                    <td className="px-3 text-right text-base font-extrabold text-ink">{r.total}</td>
                                    <td className="px-3">
                                        <div className="flex h-2.5 w-40 overflow-hidden rounded-full bg-surface-2">
                                            {r.total > 0 && [['finalizado', 'bg-teal-udh'], ['en_proceso', 'bg-sky-500'], ['observado', 'bg-amber-500'], ['rechazado', 'bg-red-500']].map(([k, c]) => <div key={k} className={c} style={{ width: `${(r[k] / r.total) * 100}%` }} title={`${k}: ${r[k]}`} />)}
                                        </div>
                                    </td>
                                    <td className="px-3 text-right">{fmtMoney(r.revenue)}</td>
                                    <td><ChevronDown className={cx('size-4 text-muted transition', open === r.id && 'rotate-180')} /></td>
                                </tr>
                                {open === r.id && (
                                    <tr className="border-b border-line bg-surface-2/60">
                                        <td colSpan={9} className="px-5 py-4">
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div className="grid grid-cols-3 gap-2 text-center">
                                                    {[['En proceso', r.en_proceso, 'text-sky-600'], ['Observadas', r.observado, 'text-amber-600'], ['Finalizadas', r.finalizado, 'text-teal-udh-600']].map(([l, v, c]) => (
                                                        <div key={l} className="rounded-xl bg-surface p-3 ring-1 ring-line"><p className={cx('text-xl font-extrabold', c)}>{v}</p><p className="text-[11px] text-muted">{l}</p></div>
                                                    ))}
                                                </div>
                                                <div>
                                                    <p className="mb-2 text-xs font-bold tracking-wider text-muted uppercase">Más solicitados</p>
                                                    {(data.top_by_program[r.id] || []).map((t) => <p key={t.procedure_name} className="flex justify-between text-sm"><span className="truncate text-ink">{t.procedure_name}</span><b>{t.total}</b></p>)}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </Fragment>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="font-extrabold text-ink"><td className="px-5 py-3.5">Total</td><td /><td /><td /><td /><td className="px-3 text-right">{totals.total}</td><td className="px-3 text-xs text-muted">{totals.fin} finalizadas</td><td className="px-3 text-right">{fmtMoney(totals.rev)}</td><td /></tr>
                    </tfoot>
                </table>
            </Card>
        </div>
    );
}
