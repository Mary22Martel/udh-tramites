import axios from 'axios';

// Cliente HTTP: usa la sesión de Laravel (cookies). Axios envía el token CSRF (cookie XSRF-TOKEN) automáticamente.
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
});

api.interceptors.response.use(
    (r) => r,
    (error) => {
        const status = error.response?.status;
        if (status === 401 && !window.location.pathname.startsWith('/login') && !error.config?.skipAuthRedirect) {
            window.location.href = '/login';
        }
        if (status === 419) {
            // Sesión expirada: recargar para obtener un nuevo token CSRF
            window.location.reload();
        }
        return Promise.reject(error);
    },
);

/** Mensaje de error legible desde una respuesta de Laravel. */
export function errorMessage(error, fallback = 'Ocurrió un error inesperado.') {
    const data = error?.response?.data;
    if (data?.errors) {
        // Muestra hasta 3 problemas a la vez, para corregir todo de una
        const all = Object.values(data.errors).flat().map(String);
        const shown = all.slice(0, 3).join(' · ');
        return all.length > 3 ? `${shown} · y ${all.length - 3} más.` : shown;
    }
    return data?.message || fallback;
}

/** Descarga un archivo de la API (respeta la sesión). */
export async function downloadFile(url, params = {}) {
    const res = await api.get(url, { params, responseType: 'blob' });
    const disposition = res.headers['content-disposition'] || '';
    const match = /filename="?([^"]+)"?/.exec(disposition);
    const name = match ? decodeURIComponent(match[1]) : 'archivo';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(res.data);
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 2000);
}

export default api;
