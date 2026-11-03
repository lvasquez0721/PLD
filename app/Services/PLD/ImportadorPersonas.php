<?php

namespace App\Services\PLD;

use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\PersonaImportacion;
use App\Models\Clientes\TbClientes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ImportadorPersonas
{
    private const MAX_FILAS = 100;

    /** @var array<string, array<string, mixed>> */
    private array $config;

    private bool $catalogosCargados = false;

    /** @var array<string, string> */
    private array $nacionalidadesPorId = [];

    /** @var array<string, string> */
    private array $nacionalidadesPorNombre = [];

    /** @var array<string, string> */
    private array $estadosPorId = [];

    /** @var array<string, string> */
    private array $estadosPorNombre = [];

    /** @var array<string, array{id:int, cve:string, nombre:string}> */
    private array $ocupaciones = [];

    /** @var array<string, int> */
    private array $ocupacionesPorNombre = [];

    public function __construct()
    {
        $this->config = config('personas_importacion', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function plantilla(string $tipoPersona): array
    {
        $this->validarTipoPersona($tipoPersona);

        return $this->config[$tipoPersona] ?? [];
    }

    public function prevalidar(UploadedFile $archivo, string $tipoPersona, ?int $usuarioId = null): PersonaImportacion
    {
        $this->validarTipoPersona($tipoPersona);

        $nombreBase = Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'personas';
        $ruta = 'personas/'.$tipoPersona.'/'.now()->format('YmdHis').'_'.$nombreBase.'.csv';

        Storage::disk('local')->put($ruta, $archivo->get());

        $parsed = $this->parsear(Storage::disk('local')->path($ruta), $tipoPersona);
        $errores = $parsed['errores'];

        if ($parsed['total'] === 0) {
            $errores[] = [
                'linea' => null,
                'campo' => null,
                'valor' => null,
                'mensaje' => 'El archivo no contiene filas de datos.',
            ];
        }

        if ($parsed['total'] > self::MAX_FILAS) {
            $errores[] = [
                'linea' => null,
                'campo' => null,
                'valor' => $parsed['total'],
                'mensaje' => 'El archivo no debe superar las '.self::MAX_FILAS.' filas de datos.',
            ];
        }

        $importacion = PersonaImportacion::create([
            'tipo_persona' => $tipoPersona,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            'hash' => hash_file('sha256', Storage::disk('local')->path($ruta)),
            'total_filas' => $parsed['total'],
            'filas_validas' => empty($errores) ? $parsed['validas'] : 0,
            'filas_error' => count($errores),
            'errores' => array_slice($errores, 0, 500),
            'resumen' => $parsed['resumen'],
            'estado' => empty($errores) ? 'prevalidado' : 'error',
            'usuario_id' => $usuarioId,
        ]);

        if ($importacion->estado === 'error') {
            Storage::disk('local')->delete($ruta);
        }

        return $importacion;
    }

    public function confirmar(PersonaImportacion $importacion): PersonaImportacion
    {
        if ($importacion->estado !== 'prevalidado') {
            return $importacion;
        }

        $path = Storage::disk('local')->path($importacion->ruta);
        if (! is_file($path)) {
            $importacion->update([
                'estado' => 'error',
                'filas_error' => 1,
                'errores' => [[
                    'linea' => null,
                    'campo' => 'archivo',
                    'valor' => null,
                    'mensaje' => 'No se encontró el archivo CSV de la importación. Vuelve a cargarlo.',
                ]],
            ]);

            return $importacion->fresh();
        }

        $parsed = $this->parsear($path, $importacion->tipo_persona);

        if (! empty($parsed['errores']) || $parsed['total'] === 0) {
            $errores = $parsed['errores'];
            if ($parsed['total'] === 0) {
                $errores[] = [
                    'linea' => null,
                    'campo' => null,
                    'valor' => null,
                    'mensaje' => 'El archivo no contiene filas de datos.',
                ];
            }

            $importacion->update([
                'estado' => 'error',
                'filas_validas' => 0,
                'filas_error' => count($errores),
                'errores' => array_slice($errores, 0, 500),
            ]);
            Storage::disk('local')->delete($importacion->ruta);

            return $importacion->fresh();
        }

        $personas = [];
        $resultados = [];

        try {
            DB::transaction(function () use ($parsed, &$personas, &$resultados) {
                foreach ($parsed['rows'] as $row) {
                    $persona = TbClientes::create($row['persona']);
                    $persona->domicilios()->create($row['domicilio']);

                    $resultados[] = app(RegistroPLDService::class)
                        ->analizarYRegistrar($persona, [$row['domicilio']]);

                    $personas[] = $persona;
                }
            });
        } catch (Throwable $e) {
            Log::error('Error al importar personas desde CSV: '.$e->getMessage(), [
                'importacion_id' => $importacion->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $mensaje = 'No fue posible importar el archivo. Revisa los datos e intenta nuevamente.';
            if (config('app.debug')) {
                $mensaje .= ' '.$e->getMessage();
            }

            $importacion->update([
                'estado' => 'error',
                'filas_validas' => 0,
                'filas_error' => 1,
                'errores' => [[
                    'linea' => null,
                    'campo' => null,
                    'valor' => null,
                    'mensaje' => $mensaje,
                ]],
            ]);
            Storage::disk('local')->delete($importacion->ruta);

            return $importacion->fresh();
        }

        $alertasGeneradas = 0;
        $marcadasAltoRiesgo = 0;

        foreach ($personas as $index => $persona) {
            $resultado = $resultados[$index] ?? [];

            $alertasGeneradas += count($resultado['alertasGeneradas'] ?? []);
            $marcadasAltoRiesgo += ($resultado['esAltoRiesgo'] ?? false) ? 1 : 0;

            try {
                app(RegistroPLDService::class)->notificar($persona, $resultado);
            } catch (Throwable $e) {
                Log::warning('No se pudo notificar el alta masiva de la persona '.$persona->IDCliente.': '.$e->getMessage());
            }
        }

        $importacion->update([
            'estado' => 'importado',
            'filas_validas' => count($personas),
            'filas_error' => 0,
            'errores' => [],
            'resumen' => array_merge($importacion->resumen ?? [], [
                'importadas' => count($personas),
                'alertas_generadas' => $alertasGeneradas,
                'marcadas_alto_riesgo' => $marcadasAltoRiesgo,
            ]),
        ]);

        Storage::disk('local')->delete($importacion->ruta);

        return $importacion->fresh();
    }

    /**
     * @return array{total:int, validas:int, rows:array<int, array<string, mixed>>, errores:array<int, array<string, mixed>>, resumen:array<string, mixed>}
     */
    private function parsear(string $path, string $tipoPersona): array
    {
        $this->cargarCatalogos();

        $contenido = (string) file_get_contents($path);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;

        $primeraLinea = strtok($contenido, "\n") ?: '';
        $delimitador = $this->detectarDelimitador($primeraLinea);

        $tmp = tempnam(sys_get_temp_dir(), 'personas_');
        file_put_contents($tmp, $contenido);
        $file = new \SplFileObject($tmp);
        $file->setCsvControl($delimitador, '"', '\\');

        $headers = [];
        $headersLeidos = false;
        $rows = [];
        $errores = [];
        $rfcsArchivo = [];
        $total = 0;
        $numeroLinea = 0;

        while (! $file->eof()) {
            $data = $file->fgetcsv();
            $numeroLinea++;

            if ($data === false || $data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }

            if (! $headersLeidos) {
                $headers = array_map(fn ($header) => $this->normalizarHeader((string) $header), $data);
                $this->validarHeaders($tipoPersona, $headers, $errores);
                $headersLeidos = true;

                continue;
            }

            $total++;
            $linea = $numeroLinea;
            $fila = [];
            foreach ($headers as $i => $header) {
                $fila[$header] = trim((string) ($data[$i] ?? ''));
            }

            $resultado = $this->normalizarFila($fila, $tipoPersona, $linea, $errores, $rfcsArchivo);
            if ($resultado !== null) {
                $rows[] = $resultado;
            }
        }
        @unlink($tmp);

        if (! $headersLeidos) {
            $errores[] = [
                'linea' => 1,
                'campo' => null,
                'valor' => null,
                'mensaje' => 'El archivo está vacío o no tiene encabezados.',
            ];
        }

        return [
            'total' => $total,
            'validas' => count($rows),
            'rows' => $rows,
            'errores' => $errores,
            'resumen' => [
                'tipo_persona' => $tipoPersona,
                'total' => $total,
                'validas' => count($rows),
                'errores' => count($errores),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $errores
     */
    private function validarHeaders(string $tipoPersona, array $headers, array &$errores): void
    {
        $esperados = $this->config[$tipoPersona]['headers'] ?? [];
        $requeridos = $this->config[$tipoPersona]['required'] ?? [];

        $faltantes = array_values(array_diff($esperados, $headers));
        $faltantesRequeridos = array_values(array_intersect($faltantes, $requeridos));

        if (! empty($faltantesRequeridos)) {
            $errores[] = [
                'linea' => 1,
                'campo' => implode(', ', $faltantesRequeridos),
                'valor' => null,
                'mensaje' => 'Faltan columnas obligatorias en el CSV: '.implode(', ', $faltantesRequeridos).'.',
            ];
        }
    }

    /**
     * @param  array<string, string>  $fila
     * @param  array<int, array<string, mixed>>  $errores
     * @param  array<string, bool>  $rfcsArchivo
     * @return array{persona:array<string, mixed>, domicilio:array<string, mixed>}|null
     */
    private function normalizarFila(array $fila, string $tipoPersona, int $linea, array &$errores, array &$rfcsArchivo): ?array
    {
        $erroresFila = [];

        $agregarError = function (string $campo, $valor, string $mensaje) use (&$erroresFila): void {
            $erroresFila[] = [
                'linea' => null,
                'campo' => $campo,
                'valor' => $valor,
                'mensaje' => $mensaje,
            ];
        };

        $valor = fn (string $campo): string => trim((string) ($fila[$campo] ?? ''));

        $tipos = [
            '1' => 1,
            'pf' => 1,
            'fisica' => 1,
            'persona fisica' => 1,
            '2' => 2,
            'pm' => 2,
            'moral' => 2,
            'persona moral' => 2,
        ];
        $tipoRaw = $this->normalizarTexto($valor('tipo_persona'));
        $idTipoPersona = $tipos[$tipoRaw] ?? null;
        if ($idTipoPersona === null) {
            $agregarError('tipo_persona', $valor('tipo_persona'), 'Indica Persona Física o Persona Moral.');
        }

        $esFisica = $idTipoPersona === 1;

        $rfc = strtoupper($valor('rfc'));
        if ($rfc === '') {
            $agregarError('rfc', $rfc, 'El RFC es obligatorio.');
        } elseif (mb_strlen($rfc) > 20) {
            $agregarError('rfc', $rfc, 'El RFC no debe superar 20 caracteres.');
        } elseif (isset($rfcsArchivo[$rfc])) {
            $agregarError('rfc', $rfc, 'El RFC está duplicado en el archivo.');
        } else {
            $rfcsArchivo[$rfc] = true;
            if (TbClientes::whereRaw('UPPER(TRIM(RFC)) = ?', [$rfc])->exists()) {
                $agregarError('rfc', $rfc, 'Ya existe una persona registrada con ese RFC.');
            }
        }

        $nombre = $valor('nombre');
        $apellidoPaterno = $valor('apellido_paterno');
        $apellidoMaterno = $valor('apellido_materno');
        $razonSocial = $valor('razon_social');

        if ($esFisica) {
            if ($nombre === '') {
                $agregarError('nombre', $nombre, 'El nombre es obligatorio para personas físicas.');
            }
            if ($apellidoPaterno === '') {
                $agregarError('apellido_paterno', $apellidoPaterno, 'El apellido paterno es obligatorio para personas físicas.');
            }
        } elseif ($idTipoPersona === 2 && $razonSocial === '') {
            $agregarError('razon_social', $razonSocial, 'La razón social es obligatoria para personas morales.');
        }

        $maximos = [
            'nombre' => 255,
            'apellido_paterno' => 255,
            'apellido_materno' => 255,
            'razon_social' => 255,
            'curp' => 18,
            'folio_mercantil' => 255,
            'calle' => 255,
            'no_exterior' => 255,
            'no_interior' => 255,
            'colonia' => 255,
            'cp' => 10,
            'municipio' => 255,
            'localidad' => 255,
            'telefono' => 20,
        ];
        foreach ($maximos as $campo => $maximo) {
            if (mb_strlen($valor($campo)) > $maximo) {
                $agregarError($campo, $valor($campo), "El campo {$campo} no debe superar {$maximo} caracteres.");
            }
        }

        $curp = $esFisica ? strtoupper($valor('curp')) : '';
        $nacionalidadRaw = $valor('nacionalidad');
        $nacionalidad = $this->resolverNacionalidad($nacionalidadRaw);
        if ($nacionalidad === null) {
            $agregarError('nacionalidad', $nacionalidadRaw, 'La nacionalidad es obligatoria y debe existir en el catálogo.');
        }

        $ocupacionRaw = $valor('ocupacion_giro');
        $ocupacion = $this->resolverOcupacion($ocupacionRaw, $tipoPersona);
        if ($ocupacion === null) {
            $agregarError('ocupacion_giro', $ocupacionRaw, 'La ocupación/giro no existe en el catálogo.');
        }

        $estadoNacimientoRaw = $valor('estado_nacimiento');
        $estadoNacimiento = null;
        if ($estadoNacimientoRaw !== '') {
            $estadoNacimiento = $this->resolverEstado($estadoNacimientoRaw);
            if ($estadoNacimiento === null) {
                $agregarError('estado_nacimiento', $estadoNacimientoRaw, 'El estado de nacimiento no existe en el catálogo.');
            }
        }

        $fechaNacimientoRaw = $valor('fecha_nacimiento');
        $fechaNacimiento = $this->resolverFecha($fechaNacimientoRaw);
        if ($fechaNacimientoRaw !== '' && $fechaNacimiento === null) {
            $agregarError('fecha_nacimiento', $fechaNacimientoRaw, 'La fecha de nacimiento no tiene un formato válido.');
        }

        $fechaConstitucionRaw = $valor('fecha_constitucion');
        $fechaConstitucion = $this->resolverFecha($fechaConstitucionRaw);
        if ($fechaConstitucionRaw !== '' && $fechaConstitucion === null) {
            $agregarError('fecha_constitucion', $fechaConstitucionRaw, 'La fecha de constitución no tiene un formato válido.');
        }

        $ingresosRaw = trim($valor('ingresos_estimados'));
        $ingresos = null;
        if ($ingresosRaw !== '') {
            $ingresosLimpios = str_replace([',', '$', ' '], '', $ingresosRaw);
            if (! is_numeric($ingresosLimpios) || (float) $ingresosLimpios < 0) {
                $agregarError('ingresos_estimados', $ingresosRaw, 'Los ingresos estimados deben ser un número mayor o igual a cero.');
            } else {
                $ingresos = (float) $ingresosLimpios;
            }
        }

        $activoRaw = $valor('activo');
        $activo = $this->resolverActivo($activoRaw);
        if ($activo === null) {
            $agregarError('activo', $activoRaw, 'El estatus activo debe ser Sí/No o 1/0.');
        }

        $calle = $valor('calle');
        $colonia = $valor('colonia');
        $cp = $valor('cp');
        $municipio = $valor('municipio');
        $estadoRaw = $valor('estado');

        foreach ([
            'calle' => ['valor' => $calle, 'mensaje' => 'La calle es obligatoria.'],
            'colonia' => ['valor' => $colonia, 'mensaje' => 'La colonia es obligatoria.'],
            'cp' => ['valor' => $cp, 'mensaje' => 'El código postal es obligatorio.'],
            'municipio' => ['valor' => $municipio, 'mensaje' => 'El municipio es obligatorio.'],
        ] as $campo => $regla) {
            if ($regla['valor'] === '') {
                $agregarError($campo, $regla['valor'], $regla['mensaje']);
            }
        }

        $estado = $this->resolverEstado($estadoRaw);
        if ($estado === null) {
            $agregarError('estado', $estadoRaw, 'El estado del domicilio es obligatorio y debe existir en el catálogo.');
        }

        if (! empty($erroresFila)) {
            foreach ($erroresFila as $error) {
                $error['linea'] = $linea;
                $errores[] = $error;
            }

            return null;
        }

        $persona = [
            'RFC' => $rfc,
            'Nombre' => $esFisica ? $nombre : null,
            'ApellidoPaterno' => $esFisica ? $apellidoPaterno : null,
            'ApellidoMaterno' => $esFisica ? ($apellidoMaterno !== '' ? $apellidoMaterno : null) : null,
            'RazonSocial' => $esFisica ? null : $razonSocial,
            'IDTipoPersona' => $idTipoPersona,
            'CURP' => $esFisica && $curp !== '' ? $curp : null,
            'IDOcupacionGiro' => $ocupacion,
            'FechaNacimiento' => $esFisica ? $fechaNacimiento : null,
            'FechaConstitucion' => $esFisica ? null : $fechaConstitucion,
            'FolioMercantil' => $valor('folio_mercantil') !== '' ? $valor('folio_mercantil') : null,
            'IDNacionalidad' => $nacionalidad,
            'IDEstadoNacimiento' => $estadoNacimiento,
            'IngresosEstimados' => $ingresos,
            'Preguntas' => $valor('preguntas') !== '' ? $valor('preguntas') : null,
            'TipoPersona' => $tipoPersona,
            'ckAgente' => $tipoPersona === TbClientes::TIPO_AGENTE ? 1 : 0,
            'Activo' => $activo,
            'CoincideEnListasNegras' => 0,
            'EsPPEActivo' => false,
            'EsAltoRiesgo' => false,
        ];

        $domicilio = [
            'Calle' => $calle,
            'NoExterior' => $valor('no_exterior') !== '' ? $valor('no_exterior') : null,
            'NoInterior' => $valor('no_interior') !== '' ? $valor('no_interior') : null,
            'Colonia' => $colonia,
            'CP' => $cp,
            'IDEstado' => (string) $estado,
            'Municipio' => $municipio,
            'Localidad' => $valor('localidad') !== '' ? $valor('localidad') : null,
            'Telefono' => $valor('telefono') !== '' ? $valor('telefono') : null,
        ];

        return ['persona' => $persona, 'domicilio' => $domicilio];
    }

    private function resolverNacionalidad(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $id = strtoupper($raw);
        if (isset($this->nacionalidadesPorId[$id])) {
            return $this->nacionalidadesPorId[$id];
        }

        return $this->nacionalidadesPorNombre[$this->normalizarTexto($raw)] ?? null;
    }

    private function resolverEstado(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw) && isset($this->estadosPorId[(string) (int) $raw])) {
            return $this->estadosPorId[(string) (int) $raw];
        }

        return $this->estadosPorNombre[$this->normalizarTexto($raw)] ?? null;
    }

    private function resolverOcupacion(string $raw, string $tipoPersona): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            $default = $tipoPersona === TbClientes::TIPO_AGENTE
                ? 'Agente de seguros'
                : 'Empleado del sector privado';

            $normalizado = $this->normalizarTexto($default);
            foreach ($this->ocupacionesPorNombre as $nombre => $id) {
                if (str_starts_with($nombre, $normalizado)) {
                    return $id;
                }
            }

            return null;
        }

        if (is_numeric($raw)) {
            foreach ($this->ocupaciones as $ocupacion) {
                if ((string) $ocupacion['id'] === (string) (int) $raw || $ocupacion['cve'] === trim($raw)) {
                    return $ocupacion['id'];
                }
            }
        }

        return $this->ocupacionesPorNombre[$this->normalizarTexto($raw)] ?? null;
    }

    private function resolverFecha(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!'.$formato, $raw);
            if ($fecha !== false && $fecha->format($formato) === $raw) {
                return $fecha->format('Y-m-d');
            }
        }

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $raw, $partes)
            && checkdate((int) $partes[2], (int) $partes[1], (int) $partes[3])) {
            return sprintf('%04d-%02d-%02d', (int) $partes[3], (int) $partes[2], (int) $partes[1]);
        }

        return null;
    }

    private function resolverActivo(string $raw): ?bool
    {
        $raw = $this->normalizarTexto($raw);
        if ($raw === '') {
            return true;
        }

        if (in_array($raw, ['si', 's', '1', 'true', 'activo', 'yes'], true)) {
            return true;
        }

        if (in_array($raw, ['no', 'n', '0', 'false', 'inactivo'], true)) {
            return false;
        }

        return null;
    }

    private function cargarCatalogos(): void
    {
        if ($this->catalogosCargados) {
            return;
        }

        foreach (CatNacionalidad::get(['IDNacionalidad', 'Nacionalidad']) as $nacionalidad) {
            $id = (string) $nacionalidad->IDNacionalidad;
            $this->nacionalidadesPorId[strtoupper(trim($id))] = $id;
            $this->nacionalidadesPorNombre[$this->normalizarTexto($nacionalidad->Nacionalidad)] = $id;
        }

        foreach (CatEstados::get(['IDEstado', 'Estado']) as $estado) {
            $id = (string) $estado->IDEstado;
            $this->estadosPorId[$id] = $id;
            $this->estadosPorNombre[$this->normalizarTexto($estado->Estado)] = $id;
        }

        foreach (CatOcupacionesGiros::get(['IDOcupacionGiro', 'CVE_GIRO', 'OcupacionGiro']) as $ocupacion) {
            $id = (int) $ocupacion->IDOcupacionGiro;
            $cve = trim((string) $ocupacion->CVE_GIRO);
            $nombre = trim((string) $ocupacion->OcupacionGiro);

            $this->ocupaciones[$id] = ['id' => $id, 'cve' => $cve, 'nombre' => $nombre];

            $this->ocupacionesPorNombre[$this->normalizarTexto($nombre)] = $id;
        }

        $this->catalogosCargados = true;
    }

    private function detectarDelimitador(string $linea): string
    {
        $candidatos = [',' => substr_count($linea, ','), ';' => substr_count($linea, ';'), "\t" => substr_count($linea, "\t")];

        arsort($candidatos);

        return (string) array_key_first($candidatos);
    }

    private function normalizarHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = $this->normalizarTexto($header);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $header) ?? '', '_');
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? $texto;

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    private function validarTipoPersona(string $tipoPersona): void
    {
        if (! in_array($tipoPersona, [TbClientes::TIPO_AGENTE, TbClientes::TIPO_COLABORADOR], true)) {
            throw new InvalidArgumentException('El tipo de persona para importación no es válido.');
        }
    }
}
