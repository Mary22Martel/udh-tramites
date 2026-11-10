import { useSearchParams } from 'react-router-dom';

/** Abre el detalle de una solicitud usando ?ver=ID en la URL (permite enlazar desde notificaciones). */
export default function useDetailParam() {
    const [params, setParams] = useSearchParams();
    const id = params.get('ver');
    const open = (sid) => { params.set('ver', sid); setParams(params, { replace: false }); };
    const close = () => { params.delete('ver'); setParams(params, { replace: true }); };
    return [id, open, close];
}
