import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { AlarmClock, AlertTriangle, CheckCircle2, Clock, FileStack, GraduationCap, Plus, Timer, Upload, Wallet } from 'lucide-react';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import useDetailParam from '../../lib/useDetailParam';
import { Button, Card, PageHeader, Select, Spinner, StatCard } from '../../components/ui';
import { StackedBars, StatusDonut, TrendChart } from '../../components/Charts';
import SubmissionList from '../../components/SubmissionList';
import SubmissionDetail from '../../components/SubmissionDetail';
import { fmtMoney, fmtNumber } from '../../lib/format';

export default function AdminDashboard() {
    const { meta } = useAuth();
    const navigate = useNavigate();
    const [facultyId, setFacultyId] = useState('');
    const [data, setData] = useState(null);
    const [detail, openDetail, closeDetail] = useDetailParam();
    const load = () => api.get('/dashboard', { params: { faculty_id: facultyId || undefined } }).then(({ data }) => setData(data));
    useEffect(() => { load(); }, [facultyId]); // eslint-disable-line

    if (!data) return <Spinner />;
    const k = data.kpis;

    return (
        <div>
            <PageHeader
                kicker="Panel de control"
                title="Gestión de trámites universitarios"
                description="Indicadores en tiempo real de las solicitudes de todas las carreras y áreas."
                actions={
                    <>
                        <Select value={facultyId} onChange={(e) => setFacultyId(e.target.value)} className="w-64">
                            <option value="">Todas las facultades</option>
                            {meta?.faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                        </Select>
                        <Button variant="secondary" icon={Upload} onClick={() => navigate('/admin/carga')}>Cargar datos</Button>
                        <Button icon={Plus} onClick={() => navigate('/admin/tramites/nuevo')}>Nuevo trámite</Button>
                    </>
                }
            />
            <div className="mb-5 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Solicitudes" value={fmtNumber(k.total)} icon={FileStack} tone="navy" hint={`${k.students} estudiantes · ${k.programs} carreras`} />
                <StatCard label="En proceso" value={fmtNumber(k.en_proceso)} icon={Clock} tone="blue" hint={`${k.overdue} fuera del plazo estimado`} />
                <StatCard label="Observadas" value={fmtNumber(k.observado)} icon={AlertTriangle} tone="amber" hint="Esperan subsanación del estudiante" />
                <StatCard label="Finalizadas" value={fmtNumber(k.finalizado)} icon={CheckCircle2} tone="teal" hint={`${k.rechazado} denegadas`} />
            </div>
            <div className="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
                <StatCard label="Tiempo promedio" value={`${k.avg_days} d`} icon={Timer} tone="gold" hint="Desde registro hasta emisión" />
                <StatCard label="Recaudado en línea" value={fmtMoney(k.revenue)} icon={Wallet} tone="teal" hint="Pagos confirmados por la pasarela" />
                <StatCard label="Trámites activos" value={k.procedures} icon={GraduationCap} tone="navy" hint="En el catálogo (TUPA)" />
                <StatCard label="Fuera de plazo" value={k.overdue} icon={AlarmClock} tone="red" hint="Superan los días estimados" />
            </div>

            <div className="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-[1.6fr_1fr]">
                <Card className="p-5">
                    <p className="font-extrabold text-ink">Evolución mensual</p>
                    <p className="mb-4 text-sm text-muted">Solicitudes registradas vs. finalizadas en los últimos 6 meses.</p>
                    <TrendChart data={data.by_month} series={[{ key: 'registradas', name: 'Registradas', color: '#0f2440' }, { key: 'finalizadas', name: 'Finalizadas', color: '#2eb8a0' }]} />
                </Card>
                <Card className="p-5">
                    <p className="font-extrabold text-ink">Estado de las solicitudes</p>
                    <p className="mb-4 text-sm text-muted">Distribución actual.</p>
                    <StatusDonut counts={data.by_status} labels={meta?.statuses || {}} />
                </Card>
            </div>

            <div className="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-[1.6fr_1fr]">
                <Card className="p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <p className="font-extrabold text-ink">Solicitudes por carrera</p>
                            <p className="text-sm text-muted">Cantidad de trámites por programa académico y su estado.</p>
                        </div>
                        <Button variant="ghost" size="sm" onClick={() => navigate('/admin/reportes')}>Ver reporte</Button>
                    </div>
                    <StackedBars data={data.by_program} height={Math.max(260, data.by_program.length * 30)} />
                </Card>
                <div className="space-y-5">
                    <Card className="p-5">
                        <p className="mb-4 font-extrabold text-ink">Carga actual por área</p>
                        <ul className="space-y-3">
                            {data.by_area.map((a) => (
                                <li key={a.area}>
                                    <div className="mb-1 flex justify-between text-sm"><span className="font-semibold text-ink">{a.label}</span><span className="font-bold">{a.total}</span></div>
                                    <div className="h-2 rounded-full bg-surface-2"><div className="h-2 rounded-full bg-gold-udh" style={{ width: `${(a.total / Math.max(...data.by_area.map((x) => x.total))) * 100}%` }} /></div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card className="p-5">
                        <p className="mb-4 font-extrabold text-ink">Trámites más solicitados</p>
                        <ol className="space-y-2.5">
                            {data.top_procedures.map((p, i) => (
                                <li key={p.name} className="flex items-center gap-3 text-sm">
                                    <span className="grid size-6 shrink-0 place-items-center rounded-md bg-surface-2 text-xs font-bold text-muted ring-1 ring-line">{i + 1}</span>
                                    <span className="flex-1 truncate font-medium text-ink">{p.name}</span>
                                    <span className="font-bold text-ink">{p.total}</span>
                                </li>
                            ))}
                        </ol>
                    </Card>
                </div>
            </div>

            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-lg font-extrabold text-ink">Actividad reciente</h2>
                <Button variant="ghost" size="sm" onClick={() => navigate('/admin/solicitudes')}>Ver todas</Button>
            </div>
            <SubmissionList items={data.recent} onOpen={(s) => openDetail(s.id)} />
            <SubmissionDetail id={detail} onClose={closeDetail} onChanged={load} />
        </div>
    );
}
