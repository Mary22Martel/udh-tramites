import { useEffect, useState } from 'react';
import { Building2, GraduationCap, Landmark, Pencil, Plus, Store, Trash2, Users } from 'lucide-react';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Badge, Button, Callout, Card, Field, IconButton, Input, Modal, PageHeader, Select, Spinner, Tabs, Textarea, Toggle } from '../../components/ui';

function FormModal({ open, title, fields, initial, onClose, onSubmit }) {
    const [form, setForm] = useState(initial || {});
    const [busy, setBusy] = useState(false);
    useEffect(() => setForm(initial || {}), [initial, open]);
    const submit = async () => { setBusy(true); try { await onSubmit(form); } finally { setBusy(false); } };
    return (
        <Modal open={open} onClose={onClose} title={title} size="sm" footer={<><Button variant="ghost" onClick={onClose}>Cancelar</Button><Button loading={busy} onClick={submit}>Guardar</Button></>}>
            <div className="space-y-4">
                {fields.map((f) => (
                    <Field key={f.key} label={f.label} hint={f.hint}>
                        {f.options ? (
                            <Select value={form[f.key] ?? ''} onChange={(e) => setForm({ ...form, [f.key]: e.target.value })}>
                                <option value="">{f.placeholder || 'Selecciona…'}</option>
                                {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </Select>
                        ) : f.textarea ? (
                            <Textarea value={form[f.key] ?? ''} onChange={(e) => setForm({ ...form, [f.key]: e.target.value })} />
                        ) : (
                            <Input value={form[f.key] ?? ''} disabled={f.disabled} onChange={(e) => setForm({ ...form, [f.key]: f.upper ? e.target.value.toUpperCase() : e.target.value })} />
                        )}
                    </Field>
                ))}
            </div>
        </Modal>
    );
}

function Faculties() {
    const { loadMeta, meta } = useAuth();
    const toast = useToast();
    const [data, setData] = useState(null);
    const [programs, setPrograms] = useState([]);
    const [edit, setEdit] = useState(null); // {type, item}
    const load = () => Promise.all([api.get('/faculties'), api.get('/programs')]).then(([f, p]) => { setData(f.data.data); setPrograms(p.data.data); });
    useEffect(() => { load(); }, []);
    if (!data) return <Spinner />;

    const save = async (form) => {
        try {
            const base = edit.type === 'faculty' ? '/faculties' : '/programs';
            edit.item?.id ? await api.put(`${base}/${edit.item.id}`, form) : await api.post(base, form);
            toast.success('Guardado.');
            setEdit(null); load(); loadMeta();
        } catch (e) { toast.error(errorMessage(e)); }
    };
    const remove = async (type, item) => {
        if (!window.confirm(`¿Eliminar ${item.name}?`)) return;
        try { await api.delete(`/${type === 'faculty' ? 'faculties' : 'programs'}/${item.id}`); toast.success('Eliminado.'); load(); loadMeta(); } catch (e) { toast.error(errorMessage(e)); }
    };
    const progStats = Object.fromEntries(programs.map((p) => [p.id, p]));

    return (
        <>
            <div className="mb-4 flex justify-end gap-2">
                <Button variant="secondary" icon={Plus} onClick={() => setEdit({ type: 'program', item: {} })}>Nueva carrera</Button>
                <Button icon={Plus} onClick={() => setEdit({ type: 'faculty', item: {} })}>Nueva facultad</Button>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {data.map((f) => (
                    <Card key={f.id} className="p-5">
                        <div className="flex items-start gap-3">
                            <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-gold-udh/15 text-amber-700 dark:text-gold-udh"><Landmark className="size-5" /></div>
                            <div className="min-w-0 flex-1">
                                <p className="font-extrabold text-ink">{f.name}</p>
                                <p className="text-sm text-muted">{f.code} · {f.dean_name || 'Sin decano registrado'}</p>
                            </div>
                            <IconButton icon={Pencil} label="Editar" onClick={() => setEdit({ type: 'faculty', item: f })} />
                            <IconButton icon={Trash2} tone="danger" label="Eliminar" onClick={() => remove('faculty', f)} />
                        </div>
                        <div className="mt-4 space-y-1.5">
                            {f.programs.length === 0 && <p className="text-sm text-muted">Sin carreras.</p>}
                            {f.programs.map((p) => (
                                <div key={p.id} className="flex items-center gap-3 rounded-xl bg-surface-2 px-3 py-2">
                                    <GraduationCap className="size-4 shrink-0 text-accent" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-ink">{p.name}</p>
                                        <p className="truncate text-xs text-muted">{p.code} · {meta?.levels?.[progStats[p.id]?.level] || ""} · {p.director_name || 'Sin director'} · {progStats[p.id]?.students_count ?? 0} estudiantes · {progStats[p.id]?.submissions_count ?? 0} solicitudes</p>
                                    </div>
                                    <IconButton icon={Pencil} label="Editar" onClick={() => setEdit({ type: 'program', item: { ...progStats[p.id], faculty_id: f.id } })} />
                                    <IconButton icon={Trash2} tone="danger" label="Eliminar" onClick={() => remove('program', p)} />
                                </div>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>
            <FormModal
                open={!!edit}
                title={edit?.type === 'faculty' ? (edit?.item?.id ? 'Editar facultad' : 'Nueva facultad') : (edit?.item?.id ? 'Editar carrera' : 'Nueva carrera')}
                initial={edit?.item}
                onClose={() => setEdit(null)}
                onSubmit={save}
                fields={edit?.type === 'faculty'
                    ? [{ key: 'code', label: 'Código', upper: true }, { key: 'name', label: 'Nombre' }, { key: 'dean_name', label: 'Decano(a)' }, { key: 'dean_email', label: 'Correo del decanato' }]
                    : [{ key: 'faculty_id', label: 'Facultad', options: data.map((f) => ({ value: f.id, label: f.name })) }, { key: 'code', label: 'Código', upper: true }, { key: 'name', label: 'Nombre de la carrera' }, { key: 'director_name', label: 'Director(a)' }, { key: 'director_email', label: 'Correo del director(a)' }]}
            />
        </>
    );
}

function ChannelsModal({ area, staff, onClose, onSaved }) {
    const { meta } = useAuth();
    const toast = useToast();
    const [has, setHas] = useState(false);
    const [channels, setChannels] = useState([]);
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        if (!area) return;
        setHas(area.has_channels);
        setChannels(area.channels.map((c) => ({ id: c.id, name: c.name, responsible_id: c.responsible_id || '', program_ids: c.programs.map((p) => p.id) })));
    }, [area]);
    if (!area) return null;
    const used = channels.flatMap((c) => c.program_ids);
    const unassigned = (meta?.programs || []).filter((p) => !used.includes(p.id));
    const setC = (i, k, v) => setChannels(channels.map((c, j) => (j === i ? { ...c, [k]: v } : c)));

    const save = async () => {
        setBusy(true);
        try {
            const { data } = await api.put(`/areas/${area.id}/channels`, { has_channels: has, channels: channels.map((c) => ({ ...c, responsible_id: c.responsible_id || null })) });
            toast.success('Ventanillas actualizadas. Las solicitudes en curso se reasignaron.');
            onSaved(data.data);
        } catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
    };

    return (
        <Modal open onClose={onClose} size="lg" title={`Ventanillas de ${area.name}`} subtitle="Reparte el trabajo del área por carreras: cada operador ve solo las solicitudes de sus carreras."
            footer={<><Button variant="ghost" onClick={onClose}>Cancelar</Button><Button loading={busy} onClick={save}>Guardar ventanillas</Button></>}>
            <Toggle checked={has} onChange={setHas} label="Atender por ventanillas" />
            {has && (
                <div className="mt-5 space-y-3">
                    {unassigned.length > 0 && <Callout tone="warning">Carreras sin ventanilla (las verá solo la jefatura): {unassigned.map((p) => p.code).join(', ')}</Callout>}
                    {channels.map((c, i) => (
                        <div key={i} className="grid gap-3 rounded-xl border border-line bg-surface-2/50 p-4 md:grid-cols-2">
                            <Field label="Nombre de la ventanilla"><Input value={c.name} onChange={(e) => setC(i, 'name', e.target.value)} /></Field>
                            <Field label="Operador responsable">
                                <Select value={c.responsible_id} onChange={(e) => setC(i, 'responsible_id', e.target.value)}>
                                    <option value="">Sin asignar</option>
                                    {staff.map((u) => <option key={u.id} value={u.id}>{u.name} · {u.email}</option>)}
                                </Select>
                            </Field>
                            <div className="md:col-span-2">
                                <p className="mb-2 text-[13px] font-semibold text-ink">Carreras que atiende</p>
                                <div className="flex flex-wrap gap-2">
                                    {meta.programs.map((p) => {
                                        const on = c.program_ids.includes(p.id);
                                        const taken = !on && used.includes(p.id);
                                        return (
                                            <button key={p.id} type="button" disabled={taken} onClick={() => setC(i, 'program_ids', on ? c.program_ids.filter((x) => x !== p.id) : [...c.program_ids, p.id])}
                                                className={`rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 transition ${on ? 'bg-teal-udh text-white ring-teal-udh' : taken ? 'text-muted/50 ring-line' : 'text-ink ring-line hover:ring-teal-udh'}`}>
                                                {p.code}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                            <div className="text-right md:col-span-2"><Button variant="ghost" size="sm" icon={Trash2} className="text-red-600" onClick={() => setChannels(channels.filter((_, j) => j !== i))}>Quitar ventanilla</Button></div>
                        </div>
                    ))}
                    <Button variant="secondary" icon={Plus} onClick={() => setChannels([...channels, { name: `Ventanilla ${channels.length + 1}`, responsible_id: '', program_ids: [] }])}>Agregar ventanilla</Button>
                </div>
            )}
        </Modal>
    );
}

function Areas() {
    const { loadMeta } = useAuth();
    const toast = useToast();
    const [data, setData] = useState(null);
    const [staff, setStaff] = useState([]);
    const [edit, setEdit] = useState(null);
    const [channels, setChannels] = useState(null);
    const load = () => Promise.all([api.get('/areas'), api.get('/staff')]).then(([a, s]) => { setData(a.data.data); setStaff(s.data.data.filter((u) => u.role !== 'ADMINISTRADOR')); });
    useEffect(() => { load(); }, []);
    if (!data) return <Spinner />;

    const save = async (form) => {
        try {
            edit.id ? await api.put(`/areas/${edit.id}`, form) : await api.post('/areas', form);
            toast.success('Área guardada.'); setEdit(null); load(); loadMeta();
        } catch (e) { toast.error(errorMessage(e)); }
    };
    const remove = async (a) => {
        if (!window.confirm(`¿Eliminar ${a.name}?`)) return;
        try { await api.delete(`/areas/${a.id}`); toast.success('Área eliminada.'); load(); loadMeta(); } catch (e) { toast.error(errorMessage(e)); }
    };

    return (
        <>
            <Callout className="mb-4">Las áreas centrales atienden pasos del flujo de los trámites. <b>Tesorería</b> y <b>Matrícula</b> son áreas del sistema. Puedes crear otras (por ejemplo, Biblioteca) y usarlas en el constructor de trámites.</Callout>
            <div className="mb-4 flex justify-end"><Button icon={Plus} onClick={() => setEdit({})}>Nueva área</Button></div>
            <div className="grid gap-4 lg:grid-cols-2">
                {data.map((a) => (
                    <Card key={a.id} className="p-5">
                        <div className="flex items-start gap-3">
                            <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300"><Building2 className="size-5" /></div>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2"><p className="font-extrabold text-ink">{a.name}</p>{a.is_system && <Badge tone="gold">Sistema</Badge>}</div>
                                <p className="text-sm text-muted">{a.code} · Responsable: {a.responsible?.name || 'sin asignar'} · {a.members_count} miembro(s)</p>
                                {a.description && <p className="mt-1 text-sm text-muted">{a.description}</p>}
                            </div>
                            <IconButton icon={Pencil} label="Editar" onClick={() => setEdit({ ...a, responsible_id: a.responsible_id || '' })} />
                            {!a.is_system && <IconButton icon={Trash2} tone="danger" label="Eliminar" onClick={() => remove(a)} />}
                        </div>
                        <div className="mt-4 rounded-xl bg-surface-2 p-3">
                            <div className="mb-2 flex items-center justify-between">
                                <p className="flex items-center gap-2 text-sm font-bold text-ink"><Store className="size-4 text-accent" /> {a.has_channels ? `${a.channels.length} ventanillas` : 'Atención sin ventanillas'}</p>
                                <Button size="sm" variant="secondary" onClick={() => setChannels(a)}>Gestionar</Button>
                            </div>
                            {a.has_channels && a.channels.map((c) => (
                                <p key={c.id} className="truncate text-xs text-muted"><b className="text-ink">{c.name.split(':')[0]}</b> · {c.responsible?.name || 'sin operador'} · {c.programs.map((p) => p.code).join(', ')}</p>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>
            <FormModal open={!!edit} title={edit?.id ? 'Editar área' : 'Nueva área'} initial={edit} onClose={() => setEdit(null)} onSubmit={save}
                fields={[
                    { key: 'code', label: 'Código', upper: true, hint: 'Ej.: BIBLIOTECA. Se usa en el flujo de los trámites.', disabled: edit?.is_system },
                    { key: 'name', label: 'Nombre' },
                    { key: 'description', label: 'Descripción', textarea: true },
                    { key: 'responsible_id', label: 'Jefe / responsable', options: staff.map((u) => ({ value: u.id, label: `${u.name} (${u.role_label})` })), placeholder: 'Sin asignar' },
                ]} />
            {channels && <ChannelsModal area={channels} staff={staff} onClose={() => setChannels(null)} onSaved={(d) => { setData(d); setChannels(null); }} />}
        </>
    );
}

export default function Organization() {
    const [tab, setTab] = useState('facultades');
    return (
        <div>
            <PageHeader kicker="Estructura" title="Organización universitaria" description="Facultades, carreras, áreas administrativas y ventanillas de atención." />
            <Tabs value={tab} onChange={setTab} tabs={[{ value: 'facultades', label: 'Facultades y carreras', icon: Landmark }, { value: 'areas', label: 'Áreas y ventanillas', icon: Users }]} />
            {tab === 'facultades' ? <Faculties /> : <Areas />}
        </div>
    );
}
