<?php

namespace App\Http\Middleware;

use App\Models\LogApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        try {
            $response = $next($request);

            $this->registrarLog($request, $start, $response->getStatusCode());

            return $response;
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            $this->registrarLog($request, $start, $status);

            throw $e;
        }
    }

    private function registrarLog(Request $request, float $start, int $estatus): void
    {
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        try {
            $user = $request->user('sanctum') ?? $request->user() ?? null;

            LogApi::create([
                'IDUsuario' => $user?->id,
                'Usuario' => $user?->usuario ?? $user?->email ?? $user?->name,
                'Metodo' => $request->method(),
                'Ruta' => $request->path(),
                'URL' => $request->fullUrl(),
                'IP' => $request->ip(),
                'UserAgent' => mb_substr((string) $request->header('User-Agent'), 0, 4000),
                'Estatus' => $estatus,
                'DuracionMs' => $durationMs,
                'Payload' => $this->payloadSanitizado($request),
            ]);
        } catch (\Throwable $e) {
            // El logeo nunca debe romper la respuesta de la petición.
            \Log::warning('[LogApiRequests] No se pudo registrar el log de la petición.', [
                'error' => $e->getMessage(),
                'ruta' => $request->path(),
            ]);
        }
    }

    private function payloadSanitizado(Request $request): ?string
    {
        $camposSensibles = ['password', 'password_confirmation', 'contraseña', 'clave', 'token', 'api_token', 'access_token'];

        $payload = $request->except(['_token']);
        $payload = collect($payload)->mapWithKeys(function ($value, $key) use ($camposSensibles) {
            if (in_array(strtolower((string) $key), $camposSensibles)) {
                return [$key => '***'];
            }

            return [$key => $value];
        })->toArray();

        if (empty($payload)) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Evitar guardar payloads demasiado grandes.
        if (strlen((string) $json) > 20000) {
            return substr((string) $json, 0, 20000).'... [TRUNCADO]';
        }

        return $json;
    }
}
