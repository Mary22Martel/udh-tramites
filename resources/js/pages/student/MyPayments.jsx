import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { CheckCircle2, Clock, Download, ExternalLink, Receipt, Wallet } from 'lucide-react';
import api from '../../lib/api';
import { Badge, Button, Callout, Card, EmptyState, PageHeader, Spinner, Tabs } from '../../components/ui';
import PayModal from '../../components/PayModal';
import { fmtDate, fmtDateTime, fmtMoney } from '../../lib/format';

/** Mis pagos: tickets generados por las solicitudes con costo. */
export default function MyPayments() {
    const navigate = useNavigate();
    const [params, setParams] = useSearchParams();
    const [data, setData] = useState(null);
    const [tab, setTab] = useState('PENDIENTE');
    const [paying, setPaying] = useState(null);

    const load = () => api.get('/payments/mine').then(({ data }) => {
        setData(data);
        const want = params.get('pagar');
        if (want) {
            const t = data.data.find((p) => String(p.id) === want && p.status === 'PENDIENTE');
            if (t) setPaying(t);
            params.delete('pagar');
            setParams(params, { replace: true });
        }
    });
    useEffect(() => { load(); }, []); // eslint-disable-line

    if (!data) return <Spinner />;
    const pending = data.data.filter((p) => p.status === 'PENDIENTE');
    const paid = data.data.filter((p) => p.status === 'PAGADO');
    const list = tab === 'PENDIENTE' ? pending : paid;

    return (
        <div>
            <PageHeader kicker="Pagos" title="Mis pagos" description="Cada solicitud con costo genera un ticket. Págalo aquí mismo con tarjeta, Yape u otros medios para que tu trámite empiece a atenderse." />
            {pending.length > 0 && (
                <Callout tone="warning" className="mb-5" title={`Tienes ${pending.length} pago(s) pendiente(s) por ${fmtMoney(data.pending_total)}`}>
                    Tus trámites no empiezan a atenderse hasta que pagues su ticket.
                </Callout>
            )}
            <Tabs value={tab} onChange={setTab} tabs={[
                { value: 'PENDIENTE', label: 'Por pagar', icon: Clock, count: pending.length },
                { value: 'PAGADO', label: 'Pagados', icon: CheckCircle2, count: paid.length },
            ]} />

            {list.length === 0 ? (
                <Card><EmptyState icon={Wallet} title={tab === 'PENDIENTE' ? 'No tienes pagos pendientes' : 'Aún no tienes pagos realizados'} description={tab === 'PENDIENTE' ? 'Cuando inicies un trámite con costo, su ticket aparecerá aquí.' : undefined} /></Card>
            ) : (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {list.map((p) => (
                        <Card key={p.id} className="flex flex-col overflow-hidden">
                            <div className="flex items-start justify-between gap-3 border-b border-dashed border-line p-5">
                                <div className="min-w-0">
                                    <p className="flex items-center gap-2 text-xs font-bold tracking-wider text-muted uppercase"><Receipt className="size-4" /> Ticket {p.code}</p>
                                    <p className="mt-1.5 line-clamp-2 font-extrabold text-ink">{p.submission?.procedure_name}</p>
                                    <p className="text-xs text-muted">Expediente {p.submission?.code} · generado el {fmtDate(p.created_at)}</p>
                                </div>
                                <Badge tone={p.status === 'PAGADO' ? 'teal' : 'gold'}>{p.status === 'PAGADO' ? 'Pagado' : 'Por pagar'}</Badge>
                            </div>
                            <div className="flex flex-1 flex-col gap-3 p-5">
                                <p className="text-3xl font-extrabold text-ink">{fmtMoney(p.amount)}</p>
                                {p.status === 'PAGADO' ? (
                                    <p className="text-sm text-muted">{p.method_label} {p.reference} · {fmtDateTime(p.paid_at)}<br /><span className="font-mono text-xs">{p.transaction_code}</span></p>
                                ) : (
                                    p.failure_reason && <p className="text-xs text-red-600">Último intento rechazado: {p.failure_reason}</p>
                                )}
                                <div className="mt-auto flex flex-wrap gap-2">
                                    {p.status === 'PENDIENTE' ? (
                                        <Button className="flex-1" icon={Wallet} onClick={() => setPaying(p)}>Pagar ahora</Button>
                                    ) : (
                                        <Button variant="secondary" icon={Download} onClick={() => window.open(`/api/payments/${p.id}/receipt`, '_blank')}>Comprobante</Button>
                                    )}
                                    <Button variant="ghost" icon={ExternalLink} onClick={() => navigate(`/mis-tramites?ver=${p.submission_id}`)}>Ver trámite</Button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}
            <PayModal ticket={paying} onClose={() => setPaying(null)} onPaid={load} />
        </div>
    );
}
