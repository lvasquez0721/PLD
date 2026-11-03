<?php

namespace App\Services\Ebr;

use App\Models\Ebr\EbrEjercicio;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportadorEbr
{
    public function excel(EbrEjercicio $ejercicio): string
    {
        $resultados = $ejercicio->resultados ?? [];
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->hojaResumen($spreadsheet, $ejercicio, $resultados);
        $this->hojaProductos($spreadsheet, $resultados);
        $this->hojaClientes($spreadsheet, $resultados);
        $this->hojaGeografia($spreadsheet, $resultados);
        $this->hojaCanales($spreadsheet, $resultados);
        $this->hojaTransacciones($spreadsheet, $resultados);
        $this->hojaMitigantes($spreadsheet, $ejercicio);
        $this->hojaAdvertencias($spreadsheet, $ejercicio);

        $spreadsheet->setActiveSheetIndex(0);
        $path = storage_path("app/private/ebr/{$ejercicio->anio}/exports");
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }
        $file = "{$path}/Implementacion_MEBR_{$ejercicio->anio}_".now()->format('YmdHis').'.xlsx';
        (new Xlsx($spreadsheet))->save($file);

        return $file;
    }

    private function hojaResumen(Spreadsheet $spreadsheet, EbrEjercicio $ejercicio, array $r): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen');
        $this->titulo($sheet, 'A1:D1', "EBR {$ejercicio->anio} - Resumen institucional");
        $sheet->fromArray([
            ['Ejercicio', $ejercicio->anio],
            ['Periodo', ($ejercicio->fecha_inicio?->format('Y-m-d') ?? '').' al '.($ejercicio->fecha_fin?->format('Y-m-d') ?? '')],
            ['Estado', $ejercicio->estado],
            ['Calculado', $ejercicio->calculado_at?->format('Y-m-d H:i') ?? ''],
            [],
            ['Elemento', 'Peso', 'Riesgo inherente', 'Nivel', 'Riesgo residual', 'Nivel residual'],
        ], null, 'A3');

        $row = 10;
        foreach ($r['institucion']['detalle'] ?? [] as $detalle) {
            $sheet->fromArray([[
                $detalle['elemento'], $detalle['peso'], $detalle['inherente'], $detalle['nivel'], $detalle['residual'], $detalle['nivel_residual'],
            ]], null, 'A'.$row);
            $row++;
        }
        $sheet->fromArray([
            ['Institución', '', $r['institucion']['inherente'] ?? 0, $r['institucion']['nivel_inherente'] ?? '', $r['institucion']['residual'] ?? 0, $r['institucion']['nivel_residual'] ?? ''],
            ['Mitigante entidad', '', '', '', $r['institucion']['mitigante_entidad'] ?? 0, ''],
            ['Residual final', '', '', '', $r['institucion']['residual_final'] ?? 0, $r['institucion']['nivel'] ?? ''],
        ], null, 'A'.$row);
        $this->formatoTabla($sheet, 'A9:F'.($row + 2));
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function hojaProductos(Spreadsheet $spreadsheet, array $r): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Productos y Servicios');
        $this->titulo($sheet, 'A1:H1', 'Productos y Servicios');
        $sheet->fromArray([['Producto', 'Inherente', 'Nivel', 'Residual', 'Nivel residual', 'Ops. entrada', 'Monto entrada', 'Ops. salida']], null, 'A3');
        $row = 4;
        foreach ($r['elementos']['productos']['detalle'] ?? [] as $p) {
            $sheet->fromArray([[$p['grupo'], $p['inherente'], $p['nivel'], $p['residual'], $p['nivel_residual'], $p['distribuciones']['ops_in'] ?? 0, $p['distribuciones']['monto_in'] ?? 0, $p['distribuciones']['ops_out'] ?? 0]], null, 'A'.$row);
            $row++;
        }
        $sheet->fromArray([['Elemento', $r['elementos']['productos']['inherente'] ?? 0, $r['elementos']['productos']['nivel'] ?? '', $r['elementos']['productos']['residual'] ?? 0, $r['elementos']['productos']['nivel_residual'] ?? '']], null, 'A'.$row);
        $this->formatoTabla($sheet, 'A3:H'.$row);
        $this->autosize($sheet, 'A', 'H');
    }

    private function hojaClientes(Spreadsheet $spreadsheet, array $r): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Clientes');
        $this->titulo($sheet, 'A1:H1', 'Clientes - Personas Físicas y Morales');
        $fila = 3;
        foreach (['pf' => 'Personas Físicas', 'pm' => 'Personas Morales'] as $clave => $titulo) {
            $sheet->setCellValue('A'.$fila, $titulo);
            $fila++;
            $sheet->fromArray([['Indicador', 'Peso', 'Inherente', 'Nivel', 'Mitigante', 'Residual', 'Nivel residual', 'Métrica dominante']], null, 'A'.$fila);
            $fila++;
            foreach ($r['elementos']['clientes'][$clave]['indicadores'] ?? [] as $i) {
                $sheet->fromArray([[$i['nombre'], $i['peso'], $i['inherente'], $i['nivel'], $i['mitigante'], $i['residual'], $i['nivel_residual'], $i['metrica_dominante']]], null, 'A'.$fila);
                $fila++;
            }
            $sheet->fromArray([['Total tipo', '', $r['elementos']['clientes'][$clave]['inherente'] ?? 0, $r['elementos']['clientes'][$clave]['nivel'] ?? '', '', $r['elementos']['clientes'][$clave]['residual'] ?? 0, $r['elementos']['clientes'][$clave]['nivel_residual'] ?? '']], null, 'A'.$fila);
            $fila += 2;
        }
        $this->formatoTabla($sheet, 'A3:H'.max(3, $fila - 1));
        $this->autosize($sheet, 'A', 'H');
    }

    private function hojaGeografia(Spreadsheet $spreadsheet, array $r): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Países y Áreas Geográficas');
        $this->titulo($sheet, 'A1:G1', 'Países y Áreas Geográficas');

        $row = 3;
        $sheet->fromArray([['País', 'Score', 'Inherente', 'Nivel', 'Residual', 'Nivel residual']], null, 'A'.$row);
        $row++;

        $paises = $r['elementos']['geografia']['paises'] ?? [];
        if (empty($paises)) {
            $paises = [$r['elementos']['geografia']['pais'] ?? []];
        }
        foreach ($paises as $pais) {
            $sheet->fromArray([[
                $pais['pais'] ?? 'México',
                $pais['score'] ?? 0,
                $pais['inherente'] ?? 0,
                $pais['nivel'] ?? '',
                $pais['residual'] ?? 0,
                $pais['nivel_residual'] ?? '',
            ]], null, 'A'.$row);
            $row++;
        }

        $row++;
        $sheet->fromArray([['Estado', 'Score paz', 'Score frontera', 'Inherente', 'Nivel', 'Residual', 'Nivel residual']], null, 'A'.$row);
        $row++;
        foreach ($r['elementos']['geografia']['estados'] ?? [] as $e) {
            $sheet->fromArray([[$e['estado'], $e['score_paz'], $e['score_frontera'], $e['inherente'], $e['nivel'], $e['residual'], $e['nivel_residual']]], null, 'A'.$row);
            $row++;
        }
        $this->formatoTabla($sheet, 'A3:G'.$row);
        $this->autosize($sheet, 'A', 'G');
    }

    private function hojaCanales(Spreadsheet $spreadsheet, array $r): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Canales');
        $this->titulo($sheet, 'A1:F1', 'Canales de Envío y Distribución');
        $sheet->fromArray([['Canal', 'Inherente', 'Nivel', 'Residual', 'Nivel residual', 'Ops. entrada']], null, 'A3');
        $row = 4;
        foreach ($r['elementos']['canales']['detalle'] ?? [] as $c) {
            $sheet->fromArray([[$c['grupo'], $c['inherente'], $c['nivel'], $c['residual'], $c['nivel_residual'], $c['distribuciones']['ops_in'] ?? 0]], null, 'A'.$row);
            $row++;
        }
        $sheet->fromArray([['Elemento', $r['elementos']['canales']['inherente'] ?? 0, $r['elementos']['canales']['nivel'] ?? '', $r['elementos']['canales']['residual'] ?? 0, $r['elementos']['canales']['nivel_residual'] ?? '']], null, 'A'.$row);
        $this->formatoTabla($sheet, 'A3:F'.$row);
        $this->autosize($sheet, 'A', 'F');
    }

    private function hojaTransacciones(Spreadsheet $spreadsheet, array $r): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Transacciones');
        $this->titulo($sheet, 'A1:I1', 'Transacciones');
        $sheet->fromArray([['Indicador', 'Patrón', 'Puntaje', 'Alertas', 'Contratos', 'Clientes', 'Impacto', 'Inherente', 'Nivel']], null, 'A3');
        $row = 4;
        foreach ($r['elementos']['transacciones']['indicadores'] ?? [] as $t) {
            $sheet->fromArray([[$t['nombre'], $t['patron'], $t['puntaje'], $t['alertas'], $t['contratos'], $t['clientes'], $t['impacto'], $t['inherente'], $t['nivel']]], null, 'A'.$row);
            $row++;
        }
        $resumenDatos = $r['resumen_datos'] ?? [];
        $sheet->fromArray([
            ['Alertas generadas', '', '', $resumenDatos['alertas'] ?? 0, '', '', '', '', ''],
            ['Alertas procedentes', '', '', $resumenDatos['alertas_procedentes'] ?? 0, '', '', '', '', ''],
            ['Alertas relevantes (5.4)', '', '', $resumenDatos['alertas_relevantes'] ?? 0, '', '', '', '', ''],
        ], null, 'A'.$row);
        $row += 3;
        $sheet->fromArray([['Elemento', '', '', '', '', '', '', $r['elementos']['transacciones']['inherente'] ?? 0, $r['elementos']['transacciones']['nivel'] ?? '']], null, 'A'.$row);
        $this->formatoTabla($sheet, 'A3:I'.$row);
        $this->autosize($sheet, 'A', 'I');
    }

    private function hojaMitigantes(Spreadsheet $spreadsheet, EbrEjercicio $ejercicio): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Mitigantes');
        $this->titulo($sheet, 'A1:F1', 'Mitigantes');
        $sheet->fromArray([['No.', 'Nombre', 'Alcance', 'Máximo', 'Nivel', 'Efecto']], null, 'A3');
        $niveles = config('ebr.niveles_efectividad');
        $mitigantes = collect(config('ebr.mitigantes'))->map(function ($base) use ($niveles) {
            $nivel = $base['nivel'];
            $efectividad = (float) ($niveles[$nivel] ?? 1);

            return [
                'id' => (int) $base['id'],
                'nombre' => $base['nombre'],
                'scope' => $base['scope'],
                'max' => (float) $base['max'],
                'nivel' => $nivel,
                'efecto' => round((float) $base['max'] * $efectividad, 6),
            ];
        })->all();

        $row = 4;
        foreach ($mitigantes as $m) {
            $sheet->fromArray([[$m['id'], $m['nombre'], $m['scope'], $m['max'], $m['nivel'], $m['efecto']]], null, 'A'.$row);
            $row++;
        }
        $totalEntidad = collect($mitigantes)->where('scope', 'entidad')->sum('efecto');
        $sheet->fromArray([['', 'Total mitigantes de entidad', 'entidad', '', '', $totalEntidad]], null, 'A'.$row);
        $row++;
        $this->formatoTabla($sheet, 'A3:F'.$row);
        $this->autosize($sheet, 'A', 'F');
    }

    private function hojaAdvertencias(Spreadsheet $spreadsheet, EbrEjercicio $ejercicio): void
    {
        $sheet = $spreadsheet->createSheet()->setTitle('Advertencias');
        $this->titulo($sheet, 'A1:A1', 'Advertencias del cálculo');
        $row = 3;
        foreach ($ejercicio->advertencias ?? [] as $advertencia) {
            $sheet->setCellValue('A'.$row, $advertencia);
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(120);
    }

    private function titulo($sheet, string $rango, string $titulo): void
    {
        $sheet->mergeCells($rango);
        $sheet->setCellValue(explode(':', $rango)[0], $titulo);
        $sheet->getStyle(explode(':', $rango)[0])->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle(explode(':', $rango)[0])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    private function formatoTabla($sheet, string $rango): void
    {
        $sheet->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getStyle($rango)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    }

    private function autosize($sheet, string $desde, string $hasta): void
    {
        foreach (range($desde, $hasta) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
