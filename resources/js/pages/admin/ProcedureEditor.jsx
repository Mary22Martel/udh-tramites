import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowDown, ArrowLeft, ArrowUp, Paperclip, Plus, Save, Trash2, Workflow } from 'lucide-react';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Badge, Button, Callout, Card, Field, IconButton, Input, PageHeader, Select, Spinner, Textarea, Toggle } from '../../components/ui';

const EMPTY = {
    name: '', code: '', description: '', category: 'Constancias y Certificados', cost: 5, requires_payment: true,
    payment_concept: '', target_scope: 'TODOS', program_ids: [], faculty_ids: [], is_active: true,
    requirements: [],
    steps: [
        { name: 'Solicitud del estudiante', description: 'El estudiante registra su solicitud y su justificación.', area: 'ESTUDIANTE', action_required: 'SOLICITUD', document_requirement: null, can_observe: false, can_return: false, days: 0 },
        { name: 'Revisión del programa', description: 'El programa académico revisa y aprueba.', area: 'PROGRAMA_ACADEMICO', action_required: 'APROBAR', document_requirement: null, can_observe: true, can_return: true, days: 3 },
        { name: 'Matrícula · entrega del documento', description: 'Matrícula aprueba y sube el documento final para el estudiante.', area: 'MATRICULA', action_required: 'APROBAR', document_requirement: null, can_observe: true, can_return: true, days: 3 },
    ],
};
const FILE_TYPES = ['.pdf', '.jpg', '.png', '.docx'];

