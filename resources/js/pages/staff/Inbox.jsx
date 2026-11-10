import { useEffect, useState } from 'react';
import api from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import useDetailParam from '../../lib/useDetailParam';
import { Callout, PageHeader, Pagination, SearchBar, Select, Spinner } from '../../components/ui';
import SubmissionList from '../../components/SubmissionList';
import SubmissionDetail from '../../components/SubmissionDetail';

export default function Inbox({ scope }) {
    const { user, meta } = useAuth();
    const [search, setSearch] = useState('');
    const [programId, setProgramId] = useState('');
    const [status, setStatus] = useState('TODOS');
    const [area, setArea] = useState('');
    const [page, setPage] = useState(1);
    const [res, setRes] = useState(null);
    const [detail, openDetail, closeDetail] = useDetailParam();
    const pending = scope === 'pendientes';

    const load = () => api.get('/submissions', { params: { scope, search, program_id: programId, status: pending ? undefined : status, area: area || undefined, page } }).then(({ data }) => setRes(data));
    useEffect(() => { setRes(null); setPage(1); }, [scope]);
    useEffect(() => { const t = setTimeout(load, 250); return () => clearTimeout(t); }, [scope, search, programId, status, area, page]); // eslint-disable-line

    const who = user.role === 'ADMINISTRADOR' ? 'todas las áreas' : user.area?.name || (user.role === 'FACULTAD' ? user.faculty?.name : `Programa de ${user.program?.name}`);

    return (
        <div>
            <PageHeader
                kicker={pending ? 'Bandeja de tareas' : 'Historial'}
                title={pending ? 'Solicitudes por atender' : 'Solicitudes atendidas'}
                description={pending ? `Solicitudes cuyo paso actual corresponde a ${who}. Abre cada una para aprobar, observar o denegar.` : 'Solicitudes en las que registraste alguna actuación.'}
            />
            {pending && user.role === 'MATRICULA' && user.counts && (
                <Callout className="mb-4">Las solicitudes se reparten por <b>ventanillas</b> según la carrera del estudiante. Los operadores ven solo su ventanilla; la jefatura ve todas.</Callout>
            )}
            <SearchBar value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Buscar por estudiante, código o trámite…" count={res?.meta.total} countLabel={pending ? 'pendientes' : 'atendidas'}>
                <Select value={programId} onChange={(e) => { setProgramId(e.target.value); setPage(1); }} className="md:w-64">
                    <option value="">Todas las carreras</option>
                    {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </Select>
                {user.role === 'ADMINISTRADOR' && pending && (
                    <Select value={area} onChange={(e) => { setArea(e.target.value); setPage(1); }} className="md:w-56">
                        <option value="">Todas las áreas</option>
                        {Object.entries(meta?.step_areas || {}).filter(([k]) => k !== 'ESTUDIANTE').map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </Select>
                )}
                {!pending && (
                    <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="md:w-44">
                        <option value="TODOS">Todos los estados</option>
                        {Object.entries(meta?.statuses || {}).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                    </Select>
                )}
            </SearchBar>
            {!res ? <Spinner /> : (
                <>
                    <SubmissionList items={res.data} onOpen={(s) => openDetail(s.id)} emptyTitle={pending ? '¡Bandeja al día!' : 'Aún no atendiste solicitudes'} emptyDescription={pending ? 'No hay solicitudes esperando tu atención.' : undefined} />
                    <Pagination meta={res.meta} onPage={setPage} />
                </>
            )}
            <SubmissionDetail id={detail} onClose={closeDetail} onChanged={load} />
        </div>
    );
}
