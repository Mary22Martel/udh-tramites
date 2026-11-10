import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import api from '../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);

    const loadMeta = useCallback(async () => {
        const { data } = await api.get('/meta');
        setMeta(data);
    }, []);

    const refresh = useCallback(async () => {
        try {
            const { data } = await api.get('/me', { skipAuthRedirect: true });
            setUser(data.user);
            if (!meta) loadMeta();
            return data.user;
        } catch {
            setUser(null);
            return null;
        }
    }, [meta, loadMeta]);

    useEffect(() => {
        refresh().finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Actualiza contadores (bandeja, notificaciones) cada 30 s
    useEffect(() => {
        if (!user) return;
        const t = setInterval(refresh, 30000);
        return () => clearInterval(t);
    }, [user, refresh]);

    const login = async (email, password, remember = true) => {
        const { data } = await api.post('/login', { email, password, remember });
        setUser(data.user);
        loadMeta();
        return data.user;
    };

    const demoLogin = async (email) => {
        const { data } = await api.post('/demo-login', { email });
        setUser(data.user);
        loadMeta();
        return data.user;
    };

    const logout = async () => {
        await api.post('/logout').catch(() => {});
        setUser(null);
        window.location.href = '/login';
    };

    return (
        <AuthContext.Provider value={{ user, meta, loading, login, demoLogin, logout, refresh, loadMeta }}>
            {children}
        </AuthContext.Provider>
    );
}

export const useAuth = () => useContext(AuthContext);

/** Ruta de inicio según el rol del usuario. */
export function homeFor(user) {
    if (!user) return '/login';
    if (user.role === 'ADMINISTRADOR') return '/admin';
    if (user.role === 'ESTUDIANTE') return '/inicio';
    return '/bandeja';
}
