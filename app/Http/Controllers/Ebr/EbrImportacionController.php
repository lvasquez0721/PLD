<?php

namespace App\Http\Controllers\Ebr;

use App\Http\Controllers\Controller;
use App\Models\Ebr\EbrEjercicio;
use App\Models\Ebr\EbrImportacion;
use App\Services\Ebr\ImportadorEbr;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EbrImportacionController extends Controller
{
    public function plantilla(string $tipo, ImportadorEbr $importador): StreamedResponse
    {
        abort_unless(in_array($tipo, ['clientes', 'siniestros', 'alertas'], true), 404);

        $plantilla = $importador->plantilla($tipo);
        $nombre = "plantilla_ebr_{$tipo}.csv";

        return response()->streamDownload(function () use ($plantilla) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $plantilla['headers'] ?? []);
            $ejemplos = $plantilla['ejemplos'] ?? (empty($plantilla['ejemplo']) ? [] : [$plantilla['ejemplo']]);
            foreach ($ejemplos as $ejemplo) {
                fputcsv($file, $ejemplo);
            }
            fclose($file);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function prevalidar(Request $request, EbrEjercicio $ejercicio, string $tipo, ImportadorEbr $importador)
    {
        $this->autorizarEdicion();
        abort_unless(in_array($tipo, ['clientes', 'siniestros', 'alertas'], true), 404);

        if ($ejercicio->estado === 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'El ejercicio está cerrado. Reábrelo para importar.']);
        }

        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
        $postMax = $this->bytes(ini_get('post_max_size') ?: '0');
        if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
            return back()->withErrors([
                'archivo' => 'El archivo excede el límite de carga del servidor (post_max_size = '.ini_get('post_max_size').').',
            ])->withInput();
        }

        $archivo = $request->file('archivo');
        if ($archivo instanceof UploadedFile && ! $archivo->isValid()) {
            $limite = ini_get('upload_max_filesize') ?: '2M';
            $mensaje = match ($archivo->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "El archivo excede el límite de carga del servidor ({$limite}). Reduce el archivo o solicita que se aumente upload_max_filesize/post_max_size.",
                UPLOAD_ERR_PARTIAL => 'La carga del archivo se interrumpió. Intenta subirlo de nuevo.',
                UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene configurado un directorio temporal para las cargas.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo temporal. Revisa permisos y espacio en disco.',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga del archivo.',
                default => 'No fue posible subir el archivo. Revisa el tamaño y vuelve a intentarlo.',
            };

            return back()->withErrors(['archivo' => $mensaje])->withInput();
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ], [
            'archivo.required' => 'Selecciona un archivo CSV.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.uploaded' => 'No fue posible subir el archivo. Verifica el tamaño permitido por el servidor.',
            'archivo.mimes' => 'El archivo debe ser CSV o TXT.',
            'archivo.max' => 'El archivo no debe superar los 20 MB.',
        ]);

        $importacion = $importador->prevalidar($ejercicio, $tipo, $archivo, $request->user()?->id);

        return redirect()->route('ebr.show', $ejercicio)->with([
            'importacion_reciente' => [
                'id' => $importacion->id,
                'tipo' => $importacion->tipo,
                'estado' => $importacion->estado,
                'total_filas' => $importacion->total_filas,
                'filas_validas' => $importacion->filas_validas,
                'filas_error' => $importacion->filas_error,
                'resumen' => $importacion->resumen,
                'nombre_archivo' => $importacion->nombre_archivo,
            ],
            'errores_importacion' => $importacion->errores ?? [],
            'toast' => [
                'type' => $importacion->estado === 'error' ? 'error' : 'success',
                'message' => $importacion->estado === 'error'
                    ? "La prevalidación encontró {$importacion->filas_error} error(es). Corrige el CSV y vuelve a cargarlo."
                    : "Archivo prevalidado: {$importacion->filas_validas} fila(s) válida(s). Confirma la importación.",
            ],
        ]);
    }

    public function confirmar(EbrEjercicio $ejercicio, EbrImportacion $importacion, ImportadorEbr $importador)
    {
        $this->autorizarEdicion();

        abort_unless($importacion->ejercicio_id === $ejercicio->id, 404);

        if ($ejercicio->estado === 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'El ejercicio está cerrado. Reábrelo para importar.']);
        }

        try {
            $importacion = $importador->importar($ejercicio, $importacion);
        } catch (QueryException $e) {
            report($e);

            $mensaje = 'No fue posible importar el archivo. Revisa las advertencias: hay valores fuera de rango.';
            if (config('app.debug')) {
                $mensaje .= ' '.$e->getMessage();
            }

            return back()->with('toast', [
                'type' => 'error',
                'message' => $mensaje,
            ]);
        }

        $ok = $importacion->estado === 'importado';

        return redirect()->route('ebr.show', $ejercicio)->with([
            'importacion_reciente' => [
                'id' => $importacion->id,
                'tipo' => $importacion->tipo,
                'estado' => $importacion->estado,
                'total_filas' => $importacion->total_filas,
                'filas_validas' => $importacion->filas_validas,
                'filas_error' => $importacion->filas_error,
                'resumen' => $importacion->resumen,
                'nombre_archivo' => $importacion->nombre_archivo,
            ],
            'errores_importacion' => $importacion->errores ?? [],
            'toast' => [
                'type' => $ok ? 'success' : 'error',
                'message' => $ok ? 'Archivo importado correctamente. Ya puedes calcular el EBR.' : 'No fue posible importar el archivo. Revisa los errores.',
            ],
        ]);
    }

    public function errores(EbrEjercicio $ejercicio, EbrImportacion $importacion): StreamedResponse
    {
        abort_unless($importacion->ejercicio_id === $ejercicio->id, 404);

        $errores = $importacion->errores ?? [];

        return response()->streamDownload(function () use ($errores) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['linea', 'campo', 'valor', 'mensaje']);
            foreach ($errores as $error) {
                fputcsv($file, [
                    $error['linea'] ?? '',
                    $error['campo'] ?? '',
                    $error['valor'] ?? '',
                    $error['mensaje'] ?? '',
                ]);
            }
            fclose($file);
        }, "errores_ebr_{$ejercicio->anio}_{$importacion->tipo}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1') {
            return 0;
        }

        $unidad = strtolower(substr($valor, -1));
        $numero = (float) $valor;

        return (int) match ($unidad) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    private function autorizarEdicion(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['Administrador', 'Oficial de cumplimiento']), 403, 'No tienes permiso para importar insumos del EBR.');
    }
}
