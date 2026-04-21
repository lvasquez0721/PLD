<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExportarLayoutController extends Controller
{
    public function index()
    {
        return Inertia::render('ExportarLayout/Index');
    }

    public function exportar(Request $request)
    {
        $tipo = $request->input('tipo'); // 1=Fisica, 2=Moral, 0=Ambos

        if (!in_array($tipo, ['0', '1', '2'])) {
            return response()->json(['error' => 'Tipo de persona inválido.'], 422);
        }

        if ($tipo === '1') {
            return $this->exportarFisicas();
        } elseif ($tipo === '2') {
            return $this->exportarMorales();
        } else {
            return $this->exportarAmbos();
        }
    }

    private function exportarFisicas()
    {
        $clientes = TbClientes::where('IDTipoPersona', 1)
            ->where('Activo', true)
            ->orderBy('IDCliente')
            ->get(['IDCliente', 'Nombre', 'ApellidoPaterno', 'ApellidoMaterno', 'CURP', 'RFC']);

        if ($clientes->isEmpty()) {
            return response()->json(['error' => 'No hay registros de personas físicas activas.'], 404);
        }

        $fileName = 'PersonasFisicas_' . date('Ymd_His') . '.txt';
        $lineas = ["ID;Nombre;Apellido Paterno;Apellido Materno;CURP;RFC"];

        foreach ($clientes as $i => $c) {
            $id = 'TLAPF' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $nombre     = str_replace([';', ','], '', $c->Nombre ?? '');
            $aPaterno   = str_replace([';', ','], '', $c->ApellidoPaterno ?? '');
            $aMaterno   = str_replace([';', ','], '', $c->ApellidoMaterno ?? '');
            $curp       = str_replace([';', ','], '', $c->CURP ?? '');
            $rfc        = str_replace([';', ','], '', $c->RFC ?? '');
            $lineas[] = "{$id};{$nombre};{$aPaterno};{$aMaterno};{$curp};{$rfc}";
        }

        $contenido = implode("\n", $lineas);

        return response($contenido, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length'      => mb_strlen($contenido, '8bit'),
        ]);
    }

    private function exportarMorales()
    {
        $clientes = TbClientes::where('IDTipoPersona', 2)
            ->where('Activo', true)
            ->orderBy('IDCliente')
            ->get(['IDCliente', 'RazonSocial', 'RFC']);

        if ($clientes->isEmpty()) {
            return response()->json(['error' => 'No hay registros de personas morales activas.'], 404);
        }

        $fileName = 'PersonasMorales_' . date('Ymd_His') . '.txt';
        $lineas = ["ID;Razon Social;RFC"];

        foreach ($clientes as $i => $c) {
            $id         = 'TLAPM' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $razonSocial = str_replace([';', ','], '', $c->RazonSocial ?? '');
            $rfc        = str_replace([';', ','], '', $c->RFC ?? '');
            $lineas[] = "{$id};{$razonSocial};{$rfc}";
        }

        $contenido = implode("\n", $lineas);

        return response($contenido, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Content-Length'      => mb_strlen($contenido, '8bit'),
        ]);
    }

    private function exportarAmbos()
    {
        $clientes = TbClientes::whereIn('IDTipoPersona', [1, 2])
            ->where('Activo', true)
            ->orderBy('IDTipoPersona')
            ->orderBy('IDCliente')
            ->get(['IDCliente', 'IDTipoPersona', 'Nombre', 'ApellidoPaterno', 'ApellidoMaterno', 'RazonSocial', 'RFC', 'CURP']);

        if ($clientes->isEmpty()) {
            return response()->json(['error' => 'No hay registros activos.'], 404);
        }

        $fileName = 'PersonasFisicasYMorales_' . date('Ymd_His') . '.csv';

        $callback = function () use ($clientes) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['FULL_NAME', 'TAX_ID', 'NATIONAL_ID']);

            foreach ($clientes as $c) {
                if ($c->IDTipoPersona == 1) {
                    $fullName = trim(implode(' ', array_filter([
                        $c->Nombre,
                        $c->ApellidoPaterno,
                        $c->ApellidoMaterno,
                    ])));
                } else {
                    $fullName = $c->RazonSocial ?? '';
                }

                fputcsv($handle, [
                    $fullName,
                    $c->RFC ?? '',
                    $c->CURP ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
