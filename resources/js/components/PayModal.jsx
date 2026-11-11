import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, CalendarClock, CheckCircle2, ChevronRight, CreditCard, Download, ExternalLink, Loader2, Lock, RefreshCcw, ShieldCheck, Smartphone, Store } from 'lucide-react';
import api, { errorMessage } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import { Button, Callout, Field, Input, Modal } from './ui';
import { cx, fmtDateTime, fmtMoney, payDeadline } from '../lib/format';

/**
 * Ventana para pagar un ticket. El estudiante elige:
 *  - Pagar en línea:
 *      Con Culqi (PAYMENT_DRIVER=culqi y llaves en .env): abre el Checkout de Culqi en la misma página.
 *      Sin llaves: usa el simulador (Yape, Plin, tarjeta) para la demo.
 *  - Pagar en Caja UDH: el pago ya está cargado a su código; en caja solo da su código de estudiante.
 *      El sistema encuentra el pago en caja y el trámite pasa solo a "en proceso".
 */

let configCache = null;
const loadConfig = async () => (configCache ??= (await api.get('/payments/config')).data);

const loadScript = (src) => new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) return resolve();
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = resolve;
    s.onerror = () => reject(new Error('No se pudo cargar Culqi. Revisa tu conexión a internet.'));
    document.body.appendChild(s);
});

