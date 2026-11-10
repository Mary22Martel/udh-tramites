export const STATUS = {
    EN_PROCESO: { label: 'En proceso', tone: 'blue' },
    OBSERVADO: { label: 'Observado', tone: 'amber' },
    FINALIZADO: { label: 'Finalizado', tone: 'teal' },
    RECHAZADO: { label: 'Rechazado', tone: 'red' },
};

export const ACTION_LABELS = {
    CREACION: 'Solicitud registrada',
    PAGO_REGISTRADO: 'Pago registrado',
    PAGO_VALIDADO: 'Pago validado',
    APROBADO: 'Etapa aprobada',
    OFICIO_ADJUNTADO: 'Oficio emitido',
    RESOLUCION_EMITIDA: 'Resolución emitida',
    CONSTANCIA_EMITIDA: 'Constancia emitida',
    OBSERVADO: 'Observación',
    SUBSANADO: 'Observación subsanada',
    RECHAZADO: 'Solicitud denegada',
    FINALIZADO: 'Trámite finalizado',
};

export const DOC_TYPES = {
    REQUISITO: 'Requisito',
    COMPROBANTE_PAGO: 'Comprobante de pago',
    OFICIO: 'Oficio',
    RESOLUCION: 'Resolución',
    CONSTANCIA: 'Constancia',
    INFORME: 'Informe',
    CONSTANCIA_FINAL: 'Documento final',
    SUBSANACION: 'Subsanación',
    ANEXO: 'Anexo',
};

const dateFmt = new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFmt = new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

// Las fechas sin hora (2026-11-09) se interpretan como fecha local, no UTC
const parse = (v) => new Date(/^\d{4}-\d{2}-\d{2}$/.test(v) ? `${v}T12:00:00` : v);
export const fmtDate = (v) => (v ? dateFmt.format(parse(v)) : '—');
export const todayISO = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
export const fmtDateTime = (v) => (v ? dateTimeFmt.format(new Date(v)) : '—');
export const fmtMoney = (v) => `S/ ${Number(v || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
export const fmtNumber = (v) => Number(v || 0).toLocaleString('es-PE');

export function dayMonth(v) {
    const d = new Date(v);
    return {
        day: String(d.getDate()).padStart(2, '0'),
        month: d.toLocaleDateString('es-PE', { month: 'short' }).replace('.', '').toUpperCase(),
    };
}

export function timeAgo(v) {
    if (!v) return '';
    const s = Math.round((Date.now() - new Date(v).getTime()) / 1000);
    if (s < 60) return 'hace un momento';
    const m = Math.round(s / 60);
    if (m < 60) return `hace ${m} min`;
    const h = Math.round(m / 60);
    if (h < 24) return `hace ${h} h`;
    const d = Math.round(h / 24);
    if (d < 30) return `hace ${d} día${d > 1 ? 's' : ''}`;
    return fmtDate(v);
}

export function fileSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export const cx = (...c) => c.filter(Boolean).join(' ');
