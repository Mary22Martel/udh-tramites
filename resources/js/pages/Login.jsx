import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { ArrowRight, ChevronDown, LogIn, Moon, ShieldCheck, Sparkles, Sun } from 'lucide-react';
import api, { errorMessage } from '../lib/api';
import { homeFor, useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { Button, Callout, Field, Input } from '../components/ui';
import { cx } from '../lib/format';

export default function Login() {
    const { user, login, demoLogin, loading } = useAuth();
    const { dark, toggle } = useTheme();
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [demo, setDemo] = useState(null);
    const [openGroup, setOpenGroup] = useState('ESTUDIANTE');

    useEffect(() => { api.get('/demo-accounts').then(({ data }) => setDemo(data)).catch(() => {}); }, []);

    if (!loading && user) return <Navigate to={homeFor(user)} replace />;

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        try {
            const u = await login(email, password);
            navigate(homeFor(u));
        } catch (err) {
            setError(errorMessage(err, 'No se pudo iniciar sesión.'));
        } finally {
            setBusy(false);
        }
    };

    const quick = async (mail) => {
        setBusy(true);
        try {
            const u = await demoLogin(mail);
            navigate(homeFor(u));
        } catch (err) {
            setError(errorMessage(err));
            setBusy(false);
        }
    };

    return (
        <div className="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
            {/* Panel institucional */}
            <div className="relative hidden overflow-hidden bg-navy-udh lg:flex lg:flex-col lg:justify-between lg:p-14">
                <div className="absolute inset-0 opacity-[0.14]" style={{ background: 'linear-gradient(135deg, transparent 0 58%, #2eb8a0 58% 100%)' }} />
                <div className="absolute -right-24 -bottom-24 size-[420px] rounded-full bg-teal-udh/20 blur-3xl" />
                <div className="relative flex items-center gap-4">
                    <img src="/img/escudo.png" alt="" className="h-16" />
                    <div>
                        <p className="text-lg font-extrabold text-white">Universidad de Huánuco</p>
                        <p className="text-sm text-sand-udh">Quaerens Veritatem</p>
                    </div>
                </div>
                <div className="relative max-w-lg">
                    <p className="text-xs font-bold tracking-[0.25em] text-teal-udh uppercase">Sistema de Gestión de Trámites</p>
                    <h1 className="mt-4 text-5xl leading-[1.08] font-extrabold tracking-tight text-white">Tus trámites académicos, en línea y con seguimiento en tiempo real.</h1>
                    <p className="mt-5 text-lg leading-relaxed text-white/70">Solicita constancias, certificados y resoluciones; adjunta tus requisitos y paga en línea. Cada área atiende desde su bandeja y tú ves cada paso.</p>
                    <div className="mt-8 grid grid-cols-3 gap-4">
                        {[['72+', 'trámites del TUPA'], ['15', 'carreras'], ['100%', 'trazabilidad']].map(([n, l]) => (
                            <div key={l} className="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p className="text-2xl font-extrabold text-gold-udh">{n}</p>
                                <p className="text-xs text-white/60">{l}</p>
                            </div>
                        ))}
                    </div>
                </div>
                <p className="relative text-xs text-white/40">© {new Date().getFullYear()} Universidad de Huánuco · Laboratorio de Transformación Digital</p>
            </div>

            {/* Formulario */}
            <div className="flex flex-col bg-bg">
                <div className="flex justify-end p-5">
                    <button onClick={toggle} className="grid size-11 place-items-center rounded-xl border border-line bg-surface text-ink">
                        {dark ? <Sun className="size-5" /> : <Moon className="size-5" />}
                    </button>
                </div>
                <div className="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 pb-12">
                    <img src="/img/escudo.png" alt="Escudo UDH" className="mb-6 h-20 self-start lg:hidden" />
                    <p className="kicker">Bienvenido</p>
                    <h2 className="mt-2 text-3xl font-extrabold tracking-tight text-ink">Iniciar sesión</h2>
                    <p className="mt-2 text-muted">Ingresa con tu correo institucional @udh.edu.pe</p>

                    <form onSubmit={submit} className="mt-8 space-y-4">
                        {error && <Callout tone="danger">{error}</Callout>}
                        <Field label="Correo institucional">
                            <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="codigo@udh.edu.pe" required autoFocus />
                        </Field>
                        <Field label="Contraseña">
                            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="••••••••" required />
                        </Field>
                        <Button type="submit" size="lg" className="w-full" loading={busy} icon={LogIn}>Ingresar</Button>
                    </form>

                    {demo?.enabled && (
                        <div className="mt-8 rounded-2xl border border-line bg-surface p-4">
                            <div className="mb-3 flex items-center gap-2">
                                <Sparkles className="size-4 text-gold-udh" />
                                <p className="text-sm font-bold text-ink">Acceso rápido para la demostración</p>
                            </div>
                            <p className="mb-3 text-xs text-muted">Contraseña de todas las cuentas: <code className="rounded bg-surface-2 px-1.5 py-0.5 font-bold text-ink">{demo.password}</code></p>
                            <div className="space-y-1.5">
                                {demo.groups.filter((g) => g.users.length).map((g) => (
                                    <div key={g.role} className="overflow-hidden rounded-xl border border-line">
                                        <button onClick={() => setOpenGroup(openGroup === g.role ? null : g.role)} className="flex w-full items-center justify-between px-3.5 py-2.5 text-left text-sm font-semibold text-ink hover:bg-surface-2">
                                            {g.label} <span className="flex items-center gap-2 text-xs text-muted">{g.users.length}<ChevronDown className={cx('size-4 transition', openGroup === g.role && 'rotate-180')} /></span>
                                        </button>
                                        {openGroup === g.role && (
                                            <div className="scrollbar-thin max-h-56 overflow-y-auto border-t border-line">
                                                {g.users.map((u) => (
                                                    <button key={u.email} disabled={busy} onClick={() => quick(u.email)} className="group flex w-full items-center gap-3 px-3.5 py-2.5 text-left hover:bg-teal-udh/5">
                                                        <ShieldCheck className="size-4 shrink-0 text-teal-udh" />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate text-sm font-semibold text-ink">{u.name}</span>
                                                            <span className="block truncate text-xs text-muted">{u.detail}</span>
                                                        </span>
                                                        <ArrowRight className="size-4 text-muted opacity-0 transition group-hover:opacity-100" />
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
