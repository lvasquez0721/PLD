<?php

namespace App\Http\Controllers\Ebr;

use App\Http\Controllers\Controller;
use App\Models\Ebr\EbrEjercicio;
use App\Services\Ebr\ExportadorEbr;
use Barryvdh\DomPDF\Facade\Pdf;

class EbrReporteController extends Controller
{
    public function excel(EbrEjercicio $ejercicio, ExportadorEbr $exportador)
    {
        abort_unless($ejercicio->resultados, 404, 'El ejercicio aún no tiene resultados calculados.');

        $file = $exportador->excel($ejercicio);

        return response()->download($file, "Implementacion_MEBR_{$ejercicio->anio}.xlsx")->deleteFileAfterSend(true);
    }

    public function pdf(EbrEjercicio $ejercicio)
    {
        abort_unless($ejercicio->resultados, 404, 'El ejercicio aún no tiene resultados calculados.');

        $pdf = Pdf::loadView('ebr.informe', [
            'ejercicio' => $ejercicio,
            'r' => $ejercicio->resultados,
        ])->setPaper('letter');

        return $pdf->download("Informe_EBR_{$ejercicio->anio}.pdf");
    }
}