export default function ProcedureEditor() {
    const { id } = useParams();
    const { meta } = useAuth();
    const toast = useToast();
    const navigate = useNavigate();
    const [form, setForm] = useState(id ? null : EMPTY);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!id) return;
        api.get(`/procedures/${id}`).then(({ data }) => {
            const p = data.data;
            setForm({ ...p, payment_concept: p.payment_concept || '', program_ids: p.programs.map((x) => x.id), faculty_ids: p.faculties.map((x) => x.id) });
        });
    }, [id]);

    if (!form || !meta) return <Spinner />;
    const set = (k, v) => setForm({ ...form, [k]: v });
    const setItem = (list, i, k, v) => set(list, form[list].map((x, j) => (j === i ? { ...x, [k]: v } : x)));
    const move = (list, i, d) => {
        const arr = [...form[list]];
        [arr[i], arr[i + d]] = [arr[i + d], arr[i]];
        set(list, arr);
    };

    const save = async () => {
        setBusy(true);
        try {
            const steps = form.steps.map((st, i) => ({
                ...st,
                document_requirement: null,
                action_required: i === 0 ? 'SOLICITUD' : st.area === 'TESORERIA' ? 'VERIFICAR_PAGO' : 'APROBAR',
                days: i === 0 ? null : Number(st.days) || 3, // el paso del estudiante no tiene plazo
            }));
            const payload = { ...form, steps, cost: Number(form.cost), code: form.code || null };
            const { data } = id ? await api.put(`/procedures/${id}`, payload) : await api.post('/procedures', payload);
            toast.success(id ? `Trámite actualizado (versión ${data.data.version}).` : 'Trámite creado.');
            navigate('/admin/tramites');
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="mx-auto max-w-5xl">
            <button onClick={() => navigate('/admin/tramites')} className="mb-4 flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft className="size-4" /> Volver a trámites</button>
            <PageHeader kicker={id ? `Editar · ${form.code}` : 'Nuevo trámite'} title={form.name || 'Constructor de trámite'} description="Configura los datos generales, los requisitos que adjunta el estudiante y el flujo de pasos por área."
                actions={<Button icon={Save} loading={busy} onClick={save}>Guardar trámite</Button>} />

            <Card className="mb-5 p-6">
                <p className="kicker mb-4">Datos generales</p>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                    <Field label="Nombre del trámite" className="md:col-span-4"><Input value={form.name} onChange={(e) => set('name', e.target.value)} /></Field>
                    <Field label="Código" hint="Vacío = automático" className="md:col-span-2"><Input value={form.code || ''} onChange={(e) => set('code', e.target.value.toUpperCase())} /></Field>
                    <Field label="Descripción" className="md:col-span-6"><Textarea rows={2} value={form.description || ''} onChange={(e) => set('description', e.target.value)} /></Field>
                    <Field label="Categoría" className="md:col-span-2">
                        <Select value={form.category} onChange={(e) => set('category', e.target.value)}>{meta.categories.map((c) => <option key={c}>{c}</option>)}</Select>
                    </Field>
                    <Field label="Costo (S/)" className="md:col-span-1"><Input type="number" min="0" step="0.5" value={form.cost} onChange={(e) => setForm({ ...form, cost: e.target.value, requires_payment: Number(e.target.value) > 0 })} /></Field>
                    <Field label="Concepto de pago" className="md:col-span-3"><Input value={form.payment_concept} onChange={(e) => set('payment_concept', e.target.value)} disabled={!form.requires_payment} /></Field>
                    <Field label="¿Quiénes pueden solicitarlo?" className="md:col-span-2">
                        <Select value={form.target_scope} onChange={(e) => set('target_scope', e.target.value)}>
                            <option value="TODOS">Todas las carreras</option>
                            <option value="FACULTAD">Carreras de una facultad</option>
                            <option value="PROGRAMA">Una carrera específica</option>
                        </Select>
                    </Field>
                    {form.target_scope === 'PROGRAMA' && (
                        <Field label="Carrera(s)" hint="Ctrl + clic para elegir varias" className="md:col-span-4">
                            <Select multiple className="h-28 py-2" value={form.program_ids.map(String)} onChange={(e) => set('program_ids', [...e.target.selectedOptions].map((o) => Number(o.value)))}>
                                {meta.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </Select>
                        </Field>
                    )}
                    {form.target_scope === 'FACULTAD' && (
                        <Field label="Facultad(es)" hint="Ctrl + clic para elegir varias" className="md:col-span-4">
                            <Select multiple className="h-28 py-2" value={form.faculty_ids.map(String)} onChange={(e) => set('faculty_ids', [...e.target.selectedOptions].map((o) => Number(o.value)))}>
                                {meta.faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                            </Select>
                        </Field>
                    )}
                    <div className="flex items-end md:col-span-2"><Toggle checked={form.is_active} onChange={(v) => set('is_active', v)} label="Activo en el catálogo" /></div>
                </div>
            </Card>

            <Card className="mb-5 p-6">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <p className="kicker flex items-center gap-2"><Paperclip className="size-4" /> Documentos que adjunta el estudiante ({form.requirements.length})</p>
                        <p className="mt-1 text-sm text-muted">La justificación escrita siempre se pide. Al estudiante solo se le piden los documentos marcados como <b>Obligatorio</b>; si ninguno lo está, pasa directo a la justificación.</p>
                    </div>
                    <Button size="sm" variant="secondary" icon={Plus} onClick={() => set('requirements', [...form.requirements, { name: '', description: '', is_required: true, file_types: ['.pdf'], max_size_mb: 5 }])}>Agregar requisito</Button>
                </div>
                <div className="space-y-3">
                    {form.requirements.map((r, i) => (
                        <div key={i} className="grid grid-cols-1 gap-3 rounded-xl border border-line bg-surface-2/50 p-4 md:grid-cols-12">
                            <Field label="Requisito" className="md:col-span-4"><Input value={r.name} onChange={(e) => setItem('requirements', i, 'name', e.target.value)} /></Field>
                            <Field label="Indicaciones" className="md:col-span-4"><Input value={r.description || ''} onChange={(e) => setItem('requirements', i, 'description', e.target.value)} /></Field>
                            <Field label="MB máx." className="md:col-span-1"><Input type="number" min="1" value={r.max_size_mb} onChange={(e) => setItem('requirements', i, 'max_size_mb', Number(e.target.value))} /></Field>
                            <div className="flex items-end justify-end md:col-span-3"><IconButton icon={Trash2} tone="danger" label="Quitar" onClick={() => set('requirements', form.requirements.filter((_, j) => j !== i))} /></div>
                            <div className="flex flex-wrap items-center gap-4 md:col-span-12">
                                <Toggle checked={r.is_required} onChange={(v) => setItem('requirements', i, 'is_required', v)} label="Obligatorio" />
                                <span className="text-xs font-semibold text-muted">Formatos:</span>
                                {FILE_TYPES.map((t) => (
                                    <label key={t} className="flex items-center gap-1.5 text-sm text-ink">
                                        <input type="checkbox" className="accent-teal-600" checked={(r.file_types || []).includes(t)} onChange={(e) => setItem('requirements', i, 'file_types', e.target.checked ? [...(r.file_types || []), t, ...(t === '.jpg' ? ['.jpeg'] : [])] : (r.file_types || []).filter((x) => x !== t && !(t === '.jpg' && x === '.jpeg')))} />{t}
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </Card>

            <Card className="mb-5 p-6">
                <div className="mb-4 flex items-center justify-between">
                    <p className="kicker flex items-center gap-2"><Workflow className="size-4" /> Flujo de atención ({form.steps.length} pasos)</p>
                    <Button size="sm" variant="secondary" icon={Plus} onClick={() => set('steps', [...form.steps, { name: '', description: '', area: 'MATRICULA', action_required: 'APROBAR', document_requirement: null, can_observe: true, can_return: true, days: 3 }])}>Agregar paso</Button>
                </div>
                <Callout className="mb-4">El <b>paso 1</b> es la solicitud del estudiante. Cada área siguiente <b>revisa y aprueba</b> (puede adjuntar un archivo opcional que verá el estudiante). El <b>último paso</b> sube el documento final que recibe el estudiante. Cada área tiene su <b>plazo en días hábiles</b> (3 por defecto) que empieza a contar cuando la solicitud llega a ella. «Programa académico» y «Facultad» se asignan solos según la carrera del estudiante. Agrega <b>Tesorería</b> solo si este trámite exige que apruebe el pago.</Callout>
                <ol className="space-y-3">
                    {form.steps.map((s, i) => (
                        <li key={i} className="flex gap-3">
                            <div className="flex flex-col items-center">
                                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-navy-udh text-sm font-bold text-white dark:bg-teal-udh">{i + 1}</span>
                                {i < form.steps.length - 1 && <span className="w-0.5 flex-1 bg-line" />}
                            </div>
                            <div className="mb-1 grid grid-cols-1 flex-1 gap-3 rounded-xl border border-line bg-surface-2/50 p-4 md:grid-cols-12">
                                <Field label="Nombre del paso" className={i === 0 ? 'md:col-span-5' : 'md:col-span-4'}><Input value={s.name} onChange={(e) => setItem('steps', i, 'name', e.target.value)} /></Field>
                                <Field label="Área responsable" className={i === 0 ? 'md:col-span-4' : 'md:col-span-3'}>
                                    <Select value={s.area} disabled={i === 0} onChange={(e) => setItem('steps', i, 'area', e.target.value)}>
                                        {Object.entries(meta.step_areas).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                    </Select>
                                </Field>
                                {i > 0 && (
                                    <Field label="Plazo (días hábiles)" className="md:col-span-2">
                                        <Input type="number" min="1" max="60" value={s.days ?? 3} onChange={(e) => setItem('steps', i, 'days', e.target.value === '' ? '' : Math.min(60, Math.max(1, Number(e.target.value) || 1)))}
                                            onBlur={() => !s.days && setItem('steps', i, 'days', 3)} />
                                    </Field>
                                )}
                                <div className="flex items-end justify-end gap-0.5 md:col-span-3">
                                    <IconButton icon={ArrowUp} label="Subir" disabled={i <= 1} onClick={() => move('steps', i, -1)} />
                                    <IconButton icon={ArrowDown} label="Bajar" disabled={i === 0 || i === form.steps.length - 1} onClick={() => move('steps', i, 1)} />
                                    <IconButton icon={Trash2} tone="danger" label="Quitar" disabled={i === 0} onClick={() => set('steps', form.steps.filter((_, j) => j !== i))} />
                                </div>
                                <Field label="Descripción" className="md:col-span-12"><Input value={s.description || ''} onChange={(e) => setItem('steps', i, 'description', e.target.value)} /></Field>
                                {i > 0 && (
                                    <div className="flex flex-wrap gap-5 md:col-span-12">
                                        <Toggle checked={s.can_observe} onChange={(v) => setItem('steps', i, 'can_observe', v)} label="Puede observar" />
                                        {i > 1 && <Toggle checked={s.can_return ?? true} onChange={(v) => setItem('steps', i, 'can_return', v)} label="Puede derivar a un área anterior" />}
                                        {s.area === 'TESORERIA' && <Badge tone="gold">Tesorería aprueba el pago</Badge>}
                                        {i === form.steps.length - 1 && <Badge tone="navy">Último paso: sube el documento final</Badge>}
                                    </div>
                                )}
                            </div>
                        </li>
                    ))}
                </ol>
            </Card>
            <div className="flex justify-end"><Button size="lg" icon={Save} loading={busy} onClick={save}>Guardar trámite</Button></div>
        </div>
    );
}
