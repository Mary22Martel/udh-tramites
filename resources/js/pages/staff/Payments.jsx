import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CalendarDays, ChevronRight, Clock, Download, Landmark, SlidersHorizontal, Wallet } from 'lucide-react';
import api, { downloadFile } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { Badge, Button, Card, Field, Input, PageHeader, Pagination, SearchBar, Select, Spinner, StatCard, Tabs } from '../../components/ui';
import PaymentDetail from '../../components/PaymentDetail';
import { cx, fmtDateTime, fmtMoney, fmtNumber } from '../../lib/format';

const statusBadge = (s) => <Badge tone={s === 'PAGADO' ? 'teal' : s === 'PENDIENTE' ? 'gold' : 'gray'}>{s === 'PAGADO' ? 'Pagado' : s === 'PENDIENTE' ? 'Pendiente' : s}</Badge>;

/** Tesorería / Administración: pagos que llegan por la pasarela. Solo consulta (no aprueban). */
export default function Payments() {
    const { meta } = useAuth();
    const [params, setParams] = useSearchParams();
    const [f, setF] = useState({ status: 'PAGADO', search: '', method: '', program_id: '', from: '', to: '' });
    const [showFilters, setShowFilters] = useState(false);
    const [page, setPage] = useState(1);
    const [res, setRes] = useState(null);
    const detail = params.get('pago');
    const open = (id) => { params.set('pago', id); setParams(params); };
    const close = () => { params.delete('pago'); setParams(params, { replace: true }); };
    const set = (k) => (v) => { setF({ ...f, [k]: v?.target ? v.target.value : v }); setPage(1); };

    useEffect(() => {
        const t = setTimeout(() => api.get('/payments', { params: { ...f, page } }).then(({ data }) => setRes(data)), 250);
        return () => clearTimeout(t);
    }, [JSON.stringify(f), page]); // eslint-disable-line

    const k = res?.kpis;
    return (
        <div>
            <PageHeader kicker="Tesorería" title="Pagos recibidos" description="Pagos de derechos de trámite: en línea (los confirma la pasarela) y en Caja UDH."
                actions={<Button variant="secondary" icon={Download} onClick={() => downloadFile('/payments-export', f)}>Exportar</Button>} />
            {!res ? <Spinner /> : (
                <>
                    <div className="mb-4 grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
                        <StatCard label="Recaudado" value={fmtMoney(k.total)} icon={Landmark} tone="teal" hint={`${fmtNumber(k.count)} pagos`} />
                        <StatCard label="Este mes" value={fmtMoney(k.month)} icon={CalendarDays} tone="navy" />
                        <StatCard label="Hoy" value={fmtMoney(k.today)} icon={Wallet} tone="gold" />
                        <StatCard label="Por cobrar" value={fmtMoney(k.pending_total)} icon={Clock} tone="amber" hint={`${k.pending_count} tickets`} />
                    </div>
                    <div className="scrollbar-thin mb-5 flex gap-2 overflow-x-auto pb-1">
                        {res.by_method.map((m) => (
                            <span key={m.method} className="shrink-0 rounded-xl bg-surface px-3 py-2 text-sm ring-1 ring-line"><b className="text-ink">{m.label}</b> <span className="text-muted">· {m.n} {m.n === 1 ? 'pago' : 'pagos'} · {fmtMoney(m.total)}</span></span>
                        ))}
                    </div>
                </>
            )}
            <Tabs value={f.status} onChange={set('status')} tabs={[{ value: 'PAGADO', label: 'Pagados' }, { value: 'PENDIENTE', label: 'Pendientes' }, { value: 'TODOS', label: 'Todos' }]} />
            <SearchBar value={f.search} onChange={set('search')} placeholder="Buscar ticket, estudiante, código o trámite…" count={res?.meta.total} countLabel="pagos">
                <Button variant="secondary" icon={SlidersHorizontal} onClick={() => setShowFilters(!showFilters)} className="md:w-auto">Filtros</Button>
            </SearchBar>
            {showFilters && (
                <Card className="-mt-2 mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Medio de pago">
                        <Select value={f.method} onChange={set('method')}>
                            <option value="">Todos</option>
                            {Object.entries({ CAJA: 'Caja UDH', TARJETA: 'Tarjeta', YAPE: 'Yape', PLIN: 'Plin', BILLETERA: 'Billetera', BANCA_MOVIL: 'Banca móvil', AGENTE: 'Agente' }).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </Select>
                    </Field>
                    <Field label="Carrera">
                        <Select value={f.program_id} onChange={set('program_id')}>
                            <option value="">Todas</option>
                            {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="Pagado desde"><Input type="date" value={f.from} onChange={set('from')} /></Field>
                    <Field label="Hasta"><Input type="date" value={f.to} onChange={set('to')} /></Field>
                </Card>
            )}
            {res && (
                <Card className="overflow-hidden">
                    {/* Escritorio: tabla */}
                    <table className="hidden w-full text-sm lg:table">
                        <thead>
                            <tr className="border-b border-line text-left text-xs tracking-wider text-muted uppercase">
                                <th className="px-5 py-3">Ticket</th><th className="px-3 py-3">Estudiante</th><th className="px-3 py-3">Trámite</th>
                                <th className="px-3 py-3">Medio</th><th className="px-3 py-3">Fecha</th><th className="px-3 py-3">Estado</th><th className="px-5 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            {res.data.map((p) => (
                                <tr key={p.id} className="cursor-pointer border-b border-line last:border-0 hover:bg-surface-2" onClick={() => open(p.id)}>
                                    <td className="px-5 py-3 font-mono font-bold text-ink">{p.code}</td>
                                    <td className="px-3"><p className="font-semibold text-ink">{p.student?.name}</p><p className="text-xs text-muted">{p.student?.student_code} · {p.program?.code}</p></td>
                                    <td className="max-w-[260px] px-3"><p className="truncate text-ink">{p.submission?.procedure_name}</p></td>
                                    <td className="px-3 text-muted">{p.method_label || '—'}</td>
                                    <td className="px-3 whitespace-nowrap text-muted">{fmtDateTime(p.paid_at || p.created_at)}</td>
                                    <td className="px-3">{statusBadge(p.status)}</td>
                                    <td className="px-5 text-right font-extrabold text-ink">{fmtMoney(p.amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {/* Celular y tablet: tarjetas */}
                    <div className="divide-y divide-line lg:hidden">
                        {res.data.map((p) => (
                            <button key={p.id} onClick={() => open(p.id)} className="flex w-full items-center gap-3 px-4 py-3.5 text-left hover:bg-surface-2">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2"><p className="truncate font-bold text-ink">{p.student?.name}</p>{statusBadge(p.status)}</div>
                                    <p className="truncate text-sm text-ink/80">{p.submission?.procedure_name}</p>
                                    <p className="truncate text-xs text-muted">{p.code} · {p.method_label || 'Sin pagar'} · {fmtDateTime(p.paid_at || p.created_at)}</p>
                                </div>
                                <p className="shrink-0 font-extrabold text-ink">{fmtMoney(p.amount)}</p>
                                <ChevronRight className="size-4 shrink-0 text-muted" />
                            </button>
                        ))}
                    </div>
                    {res.data.length === 0 && <p className="py-12 text-center text-muted">No hay pagos con estos filtros.</p>}
                    {res.data.length > 0 && f.status !== 'PENDIENTE' && (
                        <div className={cx('flex justify-between border-t border-line px-5 py-3 text-sm font-extrabold text-ink')}><span>Total pagado (con estos filtros)</span><span>{fmtMoney(res.kpis.filtered_total)}</span></div>
                    )}
                </Card>
            )}
            <Pagination meta={res?.meta} onPage={setPage} />
            <PaymentDetail id={detail} onClose={close} />
        </div>
    );
}
