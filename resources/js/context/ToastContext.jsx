import { createContext, useCallback, useContext, useState } from 'react';
import { AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';
import { cx } from '../lib/format';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
    const [items, setItems] = useState([]);
    const remove = (id) => setItems((xs) => xs.filter((x) => x.id !== id));
    const push = useCallback((message, type = 'success') => {
        const id = Math.random();
        setItems((xs) => [...xs, { id, message, type }]);
        setTimeout(() => remove(id), 4500);
    }, []);
    const toast = { success: (m) => push(m, 'success'), error: (m) => push(m, 'error'), info: (m) => push(m, 'info') };

    return (
        <ToastContext.Provider value={toast}>
            {children}
            <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-[min(380px,calc(100vw-2rem))] flex-col gap-2">
                {items.map((t) => {
                    const Icon = { success: CheckCircle2, error: AlertTriangle, info: Info }[t.type];
                    return (
                        <div key={t.id} className={cx('pointer-events-auto flex items-start gap-3 rounded-xl border bg-surface px-4 py-3 text-sm shadow-xl',
                            t.type === 'error' ? 'border-red-300 dark:border-red-500/40' : 'border-line')}>
                            <Icon className={cx('mt-0.5 size-[18px] shrink-0', t.type === 'error' ? 'text-red-600' : 'text-teal-udh')} />
                            <p className="flex-1 text-ink">{t.message}</p>
                            <button onClick={() => remove(t.id)} className="text-muted hover:text-ink"><X className="size-4" /></button>
                        </div>
                    );
                })}
            </div>
        </ToastContext.Provider>
    );
}

export const useToast = () => useContext(ToastContext);
