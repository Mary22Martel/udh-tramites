import { useEffect, useState } from 'react';
import {
    AlertTriangle, Ban, Check, CheckCircle2, Circle, Clock, CreditCard, Download, Eye, FileCheck2, FileText,
    History, Image as ImageIcon, MessageSquareWarning, Printer, Send, User, XCircle,
} from 'lucide-react';
import api, { downloadFile, errorMessage } from '../lib/api';
import { ACTION_LABELS, DOC_TYPES, cx, fileSize, fmtDate, fmtDateTime, fmtMoney } from '../lib/format';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { Badge, Button, Callout, Field, FileDrop, Input, Modal, Spinner, StatusBadge, Tabs, Textarea } from './ui';

const STEP_STYLE = {
    done: { ring: 'bg-teal-udh text-white', line: 'bg-teal-udh', icon: Check },
    current: { ring: 'bg-sky-500 text-white ring-4 ring-sky-500/20', line: 'bg-line', icon: Clock },
    observed: { ring: 'bg-amber-500 text-white ring-4 ring-amber-500/20', line: 'bg-line', icon: AlertTriangle },
    rejected: { ring: 'bg-red-500 text-white ring-4 ring-red-500/20', line: 'bg-line', icon: XCircle },
    pending: { ring: 'bg-surface-2 text-muted ring-1 ring-line', line: 'bg-line', icon: Circle },
};

