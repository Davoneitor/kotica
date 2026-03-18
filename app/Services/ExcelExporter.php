<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ExcelExporter — Servicio reutilizable para generar descargas Excel (.xlsx).
 *
 * Uso:
 *   return ExcelExporter::download(
 *       filename:    'entradas',
 *       moduleName:  'Entradas',
 *       headers:     ['Fecha', 'Código', 'Descripción', ...],
 *       rows:        $rows,          // array de arrays
 *       columnTypes: [0 => 'date', 6 => 'number', 7 => 'currency'],
 *       filters:     ['Obra: Oblatos', 'Desde: 2024-01-01'],
 *   );
 *
 * Tipos de columna soportados:
 *   'text'     → General (sin formato especial)
 *   'number'   → #,##0.00
 *   'integer'  → #,##0
 *   'currency' → "$"#,##0.00
 *   'date'     → DD/MM/YYYY
 */
class ExcelExporter
{
    /**
     * Genera y retorna una respuesta streamed con el archivo Excel.
     *
     * @param  string  $filename     Nombre base del archivo (sin extensión ni timestamp)
     * @param  string  $moduleName   Nombre legible del módulo para la hoja y fila informativa
     * @param  array   $headers      Etiquetas de columna
     * @param  array   $rows         Datos: array de arrays indexados por posición
     * @param  array   $columnTypes  Mapa [índice_columna => tipo] para formato numérico
     * @param  array   $filters      Descripción de filtros activos (para la fila informativa)
     */
    public static function download(
        string $filename,
        string $moduleName,
        array  $headers,
        array  $rows,
        array  $columnTypes = [],
        array  $filters     = []
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($moduleName, 0, 31)); // Excel: máx 31 chars

        $user     = Auth::user();
        $userName = $user?->name ?? 'Sistema';
        $now      = now()->format('d/m/Y H:i');

        $colCount    = count($headers);
        $lastColLtr  = self::colLetter($colCount);

        // ── Fila 1: Encabezado informativo ──────────────────────────────
        $filtersText = empty($filters)
            ? 'Sin filtros'
            : implode('  ·  ', array_filter($filters));

        $infoText = "Sistema Almacén  |  {$moduleName}  |  Generado: {$now}  |  Usuario: {$userName}  |  {$filtersText}";

        $sheet->setCellValue('A1', $infoText);
        $sheet->mergeCells("A1:{$lastColLtr}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => [
                'bold'  => true,
                'size'  => 9,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '111827'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => false,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        // ── Fila 2: Nombres de columna ───────────────────────────────────
        foreach ($headers as $i => $label) {
            $col = self::colLetter($i + 1);
            $sheet->setCellValue("{$col}2", $label);
        }

        $sheet->getStyle("A2:{$lastColLtr}2")->applyFromArray([
            'font'      => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1F2937'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Congelar encabezados (filas 1 y 2)
        $sheet->freezePane('A3');

        // ── Filas 3+: Datos ──────────────────────────────────────────────
        foreach ($rows as $rowIdx => $rowData) {
            $excelRow = $rowIdx + 3;
            foreach ($rowData as $colIdx => $value) {
                $col = self::colLetter($colIdx + 1);
                $sheet->setCellValue("{$col}{$excelRow}", $value);
            }

            // Zebra striping (filas pares con fondo muy suave)
            if ($rowIdx % 2 === 1) {
                $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F9FAFB');
            }
        }

        // ── Formatos numéricos por columna ───────────────────────────────
        if (!empty($rows)) {
            $lastDataRow = count($rows) + 2;
            foreach ($columnTypes as $colIdx => $type) {
                $col   = self::colLetter($colIdx + 1);
                $range = "{$col}3:{$col}{$lastDataRow}";

                match ($type) {
                    'number'   => $sheet->getStyle($range)->getNumberFormat()
                                        ->setFormatCode('#,##0.00'),
                    'integer'  => $sheet->getStyle($range)->getNumberFormat()
                                        ->setFormatCode('#,##0'),
                    'currency' => $sheet->getStyle($range)->getNumberFormat()
                                        ->setFormatCode('"$"#,##0.00'),
                    'date'     => $sheet->getStyle($range)->getNumberFormat()
                                        ->setFormatCode('DD/MM/YYYY'),
                    default    => null,
                };
            }

            // Bordes del área de datos
            $sheet->getStyle("A2:{$lastColLtr}{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E5E7EB'],
                    ],
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color'       => ['rgb' => '374151'],
                    ],
                ],
            ]);
        }

        // ── Auto-ancho de columnas ───────────────────────────────────────
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(self::colLetter($i))->setAutoSize(true);
        }

        // ── Respuesta streamed ───────────────────────────────────────────
        $xlsxFilename = $filename . '_' . now()->format('Ymd_Hi') . '.xlsx';
        $writer       = new Xlsx($spreadsheet);

        return response()->stream(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$xlsxFilename}\"",
                'Cache-Control'       => 'max-age=0, no-cache, no-store',
                'Pragma'              => 'no-cache',
                'Expires'             => '0',
            ]
        );
    }

    /**
     * Convierte un número de columna (1-based) a letra(s) de Excel.
     * Ej: 1 → A, 26 → Z, 27 → AA, 703 → AAA
     */
    private static function colLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $remainder = ($colNumber - 1) % 26;
            $letter    = chr(65 + $remainder) . $letter;
            $colNumber = (int)(($colNumber - 1) / 26);
        }
        return $letter;
    }
}
