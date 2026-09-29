<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDepuradorAccess
{
    /**
     * Rutas (por nombre) a las que el rol Depurador sí puede acceder.
     * El módulo de Logs (/logs) permanece accesible para todos los
     * usuarios; cualquier otra ruta redirige al Depurador a logs.index
     * (peticiones JSON reciben 403).
     *
     * @var list<string>
     */
    protected array $allowedRoutes = [
        // Módulo de Logs: único módulo permitido para el Depurador.
        'logs.index',
        'logs.show',
        // Sesión / autenticación Fortify.
        'logout',
        'login',
        'login.store',
        'two-factor.login',
        'two-factor.login.store',
        // Verificación y recuperación de contraseña (evitar bloqueos).
        'verification.notice',
        'verification.verify',
        'verification.send',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'password.confirm',
        'password.confirm.store',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('Depurador')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (in_array($routeName, $this->allowedRoutes, true)) {
            return $next($request);
        }

        // Respaldo por path para rutas sin nombre.
        if ($request->is(
            'logs', 'logs/*',
            'logout', 'login',
            'two-factor-challenge', 'two-factor-challenge/*',
            'verify-email*', 'email/*',
            'forgot-password', 'reset-password*', 'user/confirm-password'
        )) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Tu usuario solo tiene acceso al módulo de Logs.',
            ], 403);
        }

        return redirect()->route('logs.index');
    }
}
