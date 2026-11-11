<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Crypt;

/**
 * Cuenta de correo propia (área, programa o facultad).
 * La contraseña de aplicación se guarda cifrada con la APP_KEY y nunca se envía al navegador.
 */
trait HasMailAccount
{
    /** Campo donde está el correo de la entidad. */
    public function mailAddressField(): string
    {
        return match (static::class) {
            \App\Models\Program::class => 'director_email',
            \App\Models\Faculty::class => 'dean_email',
            default => 'email',
        };
    }

    public function mailAddress(): ?string
    {
        return $this->{$this->mailAddressField()} ?: null;
    }

    public function mailSecret(): ?string
    {
        if (! $this->mail_password) {
            return null;
        }
        try {
            return Crypt::decryptString($this->mail_password);
        } catch (\Throwable) {
            return null; // se cambió la APP_KEY: hay que volver a conectar el correo
        }
    }

    public function setMailSecret(?string $secret): void
    {
        $this->mail_password = $secret ? Crypt::encryptString(preg_replace('/\s+/', '', $secret)) : null;
        $this->mail_verified_at = null;
    }

    public function hasMailAccount(): bool
    {
        return (bool) ($this->mailAddress() && $this->mail_password);
    }
}
