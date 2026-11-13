import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { CalendarDays, CheckCircle2, ChevronRight, Clock, CreditCard, Download, HandCoins, Landmark, SlidersHorizontal, Wallet, X } from 'lucide-react';
import api, { downloadFile, errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Badge, Button, Callout, Card, Field, Input, Modal, PageHeader, Pagination, SearchBar, Select, Spinner, StatCard, Tabs } from '../../components/ui';
import PaymentDetail from '../../components/PaymentDetail';
import { cx, fmtDateTime, fmtMoney, fmtNumber, payDeadline } from '../../lib/format';

const statusBadge = (s) => <Badge tone={s === 'PAGADO' ? 'teal' : s === 'PENDIENTE' ? 'gold' : 'gray'}>{s === 'PAGADO' ? 'Pagado' : s === 'PENDIENTE' ? 'Pendiente' : s}</Badge>;

/** Confirmación para registrar un pago como Caja UDH. */
function ChargeModal({ payment, onClose, onDone }) {
    const toast = useToast();
    const [busy, setBusy] = useState(false);
    if (!payment) return null;
    const d = payDeadline(payment);
    const confirm = async () => {
        setBusy(true);
        try {
            const { data } = await api.post(`/caja/cobrar/${payment.id}`);
            toast.success(data.message);
            onDone(data);
        } catch (e) {
            toast.error(errorMessage(e));
            setBusy(false);
        }
    };
    const rows = [
        ['Estudiante', `${payment.student?.name} · ${payment.student?.student_code}`],
        ['Carrera', payment.program?.name],
        ['Trámite', payment.submission?.procedure_name],
        ['Expediente', payment.submission?.code],
        ['Pagar hasta', d ? `${d.date} (${d.left})` : null],
    ].filter(([, v]) => v);
    return (
        <Modal open onClose={busy ? undefined : onClose} size="sm" title="¿Confirmar el pago?" subtitle="Se registrará como pagado en Caja UDH."
            footer={<>
                <Button variant="ghost" icon={X} disabled={busy} onClick={onClose}>Cancelar</Button>
                <Button icon={CheckCircle2} loading={busy} onClick={confirm}>Sí, confirmar pago</Button>
            </>}>
            <div className="mb-4 rounded-2xl bg-navy-udh px-5 py-4 text-white">
                <p className="text-xs font-bold tracking-wider text-teal-udh uppercase">Monto recibido</p>
                <p className="text-3xl font-extrabold">{fmtMoney(payment.amount)}</p>
                <p className="mt-1 text-sm text-white/70">{payment.concept}</p>
            </div>
            <dl className="divide-y divide-line rounded-2xl border border-line">
                {rows.map(([k, v]) => (
                    <div key={k} className="flex items-start justify-between gap-4 px-4 py-2.5 text-sm">
                        <dt className="shrink-0 text-muted">{k}</dt><dd className="text-right font-semibold text-ink">{v}</dd>
                    </div>
                ))}
            </dl>
            <Callout className="mt-4">Confirma solo si ya recibiste el dinero. Se genera la boleta de caja, el estudiante ve su pago como hecho en Caja UDH y su trámite pasa a <b>en proceso</b>.</Callout>
        </Modal>
    );
}

