<?php

namespace App\Console\Commands;

use App\Models\Clientes\TbClientes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recupera Municipio/Localidad de domicilios que quedaron vacíos porque el alta
 * de clientes escribía en columnas inexistentes (IDMunicipio/IDLocalidad).
 *
 * La información original se toma del payload de la última llamada
 * POST api/clientes/guardarCliente registrada en logApi.
 *
 * Uso:
 *   php artisan clientes:backfill-domicilios --dry-run   (solo muestra)
 *   php artisan clientes:backfill-domicilios              (aplica)
 */
class BackfillDomiciliosClientes extends Command
{
    protected $signature = 'clientes:backfill-domicilios
                            {--dry-run : Solo muestra los domicilios por corregir sin modificar nada}';

    protected $description = 'Rellena Municipio/Localidad de domicilios desde el payload original de alta de clientes';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $clientes = TbClientes::with('domicilios')
            ->whereHas('domicilios', function ($q) {
                $q->whereNull('Municipio')->orWhere('Municipio', '');
            })
            ->get();

        $this->info($dryRun ? 'Modo DRY-RUN: no se modificará nada.' : 'Aplicando correcciones...');

        $cambios = [];

        foreach ($clientes as $cliente) {
            $payload = DB::table('logApi')
                ->where('Metodo', 'POST')
                ->where('Ruta', 'like', '%guardarCliente%')
                ->where('Payload', 'like', '%"RFC":"'.$cliente->RFC.'"%')
                ->orderByDesc('id')
                ->value('Payload');

            if (empty($payload)) {
                continue;
            }

            $data = json_decode($payload, true);
            if (! is_array($data) || empty($data['domicilios']) || ! is_array($data['domicilios'])) {
                continue;
            }

            foreach ($data['domicilios'] as $dom) {
                $cp = trim((string) ($dom['CP'] ?? ''));
                $calle = trim((string) ($dom['calle'] ?? ''));
                $municipio = trim((string) ($dom['municipio'] ?? ''));
                $localidad = trim((string) ($dom['localidad'] ?? ''));

                if ($municipio === '' && $localidad === '') {
                    continue;
                }

                $domicilio = $cliente->domicilios->first(function ($d) use ($cp, $calle) {
                    $mismoCp = $cp === '' || trim((string) $d->CP) === $cp;
                    $mismaCalle = $calle === '' || mb_strtoupper(trim((string) $d->Calle)) === mb_strtoupper($calle);

                    return $mismoCp && $mismaCalle;
                });

                if (! $domicilio) {
                    continue;
                }

                $nuevoMunicipio = $domicilio->Municipio ?: ($municipio !== '' ? $municipio : null);
                $nuevaLocalidad = $domicilio->Localidad ?: ($localidad !== '' ? $localidad : null);

                if ($nuevoMunicipio === $domicilio->Municipio && $nuevaLocalidad === $domicilio->Localidad) {
                    continue;
                }

                $cambios[] = [
                    'IDCliente' => $cliente->IDCliente,
                    'RFC' => $cliente->RFC,
                    'IDDomicilio' => $domicilio->IDDomicilio,
                    'Municipio' => $nuevoMunicipio,
                    'Localidad' => $nuevaLocalidad,
                ];

                if (! $dryRun) {
                    $domicilio->Municipio = $nuevoMunicipio;
                    $domicilio->Localidad = $nuevaLocalidad;
                    $domicilio->save();
                }
            }
        }

        if (empty($cambios)) {
            $this->info('Sin domicilios por corregir.');

            return self::SUCCESS;
        }

        $this->table(
            ['IDCliente', 'RFC', 'IDDomicilio', 'Municipio', 'Localidad'],
            array_map(fn ($c) => [$c['IDCliente'], $c['RFC'], $c['IDDomicilio'], $c['Municipio'], $c['Localidad']], $cambios)
        );

        if ($dryRun) {
            $this->warn(count($cambios).' domicilio(s) por corregir. Ejecuta sin --dry-run para aplicar.');
        } else {
            $this->info(count($cambios).' domicilio(s) corregidos.');
        }

        return self::SUCCESS;
    }
}
