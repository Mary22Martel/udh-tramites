/**
 * Prepara un archivo antes de subirlo (sobre todo desde el celular):
 *  - Reconoce el tipo aunque el nombre no tenga extensión (fotos de la galería en Android).
 *  - Si es una foto más pesada que el límite, la reduce automáticamente (las fotos del celular pesan 3–6 MB).
 *  - Si no cumple el formato o el tamaño, devuelve un mensaje claro.
 */
const MIME_EXT = { 'image/jpeg': '.jpg', 'image/jpg': '.jpg', 'image/png': '.png', 'application/pdf': '.pdf', 'image/heic': '.heic', 'image/heif': '.heic', 'image/webp': '.webp',
    'application/msword': '.doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx' };
const EXT_MIME = { '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.pdf': 'application/pdf', '.doc': 'application/msword',
    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' };

/** Valor para el atributo accept: extensiones + tipos MIME (los celulares filtran mejor con MIME). */
export function acceptFor(types = []) {
    const list = types.flatMap((t) => [t, EXT_MIME[t]]).filter(Boolean);
    if (types.includes('.jpg')) list.push('.jpeg');
    return [...new Set(list)].join(',');
}

export function extOf(file) {
    const m = /\.([a-z0-9]+)$/i.exec(file.name || '');
    const ext = m ? `.${m[1].toLowerCase()}` : MIME_EXT[file.type] || '';
    return ext === '.jpeg' ? '.jpg' : ext;
}

function loadImage(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('No se pudo leer la imagen.')); };
        img.src = url;
    });
}

/** Reduce una foto a máximo 1600 px y la guarda como JPG (o PNG si solo se permite PNG). */
async function compressImage(file, maxBytes, asPng) {
    const img = await loadImage(file);
    for (const side of [1600, 1280, 1024, 800]) {
        const scale = Math.min(1, side / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        if (!asPng) { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height); }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        for (const q of asPng ? [1] : [0.85, 0.7, 0.55]) {
            const blob = await new Promise((r) => canvas.toBlob(r, asPng ? 'image/png' : 'image/jpeg', q));
            if (blob && blob.size <= maxBytes) {
                const base = (file.name || 'foto').replace(/\.[^.]+$/, '') || 'foto';
                return new File([blob], `${base}${asPng ? '.png' : '.jpg'}`, { type: blob.type, lastModified: Date.now() });
            }
        }
    }
    return null;
}

/**
 * @returns {Promise<{file?: File, error?: string, note?: string}>}
 */
export async function prepareUpload(file, { types = [], maxMb = 10, label = 'El archivo' } = {}) {
    if (!file) return {};
    const allowed = types.map((t) => (t === '.jpeg' ? '.jpg' : t.toLowerCase()));
    let ext = extOf(file);
    const isImage = (file.type || '').startsWith('image/') || ['.jpg', '.png', '.heic', '.webp'].includes(ext);
    const maxBytes = maxMb * 1024 * 1024;
    const wantsImage = allowed.some((t) => ['.jpg', '.png'].includes(t));

    // Fotos: si el formato no está permitido (p. ej. HEIC de iPhone) o pesa demasiado, se convierte y reduce
    if (isImage && wantsImage && (!allowed.includes(ext) || file.size > maxBytes)) {
        try {
            const out = await compressImage(file, maxBytes, !allowed.includes('.jpg'));
            if (out) return { file: out, note: file.size > maxBytes ? `La foto pesaba ${(file.size / 1048576).toFixed(1)} MB; la reducimos a ${(out.size / 1048576).toFixed(1)} MB.` : undefined };
        } catch {
            // el navegador no pudo leer la foto (p. ej. HEIC en un navegador sin soporte): sigue la validación normal
        }
        ext = extOf(file);
    }

    if (allowed.length && !allowed.includes(ext)) {
        return { error: `${label}: formato no permitido. Sube un archivo ${allowed.join(', ')}.` };
    }
    if (file.size > maxBytes) {
        return { error: `${label}: pesa ${(file.size / 1048576).toFixed(1)} MB y el máximo es ${maxMb} MB.` };
    }
    // Asegura que el nombre tenga extensión (el servidor la revisa)
    if (!/\.[a-z0-9]+$/i.test(file.name || '') && ext) {
        return { file: new File([file], `${file.name || 'archivo'}${ext}`, { type: file.type, lastModified: file.lastModified }) };
    }
    return { file };
}
