import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowRight, CalendarClock, Clock, HandCoins, Info, Receipt, Search, Store, UserRound } from 'lucide-react';
import api, { errorMessage } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { Button, Callout, Card, EmptyState, Input, PageHeader, Spinner, StatusBadge, Tabs } from '../components/ui';
import { cx, fmtDateTime, fmtMoney, payDeadline } from '../lib/format';

/**
 * Pagos en caja: todo lo que un estudiante pagó en la Caja de la UDH, buscando por su código.
 * Los pagos de trámites hechos en caja ya aparecen como pagados y el trámite sigue su camino.
 */
export default function CajaPayments() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const isStudent = user.role === 'ESTUDIANTE';
    const [code, setCode] = useState(isStudent ? user.student_code || '' : '');
    const [res, setRes] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [tab, setTab] = useState('TODOS');
    const [charging, setCharging] = useState(null);
    const toast = useToast();

    // Simulación del cajero (Tesorería): cobra una deuda pendiente del código consultado
    const charge = async (d) => {
        if (!window.confirm(`¿Cobrar ${fmtMoney(d.amount)} por «${d.concept}»?`)) return;
        setCharging(d.id);
        try {
            const { data } = await api.post(`/caja/cobrar/${d.id}`);
            toast.success(data.message);
            await search(res.student.code);
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setCharging(null);
        }
    };

    const search = async (value = code) => {
        const c = value.trim();
        if (!c) { setError('Ingresa un código de estudiante.'); return; }
        setBusy(true);
        setError('');
        try {
            const { data } = await api.get('/caja', { params: { code: c } });
            setRes(data);
        } catch (e) {
            setRes(null);
            setError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };
    useEffect(() => { if (isStudent && code) search(code); }, []); // eslint-disable-line

    const rows = (res?.data || []).filter((r) => (tab === 'TODOS' ? true : tab === 'TRAMITES' ? !!r.tramite : !r.tramite));
    const openTramite = (r) => (isStudent ? navigate(`/mis-tramites?ver=${r.tramite.id}`) : navigate(`/pagos?pago=${r.payment_id}`));

    return (
        <div>
            <PageHeader kicker="Caja UDH" title={isStudent ? 'Mis pagos en caja' : 'Consulta de caja'}
                description={isStudent
                    ? 'Todo lo que pagaste presencialmente en la Caja de la UDH. Si pagaste un trámite en caja, aquí lo verás junto con el estado de tu trámite.'
                    : 'Busca por código de estudiante todos los pagos que hizo en la Caja de la UDH.'} />

            <Card className="mb-5 p-4 sm:p-5">
                <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={(e) => { e.preventDefault(); search(); }}>
                    <label className="block flex-1">
                        <span className="mb-1.5 block text-sm font-bold text-ink">Código de estudiante</span>
                        <Input inputMode="numeric" value={code} onChange={(e) => setCode(e.target.value.replace(/\s/g, ''))} placeholder="Ej.: 2021104829" className="font-mono text-base" />
                    </label>
                    <Button type="submit" icon={Search} loading={busy} className="sm:w-40">Consultar</Button>
                </form>
                <p className="mt-3 flex items-start gap-2 text-xs text-muted"><Info className="mt-0.5 size-3.5 shrink-0" />Por ahora los datos de caja son una simulación; luego se conectarán con el sistema de caja de la universidad.</p>
            </Card>

            {error && <Callout tone="warning" className="mb-5">{error}</Callout>}
            {busy && !res && <Spinner />}

            {res && (
                <>
                    <div className="mb-5 overflow-hidden rounded-2xl bg-navy-udh text-white">
                        <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-white/10"><UserRound className="size-6 text-teal-udh" /></span>
                                <div className="min-w-0">
                                    <p className="truncate text-lg font-extrabold">{res.student.name || 'Estudiante'}</p>
                                    <p className="truncate text-sm text-white/70">Código {res.student.code}{res.student.program ? ` · ${res.student.program}` : ''}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3 text-center sm:gap-6 sm:text-right">
                                <div><p className="text-[11px] font-bold tracking-wider text-teal-udh uppercase">Total</p><p className="text-base font-extrabold whitespace-nowrap sm:text-xl">{fmtMoney(res.total)}</p></div>
                                <div><p className="text-[11px] font-bold tracking-wider text-teal-udh uppercase">Pagos</p><p className="text-base font-extrabold whitespace-nowrap sm:text-xl">{res.count}</p></div>
                                <div><p className="text-[11px] font-bold tracking-wider text-teal-udh uppercase">Trámites</p><p className="text-base font-extrabold whitespace-nowrap sm:text-xl">{res.tramites}</p></div>
                            </div>
                        </div>
                    </div>

                    <Card className="mb-5 overflow-hidden">
                        <div className="flex items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
                            <div className="min-w-0">
                                <p className="flex items-center gap-2 font-extrabold text-ink"><Clock className="size-4 text-amber-600" /> Pendientes de pago en caja</p>
                                <p className="text-xs text-muted">{isStudent ? 'Esto es lo que verá el cajero cuando des tu código.' : 'Deudas cargadas a este código por trámites solicitados.'}</p>
                            </div>
                            {res.pending.length > 0 && <p className="shrink-0 text-lg font-extrabold whitespace-nowrap text-ink">{fmtMoney(res.pending_total)}</p>}
                        </div>
                        {res.pending.length === 0 ? (
                            <p className="px-4 py-5 text-center text-sm text-muted sm:px-5">No hay pagos pendientes para este código.</p>
                        ) : (
                            <div className="divide-y divide-line">
                                {res.pending.map((d) => {
                                    const dl = payDeadline(d);
                                    return (
                                        <div key={d.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                                            <div className="min-w-0 flex-1">
                                                <p className="font-bold text-ink">{d.concept}</p>
                                                {d.tramite && <p className="text-xs text-muted">Expediente {d.tramite.code}</p>}
                                                {dl && <p className={cx('mt-1 flex items-center gap-1.5 text-xs font-semibold', dl.urgent ? 'text-red-600' : 'text-amber-700 dark:text-gold-udh')}><CalendarClock className="size-3.5" /> Hasta el {dl.date} · {dl.left}</p>}
                                            </div>
                                            <div className="flex items-center justify-between gap-3 sm:justify-end">
                                                <p className="text-lg font-extrabold whitespace-nowrap text-ink">{fmtMoney(d.amount)}</p>
                                                {res.can_charge && <Button size="sm" icon={HandCoins} loading={charging === d.id} disabled={!!charging} onClick={() => charge(d)}>Cobrar</Button>}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        {res.can_charge && res.pending.length > 0 && (
                            <p className="flex items-start gap-2 border-t border-dashed border-line bg-gold-udh/8 px-4 py-2.5 text-xs text-muted sm:px-5"><Info className="mt-0.5 size-3.5 shrink-0" />Simulación del cajero: «Cobrar» registra el pago como si se hubiera hecho en Caja UDH.</p>
                        )}
                    </Card>

                    <p className="mb-2 text-sm font-extrabold text-ink">Pagos realizados en caja</p>
                    <Tabs value={tab} onChange={setTab} tabs={[
                        { value: 'TODOS', label: 'Todos', count: res.count },
                        { value: 'TRAMITES', label: 'Trámites', count: res.tramites },
                        { value: 'OTROS', label: 'Otros pagos', count: res.count - res.tramites },
                    ]} />

                    {rows.length === 0 ? (
                        <Card><EmptyState icon={Store} title="Sin pagos en caja" description="No hay pagos de este tipo para este código." /></Card>
                    ) : (
                        <Card className="divide-y divide-line overflow-hidden">
                            {rows.map((r) => (
                                <div key={r.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4 sm:px-5">
                                    <div className="flex min-w-0 flex-1 items-start gap-3">
                                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300"><Receipt className="size-5" /></span>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-bold text-ink">{r.concept}</p>
                                            <p className="text-xs text-muted">Boleta <span className="font-mono">{r.receipt_number}</span> · {fmtDateTime(r.paid_at)} · {r.cashier}</p>
                                            {r.tramite && (
                                                <button type="button" onClick={() => openTramite(r)}
                                                    className="mt-2 inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg bg-surface-2 px-2.5 py-1.5 text-left text-xs hover:bg-teal-udh/10">
                                                    <span className="font-semibold text-ink">Expediente {r.tramite.code}</span>
                                                    <StatusBadge status={r.tramite.status} />
                                                    {r.tramite.area && <span className="text-muted">En {r.tramite.area}</span>}
                                                    <ArrowRight className="size-3.5 text-teal-udh" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    <p className="shrink-0 pl-13 text-lg font-extrabold text-ink sm:pl-0 sm:text-right">{fmtMoney(r.amount)}</p>
                                </div>
                            ))}
                        </Card>
                    )}
                </>
            )}
        </div>
    );
}
