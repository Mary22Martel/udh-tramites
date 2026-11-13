import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AlertTriangle, ArrowRight, BookOpen, CheckCircle2, Clock, FileStack, XCircle } from 'lucide-react';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import useDetailParam from '../../lib/useDetailParam';
import { Button, Callout, Card, PageHeader, Spinner, StatCard } from '../../components/ui';
import SubmissionList from '../../components/SubmissionList';
import SubmissionDetail from '../../components/SubmissionDetail';

export default function StudentHome() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [detail, openDetail, closeDetail] = useDetailParam();
    const load = () => api.get('/dashboard').then(({ data }) => setData(data));
    useEffect(() => { load(); }, []);

    if (!data) return <Spinner />;
    const observed = data.active.filter((s) => s.status === 'OBSERVADO');
    const first = user.name.split(' ')[0];

    return (
        <div>
            <div className="relative mb-6 overflow-hidden rounded-2xl bg-navy-udh p-7 text-white md:p-9 dark:ring-1 dark:ring-line">
                <div className="absolute inset-0 opacity-[0.12]" style={{ background: 'linear-gradient(120deg, transparent 55%, #2eb8a0 55%)' }} />
                <img src="/img/escudo.png" alt="" className="absolute -right-6 -bottom-10 hidden h-56 opacity-20 md:block" />
                <div className="relative max-w-2xl">
                    <p className="text-xs font-bold tracking-[0.22em] text-teal-udh uppercase">Portal del estudiante</p>
                    <h1 className="mt-2 text-3xl font-extrabold tracking-tight">¡Hola, {first}!</h1>
                    <p className="mt-2 text-white/75">{user.program?.name} · {user.current_semester} · Código {user.student_code}</p>
                    <div className="mt-6 flex flex-wrap gap-3">
                        <Button variant="gold" icon={BookOpen} onClick={() => navigate('/catalogo')}>Iniciar un trámite</Button>
                        <Button variant="secondary" className="border-white/20 bg-white/10 text-white hover:bg-white/20" onClick={() => navigate('/mis-tramites')}>Ver mis trámites</Button>
                    </div>
                    <p className="mt-4 text-sm text-white/60">Tienes {data.available_procedures} trámites disponibles para tu carrera.</p>
                </div>
            </div>

            {data.pending_payments?.length > 0 && (
                <Callout tone="danger" className="mb-4" title={`Tienes ${data.pending_payments.length} pago(s) pendiente(s)`}>
                    Tus trámites empiezan a atenderse cuando pagas su ticket.{' '}
                    <button className="font-bold underline" onClick={() => navigate('/mis-pagos')}>Ir a Mis pagos</button>
                </Callout>
            )}
            {observed.length > 0 && (
                <Callout tone="warning" className="mb-6" title={`Tienes ${observed.length} trámite(s) observado(s)`}>
                    Revisa la observación y envía la subsanación para que tu trámite continúe.{' '}
                    <button className="font-bold underline" onClick={() => openDetail(observed[0].id)}>Subsanar ahora</button>
                </Callout>
            )}

            <div className="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="En proceso" value={data.kpis.EN_PROCESO} icon={Clock} tone="blue"  />
                <StatCard label="Observados" value={data.kpis.OBSERVADO} icon={AlertTriangle} tone="amber" />
                <StatCard label="Finalizados" value={data.kpis.FINALIZADO} icon={CheckCircle2} tone="teal" />
                <StatCard label="Por pagar" value={data.kpis.PENDIENTE_PAGO || 0} icon={XCircle} tone="gold" hint="En Mis pagos o en caja" />
            </div>

            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-lg font-extrabold text-ink">Trámites en curso</h2>
                <Link to="/mis-tramites" className="flex items-center gap-1 text-sm font-semibold text-accent">Ver todos <ArrowRight className="size-4" /></Link>
            </div>
            <SubmissionList
                items={data.active}
                showStudent={false}
                onOpen={(s) => openDetail(s.id)}
                emptyTitle="No tienes trámites en curso"
                emptyDescription="Cuando inicies un trámite, aquí verás en qué etapa se encuentra."
                emptyAction={<Button icon={FileStack} onClick={() => navigate('/catalogo')}>Ver catálogo</Button>}
            />
            <SubmissionDetail id={detail} onClose={closeDetail} onChanged={load} />
        </div>
    );
}
