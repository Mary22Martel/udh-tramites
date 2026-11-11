<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Submission;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Cuentas de correo de las áreas, programas y facultades.
 *
 *  - Con cuenta conectada (correo + contraseña de aplicación): el correo sale DESDE esa cuenta
 *    y queda guardado en sus "Enviados" de Gmail.
 *  - Sin cuenta conectada: sale con la cuenta general del .env (MAIL_USERNAME) con el nombre del área,
 *    y al área le llega una copia oculta para que tenga registro.
 */
class MailAccounts
{
    public const TYPES = ['area' => Area::class, 'program' => Program::class, 'faculty' => Faculty::class];

    public function find(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::findOrFail($id);
    }

    /** Quién envía los documentos del paso actual de una solicitud. */
    public function senderFor(?string $area, Submission $s): ?Model
    {
        $s->loadMissing(['program', 'faculty']);

        return match ($area) {
            'PROGRAMA_ACADEMICO' => $s->program,
            'FACULTAD' => $s->faculty,
            null, '', 'ESTUDIANTE', 'SISTEMA' => null,
            default => Area::where('code', $area)->first(),
        };
    }

    /** Mailer SMTP que entra con la cuenta propia de la entidad (mismo servidor que el .env). */
    public function mailerFor(Model $sender): ?Mailer
    {
        if (! $sender->hasMailAccount() || ! ($secret = $sender->mailSecret())) {
            return null;
        }
        $base = config('mail.mailers.smtp');

        return Mail::build([
            'transport' => 'smtp',
            'scheme' => $base['scheme'] ?? null,
            'host' => $base['host'] ?? 'smtp.gmail.com',
            'port' => $base['port'] ?? 587,
            'username' => $sender->mailAddress(),
            'password' => $secret,
            'timeout' => 20,
        ]);
    }

    public function present(Model $m, string $type): array
    {
        return [
            'type' => $type,
            'id' => $m->id,
            'name' => $m->name,
            'code' => $m->code,
            'group' => match ($type) { 'area' => 'Áreas', 'faculty' => 'Facultades', default => 'Programas' },
            'parent' => $type === 'program' ? $m->faculty?->name : null,
            'email' => $m->mailAddress(),
            'connected' => $m->hasMailAccount(),
            'verified_at' => $m->mail_verified_at ? \Illuminate\Support\Carbon::parse($m->mail_verified_at)->toIso8601String() : null,
        ];
    }

    public function all(): array
    {
        return [
            ...Area::orderByDesc('is_system')->orderBy('name')->get()->map(fn ($a) => $this->present($a, 'area')),
            ...Faculty::orderBy('name')->get()->map(fn ($f) => $this->present($f, 'faculty')),
            ...Program::with('faculty:id,name')->orderBy('name')->get()->map(fn ($p) => $this->present($p, 'program')),
        ];
    }
}
