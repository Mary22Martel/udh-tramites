import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BookOpen, Clock, FileCheck2, Layers, Paperclip, Sparkles, Wallet } from 'lucide-react';
import api from '../lib/api';
import { useAuth } from '../context/AuthContext';
import { Badge, Button, Card, Callout, EmptyState, Modal, PageHeader, SearchBar, Select, Spinner } from '../components/ui';
import { cx, fmtMoney } from '../lib/format';

const CAT_TONE = {
    'Constancias y Certificados': 'teal',
    'Matrícula y Régimen de Estudios': 'amber',
    'Evaluaciones y Notas': 'blue',
    'Prácticas Pre Profesionales': 'navy',
    'Investigación y Tesis': 'red',
    'Grados y Títulos': 'gold',
    'Servicios y Extensión': 'gray',
};

export function ProcedureInfo({ p, onClose, onApply }) {
    return (
        <Modal open={!!p} onClose={onClose} size="lg" title={p?.name} subtitle={p && `${p.code} · ${p.category}`}
            footer={onApply && <Button icon={Sparkles} onClick={() => onApply(p)}>Iniciar este trámite</Button>}>
            {p && (
                <div className="space-y-5">
                    <p className="text-[15px] leading-relaxed text-ink">{p.description}</p>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="rounded-xl border border-line p-3"><p className="text-xs text-muted">Costo</p><p className="text-lg font-extrabold text-ink">{p.cost > 0 ? fmtMoney(p.cost) : 'Gratuito'}</p></div>
                        <div className="rounded-xl border border-line p-3"><p className="text-xs text-muted">Plazo estimado</p><p className="text-lg font-extrabold text-ink">{p.estimated_days} días</p></div>
                        <div className="rounded-xl border border-line p-3"><p className="text-xs text-muted">Etapas</p><p className="text-lg font-extrabold text-ink">{p.steps.length}</p></div>
                    </div>
                    <div>
                        <p className="kicker mb-3">Requisitos</p>
                        <ul className="space-y-2">
                            {p.requirements.map((r) => (
                                <li key={r.id} className="flex gap-3 rounded-xl bg-surface-2 px-3.5 py-2.5">
                                    <Paperclip className="mt-0.5 size-4 shrink-0 text-accent" />
                                    <div className="text-sm">
                                        <p className="font-semibold text-ink">{r.name} {r.is_required ? <span className="text-red-500">*</span> : <span className="text-xs text-muted">(opcional)</span>}</p>
                                        <p className="text-muted">{r.description} · {(r.file_types || []).join(', ')} · máx. {r.max_size_mb} MB</p>
                                    </div>
                                </li>
                            ))}
                            {p.cost > 0 && (
                                <li className="flex gap-3 rounded-xl bg-surface-2 px-3.5 py-2.5 text-sm">
                                    <Wallet className="mt-0.5 size-4 shrink-0 text-accent" />
                                    <p><b className="text-ink">Pago de {fmtMoney(p.cost)}</b> <span className="text-muted">· {p.payment_concept || 'Derecho de trámite'}</span></p>
                                </li>
                            )}
                        </ul>
                    </div>
                    <div>
                        <p className="kicker mb-3">Flujo de atención</p>
                        <ol className="space-y-2">
                            {p.steps.map((s) => (
                                <li key={s.id} className="flex gap-3">
                                    <span className="grid size-7 shrink-0 place-items-center rounded-full bg-navy-udh text-xs font-bold text-white dark:bg-teal-udh">{s.step_number}</span>
                                    <div className="text-sm">
                                        <p className="font-semibold text-ink">{s.name}</p>
                                        <p className="text-muted">{s.area_label}{s.document_requirement ? ' · emite documento' : ''}</p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
            )}
        </Modal>
    );
}

export default function Catalog() {
    const { user, meta } = useAuth();
    const navigate = useNavigate();
    const [items, setItems] = useState(null);
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [selected, setSelected] = useState(null);
    const isStudent = user.role === 'ESTUDIANTE';

    useEffect(() => { api.get('/procedures').then(({ data }) => setItems(data.data)); }, []);

    const filtered = useMemo(() => {
        const q = search.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return (items || []).filter((p) => (!category || p.category === category)
            && (!q || (p.name + ' ' + p.code).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(q)));
    }, [items, search, category]);

    const apply = (p) => navigate(`/catalogo/${p.id}/solicitar`);

    return (
        <div>
            <PageHeader
                kicker="Texto Único de Procedimientos Administrativos"
                title="Catálogo de trámites"
                description={isStudent ? `Trámites disponibles para ${user.program?.name}.` : 'Consulta los trámites vigentes, sus requisitos, costos y flujo de atención.'}
            />
            <SearchBar value={search} onChange={setSearch} placeholder="Buscar por nombre o código del trámite…" count={filtered.length} countLabel="trámites">
                <Select value={category} onChange={(e) => setCategory(e.target.value)} className="md:w-72">
                    <option value="">Todas las categorías</option>
                    {meta?.categories.map((c) => <option key={c}>{c}</option>)}
                </Select>
            </SearchBar>

            {!items ? <Spinner /> : filtered.length === 0 ? (
                <Card><EmptyState icon={BookOpen} title="Sin resultados" description="Prueba con otro término de búsqueda." /></Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {filtered.map((p) => (
                        <Card key={p.id} className="flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-navy-udh/5">
                            <div className="mb-3 flex flex-wrap items-center gap-2">
                                <Badge tone={CAT_TONE[p.category]}>{p.category}</Badge>
                                {p.target_scope === 'PROGRAMA' && p.programs.length === 1 && <Badge tone="navy"><Sparkles className="size-3" /> Exclusivo de la carrera</Badge>}
                            </div>
                            <p className="text-xs font-semibold text-muted">{p.code}</p>
                            <h3 className="mt-1 line-clamp-2 font-extrabold tracking-tight text-ink">{p.name}</h3>
                            <p className="mt-2 line-clamp-2 flex-1 text-sm text-muted">{p.description}</p>
                            <div className="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold text-muted">
                                <span className="flex items-center gap-1"><Wallet className="size-3.5" />{p.cost > 0 ? fmtMoney(p.cost) : 'Gratuito'}</span>
                                <span className="flex items-center gap-1"><Clock className="size-3.5" />{p.estimated_days} días</span>
                                <span className="flex items-center gap-1"><Layers className="size-3.5" />{p.steps.length} etapas</span>
                                <span className="flex items-center gap-1"><FileCheck2 className="size-3.5" />{p.requirements.length} requisitos</span>
                            </div>
                            <div className={cx('mt-4 grid gap-2', isStudent ? 'grid-cols-2' : 'grid-cols-1')}>
                                <Button variant="secondary" size="sm" onClick={() => setSelected(p)}>Ver detalle</Button>
                                {isStudent && <Button size="sm" onClick={() => apply(p)}>Solicitar</Button>}
                            </div>
                        </Card>
                    ))}
                </div>
            )}
            {!isStudent && <Callout className="mt-6">El personal puede consultar el catálogo; solo los estudiantes inician solicitudes.</Callout>}
            <ProcedureInfo p={selected} onClose={() => setSelected(null)} onApply={isStudent ? apply : null} />
        </div>
    );
}
