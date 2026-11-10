import { useEffect, useRef, useState } from 'react';
import { AlertTriangle, CheckCircle2, ChevronLeft, ChevronRight, Info, Loader2, Search, UploadCloud, X } from 'lucide-react';
import { cx, STATUS } from '../lib/format';

/* ---------- Botones ---------- */
const BTN = {
    primary: 'bg-teal-udh text-white hover:bg-teal-udh-600 shadow-sm shadow-teal-udh/20',
    navy: 'bg-navy-udh text-white hover:bg-navy-udh/90 dark:bg-teal-udh dark:hover:bg-teal-udh-600',
    secondary: 'bg-surface text-ink border border-line hover:bg-surface-2',
    ghost: 'text-muted hover:text-ink hover:bg-surface-2',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    warning: 'bg-amber-500 text-white hover:bg-amber-600',
    gold: 'bg-gold-udh text-navy-udh hover:brightness-95',
};

export function Button({ variant = 'primary', size = 'md', loading, icon: Icon, className, children, ...props }) {
    const sizes = { sm: 'h-8 px-3 text-xs gap-1.5', md: 'h-10 px-4 text-sm gap-2', lg: 'h-12 px-6 text-base gap-2' };
    return (
        <button
            className={cx('inline-flex items-center justify-center rounded-xl font-semibold transition disabled:cursor-not-allowed disabled:opacity-50', BTN[variant], sizes[size], className)}
            disabled={loading || props.disabled}
            {...props}
        >
            {loading ? <Loader2 className="size-4 animate-spin" /> : Icon && <Icon className="size-4" />}
            {children}
        </button>
    );
}

export function IconButton({ icon: Icon, label, className, tone = 'default', ...props }) {
    const tones = {
        default: 'text-muted hover:text-ink hover:bg-surface-2',
        danger: 'text-muted hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10',
    };
    return (
        <button title={label} aria-label={label} className={cx('grid size-9 place-items-center rounded-lg transition', tones[tone], className)} {...props}>
            <Icon className="size-[18px]" />
        </button>
    );
}

/* ---------- Contenedores ---------- */
export function Card({ className, children, ...props }) {
    return (
        <div className={cx('min-w-0 rounded-2xl border border-line bg-surface', className)} {...props}>
            {children}
        </div>
    );
}

