<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames()->all() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',

            /* ===== ESTO TE FALTABA ===== */
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
            /* ========================== */

            // Etiqueta global "Entorno de desarrollo" (switch persistido en BD).
            'envBadge' => [
                'activo' => $this->entornoDesarrolloActivo(),
            ],
        ];
    }

    /**
     * Lee el switch de la etiqueta desde catParametriaPLD con caché corta.
     * Nunca debe romper el render si la tabla aún no existe.
     */
    private function entornoDesarrolloActivo(): bool
    {
        try {
            if (! Schema::hasTable('catParametriaPLD')) {
                return false;
            }

            return Cache::remember('entorno_desarrollo_activo', 60, function () {
                return \App\Models\CatParametriaPLD::getEntornoDesarrolloActivo();
            });
        } catch (\Throwable) {
            return false;
        }
    }
}
