@php
    $s = $submission;
    $kb = (int) round(($document->size_bytes ?? 0) / 1024);
    $size = $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : max(1, $kb).' KB';
    $ext = strtoupper(pathinfo($document->name, PATHINFO_EXTENSION) ?: 'ARCHIVO');
@endphp
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;background:#f3f6f8;font-family:Arial,Helvetica,sans-serif;color:#0f2440">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0f2440;padding:22px 28px;color:#ffffff">
        <div style="font-size:12px;letter-spacing:2px;color:#2eb8a0;font-weight:bold">UNIVERSIDAD DE HUÁNUCO · {{ mb_strtoupper(str_replace(' · UDH', '', $areaName)) }}</div>
        <div style="font-size:21px;font-weight:bold;margin-top:6px">{{ $isFinal ? 'Tu documento está listo' : 'Nuevo documento en tu trámite' }}</div>
    </td></tr>
    <tr><td style="padding:26px 28px 8px;font-size:15px;line-height:1.6">
        <p style="margin:0 0 12px">Hola, <b>{{ $s->student?->name }}</b>:</p>
        <p style="margin:0">
            Te enviamos el documento de tu trámite <b>{{ $s->procedure_name }}</b>.
            @if($isFinal) Con esto tu trámite quedó <b style="color:#1f8f7c">finalizado</b>. @endif
        </p>
    </td></tr>

    {{-- Lo principal: el archivo adjunto --}}
    <tr><td style="padding:16px 28px">
        <table cellpadding="0" cellspacing="0" style="width:100%;border:2px solid #2eb8a0;border-radius:12px;background:#f0fbf8">
            <tr>
                <td style="padding:16px 0 16px 16px;width:56px;vertical-align:middle">
                    <div style="width:48px;height:56px;border-radius:8px;background:#0f2440;color:#ffffff;font-size:11px;font-weight:bold;text-align:center;line-height:56px">{{ $ext }}</div>
                </td>
                <td style="padding:16px;vertical-align:middle">
                    <div style="font-size:12px;color:#1f8f7c;font-weight:bold;letter-spacing:1px">📎 DOCUMENTO ADJUNTO</div>
                    <div style="font-size:15px;font-weight:bold;word-break:break-all;margin-top:2px">{{ $document->name }}</div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px">{{ $ext }} · {{ $size }}</div>
                </td>
            </tr>
        </table>
        <p style="margin:10px 0 0;font-size:13px;color:#475569;text-align:center">
            <b>Descárgalo desde el adjunto al final de este correo</b> (en Gmail, pasa el mouse sobre el archivo y pulsa la flecha ⬇).
        </p>
    </td></tr>

    <tr><td style="padding:8px 28px 24px">
        <table cellpadding="0" cellspacing="0" style="width:100%;background:#f3f6f8;border-radius:10px;font-size:13px">
            <tr><td style="padding:9px 14px;color:#64748b">Expediente</td><td style="padding:9px 14px;font-weight:bold;text-align:right">{{ $s->code }}</td></tr>
            <tr><td style="padding:9px 14px;color:#64748b">Trámite</td><td style="padding:9px 14px;font-weight:bold;text-align:right">{{ $s->procedure_name }}</td></tr>
            <tr><td style="padding:9px 14px;color:#64748b">Enviado por</td><td style="padding:9px 14px;font-weight:bold;text-align:right">{{ str_replace(' · UDH', '', $areaName) }}</td></tr>
            <tr><td style="padding:9px 14px;color:#64748b">Fecha</td><td style="padding:9px 14px;font-weight:bold;text-align:right">{{ now()->format('d/m/Y H:i') }}</td></tr>
        </table>
        <p style="margin:16px 0 0;font-size:13px;color:#64748b;text-align:center">
            También lo tienes guardado en el sistema:
            <a href="{{ rtrim(config('app.url'), '/') }}/mis-tramites?ver={{ $s->id }}" style="color:#1f8f7c;font-weight:bold">ver mi trámite</a>
        </p>
    </td></tr>
    <tr><td style="padding:14px 28px;background:#f8fafc;color:#94a3b8;font-size:12px">
        Sistema de Trámites UDH. @if($areaEmail) Si tienes dudas, responde a este correo y le llegará a {{ $areaEmail }}. @endif
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
