<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MailAccounts;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;

/** Administración → Organización → Correos: conectar el correo de cada área, programa y facultad. */
class MailAccountController extends Controller
{
    public function __construct(protected MailAccounts $accounts) {}

    public function index()
    {
        return [
            'data' => $this->accounts->all(),
            'server' => [
                'host' => config('mail.mailers.smtp.host'),
                'general' => config('mail.mailers.smtp.username'),
                'ready' => config('mail.default') === 'smtp',
            ],
        ];
    }

    public function update(Request $request, string $type, int $id)
    {
        $m = $this->accounts->find($type, $id);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['nullable', 'string', 'max:100'],
            'disconnect' => ['nullable', 'boolean'],
        ], ['email.required' => 'Ingresa el correo.', 'email.email' => 'El correo no es válido.']);

        $m->{$m->mailAddressField()} = $data['email'];
        if (! empty($data['disconnect'])) {
            $m->setMailSecret(null);
        } elseif (! empty($data['password'])) {
            $m->setMailSecret($data['password']);
        }
        $m->save();

        return ['data' => $this->accounts->present($m->fresh(), $type)];
    }

    /** Envía un correo de prueba desde la cuenta de la entidad a sí misma. */
    public function test(string $type, int $id)
    {
        $m = $this->accounts->find($type, $id);
        abort_unless($m->hasMailAccount(), 422, 'Primero guarda el correo y su contraseña de aplicación.');

        try {
            $this->accounts->mailerFor($m)->raw(
                "Hola:\n\nEste es un correo de prueba del Sistema de Trámites UDH.\n\nSi lo recibiste, el correo de «{$m->name}» quedó conectado: los documentos que suban se enviarán a los estudiantes desde esta cuenta y los verás en tus «Enviados».",
                fn (Message $msg) => $msg->from($m->mailAddress(), $m->name.' · UDH')->to($m->mailAddress())->subject('Prueba de correo · Sistema de Trámites UDH'),
            );
        } catch (\Throwable $e) {
            $reason = str_contains($e->getMessage(), '535') || str_contains($e->getMessage(), 'Username and Password')
                ? 'Google rechazó el correo o la contraseña de aplicación. Revisa que la clave de 16 letras sea de esta misma cuenta.'
                : $e->getMessage();
            abort(422, 'No se pudo enviar: '.$reason);
        }

        $m->forceFill(['mail_verified_at' => now()])->save();

        return ['ok' => true, 'message' => "Listo. Revisa la bandeja de {$m->mailAddress()}.", 'data' => $this->accounts->present($m->fresh(), $type)];
    }
}
