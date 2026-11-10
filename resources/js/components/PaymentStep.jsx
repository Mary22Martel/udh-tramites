import { useState } from 'react';
import { CheckCircle2, CreditCard, Landmark, Loader2, Lock, ShieldCheck, Smartphone } from 'lucide-react';
import api, { errorMessage } from '../lib/api';
import { Button, Callout, Field, FileDrop, Input, Select } from './ui';
import { cx, fmtDateTime, fmtMoney } from '../lib/format';

/**
 * Paso de pago del asistente.
 *  - "Pagar en línea" (Yape, Plin, tarjeta): pasarela simulada, se confirma al instante.
 *  - "Ya pagué": el estudiante registra el N° de operación y sube el voucher (Tesorería lo valida).
 * Una vez aprobado el pago en línea, queda bloqueado: no se puede volver a pagar ni cambiar.
 */
const METHODS = [
    { key: 'YAPE', label: 'Yape', icon: Smartphone, color: 'bg-[#742284]' },
    { key: 'PLIN', label: 'Plin', icon: Smartphone, color: 'bg-[#00b3c9]' },
    { key: 'TARJETA', label: 'Tarjeta', icon: CreditCard, color: 'bg-navy-udh' },
];

const onlyDigits = (v, max) => v.replace(/\D/g, '').slice(0, max);
const formatCard = (v) => onlyDigits(v, 16).replace(/(\d{4})(?=\d)/g, '$1 ');
const formatExp = (v) => {
    const d = onlyDigits(v, 4);
    return d.length > 2 ? `${d.slice(0, 2)}/${d.slice(2)}` : d;
};

export function PaidCard({ payment }) {
    return (
        <div className="rounded-2xl border border-teal-udh/40 bg-teal-udh/[0.06] p-5">
            <div className="flex items-start gap-3">
                <CheckCircle2 className="mt-0.5 size-7 shrink-0 text-teal-udh" />
                <div className="min-w-0 flex-1">
                    <p className="text-lg font-extrabold text-ink">Pago realizado con éxito</p>
                    <p className="text-sm text-muted">{payment.method_label} {payment.reference} · {fmtDateTime(payment.paid_at)}</p>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                        <div className="rounded-xl bg-surface px-3 py-2 ring-1 ring-line"><p className="text-xs text-muted">Monto pagado</p><p className="font-extrabold text-ink">{fmtMoney(payment.amount)}</p></div>
                        <div className="rounded-xl bg-surface px-3 py-2 ring-1 ring-line"><p className="text-xs text-muted">Código de transacción</p><p className="font-mono font-bold text-ink">{payment.transaction_code}</p></div>
                    </div>
                    <p className="mt-3 flex items-center gap-1.5 text-xs text-muted"><Lock className="size-3.5" /> El pago ya fue procesado y Tesorería lo validará. Si regresas, solo podrás cambiar tus documentos o la justificación.</p>
                </div>
            </div>
        </div>
    );
}

