import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AlarmClock, ArrowRight, CheckCheck, Inbox, MessageSquareWarning, TrendingUp } from 'lucide-react';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import useDetailParam from '../../lib/useDetailParam';
import { Button, Card, PageHeader, Spinner, StatCard } from '../../components/ui';
import { SimpleBars } from '../../components/Charts';
import SubmissionList from '../../components/SubmissionList';
import SubmissionDetail from '../../components/SubmissionDetail';

export default function StaffHome() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [detail, openDetail, closeDetail] = useDetailParam();
    const load = () => api.get('/dashboard').then(({ data }) => setData(data));
    useEffect(() => { load(); }, []);
    if (!data) return <Spinner />;

    const scope = user.area?.name || (user.role === 'FACULTAD' ? user.faculty?.name : user.program ? `Programa de ${user.program.name}` : user.role_label);

    return (
        <div>
            <PageHeader kicker="Mi panel" title={`Hola, ${user.name.replace(/^(Ing|Lic|Mg|Dr|Dra|Arq)\.\s*/, '').split(' ')[0]}`} description={`Resumen de atención de ${scope}.`}
                actions={<Button icon={Inbox} onClick={() => navigate('/bandeja')}>Ir a mi bandeja</Button>} />
            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Pendientes" value={data.kpis.pending} icon={Inbox} tone="gold" hint="Esperan tu atención" />
                <StatCard label="Fuera de plazo" value={data.kpis.overdue} icon={AlarmClock} tone="red" hint="Superaron los días de tu área" />
                <StatCard label="Atendidas este mes" value={data.kpis.this_month} icon={TrendingUp} tone="teal" />
                <StatCard label="Observaciones emitidas" value={data.kpis.observed} icon={MessageSquareWarning} tone="amber" />
            </div>
            <div className="mb-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Card className="p-5">
                    <p className="mb-1 font-extrabold text-ink">Actuaciones por mes</p>
                    <p className="mb-4 text-sm text-muted">Aprobaciones, observaciones y denegaciones registradas por ti.</p>
                    <SimpleBars data={data.by_month} dataKey="atendidas" />
                </Card>
                <Card className="p-5">
                    <p className="mb-1 font-extrabold text-ink">Pendientes por trámite</p>
                    <p className="mb-4 text-sm text-muted">Qué trámites concentran tu bandeja.</p>
                    {data.by_procedure.length === 0 ? <p className="py-16 text-center text-sm text-muted"><CheckCheck className="mx-auto mb-2 size-8 text-teal-udh" />¡Bandeja al día!</p> : (
                        <ul className="space-y-3">
                            {data.by_procedure.map((p) => (
                                <li key={p.name}>
                                    <div className="mb-1 flex justify-between gap-3 text-sm"><span className="truncate font-semibold text-ink">{p.name}</span><span className="font-bold text-ink">{p.total}</span></div>
                                    <div className="h-2 rounded-full bg-surface-2"><div className="h-2 rounded-full bg-teal-udh" style={{ width: `${(p.total / data.by_procedure[0].total) * 100}%` }} /></div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-lg font-extrabold text-ink">Las más urgentes según el plazo</h2>
                <button onClick={() => navigate('/bandeja')} className="flex items-center gap-1 text-sm font-semibold text-accent">Ver bandeja <ArrowRight className="size-4" /></button>
            </div>
            <SubmissionList items={data.oldest} onOpen={(s) => openDetail(s.id)} emptyTitle="No tienes solicitudes pendientes" />
            <SubmissionDetail id={detail} onClose={closeDetail} onChanged={load} />
        </div>
    );
}
