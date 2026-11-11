@php($s = $submission)
<!DOCTYPE html>
<html lang="es">
<body style="margin:0;background:#f3f6f8;font-family:Arial,Helvetica,sans-serif;color:#0f2440">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0f2440;padding:22px 28px;color:#ffffff">
        <div style="font-size:12px;letter-spacing:2px;color:#2eb8a0;font-weight:bold">UNIVERSIDAD DE HUÁNUCO</div>
        <div style="font-size:20px;font-weight:bold;margin-top:6px">{{ $isFinal ? 'Tu documento está listo' : 'Nuevo documento en tu trámite' }}</div>
    </td></tr>
    <tr><td style="padding:26px 28px;font-size:15px;line-height:1.6">
        <p style="margin:0 0 14px">Hola, <b>{{ $s->student?->name }}</b>:</p>
        <p style="margin:0 0 14px">
            {{ $areaName }} subió el documento <b>{{ $document->name }}</b> a tu trámite
            <b>{{ $s->procedure_name }}</b> (expediente {{ $s->code }}).
            @if($isFinal) Con esto tu trámite quedó <b style="color:#1f8f7c">finalizado</b>. @endif
        </p>
        <p style="margin:0 0 18px">Lo encuentras <b>adjunto en este correo</b> y también en el sistema de trámites, en «Mis trámites».</p>
        <table cellpadding="0" cellspacing="0" style="width:100%;background:#f3f6f8;border-radius:10px;font-size:13px">
            <tr><td style="padding:10px 14px;color:#64748b">Expediente</td><td style="padding:10px 14px;font-weight:bold;text-align:right">{{ $s->code }}</td></tr>
            <tr><td style="padding:10px 14px;color:#64748b">Trámite</td><td style="padding:10px 14px;font-weight:bold;text-align:right">{{ $s->procedure_name }}</td></tr>
            <tr><td style="padding:10px 14px;color:#64748b">Enviado por</td><td style="padding:10px 14px;font-weight:bold;text-align:right">{{ $areaName }}</td></tr>
        </table>
        <p style="margin:22px 0 0;text-align:center">
            <a href="{{ rtrim(config('app.url'), '/') }}/mis-tramites?ver={{ $s->id }}" style="display:inline-block;background:#2eb8a0;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:10px">Ver mi trámite</a>
        </p>
    </td></tr>
    <tr><td style="padding:16px 28px;background:#f8fafc;color:#94a3b8;font-size:12px">
        Correo automático del Sistema de Trámites UDH. @if($areaEmail) Si tienes dudas, responde a este correo y le llegará a {{ $areaEmail }}. @endif
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
