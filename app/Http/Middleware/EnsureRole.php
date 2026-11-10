<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Uso en rutas: ->middleware('role:ADMINISTRADOR') o 'role:staff' (todo el personal). */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        $ok = in_array($user->role, $roles, true)
            || (in_array('staff', $roles, true) && ! $user->isStudent());

        abort_unless($ok, 403, 'No tienes permiso para esta sección.');

        return $next($request);
    }
}
