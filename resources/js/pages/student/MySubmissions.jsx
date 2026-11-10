import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
import api from '../../lib/api';
import useDetailParam from '../../lib/useDetailParam';
import { Button, PageHeader, Pagination, SearchBar, Spinner, Tabs } from '../../components/ui';
import SubmissionList from '../../components/SubmissionList';
import SubmissionDetail from '../../components/SubmissionDetail';

export default function MySubmissions() {
    const navigate = useNavigate();
    const [status, setStatus] = useState('TODOS');
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [res, setRes] = useState(null);
    const [detail, openDetail, closeDetail] = useDetailParam();

    const load = () => api.get('/submissions', { params: { status, search, page, per_page: 12 } }).then(({ data }) => setRes(data));
    useEffect(() => { const t = setTimeout(load, 250); return () => clearTimeout(t); }, [status, search, page]); // eslint-disable-line

    return (
        <div>
            <PageHeader kicker="Seguimiento" title="Mis trámites" description="Consulta el estado de cada solicitud, sus documentos y el historial de atención."
                actions={<Button icon={Plus} onClick={() => navigate('/catalogo')}>Nuevo trámite</Button>} />
            <Tabs value={status} onChange={(v) => { setStatus(v); setPage(1); }} tabs={[
                { value: 'TODOS', label: 'Todos' }, { value: 'EN_PROCESO', label: 'En proceso' }, { value: 'OBSERVADO', label: 'Observados' },
                { value: 'FINALIZADO', label: 'Finalizados' }, { value: 'RECHAZADO', label: 'Denegados' },
            ]} />
            <SearchBar value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Buscar por código o trámite…" count={res?.meta.total} countLabel="solicitudes" />
            {!res ? <Spinner /> : (
                <>
                    <SubmissionList items={res.data} showStudent={false} onOpen={(s) => openDetail(s.id)} emptyTitle="No hay trámites con este filtro" />
                    <Pagination meta={res.meta} onPage={setPage} />
                </>
            )}
            <SubmissionDetail id={detail} onClose={closeDetail} onChanged={load} />
        </div>
    );
}
