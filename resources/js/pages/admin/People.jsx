import { useEffect, useState } from 'react';
import { GraduationCap, Pencil, Plus, Power, UserCog } from 'lucide-react';
import api, { errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Badge, Button, Card, Field, IconButton, Input, Modal, PageHeader, Pagination, SearchBar, Select, Spinner, Tabs, Toggle } from '../../components/ui';
import { cx } from '../../lib/format';

function UserModal({ user, student, onClose, onSaved }) {
    const { meta } = useAuth();
    const toast = useToast();
    const [form, setForm] = useState({});
    const [busy, setBusy] = useState(false);
    useEffect(() => {
        if (!user) return;
        setForm({
            name: user.name || '', email: user.email || '', role: user.role || (student ? 'ESTUDIANTE' : 'MATRICULA'), job_title: user.job_title || '',
            dni: user.dni || '', phone: user.phone || '', area_id: user.area?.id || '', faculty_id: user.faculty?.id || '', program_id: user.program?.id || '',
            student_code: user.student_code || '', current_semester: user.current_semester || '', academic_state: user.academic_state || 'REGULAR',
            is_active: user.is_active ?? true, password: '',
        });
    }, [user, student]);
    if (!user) return null;
    const set = (k) => (e) => setForm({ ...form, [k]: e?.target ? e.target.value : e });
    const r = form.role;

    const save = async () => {
        setBusy(true);
        try {
            const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]));
            user.id ? await api.put(`/users/${user.id}`, payload) : await api.post('/users', payload);
            toast.success(user.id ? 'Usuario actualizado.' : 'Usuario creado. Contraseña inicial: udh2026');
            onSaved();
        } catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
    };

    return (
        <Modal open onClose={onClose} title={user.id ? 'Editar usuario' : student ? 'Nuevo estudiante' : 'Nuevo personal'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancelar</Button><Button loading={busy} onClick={save}>Guardar</Button></>}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Field label="Nombres y apellidos" className="sm:col-span-2"><Input value={form.name} onChange={set('name')} /></Field>
                <Field label="Correo institucional"><Input type="email" value={form.email} onChange={set('email')} /></Field>
                <Field label="Rol">
                    <Select value={r} onChange={set('role')} disabled={student}>
                        {Object.entries(meta?.roles || {}).filter(([k]) => (student ? k === 'ESTUDIANTE' : k !== 'ESTUDIANTE')).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </Select>
                </Field>
                {r === 'ESTUDIANTE' ? (
                    <>
                        <Field label="Código de estudiante"><Input value={form.student_code} onChange={set('student_code')} /></Field>
                        <Field label="Carrera"><Select value={form.program_id} onChange={set('program_id')}><option value="">Selecciona…</option>{meta.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</Select></Field>
                        <Field label="Ciclo"><Input value={form.current_semester} onChange={set('current_semester')} placeholder="VIII Ciclo" /></Field>
                        <Field label="Situación"><Select value={form.academic_state} onChange={set('academic_state')}><option>REGULAR</option><option>EGRESADO</option><option>GRADUADO</option></Select></Field>
                    </>
                ) : (
                    <>
                        <Field label="Cargo" className="sm:col-span-2"><Input value={form.job_title} onChange={set('job_title')} /></Field>
                        {['TESORERIA', 'MATRICULA', 'AREA_CENTRAL'].includes(r) && (
                            <Field label="Área"><Select value={form.area_id} onChange={set('area_id')}><option value="">Selecciona…</option>{meta.areas.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</Select></Field>
                        )}
                        {r === 'FACULTAD' && (
                            <Field label="Facultad"><Select value={form.faculty_id} onChange={set('faculty_id')}><option value="">Selecciona…</option>{meta.faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}</Select></Field>
                        )}
                        {r === 'PROGRAMA_ACADEMICO' && (
                            <Field label="Carrera que dirige"><Select value={form.program_id} onChange={set('program_id')}><option value="">Selecciona…</option>{meta.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</Select></Field>
                        )}
                    </>
                )}
                <Field label="DNI"><Input value={form.dni} onChange={set('dni')} /></Field>
                <Field label="Teléfono"><Input value={form.phone} onChange={set('phone')} /></Field>
                <Field label={user.id ? 'Nueva contraseña (opcional)' : 'Contraseña'} hint={user.id ? 'Déjalo vacío para no cambiarla.' : 'Vacío = udh2026'}><Input type="password" value={form.password} onChange={set('password')} /></Field>
                <div className="flex items-end"><Toggle checked={form.is_active} onChange={set('is_active')} label="Cuenta activa" /></div>
            </div>
        </Modal>
    );
}

export default function People() {
    const { meta } = useAuth();
    const toast = useToast();
    const [tab, setTab] = useState('personal');
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('');
    const [programId, setProgramId] = useState('');
    const [page, setPage] = useState(1);
    const [res, setRes] = useState(null);
    const [edit, setEdit] = useState(null);

    const load = () => (tab === 'personal'
        ? api.get('/staff', { params: { search, role } }).then(({ data }) => setRes({ data: data.data }))
        : api.get('/students', { params: { search, program_id: programId, page } }).then(({ data }) => setRes(data)));
    useEffect(() => { const t = setTimeout(load, 250); return () => clearTimeout(t); }, [tab, search, role, programId, page]); // eslint-disable-line

    const toggle = async (u) => { try { await api.post(`/users/${u.id}/toggle`); toast.success(u.is_active ? 'Cuenta desactivada.' : 'Cuenta activada.'); load(); } catch (e) { toast.error(errorMessage(e)); } };

    const scopeOf = (u) => u.area?.name || (u.role === 'FACULTAD' ? u.faculty?.name : u.role === 'PROGRAMA_ACADEMICO' ? u.program?.name : '') || '—';

    return (
        <div>
            <PageHeader kicker="Usuarios" title="Personal y estudiantes" description="Cuentas de acceso al sistema. También puedes cargarlas masivamente desde Excel en «Carga de datos»."
                actions={<Button icon={Plus} onClick={() => setEdit({})}>{tab === 'personal' ? 'Nuevo personal' : 'Nuevo estudiante'}</Button>} />
            <Tabs value={tab} onChange={(v) => { setTab(v); setRes(null); setSearch(''); setPage(1); }} tabs={[{ value: 'personal', label: 'Personal administrativo', icon: UserCog }, { value: 'estudiantes', label: 'Estudiantes', icon: GraduationCap }]} />
            <SearchBar value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder={tab === 'personal' ? 'Buscar por nombre o correo…' : 'Buscar por nombre o código…'} count={res ? (res.meta?.total ?? res.data.length) : undefined} countLabel="usuarios">
                {tab === 'personal' ? (
                    <Select value={role} onChange={(e) => setRole(e.target.value)} className="md:w-60">
                        <option value="">Todos los roles</option>
                        {Object.entries(meta?.roles || {}).filter(([k]) => k !== 'ESTUDIANTE').map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </Select>
                ) : (
                    <Select value={programId} onChange={(e) => { setProgramId(e.target.value); setPage(1); }} className="md:w-64">
                        <option value="">Todas las carreras</option>
                        {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </Select>
                )}
            </SearchBar>
            {!res ? <Spinner /> : (
                <>
                <Card className="divide-y divide-line md:hidden">
                    {res.data.map((u) => (
                        <div key={u.id} className={cx('flex items-center gap-3 px-4 py-3', !u.is_active && 'opacity-50')}>
                            <div className="grid size-10 shrink-0 place-items-center rounded-lg bg-teal-udh/12 text-xs font-bold text-teal-udh-600 dark:text-teal-300">{u.initials}</div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{u.name}</p>
                                <p className="truncate text-xs text-muted">{tab === 'personal' ? `${u.role_label} · ${scopeOf(u)}` : `${u.student_code} · ${u.program?.name ?? ''}`}</p>
                            </div>
                            <IconButton icon={Pencil} label="Editar" onClick={() => setEdit(u)} />
                        </div>
                    ))}
                </Card>
                <Card className="hidden overflow-x-auto md:block">
                    <table className="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr className="border-b border-line text-left text-xs tracking-wider text-muted uppercase">
                                <th className="px-5 py-3">Usuario</th>
                                <th className="px-3 py-3">{tab === 'personal' ? 'Rol' : 'Código'}</th>
                                <th className="px-3 py-3">{tab === 'personal' ? 'Área / ámbito' : 'Carrera'}</th>
                                {tab === 'estudiantes' && <th className="px-3 py-3 text-right">Solicitudes</th>}
                                <th className="px-3 py-3">Estado</th>
                                <th className="w-24" />
                            </tr>
                        </thead>
                        <tbody>
                            {res.data.map((u) => (
                                <tr key={u.id} className={cx('border-b border-line last:border-0', !u.is_active && 'opacity-50')}>
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="grid size-9 shrink-0 place-items-center rounded-lg bg-teal-udh/12 text-xs font-bold text-teal-udh-600 dark:text-teal-300">{u.initials}</div>
                                            <div className="min-w-0"><p className="truncate font-bold text-ink">{u.name}</p><p className="truncate text-xs text-muted">{u.email}{u.job_title ? ` · ${u.job_title}` : ''}</p></div>
                                        </div>
                                    </td>
                                    <td className="px-3">{tab === 'personal' ? <Badge tone="navy">{u.role_label}</Badge> : <span className="font-mono">{u.student_code}</span>}</td>
                                    <td className="px-3 text-muted">{tab === 'personal' ? scopeOf(u) : u.program?.name}</td>
                                    {tab === 'estudiantes' && <td className="px-3 text-right font-bold">{u.submissions_count}</td>}
                                    <td className="px-3"><Badge tone={u.is_active ? 'teal' : 'gray'}>{u.is_active ? 'Activo' : 'Inactivo'}</Badge></td>
                                    <td className="px-3"><div className="flex justify-end"><IconButton icon={Pencil} label="Editar" onClick={() => setEdit(u)} /><IconButton icon={Power} label="Activar/desactivar" onClick={() => toggle(u)} /></div></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
                </>
            )}
            {res?.meta && <Pagination meta={res.meta} onPage={setPage} />}
            <UserModal user={edit} student={tab === 'estudiantes'} onClose={() => setEdit(null)} onSaved={() => { setEdit(null); load(); }} />
        </div>
    );
}
