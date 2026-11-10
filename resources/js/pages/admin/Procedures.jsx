import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { GitBranch, ListPlus, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Badge, Button, Callout, Card, EmptyState, Field, IconButton, Input, Modal, PageHeader, SearchBar, Select, Spinner, Tabs, Textarea } from '../../components/ui';
import { cx, fmtMoney } from '../../lib/format';

function BulkModal({ open, onClose, onDone }) {
    const { meta } = useAuth();
    const toast = useToast();
    const [form, setForm] = useState({ names: '', program_id: '', category: '', cost: 5, days: 3 });
    const [result, setResult] = useState(null);
    const [busy, setBusy] = useState(false);
    const submit = async () => {
        setBusy(true);
        try {
            const { data } = await api.post('/procedures-bulk', form);
            setResult(data);
            toast.success(`${data.created.length} trámites creados.`);
            onDone();
        } catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
    };
    return (
        <Modal open={open} onClose={() => { setResult(null); onClose(); }} title="Registro masivo de trámites" subtitle="Pega una lista de nombres (uno por línea). Se crean con un flujo estándar que luego puedes editar."
            footer={<><Button variant="ghost" onClick={onClose}>Cerrar</Button><Button icon={ListPlus} loading={busy} onClick={submit} disabled={!form.names.trim()}>Crear trámites</Button></>}>
            <div className="space-y-4">
                <Field label="Nombres de los trámites"><Textarea rows={8} value={form.names} onChange={(e) => setForm({ ...form, names: e.target.value })} placeholder={'CONSTANCIA DE PRÁCTICAS\nCERTIFICADO DE IDIOMAS\n…'} /></Field>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="¿Para qué carrera?" hint="Vacío = trámite general para todas.">
                        <Select value={form.program_id} onChange={(e) => setForm({ ...form, program_id: e.target.value })}>
                            <option value="">Todas las carreras (general)</option>
                            {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </Select>
                    </Field>
                    <Field label="Categoría" hint="Vacío = se detecta por el nombre.">
                        <Select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
                            <option value="">Automática</option>
                            {meta?.categories.map((c) => <option key={c}>{c}</option>)}
                        </Select>
                    </Field>
                    <Field label="Costo (S/)"><Input type="number" min="0" step="0.5" value={form.cost} onChange={(e) => setForm({ ...form, cost: e.target.value })} /></Field>
                    <Field label="Días estimados"><Input type="number" min="1" value={form.days} onChange={(e) => setForm({ ...form, days: e.target.value })} /></Field>
                </div>
                {result && <Callout tone="success">Creados: {result.created.length}. Omitidos por duplicado: {result.skipped.length}{result.skipped.length ? ` (${result.skipped.join(', ')})` : ''}.</Callout>}
            </div>
        </Modal>
    );
}

function BifurcateModal({ proc, onClose, onDone }) {
    const { meta } = useAuth();
    const toast = useToast();
    const [programId, setProgramId] = useState('');
    const submit = async () => {
        try {
            const { data } = await api.post(`/procedures/${proc.id}/bifurcate`, { program_id: programId });
            toast.success('Versión propia de la carrera creada. Ahora puedes personalizar su flujo.');
            onDone(data.data);
        } catch (e) { toast.error(errorMessage(e)); }
    };
    return (
        <Modal open={!!proc} onClose={onClose} size="sm" title="Crear versión para una carrera" subtitle={proc?.name}
            footer={<><Button variant="ghost" onClick={onClose}>Cancelar</Button><Button icon={GitBranch} disabled={!programId} onClick={submit}>Crear versión</Button></>}>
            <p className="mb-4 text-sm text-muted">Se copia el trámite con sus requisitos y pasos. Los estudiantes de la carrera elegida verán esta versión en lugar de la general.</p>
            <Field label="Carrera">
                <Select value={programId} onChange={(e) => setProgramId(e.target.value)}>
                    <option value="">Selecciona…</option>
                    {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </Select>
            </Field>
        </Modal>
    );
}

export default function Procedures() {
    const { meta } = useAuth();
    const toast = useToast();
    const navigate = useNavigate();
    const [items, setItems] = useState(null);
    const [search, setSearch] = useState('');
    const [scope, setScope] = useState('ALL');
    const [category, setCategory] = useState('');
    const [programId, setProgramId] = useState('');
    const [bulk, setBulk] = useState(false);
    const [bifurcate, setBifurcate] = useState(null);

    const load = () => api.get('/procedures', { params: { scope, program_id: programId || undefined } }).then(({ data }) => setItems(data.data));
    useEffect(() => { load(); }, [scope, programId]); // eslint-disable-line

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return (items || []).filter((p) => (!category || p.category === category) && (!q || `${p.name} ${p.code}`.toLowerCase().includes(q)));
    }, [items, search, category]);

    const toggle = async (p) => {
        await api.post(`/procedures/${p.id}/toggle`);
        toast.success(p.is_active ? 'Trámite desactivado: ya no aparece en el catálogo.' : 'Trámite activado.');
        load();
    };
    const remove = async (p) => {
        if (!window.confirm(`¿Eliminar "${p.name}"? Esta acción no se puede deshacer.`)) return;
        try { await api.delete(`/procedures/${p.id}`); toast.success('Trámite eliminado.'); load(); } catch (e) { toast.error(errorMessage(e)); }
    };

    return (
        <div>
            <PageHeader kicker="Gestión de contenido" title="Trámites (TUPA)" description="Define los trámites, sus requisitos, costos y el flujo de áreas que los atienden. Puedes crear versiones específicas por carrera."
                actions={<><Button variant="secondary" icon={ListPlus} onClick={() => setBulk(true)}>Registro masivo</Button><Button icon={Plus} onClick={() => navigate('/admin/tramites/nuevo')}>Nuevo trámite</Button></>} />
            <Tabs value={category} onChange={setCategory} tabs={[{ value: '', label: 'Todas' }, ...(meta?.categories || []).map((c) => ({ value: c, label: c, count: (items || []).filter((p) => p.category === c).length }))]} />
            <SearchBar value={search} onChange={setSearch} placeholder="Buscar por nombre o código…" count={filtered.length} countLabel="trámites">
                <Select value={scope} onChange={(e) => setScope(e.target.value)} className="md:w-52">
                    <option value="ALL">Cualquier alcance</option>
                    <option value="TODOS">Generales (todas)</option>
                    <option value="PROGRAMA">Por carrera</option>
                    <option value="FACULTAD">Por facultad</option>
                </Select>
                <Select value={programId} onChange={(e) => setProgramId(e.target.value)} className="md:w-60">
                    <option value="">Cualquier carrera</option>
                    {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </Select>
            </SearchBar>
            {!items ? <Spinner /> : filtered.length === 0 ? <Card><EmptyState title="No hay trámites" /></Card> : (
                <Card className="divide-y divide-line">
                    {filtered.map((p) => (
                        <div key={p.id} className={cx('flex items-center gap-4 px-5 py-4', !p.is_active && 'opacity-55')}>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <button onClick={() => navigate(`/admin/tramites/${p.id}`)} className="text-left font-extrabold tracking-tight text-ink hover:text-accent">{p.name}</button>
                                    <Badge tone={p.is_active ? 'teal' : 'gray'}>{p.is_active ? 'Activo' : 'Inactivo'}</Badge>
                                    {p.target_scope === 'PROGRAMA' && p.programs.slice(0, 4).map((pr) => <Badge key={pr.id} tone="navy">{pr.code}</Badge>)}
                                    {p.target_scope === 'PROGRAMA' && p.programs.length > 4 && <Badge tone="gray" title={p.programs.map((x) => x.code).join(', ')}>+{p.programs.length - 4} carreras</Badge>}
                                    {p.target_scope === 'FACULTAD' && p.faculties.map((f) => <Badge key={f.id} tone="gold">{f.code}</Badge>)}
                                </div>
                                <p className="mt-1 truncate text-xs tracking-wide text-muted uppercase">
                                    {p.code} · {p.category} · {p.cost > 0 ? fmtMoney(p.cost) : 'Gratuito'} · {p.estimated_days} días · {p.steps.length} pasos · {p.requirements.length} requisitos · v{p.version} · {p.submissions_count} solicitudes
                                </p>
                                <p className="mt-1.5 hidden truncate text-xs text-muted md:block">{p.steps.map((s) => s.area_label).join('  →  ')}</p>
                            </div>
                            <div className="flex shrink-0 gap-0.5">
                                {p.target_scope === 'TODOS' && <IconButton icon={GitBranch} label="Crear versión para una carrera" onClick={() => setBifurcate(p)} />}
                                <IconButton icon={Power} label={p.is_active ? 'Desactivar' : 'Activar'} onClick={() => toggle(p)} />
                                <IconButton icon={Pencil} label="Editar" onClick={() => navigate(`/admin/tramites/${p.id}`)} />
                                <IconButton icon={Trash2} label="Eliminar" tone="danger" onClick={() => remove(p)} />
                            </div>
                        </div>
                    ))}
                </Card>
            )}
            <BulkModal open={bulk} onClose={() => setBulk(false)} onDone={load} />
            <BifurcateModal proc={bifurcate} onClose={() => setBifurcate(null)} onDone={(p) => { setBifurcate(null); navigate(`/admin/tramites/${p.id}`); }} />
        </div>
    );
}
