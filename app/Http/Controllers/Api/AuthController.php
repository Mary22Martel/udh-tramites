<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Presenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }

        $request->session()->regenerate();

        return ['user' => Presenter::user($request->user(), true)];
    }

    /** Acceso rápido para la presentación (solo si DEMO_MODE=true en .env). */
    public function demoLogin(Request $request)
    {
        abort_unless(config('app.demo_mode'), 404);
        $user = User::where('email', $request->input('email'))->where('is_active', true)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return ['user' => Presenter::user($user, true)];
    }

    public function demoAccounts()
    {
        if (! config('app.demo_mode')) {
            return ['enabled' => false, 'groups' => []];
        }

        $pick = fn ($q) => $q->orderBy('id')->get()->map(fn ($u) => [
            'email' => $u->email,
            'name' => $u->name,
            'detail' => $u->job_title ?: ($u->program?->name ?? $u->role_label),
        ]);

        return [
            'enabled' => true,
            'password' => \App\Services\ImportService::DEFAULT_PASSWORD,
            'groups' => [
                ['role' => 'ADMINISTRADOR', 'label' => 'Administración', 'users' => $pick(User::where('role', 'ADMINISTRADOR'))],
                ['role' => 'ESTUDIANTE', 'label' => 'Estudiantes', 'users' => $pick(User::with('program')->where('role', 'ESTUDIANTE')->whereIn('email', [
                    '2021104829@udh.edu.pe', '2020201944@udh.edu.pe', '2022100871@udh.edu.pe', '2020210319@udh.edu.pe', '2021102941@udh.edu.pe',
                ]))],
                ['role' => 'AREAS', 'label' => 'Tesorería y Matrícula', 'users' => $pick(User::whereIn('role', ['TESORERIA', 'MATRICULA']))],
                ['role' => 'PROGRAMA_ACADEMICO', 'label' => 'Directores de programa', 'users' => $pick(User::with('program')->where('role', 'PROGRAMA_ACADEMICO'))],
                ['role' => 'FACULTAD', 'label' => 'Decanatos', 'users' => $pick(User::where('role', 'FACULTAD'))],
                ['role' => 'AREA_CENTRAL', 'label' => 'Otras áreas', 'users' => $pick(User::where('role', 'AREA_CENTRAL'))],
            ],
        ];
    }

    public function me(Request $request)
    {
        return ['user' => Presenter::user($request->user(), true)];
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);
        $user->phone = $data['phone'] ?? $user->phone;
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return ['user' => Presenter::user($user, true)];
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['ok' => true];
    }
}
