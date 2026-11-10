import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
    BarChart3, Bell, BookOpen, Wallet, Landmark, Building2, ChevronDown, ChevronLeft, ClipboardCheck, FileStack, FolderKanban,
    History, Inbox, LayoutDashboard, LogOut, Menu, Moon, PlusCircle, Sun, Upload, UserCircle2, Users, X,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import api from '../lib/api';
import { cx, timeAgo } from '../lib/format';

function navFor(user) {
    const c = user.counts || {};
    if (user.role === 'ADMINISTRADOR') {
        return [
            { section: 'Principal', items: [
                { to: '/admin', label: 'Panel de control', icon: LayoutDashboard, end: true },
                { to: '/admin/reportes', label: 'Trámites por carrera', icon: BarChart3 },
            ] },
            { section: 'Gestión', items: [
                { to: '/admin/solicitudes', label: 'Solicitudes', icon: FileStack },
                { to: '/pagos', label: 'Pagos', icon: Landmark },
                { to: '/bandeja', label: 'Bandeja general', icon: Inbox, badge: c.tasks },
                { to: '/admin/tramites', label: 'Trámites (TUPA)', icon: FolderKanban },
                { to: '/admin/organizacion', label: 'Organización', icon: Building2 },
                { to: '/admin/usuarios', label: 'Personal y estudiantes', icon: Users },
                { to: '/admin/carga', label: 'Carga de datos', icon: Upload },
            ] },
        ];
    }
    if (user.role === 'ESTUDIANTE') {
        return [
            { section: 'Principal', items: [
                { to: '/inicio', label: 'Inicio', icon: LayoutDashboard },
                { to: '/catalogo', label: 'Catálogo de trámites', icon: BookOpen },
                { to: '/mis-tramites', label: 'Mis trámites', icon: FileStack, badge: c.observed, badgeTone: 'amber' },
                { to: '/mis-pagos', label: 'Mis pagos', icon: Wallet, badge: c.payments, badgeTone: 'red' },
            ] },
        ];
    }
    return [
        { section: 'Principal', items: [
            ...(user.role === 'TESORERIA' ? [{ to: '/pagos', label: 'Pagos recibidos', icon: Landmark }] : []),
            { to: '/panel', label: 'Mi panel', icon: LayoutDashboard },
            { to: '/bandeja', label: 'Bandeja de tareas', icon: Inbox, badge: c.tasks },
            { to: '/atendidos', label: 'Historial atendido', icon: History },
        ] },
        { section: 'Consulta', items: [
            { to: '/catalogo', label: 'Catálogo de trámites', icon: BookOpen },
        ] },
    ];
}

function Brand({ collapsed }) {
    return (
        <div className={cx('flex flex-col items-center text-center', collapsed ? 'px-2 py-5' : 'px-6 pt-7 pb-5')}>
            <img src="/img/escudo.png" alt="Escudo UDH" className={cx('drop-shadow-sm transition-all', collapsed ? 'h-11' : 'h-24')} />
            {!collapsed && (
                <>
                    <p className="mt-4 text-[1.35rem] leading-tight font-extrabold tracking-tight text-ink">Gestión de Trámites</p>
                    <p className="mt-1 text-[15px] text-muted">Universidad de Huánuco</p>
                    <span className="mt-4 h-[3px] w-36 rounded-full bg-gold-udh/80" />
                </>
            )}
        </div>
    );
}

