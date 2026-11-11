import { AlertTriangle, ChevronRight, FileText } from 'lucide-react';
import { Card, EmptyState, ProgressBar, StatusBadge } from './ui';
import { cx, dayMonth, fmtDate, timeAgo } from '../lib/format';

/** Lista de solicitudes con el estilo del panel (fecha en bloque + título + detalle). */
export default function SubmissionList({ items, onOpen, showStudent = true, emptyTitle = 'No hay solicitudes', emptyDescription, emptyAction }) {
    if (!items?.length) {
        return (
            <Card>
                <EmptyState icon={FileText} title={emptyTitle} description={emptyDescription} action={emptyAction} />
            </Card>
        );
    }
    return (
        <Card className="divide-y divide-line overflow-hidden">
            {items.map((s) => {
                const d = dayMonth(s.created_at);
                const tone = s.status === 'OBSERVADO' ? 'amber' : s.status === 'RECHAZADO' ? 'red' : 'teal';
                return (
                    <button key={s.id} onClick={() => onOpen(s)} className="group flex w-full items-center gap-4 px-4 py-4 text-left transition hover:bg-surface-2 md:px-6">
                        <div className="hidden size-[60px] shrink-0 flex-col items-center justify-center rounded-xl bg-sand-udh/35 text-navy-udh sm:flex dark:bg-sand-udh/10 dark:text-sand-udh">
                            <span className="text-xl leading-none font-extrabold">{d.day}</span>
                            <span className="mt-1 text-[10px] font-bold tracking-wider">{d.month}</span>
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                <span className="min-w-0 truncate font-extrabold tracking-tight text-ink">{showStudent ? s.student?.name : s.procedure_name}</span>
                                <StatusBadge status={s.status} />
                                {s.status === 'OBSERVADO' && <AlertTriangle className="size-4 text-amber-500" />}
                            </div>
                            {showStudent && <p className="mt-0.5 truncate text-sm font-medium text-ink/90">{s.procedure_name}</p>}
                            <p className="mt-1 truncate text-xs text-muted">
                                {s.code}
                                {showStudent && s.student?.student_code ? ` · ${s.student.student_code}` : ''}
                                {s.current_step ? ` · En ${s.current_step.area_label}` : ''}
                                {` · ${timeAgo(s.updated_at)}`}
                            </p>
                            <div className="mt-2.5 flex items-center gap-3">
                                <div className="w-full max-w-xs"><ProgressBar value={s.progress} tone={tone} /></div>
                                <span className="text-[11px] font-semibold text-muted">{s.progress}%</span>
                                {s.status === 'FINALIZADO' && s.finished_at && <span className="hidden text-[11px] text-muted md:inline">Finalizado el {fmtDate(s.finished_at)}</span>}
                            </div>
                        </div>
                        <ChevronRight className={cx('size-5 shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-ink')} />
                    </button>
                );
            })}
        </Card>
    );
}