const onlyDigits = (v, max) => v.replace(/\D/g, '').slice(0, max);
const formatCard = (v) => onlyDigits(v, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
const formatExp = (v) => { const d = onlyDigits(v, 4); return d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d; };

const METHODS = [
    { key: 'YAPE', label: 'Yape', color: 'bg-[#742284]', icon: Smartphone },
    { key: 'PLIN', label: 'Plin', color: 'bg-[#00b3c9]', icon: Smartphone },
    { key: 'TARJETA', label: 'Tarjeta', color: 'bg-navy-udh', icon: CreditCard },
];

function Success({ result, onClose }) {
    const navigate = useNavigate();
    const p = result.data;
    return (
        <div className="text-center">
            <CheckCircle2 className="mx-auto size-16 text-teal-udh" />
            <p className="mt-3 text-2xl font-extrabold text-ink">{p.method === 'CAJA' ? '¡Pago en caja confirmado!' : '¡Pago realizado!'}</p>
            <p className="mt-1 text-muted">Tu trámite <b className="text-ink">{p.submission?.code}</b> ya está <b className="text-ink">en proceso</b>.</p>
            <div className="mx-auto mt-5 grid max-w-md gap-2 text-left text-sm">
                {(p.method === 'CAJA'
                    ? [['Monto', fmtMoney(p.amount)], ['Medio', 'Caja UDH'], ['Boleta', p.reference], ['Fecha', fmtDateTime(p.paid_at)]]
                    : [['Monto', fmtMoney(p.amount)], ['Medio', `${p.method_label} ${p.reference || ''}`], ['Ticket', p.code], ['Transacción', p.transaction_code], ['Fecha', fmtDateTime(p.paid_at)]]).map(([k, v]) => (
                    <div key={k} className="flex justify-between gap-4 rounded-lg bg-surface-2 px-3 py-2"><span className="text-muted">{k}</span><span className="truncate font-semibold text-ink">{v}</span></div>
                ))}
            </div>
            {result.submission?.current_step && (
                <p className="mt-4 text-sm text-muted">Siguiente etapa: <b className="text-ink">{result.submission.current_step.name}</b> ({result.submission.current_step.area_label})</p>
            )}
            <div className="mt-6 flex flex-wrap justify-center gap-2">
                <Button variant="secondary" icon={Download} onClick={() => window.open(`/api/payments/${p.id}/receipt`, '_blank')}>Comprobante</Button>
                <Button icon={ExternalLink} onClick={() => { onClose(); navigate(`/mis-tramites?ver=${p.submission_id}`); }}>Ver mi trámite</Button>
            </div>
        </div>
    );
}

function SimulatedForm({ ticket, onPaid }) {
    const [method, setMethod] = useState('YAPE');
    const [f, setF] = useState({ phone: '', otp: '', card_number: '', card_exp: '', card_cvv: '', card_name: '' });
    const [errors, setErrors] = useState({});
    const [failure, setFailure] = useState('');
    const [busy, setBusy] = useState(false);
    const set = (k, v) => { setF({ ...f, [k]: v }); setErrors({ ...errors, [k]: undefined }); setFailure(''); };

    const validate = () => {
        const e = {};
        if (method !== 'TARJETA') {
            if (!/^9\d{8}$/.test(f.phone)) e.phone = 'Debe tener 9 dígitos y empezar con 9.';
            if (!/^\d{6}$/.test(f.otp)) e.otp = 'El código de aprobación tiene 6 dígitos.';
        } else {
            if (f.card_number.replace(/\s/g, '').length < 15) e.card_number = 'Número de tarjeta incompleto.';
            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(f.card_exp)) e.card_exp = 'Formato MM/AA.';
            if (!/^\d{3,4}$/.test(f.card_cvv)) e.card_cvv = '3 o 4 dígitos.';
            if (f.card_name.trim().length < 3) e.card_name = 'Nombre como figura en la tarjeta.';
        }
        setErrors(e);
        return !Object.keys(e).length;
    };

    const pay = async () => {
        if (!validate()) return;
        setBusy(true);
        setFailure('');
        try {
            const body = method === 'TARJETA'
                ? { method, card_number: f.card_number.replace(/\s/g, ''), card_exp: f.card_exp, card_cvv: f.card_cvv, card_name: f.card_name }
                : { method, phone: f.phone, otp: f.otp };
            const { data } = await api.post(`/payments/${ticket.id}/simulate`, body);
            onPaid(data);
        } catch (err) {
            const fields = err.response?.data?.errors || {};
            setErrors(Object.fromEntries(Object.entries(fields).map(([k, v]) => [k, v[0]])));
            setFailure(errorMessage(err, 'No se pudo procesar el pago.'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
                {METHODS.map((m) => (
                    <button key={m.key} type="button" disabled={busy} onClick={() => { setMethod(m.key); setErrors({}); setFailure(''); }}
                        className={cx('flex flex-col items-center gap-2 rounded-xl border-2 p-3 text-sm font-bold transition', method === m.key ? 'border-teal-udh bg-teal-udh/5 text-ink' : 'border-line text-muted hover:border-teal-udh/50')}>
                        <span className={cx('grid size-10 place-items-center rounded-xl text-white', m.color)}><m.icon className="size-5" /></span>
                        {m.label}
                    </button>
                ))}
            </div>
            {method !== 'TARJETA' ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label={`Celular registrado en ${method === 'YAPE' ? 'Yape' : 'Plin'}`} error={errors.phone}>
                        <Input inputMode="numeric" value={f.phone} onChange={(e) => set('phone', onlyDigits(e.target.value, 9))} placeholder="987654321" />
                    </Field>
                    <Field label="Código de aprobación" error={errors.otp} hint="6 dígitos, desde tu app.">
                        <Input inputMode="numeric" value={f.otp} onChange={(e) => set('otp', onlyDigits(e.target.value, 6))} placeholder="••••••" className="font-mono tracking-[0.4em]" />
                    </Field>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <Field label="Número de tarjeta" error={errors.card_number} className="sm:col-span-4">
                        <Input inputMode="numeric" value={f.card_number} onChange={(e) => set('card_number', formatCard(e.target.value))} placeholder="4111 1111 1111 1111" className="font-mono" />
                    </Field>
                    <Field label="Titular" error={errors.card_name} className="sm:col-span-2">
                        <Input value={f.card_name} onChange={(e) => set('card_name', e.target.value.toUpperCase())} placeholder="JUAN PEREZ" />
                    </Field>
                    <Field label="Vence" error={errors.card_exp}><Input inputMode="numeric" value={f.card_exp} onChange={(e) => set('card_exp', formatExp(e.target.value))} placeholder="MM/AA" /></Field>
                    <Field label="CVV" error={errors.card_cvv}><Input type="password" inputMode="numeric" value={f.card_cvv} onChange={(e) => set('card_cvv', onlyDigits(e.target.value, 4))} placeholder="•••" /></Field>
                </div>
            )}
            {failure && <Callout tone="danger">{failure}</Callout>}
            <Button size="lg" className="w-full" onClick={pay} disabled={busy}>
                {busy ? <><Loader2 className="size-5 animate-spin" /> Procesando pago…</> : <><Lock className="size-4" /> Pagar {fmtMoney(ticket.amount)}</>}
            </Button>
            <p className="text-center text-xs text-muted">Modo de prueba · Yape/Plin: cualquier código de 6 dígitos (000000 = rechazado) · Tarjeta 4111 1111 1111 1111 (aprobada) o 4000 0000 0000 0002 (rechazada).</p>
        </div>
    );
}

function ChooseMethod({ cfg, ticket, onOnline, onCaja }) {
    const deadline = payDeadline(ticket);
    const Option = ({ icon: Icon, title, text, onClick, tone }) => (
        <button type="button" onClick={onClick} className="flex w-full items-center gap-4 rounded-2xl border-2 border-line p-4 text-left transition hover:border-teal-udh hover:bg-teal-udh/5">
            <span className={cx('grid size-12 shrink-0 place-items-center rounded-xl text-white', tone)}><Icon className="size-6" /></span>
            <span className="min-w-0 flex-1">
                <span className="block font-extrabold text-ink">{title}</span>
                <span className="block text-sm text-muted">{text}</span>
            </span>
            <ChevronRight className="size-5 shrink-0 text-muted" />
        </button>
    );
    return (
        <div className="space-y-3">
            <p className="text-sm font-bold text-ink">¿Cómo quieres pagar?</p>
            <Option icon={CreditCard} tone="bg-teal-udh" onClick={onOnline} title="Pagar en línea"
                text={cfg?.driver === 'culqi' ? 'Tarjeta, Yape y más con Culqi. Se confirma al instante.' : 'Yape, Plin o tarjeta. Se confirma al instante.'} />
            <Option icon={Store} tone="bg-navy-udh" onClick={onCaja} title="Pagar en Caja UDH"
                text="Presencial: solo da tu código de estudiante; el pago ya está cargado." />
            {deadline && (
                <p className={cx('flex items-center gap-2 rounded-xl px-3 py-2 text-sm', deadline.urgent ? 'bg-red-500/10 text-red-700 dark:text-red-300' : 'bg-surface-2 text-muted')}>
                    <CalendarClock className="size-4 shrink-0" />
                    <span>Tienes hasta el <b>{deadline.date}</b> ({deadline.left}). Si no pagas, la solicitud se anula.</span>
                </p>
            )}
        </div>
    );
}

function CajaInstructions({ cfg, ticket, user, onPaid, onBack }) {
    const [busy, setBusy] = useState('');
    const [msg, setMsg] = useState('');
    const deadline = payDeadline(ticket);
    const call = async (kind) => {
        setBusy(kind);
        setMsg('');
        try {
            const { data } = await api.post(`/payments/${ticket.id}/caja/${kind}`);
            if (data.paid) onPaid(data);
            else setMsg(data.message);
        } catch (e) {
            setMsg(errorMessage(e));
        } finally {
            setBusy('');
        }
    };
    const rows = [
        ['Concepto', ticket.concept],
        ['Monto', fmtMoney(ticket.amount)],
        ...(deadline ? [['Pagar hasta', `${deadline.date} (${deadline.left})`]] : []),
    ];
    return (
        <div className="space-y-4">
            <div>
                <p className="font-extrabold text-ink">Paga en cualquier Caja de la UDH</p>
                <p className="mt-1 text-sm text-muted">Este pago ya está cargado a tu código. En caja solo di tu <b>código de estudiante</b>: el cajero verá tus pagos pendientes y te cobrará. Luego tu trámite pasa solo a <b>en proceso</b>.</p>
            </div>
            <div className="rounded-2xl bg-teal-udh/10 px-4 py-3 text-center ring-1 ring-teal-udh/30">
                <p className="text-xs font-bold tracking-wider text-teal-udh-600 uppercase dark:text-teal-300">Tu código de estudiante</p>
                <p className="mt-0.5 font-mono text-2xl font-extrabold tracking-wider text-ink">{user.student_code}</p>
            </div>
            <div>
                <p className="mb-1.5 text-xs font-bold text-muted uppercase">El cajero verá este pago pendiente</p>
                <div className="divide-y divide-line rounded-2xl border border-line">
                    {rows.map(([k, v]) => (
                        <div key={k} className="flex items-start justify-between gap-4 px-4 py-2.5 text-sm">
                            <span className="shrink-0 text-muted">{k}</span>
                            <span className="text-right font-bold text-ink">{v}</span>
                        </div>
                    ))}
                </div>
            </div>
            {msg && <Callout tone="warning">{msg}</Callout>}
            <div className="flex flex-col gap-2 sm:flex-row">
                <Button variant="ghost" icon={ArrowLeft} onClick={onBack} disabled={!!busy}>Cambiar medio</Button>
                <Button variant="secondary" icon={RefreshCcw} className="sm:ml-auto" loading={busy === 'check'} disabled={!!busy} onClick={() => call('check')}>Ya pagué, verificar</Button>
            </div>
            {cfg?.caja_simulated && (
                <div className="rounded-2xl border border-dashed border-gold-udh/60 bg-gold-udh/10 p-3">
                    <p className="text-xs text-ink"><b>Modo demostración:</b> todavía no estamos conectados al sistema de Caja real. Usa este botón para simular que el cajero te cobró (también lo puede cobrar Tesorería desde «Consulta de caja»).</p>
                    <Button size="sm" icon={Store} className="mt-2 w-full" loading={busy === 'simulate'} disabled={!!busy} onClick={() => call('simulate')}>Simular pago en caja</Button>
                </div>
            )}
        </div>
    );
}

export default function PayModal({ ticket, onClose, onPaid }) {
    const { user, refresh } = useAuth();
    const [cfg, setCfg] = useState(null);
    const [stage, setStage] = useState('loading'); // loading | choose | simulated | caja | culqi | processing | success | error
    const [result, setResult] = useState(null);
    const [message, setMessage] = useState('');
    const [notice, setNotice] = useState('');

    const paid = (data) => {
        setResult(data);
        setStage('success');
        refresh();
        onPaid?.(data);
    };

    const openCulqi = async (c) => {
        setStage('culqi');
        try {
            await loadScript('https://js.culqi.com/checkout-js');
            let orderId = null;
            if (c.orders) {
                const { data } = await api.post(`/payments/${ticket.id}/culqi/order`);
                orderId = data.order_id;
                // Sin orden, Culqi solo muestra tarjeta: avisamos el motivo
                setNotice(orderId ? '' : `Culqi no creó la orden, por eso solo aparece tarjeta. Motivo: ${data.message || 'desconocido'}`);
            }
            const paymentMethods = { tarjeta: true, yape: true, billetera: !!orderId, bancaMovil: !!orderId, agente: !!orderId, cuotealo: false };
            const culqi = new window.CulqiCheckout(c.public_key, {
                settings: { title: 'Universidad de Huánuco', currency: 'PEN', amount: Math.round(ticket.amount * 100), ...(orderId ? { order: orderId } : {}) },
                client: { email: user.email },
                options: { lang: 'auto', installments: false, modal: true, paymentMethods, paymentMethodsSort: Object.keys(paymentMethods) },
                appearance: {
                    theme: 'default',
                    menuType: 'sidebar',
                    buttonCardPayText: 'Pagar',
                    logo: `${window.location.origin}/img/escudo.png`,
                    defaultStyle: { bannerColor: '#0f2440', buttonBackground: '#2eb8a0', menuColor: '#2eb8a0', linksColor: '#2eb8a0', buttonTextColor: '#ffffff', priceColor: '#0f2440' },
                },
            });
            culqi.culqi = async () => {
                if (culqi.token) {
                    const token = culqi.token;
                    culqi.close();
                    setStage('processing');
                    try {
                        const { data } = await api.post(`/payments/${ticket.id}/culqi/charge`, { token: token.id, method: token.iin ? 'tarjeta' : 'yape' });
                        paid(data);
                    } catch (e) {
                        setMessage(errorMessage(e, 'Culqi rechazó el pago.'));
                        setStage('error');
                    }
                } else if (culqi.order) {
                    culqi.close();
                    setStage('processing');
                    const { data } = await api.post(`/payments/${ticket.id}/culqi/check`);
                    if (data.paid) paid(data);
                    else { setMessage(data.message); setStage('error'); }
                } else {
                    setMessage(culqi.error?.user_message || culqi.error?.merchant_message || 'No se completó el pago.');
                    setStage('error');
                }
            };
            culqi.open();
        } catch (e) {
            setMessage(e.message || errorMessage(e));
            setStage('error');
        }
    };

    useEffect(() => {
        if (!ticket) return;
        setStage('loading');
        setResult(null);
        setMessage('');
        loadConfig().then((c) => {
            setCfg(c);
            setStage('choose');
        });
    }, [ticket?.id]); // eslint-disable-line

    const online = () => (cfg?.driver === 'culqi' ? openCulqi(cfg) : setStage('simulated'));

    if (!ticket) return null;

    return (
        <Modal open onClose={onClose} title={stage === 'success' ? 'Pago confirmado' : 'Pagar trámite'} subtitle={stage === 'success' ? undefined : ticket.concept}>
            {stage !== 'success' && (
                <div className="mb-5 flex items-center justify-between gap-4 rounded-2xl bg-navy-udh px-5 py-4 text-white">
                    <div>
                        <p className="text-xs font-bold tracking-wider text-teal-udh uppercase">Monto a pagar</p>
                        <p className="text-3xl font-extrabold">{fmtMoney(ticket.amount)}</p>
                    </div>
                    <p className="flex items-center gap-2 text-right text-xs text-white/70"><ShieldCheck className="size-5 text-teal-udh" />{stage === 'caja' ? 'Caja UDH' : cfg?.driver === 'culqi' ? 'Pago seguro con Culqi' : 'Pasarela de prueba'}</p>
                </div>
            )}
            {stage === 'loading' && <p className="flex items-center justify-center gap-2 py-8 text-muted"><Loader2 className="size-5 animate-spin" /> Preparando el pago…</p>}
            {stage === 'choose' && <ChooseMethod cfg={cfg} ticket={ticket} onOnline={online} onCaja={() => setStage('caja')} />}
            {stage === 'caja' && <CajaInstructions cfg={cfg} ticket={ticket} user={user} onPaid={paid} onBack={() => setStage('choose')} />}
            {stage === 'simulated' && (
                <>
                    <SimulatedForm ticket={ticket} onPaid={paid} />
                    <Button variant="ghost" size="sm" icon={ArrowLeft} className="mt-3" onClick={() => setStage('choose')}>Cambiar medio de pago</Button>
                </>
            )}
            {stage === 'culqi' && (
                <div className="py-6 text-center text-sm text-muted">
                    <Loader2 className="mx-auto mb-3 size-6 animate-spin text-teal-udh" />
                    Completa el pago en la ventana de Culqi (tarjeta, Yape{cfg?.orders ? ', Plin, banca móvil o agentes' : ''}).
                    {notice && <Callout tone="warning" className="mt-3 text-left">{notice}</Callout>}
                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        <Button variant="ghost" icon={ArrowLeft} onClick={() => setStage('choose')}>Cambiar medio</Button>
                        <Button variant="secondary" onClick={() => openCulqi(cfg)}>Volver a abrir Culqi</Button>
                    </div>
                </div>
            )}
            {stage === 'processing' && <p className="flex items-center justify-center gap-2 py-8 text-muted"><Loader2 className="size-5 animate-spin" /> Confirmando el pago con la pasarela…</p>}
            {stage === 'error' && (
                <div className="space-y-4">
                    <Callout tone="danger">{message}</Callout>
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={onClose}>Cerrar</Button>
                        <Button onClick={() => setStage('choose')}>Intentar de nuevo</Button>
                    </div>
                </div>
            )}
            {stage === 'success' && result && <Success result={result} onClose={onClose} />}
        </Modal>
    );
}
