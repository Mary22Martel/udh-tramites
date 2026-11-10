import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { QRCodeSVG } from 'qrcode.react';
import { Printer } from 'lucide-react';
import api from '../lib/api';
import { Button, Spinner } from '../components/ui';

/** Constancia imprimible con código QR de verificación (página pública). */
export default function OfficialDocument() {
    const { code } = useParams();
    const [d, setD] = useState(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        document.documentElement.classList.remove('dark');
        api.get(`/verificar/${code}`).then(({ data }) => (data.valid ? setD(data) : setError(true))).catch(() => setError(true));
    }, [code]);

    if (error) return <p className="p-10 text-center text-slate-600">Documento no válido o trámite no finalizado.</p>;
    if (!d) return <Spinner className="min-h-screen" />;
    const url = `${window.location.origin}/verificar/${d.document_code}`;
    const date = new Date(d.finished_at).toLocaleDateString('es-PE', { day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <div className="min-h-screen bg-slate-200 py-8 print:bg-white print:py-0">
            <div className="no-print mx-auto mb-4 flex max-w-[800px] justify-end px-4">
                <Button icon={Printer} onClick={() => window.print()}>Imprimir / Guardar PDF</Button>
            </div>
            <article className="relative mx-auto aspect-[1/1.414] max-w-[800px] overflow-hidden bg-white text-slate-900 shadow-2xl print:shadow-none">
                <div className="h-3 bg-gradient-to-r from-[#f2d68a] via-[#f5b400] to-[#2eb8a0]" />
                <img src="/img/escudo.png" alt="" className="pointer-events-none absolute top-1/2 left-1/2 w-[55%] -translate-x-1/2 -translate-y-1/2 opacity-[0.05]" />
                <div className="relative flex h-[calc(100%-12px)] flex-col px-[9%] py-[7%]">
                    <header className="flex items-center gap-5 border-b-2 border-[#2eb8a0] pb-5">
                        <img src="/img/logo-udh.png" alt="Universidad de Huánuco" className="h-16" />
                        <div className="ml-auto text-right text-xs text-slate-500">
                            <p className="font-bold text-slate-700">Dirección de Matrícula y Registros Académicos</p>
                            <p>Expediente {d.code}</p>
                        </div>
                    </header>
                    <h1 className="mt-10 text-center text-3xl font-extrabold tracking-[0.3em] text-[#0f2440]">CONSTANCIA</h1>
                    <p className="mt-2 text-center text-sm font-semibold text-slate-500">{d.document_code}</p>
                    <div className="mt-10 space-y-5 text-justify text-[15px] leading-8">
                        <p>La <b>Universidad de Huánuco</b>, a través de la Dirección de Matrícula y Registros Académicos, hace constar que:</p>
                        <p className="text-center text-xl font-extrabold tracking-wide text-[#0f2440] uppercase">{d.student_name}</p>
                        <p>identificado(a) con código de estudiante N° <b>{d.student_code}</b>, del Programa Académico de <b>{d.program}</b> de la <b>{d.faculty}</b>, ha culminado satisfactoriamente el trámite de <b>“{d.procedure_name}”</b>, conforme a lo establecido en el Texto Único de Procedimientos Administrativos de la Universidad.</p>
                        <p>Se expide la presente a solicitud del interesado para los fines que estime conveniente.</p>
                    </div>
                    <p className="mt-8 text-right text-[15px]">Huánuco, {date}</p>
                    <div className="mt-auto flex items-end justify-between gap-8">
                        <div className="flex items-center gap-4">
                            <QRCodeSVG value={url} size={110} fgColor="#0f2440" level="M" />
                            <div className="text-[11px] leading-relaxed text-slate-500">
                                <p className="font-bold text-slate-700">Verificación de autenticidad</p>
                                <p>Escanee el código QR o ingrese a</p>
                                <p className="font-mono break-all text-slate-700">{url}</p>
                            </div>
                        </div>
                        <div className="w-56 text-center">
                            <div className="border-t border-slate-400 pt-2 text-xs">
                                <p className="font-bold">Director(a) de Matrícula y Registros</p>
                                <p className="text-slate-500">Firma digital</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    );
}
