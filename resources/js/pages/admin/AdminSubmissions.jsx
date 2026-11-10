import { useEffect, useState } from 'react';
import { Download } from 'lucide-react';
import api, { downloadFile } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import useDetailParam from '../../lib/useDetailParam';
import { Button, Card, Field, Input, PageHeader, Pagination, SearchBar, Select, Spinner, Tabs } from '../../components/ui';
import SubmissionList from '../../components/SubmissionList';
import SubmissionDetail from '../../components/SubmissionDetail';

export default function AdminSubmissions() {
    const { meta } = useAuth();
    const [f, setF] = useState({ status: 'TODOS', search: '', faculty_id: '', program_id: '', from: '', to: '' });
    const [page, setPage] = useState(1);
    const [res, setRes] = useState(null);
    const [detail, openDetail, closeDetail] = useDetailParam();
    const set = (k) => (v) => { setF({ ...f, [k]: v?.target ? v.target.value : v }); setPage(1); };
    const params = { ...f, scope: 'todas', page };

    const load = () => api.get('/submissions', { params }).then(({ data }) => setRes(data));
    useEffect(() => { const t = setTimeout(load, 250); return () => clearTimeout(t); }, [JSON.stringify(f), page]); // eslint-disable-line

    const programs = (meta?.programs || []).filter((p) => !f.faculty_id || String(p.faculty_id) === String(f.faculty_id));

    return (
        <div>
            <PageHeader kicker="Gestión" title="Todas las solicitudes" description="Consulta, filtra y exporta las solicitudes de todas las carreras."
                actions={<Button variant="secondary" icon={Download} onClick={() => downloadFile('/submissions-export', f)}>Exportar a Excel (CSV)</Button>} />
            <Tabs value={f.status} onChange={set('status')} tabs={[{ value: 'TODOS', label: 'Todas' }, ...Object.entries(meta?.statuses || {}).map(([k, v]) => ({ value: k, label: v }))]} />
            <Card className="mb-4 grid gap-3 p-4 md:grid-cols-4">
                <Field label="Facultad">
                    <Select value={f.faculty_id} onChange={(e) => setF({ ...f, faculty_id: e.target.value, program_id: '' })}>
                        <option value="">Todas</option>
                        {meta?.faculties.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                    </Select>
                </Field>
                <Field label="Carrera">
                    <Select value={f.program_id} onChange={set('program_id')}>
                        <option value="">Todas</option>
                        {programs.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                    </Select>
                </Field>
                <Field label="Desde"><Input type="date" value={f.from} onChange={set('from')} /></Field>
                <Field label="Hasta"><Input type="date" value={f.to} onChange={set('to')} /></Field>
            </Card>
            <SearchBar value={f.search} onChange={set('search')} placeholder="Buscar por expediente, estudiante, código o trámite…" count={res?.meta.total} countLabel="solicitudes" />
            {!res ? <Spinner /> : (
                <>
                    <SubmissionList items={res.data} onOpen={(s) => openDetail(s.id)} />
                    <Pagination meta={res.meta} onPage={setPage} />
                </>
            )}
            <SubmissionDetail id={detail} onClose={closeDetail} onChanged={load} />
        </div>
    );
}
