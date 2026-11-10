import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { BadgeCheck, Printer, QrCode, Search, ShieldAlert } from 'lucide-react';
import api from '../lib/api';
import { Button, Card, Input, PageHeader, Spinner, StatusBadge } from '../components/ui';
import { fmtDateTime } from '../lib/format';

export default function Verify() {
    const { code } = useParams();
    const navigate = useNavigate();
    const [value, setValue] = useState(code || '');
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!code) return setResult(null);
        setLoading(true);
        api.get(`/verificar/${encodeURIComponent(code)}`).then(({ data }) => setResult(data)).catch(() => setResult({ valid: false, notFound: true })).finally(() => setLoading(false));
    }, [code]);

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader kicker="Autenticidad" title="Verificar documento" description="Ingresa el código de verificación del documento (DOC-UDH-…) o el número de expediente (UDH-…). También puedes escanear el código QR impreso en la constancia." />
            <Card className="p-5">
                <form onSubmit={(e) => { e.preventDefault(); value && navigate(`/verificar/${value.trim()}`); }} className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <QrCode className="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" />
                        <Input value={value} onChange={(e) => setValue(e.target.value.toUpperCase())} placeholder="DOC-UDH-2026-XXXXXX" className="pl-10 font-mono" />
                    </div>
                    <Button type="submit" icon={Search}>Verificar</Button>
                </form>
            </Card>
            {loading && <Spinner />}
            {!loading && result && (
                result.valid ? (
                    <Card className="mt-5 overflow-hidden">
                        <div className="flex items-center gap-4 bg-teal-udh px-6 py-5 text-white">
                            <BadgeCheck className="size-10" />
                            <div>
                                <p className="text-lg font-extrabold">Documento auténtico</p>
                                <p className="text-sm text-white/80">Emitido por la Universidad de Huánuco</p>
                            </div>
                        </div>
                        <dl className="grid gap-4 p-6 sm:grid-cols-2">
                            {[['Código de verificación', result.document_code], ['Expediente', result.code], ['Trámite', result.procedure_name], ['Estudiante', `${result.student_name} (${result.student_code})`], ['Programa académico', result.program], ['Facultad', result.faculty], ['Fecha de emisión', fmtDateTime(result.finished_at)]].map(([k, v]) => (
                                <div key={k}><dt className="text-xs font-bold tracking-wider text-muted uppercase">{k}</dt><dd className="mt-1 font-semibold text-ink">{v}</dd></div>
                            ))}
                        </dl>
                        <div className="border-t border-line px-6 py-4 text-right">
                            <Button variant="secondary" icon={Printer} onClick={() => window.open(`/documento/${result.document_code}`, '_blank')}>Ver constancia</Button>
                        </div>
                    </Card>
                ) : (
                    <Card className="mt-5 flex items-start gap-4 p-6">
                        <ShieldAlert className="size-10 shrink-0 text-amber-500" />
                        <div>
                            <p className="text-lg font-extrabold text-ink">{result.notFound ? 'Código no encontrado' : 'El trámite aún no ha finalizado'}</p>
                            <p className="mt-1 text-sm text-muted">{result.notFound ? 'No existe un documento con ese código. Revisa que esté bien escrito.' : <>El expediente <b>{result.code}</b> ({result.procedure_name}) está en estado <StatusBadge status={result.status} />. Aún no tiene documento final.</>}</p>
                        </div>
                    </Card>
                )
            )}
        </div>
    );
}