function Sidebar({ user, collapsed, onCollapse, mobileOpen, onCloseMobile }) {
    const sections = navFor(user);
    const navigate = useNavigate();
    return (
        <>
            {mobileOpen && <div className="fixed inset-0 z-30 bg-slate-950/50 lg:hidden" onClick={onCloseMobile} />}
            <aside
                className={cx(
                    'fixed inset-y-0 left-0 z-40 flex flex-col border-r border-line bg-sidebar transition-all duration-200 lg:sticky lg:top-0 lg:h-screen',
                    collapsed ? 'lg:w-[84px]' : 'lg:w-[300px]',
                    mobileOpen ? 'w-[290px] translate-x-0' : 'w-[290px] -translate-x-full lg:translate-x-0',
                )}
            >
                <button onClick={onCloseMobile} className="absolute top-3 right-3 text-muted lg:hidden"><X className="size-5" /></button>
                <button
                    onClick={onCollapse}
                    className="absolute top-[68px] -right-4 z-10 hidden size-8 place-items-center rounded-full border border-line bg-surface text-muted shadow-sm hover:text-ink lg:grid"
                    title={collapsed ? 'Expandir menú' : 'Contraer menú'}
                >
                    <ChevronLeft className={cx('size-4 transition', collapsed && 'rotate-180')} />
                </button>

                <Brand collapsed={collapsed} />
                <div className="mx-5 border-t border-line" />

                <nav className="scrollbar-thin flex-1 overflow-y-auto px-3 py-4">
                    {user.role === 'ESTUDIANTE' && (
                        <button
                            onClick={() => { navigate('/catalogo'); onCloseMobile(); }}
                            className={cx('mb-4 flex w-full items-center justify-center gap-2 rounded-xl bg-teal-udh py-3 text-sm font-bold text-white shadow-md shadow-teal-udh/20 hover:bg-teal-udh-600', collapsed && 'lg:px-0')}
                        >
                            <PlusCircle className="size-5" /> {!collapsed && 'Nuevo trámite'}
                        </button>
                    )}
                    {sections.map((s) => (
                        <div key={s.section} className="mb-4">
                            {!collapsed && <p className="mb-2 px-3 text-[11px] font-bold tracking-[0.18em] text-muted uppercase">{s.section}</p>}
                            <div className="space-y-1">
                                {s.items.map((it) => (
                                    <NavLink
                                        key={it.to}
                                        to={it.to}
                                        end={it.end}
                                        onClick={onCloseMobile}
                                        title={collapsed ? it.label : undefined}
                                        className={({ isActive }) => cx(
                                            'group flex items-center gap-3.5 rounded-xl px-3 py-3 text-[15px] font-semibold transition',
                                            collapsed && 'lg:justify-center',
                                            isActive
                                                ? 'bg-navy-udh text-white shadow-md shadow-navy-udh/20 dark:bg-teal-udh/15 dark:text-teal-200 dark:shadow-none dark:ring-1 dark:ring-teal-udh/30'
                                                : 'text-ink/85 hover:bg-surface-2 hover:text-ink',
                                        )}
                                    >
                                        <it.icon className="size-[21px] shrink-0" strokeWidth={1.8} />
                                        {!collapsed && <span className="flex-1 truncate">{it.label}</span>}
                                        {!!it.badge && (
                                            <span className={cx('grid min-w-6 place-items-center rounded-full px-1.5 text-[11px] font-bold',
                                                collapsed && 'lg:absolute lg:ml-6 lg:-mt-6',
                                                it.badgeTone === 'amber' ? 'bg-amber-500 text-white' : it.badgeTone === 'red' ? 'bg-red-500 text-white' : 'bg-gold-udh text-navy-udh')}>
                                                {it.badge}
                                            </span>
                                        )}
                                    </NavLink>
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className={cx('flex items-center gap-3 border-t border-line p-4', collapsed && 'lg:justify-center')}>
                    <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-teal-udh/12 font-bold text-teal-udh-600 dark:text-teal-300">{user.initials}</div>
                    {!collapsed && (
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-bold text-ink">{user.name}</p>
                            <p className="truncate text-xs text-muted">{user.email}</p>
                        </div>
                    )}
                </div>
            </aside>
        </>
    );
}

function NotificationsMenu({ count, onChange }) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState([]);
    const ref = useRef();
    const navigate = useNavigate();
    const { user } = useAuth();

    useEffect(() => {
        if (!open) return;
        api.get('/notifications').then(({ data }) => setItems(data.data));
        const h = (e) => !ref.current?.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, [open]);

    const go = async (n) => {
        if (!n.read_at) await api.post(`/notifications/${n.id}/read`);
        setOpen(false);
        onChange();
        if (n.type === 'PAYMENT') navigate('/mis-pagos');
        else if (n.submission_id) navigate(`${user.role === 'ESTUDIANTE' ? '/mis-tramites' : '/bandeja'}?ver=${n.submission_id}`);
    };
    const readAll = async () => {
        await api.post('/notifications/read-all');
        setItems((xs) => xs.map((x) => ({ ...x, read_at: x.read_at || new Date().toISOString() })));
        onChange();
    };
    const dot = { INFO: 'bg-sky-500', SUCCESS: 'bg-teal-udh', WARNING: 'bg-amber-500', ALERT: 'bg-red-500', PAYMENT: 'bg-gold-udh' };

    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)} className="relative grid size-12 place-items-center rounded-xl border border-line bg-surface text-ink hover:bg-surface-2" title="Notificaciones">
                <Bell className="size-5" />
                {count > 0 && <span className="absolute -top-1 -right-1 grid min-w-5 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{count}</span>}
            </button>
            {open && (
                <div className="absolute right-0 z-50 mt-2 w-[min(380px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-line bg-surface shadow-2xl">
                    <div className="flex items-center justify-between border-b border-line px-4 py-3">
                        <p className="font-bold text-ink">Notificaciones</p>
                        <button onClick={readAll} className="text-xs font-semibold text-accent hover:underline">Marcar todas como leídas</button>
                    </div>
                    <div className="scrollbar-thin max-h-[420px] overflow-y-auto">
                        {items.length === 0 && <p className="px-4 py-10 text-center text-sm text-muted">No tienes notificaciones.</p>}
                        {items.map((n) => (
                            <button key={n.id} onClick={() => go(n)} className={cx('flex w-full gap-3 border-b border-line px-4 py-3 text-left last:border-0 hover:bg-surface-2', !n.read_at && 'bg-teal-udh/[0.04]')}>
                                <span className={cx('mt-1.5 size-2 shrink-0 rounded-full', n.read_at ? 'bg-transparent' : dot[n.type])} />
                                <span className="min-w-0">
                                    <span className="block text-sm font-bold text-ink">{n.title}</span>
                                    <span className="mt-0.5 block text-[13px] leading-snug text-muted">{n.message}</span>
                                    <span className="mt-1 block text-[11px] text-muted">{timeAgo(n.created_at)}</span>
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function UserMenu({ user, onLogout }) {
    const [open, setOpen] = useState(false);
    const ref = useRef();
    useEffect(() => {
        const h = (e) => !ref.current?.contains(e.target) && setOpen(false);
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);
    const subtitle = user.role === 'ESTUDIANTE' ? `${user.student_code} · ${user.program?.name ?? ''}` : user.job_title || user.role_label;
    return (
        <div className="relative" ref={ref}>
            <button onClick={() => setOpen(!open)} className="flex items-center gap-3 rounded-xl py-1.5 pr-2 pl-1.5 hover:bg-surface-2">
                <div className="grid size-11 place-items-center rounded-xl bg-teal-udh/12 font-bold text-teal-udh-600 dark:text-teal-300">{user.initials}</div>
                <div className="hidden max-w-[220px] text-left sm:block">
                    <p className="truncate text-sm font-bold text-ink">{user.name}</p>
                    <p className="truncate text-xs text-muted">{subtitle}</p>
                </div>
                <ChevronDown className="size-4 text-muted" />
            </button>
            {open && (
                <div className="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-2xl border border-line bg-surface p-1.5 shadow-2xl">
                    <div className="px-3 py-2.5">
                        <p className="text-sm font-bold text-ink">{user.name}</p>
                        <p className="text-xs text-muted">{user.role_label}</p>
                    </div>
                    <Link to="/perfil" onClick={() => setOpen(false)} className="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium text-ink hover:bg-surface-2">
                        <UserCircle2 className="size-4" /> Mi perfil
                    </Link>
                    <button onClick={onLogout} className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                        <LogOut className="size-4" /> Cerrar sesión
                    </button>
                </div>
            )}
        </div>
    );
}

export default function Layout() {
    const { user, logout, refresh } = useAuth();
    const { dark, toggle } = useTheme();
    const location = useLocation();
    const [collapsed, setCollapsed] = useState(() => {
        try { return localStorage.getItem('udh-sidebar') === '1'; } catch { return false; }
    });
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => { window.scrollTo(0, 0); }, [location.pathname]);

    const collapse = () => {
        setCollapsed(!collapsed);
        try { localStorage.setItem('udh-sidebar', collapsed ? '0' : '1'); } catch { /* sin almacenamiento */ }
    };

    return (
        <div className="flex min-h-screen">
            <Sidebar user={user} collapsed={collapsed} onCollapse={collapse} mobileOpen={mobileOpen} onCloseMobile={() => setMobileOpen(false)} />
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="no-print sticky top-0 z-20 flex h-[88px] items-center gap-3 border-b border-line bg-bg/85 px-4 backdrop-blur md:px-8">
                    <button onClick={() => setMobileOpen(true)} className="grid size-11 place-items-center rounded-xl border border-line bg-surface lg:hidden"><Menu className="size-5" /></button>
                    <div className="hidden min-w-0 items-center gap-2 text-sm text-muted md:flex">
                        <ClipboardCheck className="size-4 text-accent" />
                        <span className="truncate">{user.role_label}{user.area ? ` · ${user.area.name}` : ''}{user.role === 'FACULTAD' && user.faculty ? ` · ${user.faculty.name}` : ''}{user.role === 'PROGRAMA_ACADEMICO' && user.program ? ` · ${user.program.name}` : ''}</span>
                    </div>
                    <div className="ml-auto flex items-center gap-2 md:gap-3">
                        <button onClick={toggle} className="grid size-12 place-items-center rounded-xl border border-line bg-surface text-ink hover:bg-surface-2" title={dark ? 'Modo claro' : 'Modo oscuro'}>
                            {dark ? <Sun className="size-5" /> : <Moon className="size-5" />}
                        </button>
                        <NotificationsMenu count={user.counts?.notifications || 0} onChange={refresh} />
                        <span className="mx-1 hidden h-10 w-px bg-line sm:block" />
                        <UserMenu user={user} onLogout={logout} />
                    </div>
                </header>
                <main className="mx-auto w-full max-w-[1400px] flex-1 px-4 py-7 md:px-10 md:py-9">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
