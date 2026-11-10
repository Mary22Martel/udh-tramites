import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, ArrowRight, Check, FileText, PenLine, Receipt, Send, ShieldCheck } from 'lucide-react';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Button, Callout, Card, Field, FileDrop, PageHeader, Spinner, Textarea } from '../../components/ui';
import { cx, fmtMoney } from '../../lib/format';

/**
 * Asistente para iniciar un trámite:
 *   1. Documentos    → solo si el administrador marcó algún documento como obligatorio.
 *   2. Justificación → siempre (descripción escrita).
 *   3. Confirmar.
 * Si el trámite tiene costo, al enviar se genera un ticket en "Mis pagos" y el trámite
 * empieza a atenderse cuando el estudiante lo paga.
 */
export default function NewSubmission() {
    const { id } = useParams();
    const { user, refresh } = useAuth();
    const toast = useToast();
    const navigate = useNavigate();
    const [proc, setProc] = useState(null);
    const [step, setStep] = useState(0);
    const [files, setFiles] = useState({});
    const [description, setDescription] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        api.get(`/procedures/${id}`).then(({ data }) => setProc(data.data)).catch(() => navigate('/catalogo'));
    }, [id]); // eslint-disable-line

    if (!proc) return <Spinner />;

    // Solo se piden los documentos que el administrador marcó como obligatorios
    const requiredDocs = proc.requirements.filter((r) => r.is_required);
    const needsPay = proc.requires_payment && proc.cost > 0;
    const steps = [
        ...(requiredDocs.length ? [{ key: 'req', label: 'Documentos', icon: FileText }] : []),
        { key: 'just', label: 'Justificación', icon: PenLine },
        { key: 'ok', label: 'Confirmar', icon: ShieldCheck },
    ];
    const current = steps[step].key;
    const missing = requiredDocs.filter((r) => !files[r.id]);
    const canNext = { req: missing.length === 0, just: description.trim().length >= 15, ok: true }[current];

    const submit = async () => {
        setBusy(true);
        try {
            const fd = new FormData();
            fd.append('procedure_id', proc.id);
            fd.append('description', description);
            requiredDocs.forEach((r) => files[r.id] && fd.append(`files[${r.id}]`, files[r.id]));
            const { data } = await api.post('/submissions', fd);
            refresh();
            if (data.data.status === 'PENDIENTE_PAGO' && data.data.ticket) {
                toast.success(`Solicitud ${data.data.code} registrada. Se generó tu ticket de pago ${data.data.ticket.code}.`);
                navigate('/mis-pagos');
            } else {
                toast.success(`Solicitud ${data.data.code} registrada correctamente.`);
                navigate(`/mis-tramites?ver=${data.data.id}`);
            }
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="mx-auto max-w-4xl">
            <button onClick={() => navigate('/catalogo')} className="mb-4 flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft className="size-4" /> Volver al catálogo</button>
            <PageHeader kicker={`Nueva solicitud · ${proc.code}`} title={proc.name} description={proc.description} />

            {/* Pasos del asistente */}
            <div className="mb-6 flex items-center gap-2">
                {steps.map((s, i) => (
                    <div key={s.key} className="flex flex-1 items-center gap-2">
                        <div className={cx('flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold transition',
                            i === step ? 'bg-navy-udh text-white dark:bg-teal-udh' : i < step ? 'text-teal-udh-600 dark:text-teal-300' : 'text-muted')}>
                            <span className={cx('grid size-6 place-items-center rounded-full text-xs', i < step ? 'bg-teal-udh text-white' : i === step ? 'bg-white/20' : 'bg-surface-2 ring-1 ring-line')}>
                                {i < step ? <Check className="size-3.5" /> : i + 1}
                            </span>
                            <span className={i === step ? 'inline' : 'hidden sm:inline'}>{s.label}</span>
                        </div>
                        {i < steps.length - 1 && <span className={cx('h-0.5 flex-1 rounded', i < step ? 'bg-teal-udh' : 'bg-line')} />}
                    </div>
                ))}
            </div>

            <Card className="p-6">
                {current === 'req' && (
                    <div className="space-y-5">
                        <p className="text-sm text-muted">Este trámite requiere los siguientes documentos. Todos son obligatorios.</p>
                        {requiredDocs.map((r) => (
                            <Field key={r.id} label={<>{r.name} <span className="text-red-500">*</span></>} hint={`${r.description || ''} Formatos: ${(r.file_types || []).join(', ')} · máx. ${r.max_size_mb} MB`}>
                                <FileDrop file={files[r.id]} onChange={(f) => setFiles({ ...files, [r.id]: f })} accept={(r.file_types || []).join(',')} />
                            </Field>
                        ))}
                    </div>
                )}

                {current === 'just' && (
                    <Field label={<>Descripción o justificación <span className="text-red-500">*</span></>} hint={`${description.trim().length} caracteres (mínimo 15). Explica por qué deseas realizar este trámite.`}>
                        <Textarea rows={6} value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Ej.: Solicito la constancia para presentarla en mi centro de prácticas pre profesionales…" />
                    </Field>
                )}

                {current === 'ok' && (
                    <div className="space-y-4">
                        <Callout tone="info" title="Revisa antes de enviar">Al enviar, tu solicitud pasará a la primera área responsable. Recibirás notificaciones en cada avance.</Callout>
                        {needsPay && (
                            <Callout tone="warning" title={`Este trámite cuesta ${fmtMoney(proc.cost)}`}>
                                <span className="flex items-start gap-2"><Receipt className="mt-0.5 size-4 shrink-0" /> Al enviar se generará tu <b>ticket de pago</b> en «Mis pagos». Págalo con tarjeta, Yape u otros medios y tu trámite empezará a atenderse.</span>
                            </Callout>
                        )}
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div><dt className="text-xs font-bold text-muted uppercase">Estudiante</dt><dd className="font-semibold text-ink">{user.name} · {user.student_code}</dd></div>
                            <div><dt className="text-xs font-bold text-muted uppercase">Carrera</dt><dd className="font-semibold text-ink">{user.program?.name}</dd></div>
                            <div><dt className="text-xs font-bold text-muted uppercase">Documentos adjuntos</dt><dd className="font-semibold text-ink">{requiredDocs.length ? `${Object.values(files).filter(Boolean).length} archivo(s)` : 'No requiere'}</dd></div>
                            <div><dt className="text-xs font-bold text-muted uppercase">Costo</dt><dd className="font-semibold text-ink">{needsPay ? fmtMoney(proc.cost) : 'Gratuito'}</dd></div>
                            <div className="sm:col-span-2"><dt className="text-xs font-bold text-muted uppercase">Justificación</dt><dd className="text-sm text-ink">{description}</dd></div>
                        </dl>
                        <div>
                            <p className="mb-2 text-xs font-bold text-muted uppercase">Recorrido de tu trámite</p>
                            <div className="flex flex-wrap gap-2">
                                {proc.steps.map((s) => (
                                    <span key={s.id} className="rounded-lg bg-surface-2 px-2.5 py-1 text-xs font-semibold text-ink ring-1 ring-line">
                                        {s.step_number}. {s.area_label}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                <div className="mt-8 flex items-center justify-between border-t border-line pt-5">
                    <Button variant="ghost" icon={ArrowLeft} disabled={step === 0} onClick={() => setStep(step - 1)}>Anterior</Button>
                    {current === 'req' && missing.length > 0 && <span className="hidden text-xs text-muted sm:block">Falta: {missing.map((m) => m.name).join(', ')}</span>}
                    {current !== 'ok' ? (
                        <Button disabled={!canNext} onClick={() => setStep(step + 1)}>Continuar <ArrowRight className="size-4" /></Button>
                    ) : (
                        <Button icon={Send} loading={busy} onClick={submit}>{needsPay ? 'Enviar y generar ticket' : 'Enviar solicitud'}</Button>
                    )}
                </div>
            </Card>
        </div>
    );
}
