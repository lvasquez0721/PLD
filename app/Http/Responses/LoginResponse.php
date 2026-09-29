<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * El usuario con rol Depurador aterriza directamente en el módulo
     * de Logs; el resto mantiene el comportamiento por defecto.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user();

        if ($user && $user->hasRole('Depurador')) {
            return redirect()->intended('/logs');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