function Stepper({ steps }) {
    return (
        <div className="scrollbar-thin -mx-1 overflow-x-auto px-1 pb-2">
            <ol className="flex min-w-max gap-0">
                {steps.map((st, i) => {
                    const S = STEP_STYLE[st.state];
                    return (
                        <li key={st.number} className="relative flex w-40 flex-col items-center text-center">
                            {i < steps.length - 1 && <span className={cx('absolute top-4 left-1/2 h-0.5 w-full', st.state === 'done' ? 'bg-teal-udh' : 'bg-line')} />}
                            <span className={cx('relative z-10 grid size-8 place-items-center rounded-full text-xs font-bold', S.ring)}>
                                {st.state === 'pending' ? st.number : <S.icon className="size-4" />}
                            </span>
                            <span className="mt-2 line-clamp-2 px-2 text-xs font-bold text-ink">{st.name}</span>
                            <span className="mt-0.5 line-clamp-1 px-2 text-[11px] text-muted">{st.area_label}</span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

function ActionForm({ mode, sub, onDone, onCancel }) {
    const toast = useToast();
    const p = sub.permissions;
    const [comment, setComment] = useState('');
    const [reason, setReason] = useState('');
    const [number, setNumber] = useState(p.suggested_number || '');
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        setBusy(true);
        try {
            const fd = new FormData();
            let url = `/submissions/${sub.id}/${mode}`;
            if (mode === 'approve') {
                comment && fd.append('comment', comment);
                p.needs_document && fd.append('official_number', number);
                file && fd.append('file', file);
            } else if (mode === 'observe') {
                fd.append('reason', reason);
                fd.append('detail', comment);
            } else if (mode === 'reject') {
                fd.append('reason', comment);
            } else if (mode === 'correct') {
                fd.append('comment', comment);
                file && fd.append('file', file);
            }
            const { data } = await api.post(url, fd);
            toast.success({ approve: 'Etapa aprobada. La solicitud pasó al siguiente paso.', observe: 'Observación registrada y notificada al estudiante.', reject: 'La solicitud fue denegada.', correct: 'Subsanación enviada. Tu trámite continúa.' }[mode]);
            onDone(data.data);
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };

    const tone = { approve: 'border-teal-udh/40 bg-teal-udh/[0.04]', observe: 'border-amber-400/50 bg-amber-500/[0.04]', reject: 'border-red-400/50 bg-red-500/[0.04]', correct: 'border-teal-udh/40 bg-teal-udh/[0.04]' }[mode];
    const title = { approve: 'Aprobar y pasar al siguiente paso', observe: 'Observar solicitud', reject: 'Denegar solicitud de forma definitiva', correct: 'Subsanar observación' }[mode];

    return (
        <div className={cx('mb-5 space-y-4 rounded-2xl border p-4', tone)}>
            <p className="font-bold text-ink">{title}</p>
            {mode === 'approve' && p.is_payment_step && (
                <Callout tone="info">Verifica que el número de operación <b>{sub.payment.operation}</b> por <b>{fmtMoney(sub.payment.amount)}</b> ({sub.payment.method}) figure en el estado de cuenta antes de aprobar.</Callout>
            )}
            {mode === 'approve' && p.needs_document && (
                <>
                    <Field label={`N° de ${p.document_label}`} hint="Se genera un número sugerido; puedes editarlo.">
                        <Input value={number} onChange={(e) => setNumber(e.target.value)} />
                    </Field>
                    <Field label="Adjuntar documento firmado (opcional)" hint="Si no adjuntas un archivo, el sistema genera el documento en PDF automáticamente.">
                        <FileDrop file={file} onChange={setFile} accept=".pdf,.jpg,.png,.doc,.docx" compact />
                    </Field>
                </>
            )}
            {mode === 'observe' && (
                <Field label="Motivo de la observación">
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Voucher ilegible" list="obs-reasons" />
                    <datalist id="obs-reasons">
                        <option value="Voucher ilegible" />
                        <option value="Monto de pago incorrecto" />
                        <option value="Fotografía no cumple el formato" />
                        <option value="Documento incompleto o sin firma" />
                        <option value="Requisito faltante" />
                    </datalist>
                </Field>
            )}
            <Field label={{ approve: 'Comentario (opcional)', observe: 'Qué debe corregir el estudiante', reject: 'Motivo de la denegación', correct: 'Describe la corrección realizada' }[mode]}>
                <Textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={3} />
            </Field>
            {mode === 'correct' && (
                <Field label="Adjuntar documento corregido">
                    <FileDrop file={file} onChange={setFile} accept=".pdf,.jpg,.jpeg,.png" compact />
                </Field>
            )}
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="ghost" onClick={onCancel}>Cancelar</Button>
                <Button
                    loading={busy}
                    variant={{ approve: 'primary', observe: 'warning', reject: 'danger', correct: 'primary' }[mode]}
                    icon={{ approve: CheckCircle2, observe: MessageSquareWarning, reject: Ban, correct: Send }[mode]}
                    onClick={submit}
                    disabled={(mode === 'observe' && (!reason || !comment)) || (mode === 'reject' && comment.length < 10) || (mode === 'correct' && comment.length < 5)}
                >
                    {{ approve: 'Confirmar aprobación', observe: 'Registrar y notificar', reject: 'Confirmar denegación', correct: 'Enviar subsanación' }[mode]}
                </Button>
            </div>
        </div>
    );
}

function DocumentRow({ d }) {
    const isImg = /\.(jpe?g|png)$/i.test(d.name);
    const Icon = isImg ? ImageIcon : ['OFICIO', 'RESOLUCION', 'CONSTANCIA', 'CONSTANCIA_FINAL'].includes(d.type) ? FileCheck2 : FileText;
    return (
        <div className="flex items-center gap-3 rounded-xl border border-line bg-surface-2/60 px-3.5 py-3">
            <div className={cx('grid size-10 shrink-0 place-items-center rounded-lg', d.type === 'CONSTANCIA_FINAL' ? 'bg-gold-udh/20 text-amber-700 dark:text-gold-udh' : 'bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300')}>
                <Icon className="size-5" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold text-ink">{d.official_number || d.name}</p>
                <p className="truncate text-xs text-muted">
                    {DOC_TYPES[d.type] || d.type} · {d.uploaded_by_name} · {fmtDate(d.created_at)} · {d.has_file ? fileSize(d.size_bytes) : 'generado por el sistema'}
                </p>
            </div>
            <Button variant="ghost" size="sm" icon={Eye} onClick={() => window.open(`/api/documents/${d.id}/download?inline=1`, '_blank')}>Ver</Button>
            <Button variant="secondary" size="sm" icon={Download} onClick={() => downloadFile(`/documents/${d.id}/download`)} />
        </div>
    );
}

function Timeline({ events }) {
    const color = (a) => (a === 'OBSERVADO' ? 'bg-amber-500' : a === 'RECHAZADO' ? 'bg-red-500' : a === 'FINALIZADO' ? 'bg-gold-udh' : a === 'CREACION' ? 'bg-sky-500' : 'bg-teal-udh');
    return (
        <ol className="relative ml-3 border-l-2 border-line">
            {events.map((e) => (
                <li key={e.id} className="mb-5 ml-6 last:mb-0">
                    <span className={cx('absolute -left-[9px] mt-1 size-4 rounded-full ring-4 ring-surface', color(e.action))} />
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-bold text-ink">{ACTION_LABELS[e.action] || e.action}</p>
                        <span className="text-xs text-muted">{fmtDateTime(e.created_at)}</span>
                    </div>
                    <p className="mt-0.5 text-xs font-semibold text-muted">
                        {e.actor_name} · {e.actor_area} · Paso {e.step_number}: {e.step_name}
                    </p>
                    {e.comment && <p className="mt-1.5 rounded-lg bg-surface-2 px-3 py-2 text-[13px] leading-relaxed text-ink/90">{e.comment}</p>}
                    {e.document_name && <p className="mt-1 text-xs text-accent">📎 {e.document_name}</p>}
                </li>
            ))}
        </ol>
    );
}

export default function SubmissionDetail({ id, onClose, onChanged }) {
    const { refresh } = useAuth();
    const [sub, setSub] = useState(null);
    const [tab, setTab] = useState('detalle');
    const [mode, setMode] = useState(null);

    useEffect(() => {
        if (!id) return;
        setSub(null);
        setMode(null);
        setTab('detalle');
        api.get(`/submissions/${id}`).then(({ data }) => setSub(data.data)).catch(() => onClose());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    const done = (updated) => {
        setSub(updated);
        setMode(null);
        refresh();
        onChanged?.(updated);
    };

    const p = sub?.permissions || {};
    const finalDoc = sub?.documents?.find((d) => d.type === 'CONSTANCIA_FINAL');

    const hasActions = sub && ((sub.status === 'FINALIZADO' && sub.final_document_code) || (p.can_correct && !mode) || (p.can_act && !mode));
    const footer = hasActions && (
        <>
            {sub.status === 'FINALIZADO' && sub.final_document_code && (
                <>
                    <Button variant="secondary" icon={Printer} onClick={() => window.open(`/documento/${sub.final_document_code}`, '_blank')}>Constancia con QR</Button>
                    {finalDoc && <Button variant="gold" icon={Download} onClick={() => downloadFile(`/documents/${finalDoc.id}/download`)}>Descargar documento final</Button>}
                </>
            )}
            {p.can_correct && !mode && <Button icon={Send} onClick={() => { setMode('correct'); setTab('detalle'); }}>Subsanar observación</Button>}
            {p.can_act && !mode && (
                <>
                    {p.can_reject && <Button variant="secondary" className="text-red-600" icon={Ban} onClick={() => { setMode('reject'); setTab('detalle'); }}>Denegar</Button>}
                    {p.can_observe && <Button variant="secondary" className="text-amber-600" icon={MessageSquareWarning} onClick={() => { setMode('observe'); setTab('detalle'); }}>Observar</Button>}
                    <Button icon={CheckCircle2} onClick={() => { setMode('approve'); setTab('detalle'); }}>
                        {p.is_payment_step ? 'Validar pago y continuar' : p.needs_document ? `Emitir ${p.document_label?.toLowerCase()} y continuar` : 'Aprobar y continuar'}
                    </Button>
                </>
            )}
        </>
    );

    return (
        <Modal
            open={!!id}
            onClose={onClose}
            size="lg"
            title={sub ? sub.procedure_name : 'Cargando…'}
            subtitle={sub && `Expediente ${sub.code} · ${sub.procedure_code} · ${sub.procedure_category}`}
            footer={footer}
        >
            {!sub ? <Spinner /> : (
                <div>
                    <div className="mb-5 flex flex-wrap items-center gap-2">
                        <StatusBadge status={sub.status} />
                        <Badge tone={sub.payment_status === 'VERIFICADO' ? 'teal' : sub.payment_status === 'PENDIENTE' ? 'amber' : 'gray'}>
                            <CreditCard className="size-3" /> Pago: {sub.payment_status.replace('_', ' ').toLowerCase()}
                        </Badge>
                        {sub.channel && <Badge tone="navy">{sub.channel.split(':')[0]}</Badge>}
                        <span className="ml-auto text-xs text-muted">Registrado el {fmtDateTime(sub.created_at)}</span>
                    </div>

                    <Stepper steps={sub.steps} />

                    {sub.status === 'OBSERVADO' && sub.observation && (
                        <Callout tone="warning" className="mt-4" title={`Observado por ${sub.observation.area}: ${sub.observation.reason}`}>
                            {sub.observation.detail}
                            <span className="mt-1 block text-xs opacity-80">{sub.observation.by} · {fmtDateTime(sub.observation.at)}</span>
                        </Callout>
                    )}
                    {sub.status === 'RECHAZADO' && (
                        <Callout tone="danger" className="mt-4" title="Solicitud denegada">
                            {sub.events.filter((e) => e.action === 'RECHAZADO').at(-1)?.comment}
                        </Callout>
                    )}
                    {sub.status === 'FINALIZADO' && (
                        <Callout tone="success" className="mt-4" title="Trámite culminado">
                            Documento oficial <b>{sub.final_document_code}</b> emitido el {fmtDateTime(sub.finished_at)}. Puede verificarse con el código QR.
                        </Callout>
                    )}

                    <div className="mt-5">
                        {mode && <ActionForm mode={mode} sub={sub} onDone={done} onCancel={() => setMode(null)} />}

                        <Tabs
                            value={tab}
                            onChange={setTab}
                            tabs={[
                                { value: 'detalle', label: 'Detalle', icon: User },
                                { value: 'documentos', label: 'Documentos', icon: FileText, count: sub.documents.length },
                                { value: 'historial', label: 'Historial', icon: History, count: sub.events.length },
                            ]}
                        />

                        {tab === 'detalle' && (
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-2xl border border-line p-4">
                                    <p className="kicker mb-3">Estudiante</p>
                                    <p className="font-bold text-ink">{sub.student.name}</p>
                                    <p className="text-sm text-muted">Código {sub.student.student_code} · {sub.student.email}</p>
                                    <p className="mt-2 text-sm text-ink">{sub.program?.name}</p>
                                    <p className="text-sm text-muted">{sub.faculty?.name}</p>
                                </div>
                                <div className="rounded-2xl border border-line p-4">
                                    <p className="kicker mb-3">Pago</p>
                                    {sub.cost > 0 ? (
                                        <>
                                            <p className="text-2xl font-extrabold text-ink">{fmtMoney(sub.cost)}</p>
                                            <p className="text-sm text-muted">{sub.payment.method?.replace('_', ' ')} · Operación N° {sub.payment.operation || '—'}</p>
                                            <p className="text-sm text-muted">Fecha de pago: {fmtDate(sub.payment.date)}</p>
                                        </>
                                    ) : <p className="text-sm text-muted">Trámite gratuito, no requiere pago.</p>}
                                </div>
                                <div className="rounded-2xl border border-line p-4 md:col-span-2">
                                    <p className="kicker mb-2">Justificación del estudiante</p>
                                    <p className="text-sm leading-relaxed text-ink">{sub.description}</p>
                                </div>
                                {sub.current_step && (
                                    <div className="rounded-2xl border border-sky-500/30 bg-sky-500/[0.04] p-4 md:col-span-2">
                                        <p className="kicker mb-2 !text-sky-600 dark:!text-sky-300">Etapa actual</p>
                                        <p className="font-bold text-ink">Paso {sub.current_step.number} de {sub.total_steps}: {sub.current_step.name}</p>
                                        <p className="text-sm text-muted">Responsable: {sub.current_step.area_label}{sub.channel ? ` · ${sub.channel}` : ''}</p>
                                    </div>
                                )}
                            </div>
                        )}
                        {tab === 'documentos' && (
                            <div className="space-y-2">
                                {sub.documents.length === 0 && <p className="py-6 text-center text-sm text-muted">Sin documentos.</p>}
                                {sub.documents.map((d) => <DocumentRow key={d.id} d={d} />)}
                            </div>
                        )}
                        {tab === 'historial' && <Timeline events={sub.events} />}
                    </div>
                </div>
            )}
        </Modal>
    );
}
