import { useEffect, useState } from 'react';
import { Check, Clock, Download, FileText, GraduationCap, Receipt, XCircle } from 'lucide-react';
import api from '../lib/api';
import { Badge, Button, Modal, Spinner, StatusBadge } from './ui';
import { cx, fmtDateTime, fmtMoney } from '../lib/format';

function Row({ label, children, mono }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-line py-2.5 last:border-0">
            <dt className="shrink-0 text-sm text-muted">{label}</dt>
            <dd className={cx('min-w-0 text-right text-sm font-semibold break-words text-ink', mono && 'font-mono text-[13px]')}>{children || '—'}</dd>
        </div>
    );
}

function Section({ icon: Icon, title, children }) {
    return (
        <section className="rounded-2xl border border-line p-4">
            <p className="mb-1 flex items-center gap-2 text-xs font-bold tracking-wider text-accent uppercase"><Icon className="size-4" /> {title}</p>
            <dl>{children}</dl>
        </section>
    );
}

/** Detalle de un pago para Tesorería: pago, estudiante y trámite (resumido). */
export default function PaymentDetail({ id, onClose }) {
    const [p, setP] = useState(null);
    useEffect(() => {
        if (!id) return;
        setP(null);
        api.get(`/payments/${id}`).then(({ data }) => setP(data.data)).catch(onClose);
    }, [id]); // eslint-disable-line

    const st = p?.student_full;
    const t = p?.tramite;
    const paid = p?.status === 'PAGADO';

    return (
        <Modal open={!!id} onClose={onClose} title={p ? `Ticket ${p.code}` : 'Cargando…'} subtitle={p?.submission?.procedure_name}
            footer={paid && <Button icon={Download} onClick={() => window.open(`/api/payments/${p.id}/receipt`, '_blank')}>Ver comprobante</Button>}>
            {!p ? <Spinner /> : (
                <div className="space-y-4">
                    <div className="flex flex-col gap-3 rounded-2xl bg-navy-udh p-5 text-white sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-xs font-bold tracking-wider text-teal-udh uppercase">{paid ? 'Monto pagado' : 'Monto por cobrar'}</p>
                            <p className="text-3xl font-extrabold">{fmtMoney(p.amount)}</p>
                        </div>
                        <div className="sm:text-right">
                            <Badge tone={paid ? 'teal' : 'gold'} className="!text-white !ring-white/30">{paid ? 'Pagado' : 'Pendiente'}</Badge>
                            <p className="mt-1.5 text-sm text-white/75">{paid ? fmtDateTime(p.paid_at) : `Generado ${fmtDateTime(p.created_at)}`}</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Section icon={Receipt} title="Pago">
                            <Row label="Medio">{p.method_label ? `${p.method_label} ${p.reference || ''}` : null}</Row>
                            <Row label="Transacción" mono>{p.transaction_code}</Row>
                            <Row label="Pasarela">{p.gateway === 'CULQI' ? 'Culqi' : p.gateway ? 'Prueba' : null}</Row>
                            <Row label="Concepto">{p.concept}</Row>
                            {!paid && p.failure_reason && <Row label="Último rechazo"><span className="text-red-600">{p.failure_reason}</span></Row>}
                        </Section>
                        <Section icon={GraduationCap} title="Estudiante">
                            <Row label="Nombre">{st.name}</Row>
                            <Row label="Código" mono>{st.student_code}</Row>
                            <Row label="Programa">{st.program}</Row>
                            <Row label="Correo">{st.email}</Row>
                        </Section>
                    </div>

                    {t && (
                        <Section icon={FileText} title="Trámite">
                            <Row label="Expediente" mono>{t.code}</Row>
                            <Row label="Estado"><StatusBadge status={t.status} /></Row>
                            <div className="scrollbar-thin mt-3 flex gap-1.5 overflow-x-auto pb-1">
                                {t.steps.map((s, i) => (
                                    <span key={i} className={cx('flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold ring-1',
                                        s.state === 'done' ? 'bg-teal-udh/10 text-teal-udh-600 ring-teal-udh/30 dark:text-teal-300'
                                            : s.state === 'current' ? 'bg-sky-500/10 text-sky-700 ring-sky-500/30 dark:text-sky-300'
                                                : s.state === 'rejected' ? 'bg-red-500/10 text-red-600 ring-red-500/30' : 'text-muted ring-line')}>
                                        {s.state === 'done' ? <Check className="size-3" /> : s.state === 'current' ? <Clock className="size-3" /> : s.state === 'rejected' ? <XCircle className="size-3" /> : null}
                                        {i === 0 ? 'Solicitud' : s.label}
                                    </span>
                                ))}
                            </div>
                        </Section>
                    )}
                </div>
            )}
        </Modal>
    );
}