export function PageHeader({ kicker, title, description, actions }) {
    return (
        <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0 flex-1">
                {kicker && <p className="kicker mb-2">{kicker}</p>}
                <h1 className="text-[1.6rem] leading-tight font-extrabold tracking-tight text-ink md:text-[2rem]">{title}</h1>
                {description && <p className="mt-2 max-w-3xl text-sm leading-relaxed text-muted md:text-[15px]">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2 lg:shrink-0">{actions}</div>}
        </div>
    );
}

export function Callout({ tone = 'info', title, children, className }) {
    const tones = {
        info: 'border-teal-udh/30 bg-teal-udh-50 text-ink dark:bg-teal-udh/10',
        warning: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100',
        danger: 'border-red-300 bg-red-50 text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100',
        success: 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100',
    };
    const Icon = { info: Info, warning: AlertTriangle, danger: AlertTriangle, success: CheckCircle2 }[tone];
    return (
        <div className={cx('flex gap-3 rounded-2xl border px-4 py-3.5 text-sm leading-relaxed', tones[tone], className)}>
            <Icon className={cx('mt-0.5 size-[18px] shrink-0', tone === 'info' && 'text-accent')} />
            <div className="min-w-0">
                {title && <p className="font-bold">{title}</p>}
                <div>{children}</div>
            </div>
        </div>
    );
}

/* ---------- Badges ---------- */
const TONES = {
    teal: 'bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300 ring-teal-udh/25',
    blue: 'bg-sky-500/10 text-sky-700 dark:text-sky-300 ring-sky-500/25',
    amber: 'bg-amber-500/12 text-amber-700 dark:text-amber-300 ring-amber-500/30',
    red: 'bg-red-500/10 text-red-700 dark:text-red-300 ring-red-500/25',
    gray: 'bg-slate-500/10 text-muted ring-slate-500/20',
    gold: 'bg-gold-udh/15 text-amber-800 dark:text-gold-udh ring-gold-udh/30',
    navy: 'bg-navy-udh/10 text-navy-udh dark:bg-white/10 dark:text-white ring-navy-udh/15',
};

export function Badge({ tone = 'gray', className, children }) {
    return <span className={cx('inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide ring-1 ring-inset', TONES[tone], className)}>{children}</span>;
}

export function StatusBadge({ status }) {
    const s = STATUS[status] || { label: status, tone: 'gray' };
    return <Badge tone={s.tone}>{s.label}</Badge>;
}

/* ---------- Formularios ---------- */
export function Field({ label, hint, error, children, className }) {
    return (
        <label className={cx('block', className)}>
            {label && <span className="mb-1.5 block text-[13px] font-semibold text-ink">{label}</span>}
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-muted">{hint}</span>}
            {error && <span className="mt-1 block text-xs font-medium text-red-600">{error}</span>}
        </label>
    );
}

const inputCls = 'rounded-xl border border-line bg-surface px-3.5 text-sm text-ink placeholder:text-muted/70 outline-none transition focus:border-teal-udh focus:ring-4 focus:ring-teal-udh/15 disabled:opacity-60';

// Ancho completo salvo que se indique otro ancho (w-64, md:w-60…)
const width = (c = '') => (/(^|\s)(\w+:)?w-/.test(c) ? c : cx('w-full', c));
export const Input = ({ className, ...p }) => <input className={cx(inputCls, 'h-11', width(className))} {...p} />;
export const Textarea = ({ className, ...p }) => <textarea className={cx(inputCls, 'min-h-24 py-2.5', width(className))} {...p} />;
export const Select = ({ className, children, ...p }) => (
    <select className={cx(inputCls, 'h-11 pr-8', width(className), /(^|\s)md:w-/.test(className || '') && 'w-full')} {...p}>
        {children}
    </select>
);

export function Toggle({ checked, onChange, label }) {
    return (
        <button type="button" onClick={() => onChange(!checked)} className="inline-flex items-center gap-2.5 text-sm font-medium text-ink">
            <span className={cx('relative h-6 w-11 rounded-full transition', checked ? 'bg-teal-udh' : 'bg-slate-300 dark:bg-slate-600')}>
                <span className={cx('absolute top-0.5 size-5 rounded-full bg-white shadow transition-all', checked ? 'left-[22px]' : 'left-0.5')} />
            </span>
            {label}
        </button>
    );
}

export function SearchBar({ value, onChange, placeholder = 'Buscar…', count, countLabel, children }) {
    return (
        <Card className="mb-4 flex flex-col gap-3 p-4 md:flex-row md:items-center">
            <div className="relative flex-1">
                <Search className="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-muted" />
                <Input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-10" />
            </div>
            {children}
            {count !== undefined && (
                <span className="shrink-0 self-start rounded-full bg-surface-2 px-3.5 py-1.5 text-xs font-bold tracking-wide text-ink uppercase ring-1 ring-line md:self-auto">
                    {count} {countLabel}
                </span>
            )}
        </Card>
    );
}

export function FileDrop({ file, onChange, accept, hint, compact }) {
    const ref = useRef();
    const [over, setOver] = useState(false);
    return (
        <div
            onClick={() => ref.current?.click()}
            onDragOver={(e) => { e.preventDefault(); setOver(true); }}
            onDragLeave={() => setOver(false)}
            onDrop={(e) => { e.preventDefault(); setOver(false); e.dataTransfer.files[0] && onChange(e.dataTransfer.files[0]); }}
            className={cx(
                'flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed px-4 transition',
                compact ? 'py-2.5' : 'py-4',
                over ? 'border-teal-udh bg-teal-udh/5' : file ? 'border-teal-udh/50 bg-teal-udh/5' : 'border-line hover:border-teal-udh/60',
            )}
        >
            <input ref={ref} type="file" accept={accept} className="hidden" onChange={(e) => e.target.files[0] && onChange(e.target.files[0])} />
            {file ? <CheckCircle2 className="size-5 shrink-0 text-teal-udh" /> : <UploadCloud className="size-5 shrink-0 text-muted" />}
            <div className="min-w-0 flex-1 text-sm">
                {file ? (
                    <p className="truncate font-semibold text-ink">{file.name}</p>
                ) : (
                    <p className="text-muted"><span className="font-semibold text-accent">Selecciona un archivo</span> o arrástralo aquí</p>
                )}
                {hint && <p className="text-xs text-muted">{hint}</p>}
            </div>
            {file && (
                <button type="button" onClick={(e) => { e.stopPropagation(); onChange(null); ref.current.value = ''; }} className="text-muted hover:text-red-600">
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

/* ---------- Modal ---------- */
export function Modal({ open, onClose, title, subtitle, size = 'md', children, footer }) {
    useEffect(() => {
        if (!open) return;
        const h = (e) => e.key === 'Escape' && onClose?.();
        document.addEventListener('keydown', h);
        document.body.style.overflow = 'hidden';
        return () => { document.removeEventListener('keydown', h); document.body.style.overflow = ''; };
    }, [open, onClose]);
    if (!open) return null;
    const sizes = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl' };
    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 p-0 backdrop-blur-[2px] sm:items-center sm:p-4" onMouseDown={(e) => e.target === e.currentTarget && onClose?.()}>
            <div className={cx('flex max-h-[94vh] w-full flex-col rounded-t-2xl border border-line bg-surface shadow-2xl sm:rounded-2xl', sizes[size])}>
                <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                    <div className="min-w-0">
                        <h3 className="text-lg font-extrabold text-ink">{title}</h3>
                        {subtitle && <p className="mt-0.5 text-sm text-muted">{subtitle}</p>}
                    </div>
                    <IconButton icon={X} label="Cerrar" onClick={onClose} />
                </div>
                <div className="scrollbar-thin flex-1 overflow-y-auto px-5 py-5">{children}</div>
                {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-3.5">{footer}</div>}
            </div>
        </div>
    );
}

/* ---------- Estados vacíos y carga ---------- */
export function Spinner({ className }) {
    return (
        <div className={cx('flex items-center justify-center py-16 text-muted', className)}>
            <Loader2 className="size-6 animate-spin text-teal-udh" />
        </div>
    );
}

export function EmptyState({ icon: Icon, title, description, action }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            {Icon && (
                <div className="mb-4 grid size-14 place-items-center rounded-2xl bg-surface-2 ring-1 ring-line">
                    <Icon className="size-6 text-muted" />
                </div>
            )}
            <p className="font-bold text-ink">{title}</p>
            {description && <p className="mt-1 max-w-sm text-sm text-muted">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

export function Pagination({ meta, onPage }) {
    if (!meta || meta.last_page <= 1) return null;
    return (
        <div className="flex items-center justify-between gap-3 px-1 pt-4 text-sm text-muted">
            <span>
                Página <b className="text-ink">{meta.current_page}</b> de {meta.last_page} · {meta.total} registros
            </span>
            <div className="flex gap-1.5">
                <Button variant="secondary" size="sm" icon={ChevronLeft} disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>Anterior</Button>
                <Button variant="secondary" size="sm" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>
                    Siguiente <ChevronRight className="size-4" />
                </Button>
            </div>
        </div>
    );
}

export function Tabs({ tabs, value, onChange, fill }) {
    return (
        <div className="scrollbar-thin mb-5 flex gap-1 overflow-x-auto rounded-xl border border-line bg-surface p-1">
            {tabs.map((t) => (
                <button
                    key={t.value}
                    onClick={() => onChange(t.value)}
                    className={cx(
                        'flex shrink-0 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition sm:px-3.5',
                        fill && 'flex-1 shrink',
                        value === t.value ? 'bg-navy-udh text-white dark:bg-teal-udh' : 'text-muted hover:text-ink',
                    )}
                >
                    {t.icon && <t.icon className={cx('size-4', fill && 'hidden sm:block')} />}
                    {t.label}
                    {t.count !== undefined && <span className={cx('rounded-full px-1.5 text-[11px]', value === t.value ? 'bg-white/20' : 'bg-surface-2')}>{t.count}</span>}
                </button>
            ))}
        </div>
    );
}

export function StatCard({ label, value, icon: Icon, tone = 'teal', hint }) {
    const tones = {
        teal: 'bg-teal-udh/12 text-teal-udh-600 dark:text-teal-300',
        blue: 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
        amber: 'bg-amber-500/12 text-amber-600 dark:text-amber-300',
        red: 'bg-red-500/10 text-red-600 dark:text-red-300',
        gold: 'bg-gold-udh/15 text-amber-700 dark:text-gold-udh',
        navy: 'bg-navy-udh/10 text-navy-udh dark:bg-white/10 dark:text-white',
    };
    return (
        <Card className="min-w-0 p-4 sm:p-5">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-[11px] font-bold tracking-wider text-muted uppercase sm:text-xs">{label}</p>
                    <p className={cx('mt-1.5 font-extrabold tracking-tight break-words text-ink sm:mt-2', String(value).length > 9 ? 'text-lg sm:text-2xl' : 'text-2xl sm:text-3xl')}>{value}</p>
                    {hint && <p className="mt-1 text-xs text-muted">{hint}</p>}
                </div>
                {Icon && (
                    <div className={cx('hidden size-11 shrink-0 place-items-center rounded-xl sm:grid', tones[tone])}>
                        <Icon className="size-5" />
                    </div>
                )}
            </div>
        </Card>
    );
}

export function ProgressBar({ value, tone = 'teal' }) {
    const tones = { teal: 'bg-teal-udh', amber: 'bg-amber-500', red: 'bg-red-500', blue: 'bg-sky-500' };
    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-2 ring-1 ring-line">
            <div className={cx('h-full rounded-full transition-all', tones[tone])} style={{ width: `${Math.max(4, value)}%` }} />
        </div>
    );
}
