import { useEffect, useState } from 'react';
import { Database, Download, FileSpreadsheet, RefreshCcw, Terminal, Upload, Wand2 } from 'lucide-react';
import api, { downloadFile, errorMessage } from '../../lib/api';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import { Button, Callout, Card, Field, FileDrop, Input, PageHeader, Select, Spinner } from '../../components/ui';

function Result({ r }) {
    if (!r) return null;
    return (
        <Callout tone={r.errors.length ? 'warning' : 'success'} className="mt-4" title={`${r.created} creados · ${r.updated} actualizados · ${r.skipped.length} omitidos`}>
            {r.skipped.length > 0 && <p className="text-xs">Omitidos (ya existían): {r.skipped.slice(0, 8).join(', ')}{r.skipped.length > 8 ? '…' : ''}</p>}
            {r.errors.length > 0 && <ul className="mt-1 list-disc pl-4 text-xs">{r.errors.slice(0, 10).map((e) => <li key={e}>{e}</li>)}</ul>}
        </Callout>
    );
}

function ImportCard({ type, onDone }) {
    const toast = useToast();
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState(null);
    const run = async () => {
        setBusy(true);
        setResult(null);
        try {
            const fd = new FormData();
            fd.append('type', type.key);
            fd.append('file', file);
            const { data } = await api.post('/imports', fd);
            setResult(data.result);
            setFile(null);
            toast.success(`${type.label}: importación completada.`);
            onDone();
        } catch (e) { toast.error(errorMessage(e)); } finally { setBusy(false); }
    };
    return (
        <Card className="flex flex-col p-5">
            <div className="mb-3 flex items-start gap-3">
                <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300"><FileSpreadsheet className="size-5" /></div>
                <div className="min-w-0 flex-1">
                    <p className="font-extrabold text-ink">{type.label}</p>
                    <p className="mt-0.5 font-mono text-[11px] leading-relaxed break-words text-muted">{type.columns.join(' ; ')}</p>
                </div>
            </div>
            <div className="mt-auto space-y-3">
                <FileDrop file={file} onChange={setFile} accept=".csv,.txt" compact hint="Archivo .csv guardado desde Excel" />
                <div className="flex gap-2">
                    <Button variant="secondary" size="sm" icon={Download} onClick={() => downloadFile(`/imports/template/${type.key}`)}>Plantilla</Button>
                    <Button size="sm" icon={Upload} className="flex-1" loading={busy} disabled={!file} onClick={run}>Importar</Button>
                </div>
            </div>
            <Result r={result} />
        </Card>
    );
}

export default function DataLoad() {
    const { meta, loadMeta, logout } = useAuth();
    const toast = useToast();
    const [types, setTypes] = useState(null);
    const [gen, setGen] = useState({ program_id: '', count: 10 });
    const [genBusy, setGenBusy] = useState(false);
    const [genResult, setGenResult] = useState(null);
    const [resetBusy, setResetBusy] = useState(false);

    useEffect(() => { api.get('/imports/types').then(({ data }) => setTypes(data.data)); }, []);

    const generate = async () => {
        setGenBusy(true);
        try {
            const { data } = await api.post('/imports/generate', { program_ids: gen.program_id ? [gen.program_id] : [], count: Number(gen.count) });
            setGenResult(data.result);
            toast.success(`${data.result.created} solicitudes de demostración generadas.`);
        } catch (e) { toast.error(errorMessage(e)); } finally { setGenBusy(false); }
    };

    const reset = async () => {
        if (!window.confirm('Se BORRARÁN todos los datos y se volverán a cargar los datos iniciales de database/data. ¿Continuar?')) return;
        setResetBusy(true);
        try {
            await api.post('/imports/reset');
            toast.success('Datos reiniciados. Vuelve a iniciar sesión.');
            setTimeout(logout, 1200);
        } catch (e) { toast.error(errorMessage(e)); setResetBusy(false); }
    };

    return (
        <div>
            <PageHeader kicker="Carga de datos" title="Importar información desde Excel" description="Descarga la plantilla, llénala en Excel, guárdala como CSV y súbela. Si un registro ya existe (mismo correo o código) se actualiza." />
            <Callout className="mb-5" title="¿Cómo guardar en CSV desde Excel?">
                Archivo → Guardar como → Tipo: <b>CSV UTF-8 (delimitado por comas)</b> o <b>CSV (delimitado por punto y coma)</b>. El sistema reconoce ambos separadores y las tildes.
                Orden recomendado: <b>1)</b> Facultades y carreras → <b>2)</b> Personal → <b>3)</b> Estudiantes → <b>4)</b> Trámites por carrera.
            </Callout>
            {!types ? <Spinner /> : (
                <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {types.filter((t) => t.key !== 'solicitudes_demo').map((t) => <ImportCard key={t.key} type={t} onDone={loadMeta} />)}
                    <ImportCard type={types.find((t) => t.key === 'solicitudes_demo')} onDone={loadMeta} />
                </div>
            )}

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Card className="p-6">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="grid size-11 place-items-center rounded-xl bg-gold-udh/15 text-amber-700 dark:text-gold-udh"><Wand2 className="size-5" /></div>
                        <div><p className="font-extrabold text-ink">Generar solicitudes de demostración</p><p className="text-sm text-muted">Crea solicitudes realistas que recorren el flujo real (pagos, observaciones, resoluciones).</p></div>
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_120px_auto] sm:items-end">
                        <Field label="Carrera">
                            <Select value={gen.program_id} onChange={(e) => setGen({ ...gen, program_id: e.target.value })}>
                                <option value="">Todas las carreras</option>
                                {meta?.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </Select>
                        </Field>
                        <Field label="Cantidad c/u"><Input type="number" min="1" max="200" value={gen.count} onChange={(e) => setGen({ ...gen, count: e.target.value })} /></Field>
                        <Button icon={Wand2} loading={genBusy} onClick={generate}>Generar</Button>
                    </div>
                    <Result r={genResult} />
                </Card>
                <Card className="p-6">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="grid size-11 place-items-center rounded-xl bg-navy-udh/10 text-navy-udh dark:bg-white/10 dark:text-white"><Terminal className="size-5" /></div>
                        <div><p className="font-extrabold text-ink">Seeders (desde la terminal)</p><p className="text-sm text-muted">Los datos iniciales están en la carpeta <code>database/data</code>.</p></div>
                    </div>
                    <pre className="scrollbar-thin overflow-x-auto rounded-xl bg-navy-udh p-4 text-xs leading-relaxed text-teal-100">{`# Todo desde cero
php artisan migrate:fresh --seed

# Solo un archivo
php artisan db:seed --class=PersonalSeeder
php artisan db:seed --class=SolicitudesDemoSeeder

# Importar un CSV cualquiera
php artisan udh:importar personal C:\\ruta\\personal.csv`}</pre>
                    <div className="mt-4 flex items-center justify-between gap-3 rounded-xl border border-red-300/60 bg-red-500/[0.04] p-3">
                        <p className="text-sm text-ink"><b>Reiniciar demo:</b> borra todo y recarga los datos iniciales.</p>
                        <Button variant="danger" size="sm" icon={RefreshCcw} loading={resetBusy} onClick={reset}>Reiniciar</Button>
                    </div>
                </Card>
            </div>
            <p className="mt-6 flex items-center gap-2 text-xs text-muted"><Database className="size-3.5" /> Contraseña inicial de los usuarios importados: <b>udh2026</b></p>
        </div>
    );
}