/** Tesorería / Administración: pagos recibidos (en línea y en caja) y cobro de pagos pendientes. */
export default function Payments({ initialStatus = 'PAGADO' }) {
    const { meta } = useAuth();
    const [charging, setCharging] = useState(null);
    const [reload, setReload] = useState(0);
    const [params, setParams] = useSearchParams();
    const [f, setF] = useState({ status: initialStatus, search: '', method: '', bank: '', program_id: '', from: '', to: '' });
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
    }, [JSON.stringify(f), page, reload]); // eslint-disable-line
    const pickMethod = (m) => { setF({ ...f, status: 'PAGADO', method: f.method === m.method && f.bank === (m.bank || '') ? '' : m.method, bank: f.method === m.method && f.bank === (m.bank || '') ? '' : (m.bank || '') }); setPage(1); };

    const k = res?.kpis;
    return (
        <div>
            <PageHeader kicker="Tesorería" title={initialStatus === 'PENDIENTE' ? 'Cobrar pagos' : 'Pagos recibidos'} description="Pagos de derechos de trámite en línea y en Caja UDH. En «Por cobrar» registras los pagos que recibes en caja."
                actions={<>
                    <Button icon={HandCoins} onClick={() => { setF({ ...f, status: 'PENDIENTE', method: '', bank: '' }); setPage(1); }}>Cobrar pagos</Button>
                    <Button variant="secondary" icon={Download} onClick={() => downloadFile('/payments-export', f)}>Exportar</Button>
                </>} />
            {!res ? <Spinner /> : (
                <>
                    <div className="mb-4 grid grid-cols-2 gap-3 md:gap-4 xl:grid-cols-4">
                        <StatCard label="Recaudado" value={fmtMoney(k.total)} icon={Landmark} tone="teal" hint={`${fmtNumber(k.count)} pagos`} />
                        <StatCard label="Este mes" value={fmtMoney(k.month)} icon={CalendarDays} tone="navy" />
                        <StatCard label="Hoy" value={fmtMoney(k.today)} icon={Wallet} tone="gold" />
                        <button type="button" className="text-left" onClick={() => { setF({ ...f, status: 'PENDIENTE', method: '', bank: '' }); setPage(1); }}>
                            <StatCard label="Por cobrar" value={fmtMoney(k.pending_total)} icon={Clock} tone="amber" hint={`${k.pending_count} pendientes · ver`} />
                        </button>
                    </div>
                    <p className="mb-2 text-xs font-bold tracking-wider text-muted uppercase">Recaudado por medio de pago</p>
                    <div className="scrollbar-thin mb-5 flex gap-2 overflow-x-auto pb-1">
                        {res.by_method.map((m) => {
                            const active = f.method === m.method && f.bank === (m.bank || '');
                            return (
                                <button key={`${m.method}-${m.bank}`} type="button" onClick={() => pickMethod(m)} title="Filtrar por este medio"
                                    className={cx('flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-sm ring-1 transition', active ? 'bg-navy-udh text-white ring-navy-udh dark:bg-teal-udh' : 'bg-surface ring-line hover:ring-teal-udh')}>
                                    {m.method === 'TARJETA' && <CreditCard className={cx('size-4', active ? 'text-white' : 'text-muted')} />}
                                    <b className={active ? 'text-white' : 'text-ink'}>{m.label}</b>
                                    <span className={active ? 'text-white/80' : 'text-muted'}>· {m.n} {m.n === 1 ? 'pago' : 'pagos'} · {fmtMoney(m.total)}</span>
                                </button>
                            );
                        })}
                    </div>
                </>
            )}
            <Tabs value={f.status} onChange={set('status')} tabs={[{ value: 'PAGADO', label: 'Pagados' }, { value: 'PENDIENTE', label: 'Por cobrar', count: k?.pending_count }, { value: 'TODOS', label: 'Todos' }]} />
            {f.status === 'PENDIENTE' && (
                <Callout className="mb-4" title="Cobrar en caja">Cuando un estudiante te pague, busca su nombre o código y presiona <b>Cobrar</b>. Su trámite pasa a «en proceso» al instante.</Callout>
            )}
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
                                {f.status === 'PENDIENTE' && <th className="px-5 py-3" />}
                            </tr>
                        </thead>
                        <tbody>
                            {res.data.map((p) => (
                                <tr key={p.id} className="cursor-pointer border-b border-line last:border-0 hover:bg-surface-2" onClick={() => open(p.id)}>
                                    <td className="px-5 py-3 font-mono font-bold text-ink">{p.code}</td>
                                    <td className="px-3"><p className="font-semibold text-ink">{p.student?.name}</p><p className="text-xs text-muted">{p.student?.student_code} · {p.program?.code}</p></td>
                                    <td className="max-w-[260px] px-3"><p className="truncate text-ink">{p.submission?.procedure_name}</p></td>
                                    <td className="px-3 text-muted">{p.method_detail || '—'}</td>
                                    <td className="px-3 whitespace-nowrap text-muted">{fmtDateTime(p.paid_at || p.created_at)}</td>
                                    <td className="px-3">{statusBadge(p.status)}</td>
                                    <td className="px-5 text-right font-extrabold text-ink">{fmtMoney(p.amount)}</td>
                                    {f.status === 'PENDIENTE' && (
                                        <td className="px-5 text-right"><Button size="sm" icon={HandCoins} onClick={(e) => { e.stopPropagation(); setCharging(p); }}>Cobrar</Button></td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {/* Celular y tablet: tarjetas */}
                    <div className="divide-y divide-line lg:hidden">
                        {res.data.map((p) => (
                            <div key={p.id} className="flex w-full items-center gap-3 px-4 py-3.5 hover:bg-surface-2">
                                <button type="button" onClick={() => open(p.id)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2"><p className="truncate font-bold text-ink">{p.student?.name}</p>{statusBadge(p.status)}</div>
                                        <p className="truncate text-sm text-ink/80">{p.submission?.procedure_name}</p>
                                        <p className="truncate text-xs text-muted">{p.student?.student_code} · {p.method_detail || 'Sin pagar'} · {fmtDateTime(p.paid_at || p.created_at)}</p>
                                    </div>
                                    <p className="shrink-0 font-extrabold text-ink">{fmtMoney(p.amount)}</p>
                                    {p.status !== 'PENDIENTE' && <ChevronRight className="size-4 shrink-0 text-muted" />}
                                </button>
                                {p.status === 'PENDIENTE' && <Button size="sm" icon={HandCoins} onClick={() => setCharging(p)}>Cobrar</Button>}
                            </div>
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
            {charging && <ChargeModal payment={charging} onClose={() => setCharging(null)} onDone={() => { setCharging(null); setReload((n) => n + 1); }} />}
        </div>
    );
}
