import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    AlertTriangle, Ban, Check, CheckCircle2, Clock, CreditCard, Download, Eye, FileCheck2, FileText,
    History, Image as ImageIcon, MessageSquareWarning, Send, User, XCircle,
} from 'lucide-react';
import api, { downloadFile, errorMessage } from '../lib/api';
import { ACTION_LABELS, DOC_TYPES, cx, fileSize, fmtDate, fmtDateTime, fmtMoney, payDeadline } from '../lib/format';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { Badge, Button, Callout, Field, FileDrop, Input, Modal, Spinner, StatusBadge, Tabs, Textarea } from './ui';

const STEP_STYLE = {
    done: { ring: 'bg-teal-udh text-white', icon: Check },
    current: { ring: 'bg-sky-500 text-white ring-4 ring-sky-500/20', icon: Clock },
    observed: { ring: 'bg-amber-500 text-white ring-4 ring-amber-500/20', icon: AlertTriangle },
    rejected: { ring: 'bg-red-500 text-white ring-4 ring-red-500/20', icon: XCircle },
    pending: { ring: 'bg-surface-2 text-muted ring-1 ring-line', icon: null },
};

/** Recorrido del trámite: muestra por qué área pasa (Programa de…, Facultad de…, Matrícula). */
function Stepper({ steps }) {
    return (
        <div className="scrollbar-thin -mx-1 overflow-x-auto px-1 pb-2">
            <ol className="flex min-w-max">
                {steps.map((st, i) => {
                    const S = STEP_STYLE[st.state];
                    return (
                        <li key={st.number} className="relative flex w-32 flex-col items-center text-center sm:w-36">
                            {i < steps.length - 1 && <span className={cx('absolute top-4 left-1/2 h-0.5 w-full', st.state === 'done' ? 'bg-teal-udh' : 'bg-line')} />}
                            <span className={cx('relative z-10 grid size-8 place-items-center rounded-full text-xs font-bold', S.ring)}>
                                {S.icon ? <S.icon className="size-4" /> : st.number}
                            </span>
                            <span className="mt-2 line-clamp-2 px-1.5 text-xs font-bold text-ink">{i === 0 ? 'Solicitud' : st.area_label}</span>
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
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const lastStep = mode === 'approve' && p.is_last_step;

    const submit = async () => {
        setBusy(true);
        try {
            const fd = new FormData();
            if (mode === 'observe') {
                fd.append('reason', reason);
                fd.append('detail', comment);
            } else if (mode === 'reject') {
                fd.append('reason', comment);
            } else {
                comment && fd.append('comment', comment);
                file && fd.append('file', file);
            }
            const { data } = await api.post(`/submissions/${sub.id}/${mode}`, fd);
            toast.success({
                approve: lastStep ? 'Trámite finalizado. El estudiante ya puede descargar su documento.' : 'Aprobado. La solicitud pasó al siguiente paso.',
                observe: 'Observación enviada al estudiante.',
                reject: 'La solicitud fue denegada.',
                correct: 'Corrección enviada. Tu trámite continúa.',
            }[mode]);
            onDone(data.data);
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };

    const tone = { approve: 'border-teal-udh/40 bg-teal-udh/[0.04]', observe: 'border-amber-400/50 bg-amber-500/[0.04]', reject: 'border-red-400/50 bg-red-500/[0.04]', correct: 'border-teal-udh/40 bg-teal-udh/[0.04]' }[mode];
    const title = { approve: lastStep ? 'Aprobar y entregar el documento final' : 'Aprobar', observe: 'Observar solicitud', reject: 'Denegar solicitud', correct: 'Corregir observación' }[mode];
    const disabled = (mode === 'observe' && (!reason || !comment)) || (mode === 'reject' && comment.length < 10) || (mode === 'correct' && comment.length < 5) || (lastStep && !file);

    return (
        <div className={cx('mb-5 space-y-4 rounded-2xl border p-4', tone)}>
            <p className="font-bold text-ink">{title}</p>
            {mode === 'approve' && p.is_payment_step && (
                <Callout tone="info">Pago {sub.payment.method?.toLowerCase()} por <b>{fmtMoney(sub.payment.amount)}</b> · transacción <b>{sub.payment.operation}</b>.</Callout>
            )}
            {mode === 'observe' && (
                <Field label="Motivo">
                    <Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej.: Falta un documento" list="obs-reasons" />
                    <datalist id="obs-reasons">
                        <option value="Falta un documento" />
                        <option value="Documento ilegible" />
                        <option value="Documento sin firma" />
                        <option value="Fotografía no cumple el formato" />
                    </datalist>
                </Field>
            )}
            <Field label={{ approve: 'Comentario (opcional)', observe: 'Qué debe corregir el estudiante', reject: 'Motivo de la denegación', correct: 'Describe la corrección' }[mode]}>
                <Textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={3} />
            </Field>
            {(mode === 'approve' || mode === 'correct') && (
                <Field
                    label={lastStep ? <>Documento para el estudiante <span className="text-red-500">*</span></> : 'Adjuntar archivo (opcional)'}
                    hint={mode === 'approve'
                        ? `${lastStep ? 'Es el último paso: sube el documento que recibirá el estudiante (PDF, imagen, Word…).' : 'Si adjuntas un archivo, el estudiante podrá verlo.'} También se le enviará automáticamente a su correo${sub.student?.email ? ` (${sub.student.email})` : ''}.`
                        : undefined}
                >
                    <FileDrop file={file} onChange={setFile} accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip" compact />
                </Field>
            )}
            <div className="flex flex-wrap justify-end gap-2">
                <Button variant="ghost" onClick={onCancel}>Cancelar</Button>
                <Button loading={busy} disabled={disabled} onClick={submit}
                    variant={{ approve: 'primary', observe: 'warning', reject: 'danger', correct: 'primary' }[mode]}
                    icon={{ approve: CheckCircle2, observe: MessageSquareWarning, reject: Ban, correct: Send }[mode]}>
                    {{ approve: lastStep ? 'Aprobar y finalizar' : 'Aprobar', observe: 'Enviar observación', reject: 'Denegar', correct: 'Enviar corrección' }[mode]}
                </Button>
            </div>
        </div>
    );
}

function DocumentRow({ d, highlight }) {
    const isImg = /\.(jpe?g|png)$/i.test(d.name);
    const Icon = isImg ? ImageIcon : d.type === 'DOCUMENTO_FINAL' ? FileCheck2 : FileText;
    return (
        <div className={cx('flex items-center gap-3 rounded-xl border px-3 py-2.5', highlight ? 'border-teal-udh/40 bg-teal-udh/[0.05]' : 'border-line bg-surface-2/60')}>
            <div className={cx('grid size-9 shrink-0 place-items-center rounded-lg', d.type === 'DOCUMENTO_FINAL' ? 'bg-gold-udh/20 text-amber-700 dark:text-gold-udh' : 'bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300')}>
                <Icon className="size-[18px]" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold text-ink">{d.name}</p>
                <p className="truncate text-xs text-muted">{DOC_TYPES[d.type] || d.type} · {d.uploaded_by_name} · {fmtDate(d.created_at)}{d.has_file ? ` · ${fileSize(d.size_bytes)}` : ''}</p>
            </div>
            <button title="Ver" onClick={() => window.open(`/api/documents/${d.id}/download?inline=1`, '_blank')} className="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface hover:text-ink"><Eye className="size-4" /></button>
            <button title="Descargar" onClick={() => downloadFile(`/documents/${d.id}/download`)} className="grid size-9 place-items-center rounded-lg text-muted hover:bg-surface hover:text-ink"><Download className="size-4" /></button>
        </div>
    );
}

function Timeline({ events }) {
    const color = (a) => (a === 'OBSERVADO' ? 'bg-amber-500' : a === 'RECHAZADO' ? 'bg-red-500' : a === 'FINALIZADO' ? 'bg-gold-udh' : a === 'CREACION' ? 'bg-sky-500' : 'bg-teal-udh');
    return (
        <ol className="relative ml-3 border-l-2 border-line">
            {events.map((e) => (
                <li key={e.id} className="mb-4 ml-5 last:mb-0">
                    <span className={cx('absolute -left-[9px] mt-1 size-4 rounded-full ring-4 ring-surface', color(e.action))} />
                    <div className="flex flex-wrap items-baseline gap-x-2">
                        <p className="text-sm font-bold text-ink">{ACTION_LABELS[e.action] || e.action}</p>
                        <span className="text-xs text-muted">{fmtDateTime(e.created_at)}</span>
                    </div>
                    <p className="text-xs text-muted">{e.actor_area}</p>
                    {e.comment && <p className="mt-1 rounded-lg bg-surface-2 px-3 py-2 text-[13px] leading-relaxed text-ink/90">{e.comment}</p>}
                    {e.document_name && <p className="mt-1 truncate text-xs text-accent">📎 {e.document_name}</p>}
                </li>
            ))}
        </ol>
    );
}

export default function SubmissionDetail({ id, onClose, onChanged }) {
    const { user, refresh } = useAuth();
    const navigate = useNavigate();
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
    const start = (m) => { setMode(m); setTab('detalle'); };

    const p = sub?.permissions || {};
    const isStudent = sub && sub.student?.id === user.id;
    const finalDocs = sub?.documents?.filter((d) => d.type === 'DOCUMENTO_FINAL') || [];
    const canPay = isStudent && sub.status === 'PENDIENTE_PAGO' && sub.ticket?.status === 'PENDIENTE';

    const actions = sub && (
        <>
            {canPay && <Button icon={CreditCard} onClick={() => { onClose(); navigate('/mis-pagos'); }}>Ir a Mis pagos</Button>}
            {sub.status === 'FINALIZADO' && finalDocs[0] && <Button variant="gold" icon={Download} onClick={() => downloadFile(`/documents/${finalDocs[0].id}/download`)}>Descargar documento</Button>}
            {p.can_correct && !mode && <Button icon={Send} onClick={() => start('correct')}>Corregir observación</Button>}
            {p.can_act && !mode && (
                <>
                    {p.can_reject && <Button variant="secondary" className="text-red-600" icon={Ban} onClick={() => start('reject')}>Denegar</Button>}
                    {p.can_observe && <Button variant="secondary" className="text-amber-600" icon={MessageSquareWarning} onClick={() => start('observe')}>Observar</Button>}
                    <Button icon={CheckCircle2} onClick={() => start('approve')}>{p.is_last_step ? 'Aprobar y finalizar' : 'Aprobar'}</Button>
                </>
            )}
        </>
    );
    const hasActions = sub && (canPay || (sub.status === 'FINALIZADO' && finalDocs[0]) || (p.can_correct && !mode) || (p.can_act && !mode));

    return (
        <Modal open={!!id} onClose={onClose} size="lg" title={sub ? sub.procedure_name : 'Cargando…'} subtitle={sub && `Expediente ${sub.code}`} footer={hasActions && actions}>
            {!sub ? <Spinner /> : (
                <div>
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <StatusBadge status={sub.status} />
                        {sub.cost > 0 && sub.ticket?.status === 'PAGADO' && <Badge tone="teal"><CreditCard className="size-3" /> {sub.ticket.method === 'CAJA' ? 'Pagado en caja' : 'Pagado'}</Badge>}
                        <span className="text-xs text-muted">{fmtDate(sub.created_at)}</span>
                    </div>

                    <Stepper steps={sub.steps} />

                    {sub.status === 'PENDIENTE_PAGO' && (
                        <Callout tone="warning" className="mt-3" title="Pendiente de pago">
                            {isStudent
                                ? <>Paga {fmtMoney(sub.ticket?.amount)} en «Mis pagos» (en línea) o en <b>Caja UDH</b> dando tu código de estudiante (el pago ya está cargado), y tu trámite empezará a atenderse.</>
                                : 'El estudiante aún no paga; la solicitud no ha ingresado al flujo.'}
                            {payDeadline(sub.ticket) && <span className="mt-1 block font-semibold">{payDeadline(sub.ticket).text}. Si no se paga, la solicitud se anula.</span>}
                        </Callout>
                    )}
                    {sub.status === 'VENCIDO' && (
                        <Callout tone="danger" className="mt-3" title="Solicitud anulada por falta de pago">El pago no se realizó dentro del plazo de 5 días hábiles.</Callout>
                    )}
                    {sub.status === 'EN_PROCESO' && sub.current_step && (
                        <Callout className="mt-3">Ahora está en <b>{sub.current_step.area_label}</b>{sub.channel ? ` · ${sub.channel.split(':')[0]}` : ''}.</Callout>
                    )}
                    {sub.status === 'OBSERVADO' && sub.observation && (
                        <Callout tone="warning" className="mt-3" title={`Observado: ${sub.observation.reason}`}>
                            {sub.observation.detail}
                            <span className="mt-1 block text-xs opacity-80">{sub.observation.area} · {fmtDateTime(sub.observation.at)}</span>
                        </Callout>
                    )}
                    {sub.status === 'RECHAZADO' && (
                        <Callout tone="danger" className="mt-3" title="Solicitud denegada">{sub.events.filter((e) => e.action === 'RECHAZADO').at(-1)?.comment}</Callout>
                    )}
                    {sub.status === 'FINALIZADO' && (
                        <div className="mt-3 space-y-2">
                            <Callout tone="success" title="Trámite finalizado">
                                {finalDocs.length ? (isStudent ? 'Tu documento está listo para descargar.' : 'Documento entregado al estudiante.') : `Finalizado el ${fmtDate(sub.finished_at)}.`}
                                {sub.events.some((e) => e.action === 'CORREO_ENVIADO') && (
                                    <span className="mt-1 block">{isStudent ? 'También te lo enviamos a tu correo' : 'También se envió al correo'} <b>{sub.student?.email}</b>.</span>
                                )}
                            </Callout>
                            {finalDocs.map((d) => <DocumentRow key={d.id} d={d} highlight />)}
                        </div>
                    )}

                    <div className="mt-5">
                        {mode && <ActionForm mode={mode} sub={sub} onDone={done} onCancel={() => setMode(null)} />}
                        <Tabs fill value={tab} onChange={setTab} tabs={[
                            { value: 'detalle', label: 'Detalle', icon: User },
                            { value: 'documentos', label: 'Documentos', icon: FileText, count: sub.documents.length },
                            { value: 'historial', label: 'Historial', icon: History, count: sub.events.length },
                        ]} />

                        {tab === 'detalle' && (
                            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                {!isStudent && (
                                    <div><dt className="text-xs font-semibold text-muted">Estudiante</dt><dd className="font-semibold text-ink">{sub.student.name}</dd><dd className="text-sm text-muted">{sub.student.student_code}</dd></div>
                                )}
                                <div><dt className="text-xs font-semibold text-muted">Programa</dt><dd className="font-semibold text-ink">{sub.program?.name}</dd></div>
                                <div><dt className="text-xs font-semibold text-muted">Costo</dt><dd className="font-semibold text-ink">{sub.cost > 0 ? fmtMoney(sub.cost) : 'Gratuito'}</dd></div>
                                <div className="sm:col-span-2"><dt className="text-xs font-semibold text-muted">Justificación</dt><dd className="mt-0.5 text-sm leading-relaxed text-ink">{sub.description}</dd></div>
                            </dl>
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