export default function PaymentStep({ proc, mode, setMode, payment, onPaid, offline, setOffline, paymentMethods }) {
    const [method, setMethod] = useState('YAPE');
    const [f, setF] = useState({ phone: '', otp: '', card_number: '', card_exp: '', card_cvv: '', card_name: '' });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [failure, setFailure] = useState('');
    const set = (k, v) => { setF({ ...f, [k]: v }); setErrors({ ...errors, [k]: undefined }); setFailure(''); };

    // Validación en el navegador antes de enviar
    const validate = () => {
        const e = {};
        if (method !== 'TARJETA') {
            if (!/^9\d{8}$/.test(f.phone)) e.phone = 'Debe tener 9 dígitos y empezar con 9.';
            if (!/^\d{6}$/.test(f.otp)) e.otp = 'El código de aprobación tiene 6 dígitos.';
        } else {
            const n = f.card_number.replace(/\s/g, '');
            if (n.length < 15) e.card_number = 'Número de tarjeta incompleto.';
            if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(f.card_exp)) e.card_exp = 'Formato MM/AA.';
            if (!/^\d{3,4}$/.test(f.card_cvv)) e.card_cvv = '3 o 4 dígitos.';
            if (f.card_name.trim().length < 3) e.card_name = 'Escribe el nombre como figura en la tarjeta.';
        }
        setErrors(e);
        return Object.keys(e).length === 0;
    };

    const pay = async () => {
        if (!validate()) return;
        setProcessing(true);
        setFailure('');
        try {
            const body = method === 'TARJETA'
                ? { method, card_number: f.card_number.replace(/\s/g, ''), card_exp: f.card_exp, card_cvv: f.card_cvv, card_name: f.card_name }
                : { method, phone: f.phone, otp: f.otp };
            const { data } = await api.post('/payments/simulate', { procedure_id: proc.id, ...body });
            onPaid(data.data);
        } catch (err) {
            const fields = err.response?.data?.errors || {};
            setErrors(Object.fromEntries(Object.entries(fields).map(([k, v]) => [k, v[0]])));
            setFailure(errorMessage(err, 'No se pudo procesar el pago.'));
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="space-y-5">
            <div className="flex flex-col gap-3 rounded-2xl bg-navy-udh p-5 text-white sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-xs font-bold tracking-wider text-teal-udh uppercase">Monto a pagar</p>
                    <p className="text-3xl font-extrabold">{fmtMoney(proc.cost)}</p>
                    <p className="text-sm text-white/70">{proc.payment_concept || 'Derecho de trámite'}</p>
                </div>
                <p className="flex items-center gap-2 text-sm text-white/70"><ShieldCheck className="size-5 text-teal-udh" /> Pago seguro · entorno de prueba</p>
            </div>

            {payment ? <PaidCard payment={payment} /> : (
                <>
                    <div className="grid grid-cols-2 gap-2 rounded-xl border border-line p-1">
                        {[['online', 'Pagar en línea', Smartphone], ['voucher', 'Ya pagué (subir voucher)', Landmark]].map(([k, l, I]) => (
                            <button key={k} type="button" onClick={() => setMode(k)}
                                className={cx('flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-bold transition', mode === k ? 'bg-navy-udh text-white dark:bg-teal-udh' : 'text-muted hover:text-ink')}>
                                <I className="size-4" /> {l}
                            </button>
                        ))}
                    </div>

                    {mode === 'online' && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-3 gap-3">
                                {METHODS.map((m) => (
                                    <button key={m.key} type="button" disabled={processing} onClick={() => { setMethod(m.key); setErrors({}); setFailure(''); }}
                                        className={cx('flex flex-col items-center gap-2 rounded-xl border-2 p-3 text-sm font-bold transition', method === m.key ? 'border-teal-udh bg-teal-udh/5 text-ink' : 'border-line text-muted hover:border-teal-udh/50')}>
                                        <span className={cx('grid size-10 place-items-center rounded-xl text-white', m.color)}><m.icon className="size-5" /></span>
                                        {m.label}
                                    </button>
                                ))}
                            </div>

                            {method !== 'TARJETA' ? (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label={`Celular registrado en ${method === 'YAPE' ? 'Yape' : 'Plin'}`} error={errors.phone}>
                                        <Input inputMode="numeric" value={f.phone} onChange={(e) => set('phone', onlyDigits(e.target.value, 9))} placeholder="987654321" />
                                    </Field>
                                    <Field label="Código de aprobación" error={errors.otp} hint={`Lo encuentras en tu app de ${method === 'YAPE' ? 'Yape' : 'Plin'} (6 dígitos).`}>
                                        <Input inputMode="numeric" value={f.otp} onChange={(e) => set('otp', onlyDigits(e.target.value, 6))} placeholder="••••••" className="font-mono tracking-[0.4em]" />
                                    </Field>
                                </div>
                            ) : (
                                <div className="grid gap-4 sm:grid-cols-4">
                                    <Field label="Número de tarjeta" error={errors.card_number} className="sm:col-span-4">
                                        <Input inputMode="numeric" value={f.card_number} onChange={(e) => set('card_number', formatCard(e.target.value))} placeholder="4111 1111 1111 1111" className="font-mono" />
                                    </Field>
                                    <Field label="Nombre del titular" error={errors.card_name} className="sm:col-span-2">
                                        <Input value={f.card_name} onChange={(e) => set('card_name', e.target.value.toUpperCase())} placeholder="JUAN PEREZ" />
                                    </Field>
                                    <Field label="Vence" error={errors.card_exp}>
                                        <Input inputMode="numeric" value={f.card_exp} onChange={(e) => set('card_exp', formatExp(e.target.value))} placeholder="MM/AA" />
                                    </Field>
                                    <Field label="CVV" error={errors.card_cvv}>
                                        <Input inputMode="numeric" type="password" value={f.card_cvv} onChange={(e) => set('card_cvv', onlyDigits(e.target.value, 4))} placeholder="•••" />
                                    </Field>
                                </div>
                            )}

                            {failure && <Callout tone="danger">{failure}</Callout>}

                            <Button size="lg" className="w-full" onClick={pay} disabled={processing}>
                                {processing ? <><Loader2 className="size-5 animate-spin" /> Procesando pago…</> : <><Lock className="size-4" /> Pagar {fmtMoney(proc.cost)}</>}
                            </Button>
                            <p className="text-center text-xs text-muted">
                                Demo: Yape/Plin con cualquier código de 6 dígitos (000000 = rechazado) · Tarjeta 4111 1111 1111 1111 (aprobada) o 4000 0000 0000 0002 (rechazada).
                            </p>
                        </div>
                    )}

                    {mode === 'voucher' && (
                        <div className="space-y-4">
                            <Callout>Si pagaste en Caja UDH, en el banco o en un agente, registra tu N° de operación y adjunta el voucher. Tesorería lo validará antes de que tu trámite continúe.</Callout>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="¿Dónde pagaste?">
                                    <Select value={offline.method} onChange={(e) => setOffline({ ...offline, method: e.target.value })}>
                                        {Object.entries(paymentMethods || {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                    </Select>
                                </Field>
                                <Field label="N° de operación">
                                    <Input value={offline.operation} onChange={(e) => setOffline({ ...offline, operation: e.target.value.replace(/\s/g, '') })} placeholder="Ej.: 04512398" />
                                </Field>
                                <Field label="Fecha de pago">
                                    <Input type="date" value={offline.date} onChange={(e) => setOffline({ ...offline, date: e.target.value })} />
                                </Field>
                            </div>
                            <Field label="Voucher del pago" hint="PDF o imagen, máx. 5 MB.">
                                <FileDrop file={offline.voucher} onChange={(v) => setOffline({ ...offline, voucher: v })} accept=".pdf,.jpg,.jpeg,.png" />
                            </Field>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
