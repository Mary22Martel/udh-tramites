import { useState } from 'react';
import { KeyRound, Save } from 'lucide-react';
import api, { errorMessage } from '../lib/api';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { Button, Card, Field, Input, PageHeader } from '../components/ui';

export default function Profile() {
    const { user, refresh } = useAuth();
    const toast = useToast();
    const [form, setForm] = useState({ phone: user.phone || '', current_password: '', password: '', password_confirmation: '' });
    const [busy, setBusy] = useState(false);
    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const save = async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
            await api.put('/me', form);
            toast.success('Perfil actualizado.');
            setForm({ ...form, current_password: '', password: '', password_confirmation: '' });
            refresh();
        } catch (err) {
            toast.error(errorMessage(err));
        } finally {
            setBusy(false);
        }
    };

    const rows = [
        ['Nombre', user.name], ['Correo', user.email], ['Rol', user.role_label],
        user.student_code && ['Código', user.student_code], user.program && ['Programa', user.program.name],
        user.faculty && ['Facultad', user.faculty.name], user.current_semester && ['Ciclo', user.current_semester],
        user.area && ['Área', user.area.name], user.job_title && ['Cargo', user.job_title],
    ].filter(Boolean);

    return (
        <div className="mx-auto max-w-4xl">
            <PageHeader kicker="Cuenta" title="Mi perfil" />
            <div className="grid gap-5 md:grid-cols-[1fr_1.1fr]">
                <Card className="p-6">
                    <div className="mb-5 grid size-16 place-items-center rounded-2xl bg-teal-udh/12 text-xl font-extrabold text-teal-udh-600 dark:text-teal-300">{user.initials}</div>
                    <dl className="space-y-3">
                        {rows.map(([k, v]) => (
                            <div key={k}><dt className="text-xs font-bold tracking-wider text-muted uppercase">{k}</dt><dd className="font-semibold text-ink">{v}</dd></div>
                        ))}
                    </dl>
                </Card>
                <Card className="p-6">
                    <form onSubmit={save} className="space-y-4">
                        <Field label="Teléfono"><Input value={form.phone} onChange={set('phone')} /></Field>
                        <p className="flex items-center gap-2 pt-2 text-sm font-bold text-ink"><KeyRound className="size-4 text-accent" /> Cambiar contraseña</p>
                        <Field label="Contraseña actual"><Input type="password" value={form.current_password} onChange={set('current_password')} /></Field>
                        <Field label="Nueva contraseña"><Input type="password" value={form.password} onChange={set('password')} /></Field>
                        <Field label="Repetir nueva contraseña"><Input type="password" value={form.password_confirmation} onChange={set('password_confirmation')} /></Field>
                        <Button type="submit" icon={Save} loading={busy}>Guardar cambios</Button>
                    </form>
                </Card>
            </div>
        </div>
    );
}
