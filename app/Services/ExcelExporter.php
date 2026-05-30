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
 * Uso básico (una hoja):
 *   return ExcelExporter::download(
 *       filename:    'entradas',
 *       moduleName:  'Entradas',
 *       headers:     ['Fecha', 'Código', ...],
 *       rows:        $rows,
 *       columnTypes: [0 => 'date', 6 => 'number', 7 => 'currency'],
 *       filters:     ['Obra: Oblatos', 'Desde: 2024-01-01'],
 *       color:       '4F46E5',   // hex RGB del tab + encabezado
 *   );
 *
 * Uso multi-hoja (extraSheets):
 *   extraSheets: [
 *       ['title'=>'Manuales', 'headers'=>[...], 'rows'=>[...],
 *        'columnTypes'=>[...], 'color'=>'16A34A'],
 *   ]
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
     * @param  array   $extraSheets  Hojas adicionales: [['title','headers','rows','columnTypes','color']]
     * @param  string  $color        Color hex (sin #) del tab + fila de encabezados de la hoja principal
     */
    public static function download(
        string $filename,
        string $moduleName,
        array  $headers,
        array  $rows,
        array  $columnTypes  = [],
        array  $filters      = [],
        array  $extraSheets  = [],
        string $color        = '1F2937',
        array  $columnWidths = [],
        array  $totalRow     = []   // fila de totales al final: misma estructura que una fila de datos
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet();

        $user = Auth::user();
        $now  = now()->format('d/m/Y H:i');

        // ── Hoja principal ───────────────────────────────────────────────
        $mainSheet = $spreadsheet->getActiveSheet();
        self::fillSheet($mainSheet, $moduleName, $headers, $rows, $columnTypes, $filters, $user, $now, $color, $columnWidths, $totalRow);
        $mainSheet->getTabColor()->setRGB($color);

        // ── Hojas adicionales ────────────────────────────────────────────
        foreach ($extraSheets as $extra) {
            $sheet       = $spreadsheet->createSheet();
            $sheetColor  = $extra['color'] ?? '374151';
            self::fillSheet(
                $sheet,
                $extra['title'],
                $extra['headers'],
                $extra['rows'],
                $extra['columnTypes'] ?? [],
                $filters,
                $user,
                $now,
                $sheetColor,
                $extra['columnWidths'] ?? $columnWidths,
                $extra['totalRow'] ?? []
            );
            $sheet->getTabColor()->setRGB($sheetColor);
        }

        $spreadsheet->setActiveSheetIndex(0);

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

    private static function fillSheet(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $moduleName,
        array  $headers,
        array  $rows,
        array  $columnTypes,
        array  $filters,
        $user,
        string $now,
        string $color        = '1F2937',
        array  $columnWidths = [],
        array  $totalRow     = []
    ): void {
        $userName = $user?->name ?? 'Sistema';

        $sheet->setTitle(mb_substr($moduleName, 0, 31));

        $colCount   = count($headers);
        $lastColLtr = self::colLetter($colCount);

        // ── Fila 1: Barra informativa (siempre oscura) ───────────────────
        $filtersText = empty($filters)
            ? 'Sin filtros'
            : implode('  ·  ', array_filter($filters));

        $infoText = "Sistema Almacén  |  {$moduleName}  |  Generado: {$now}  |  Usuario: {$userName}  |  {$filtersText}";

        $sheet->setCellValue('A1', $infoText);
        $sheet->mergeCells("A1:{$lastColLtr}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => false,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        // ── Fila 2: Encabezados de columna (color de la sección) ─────────
        foreach ($headers as $i => $label) {
            $sheet->setCellValue(self::colLetter($i + 1) . '2', $label);
        }

        $sheet->getStyle("A2:{$lastColLtr}2")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Auto-filter + freeze
        $sheet->setAutoFilter("A2:{$lastColLtr}2");
        $sheet->freezePane('A3');

        // ── Filas 3+: Datos ──────────────────────────────────────────────
        // Color claro derivado del color de la sección para zebra
        $zebraRgb = self::lightenHex($color, 0.92);

        foreach ($rows as $rowIdx => $rowData) {
            $excelRow = $rowIdx + 3;
            foreach ($rowData as $colIdx => $value) {
                $sheet->setCellValue(self::colLetter($colIdx + 1) . $excelRow, $value);
            }

            if ($rowIdx % 2 === 1) {
                $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($zebraRgb);
            }
        }

        // ── Fila de totales ──────────────────────────────────────────────
        if (!empty($totalRow)) {
            $totalExcelRow = count($rows) + 3;
            foreach ($totalRow as $colIdx => $value) {
                $sheet->setCellValue(self::colLetter($colIdx + 1) . $totalExcelRow, $value);
            }
            $sheet->getStyle("A{$totalExcelRow}:{$lastColLtr}{$totalExcelRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->getStyle('A' . $totalExcelRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            // Aplicar formato currency a las celdas numéricas del total
            foreach ($columnTypes as $colIdx => $type) {
                if (in_array($type, ['currency', 'number'])) {
                    $col = self::colLetter($colIdx + 1);
                    $fmt = $type === 'currency' ? '"$"#,##0.00' : '#,##0.00';
                    $sheet->getStyle($col . $totalExcelRow)
                        ->getNumberFormat()->setFormatCode($fmt);
                }
            }
        }

        // ── Formatos numéricos por columna ───────────────────────────────
        if (!empty($rows)) {
            $lastDataRow = count($rows) + 2;
            foreach ($columnTypes as $colIdx => $type) {
                $col   = self::colLetter($colIdx + 1);
                $range = "{$col}3:{$col}{$lastDataRow}";
                match ($type) {
                    'number'   => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00'),
                    'integer'  => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0'),
                    'currency' => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('"$"#,##0.00'),
                    'date'     => $sheet->getStyle($range)->getNumberFormat()->setFormatCode('DD/MM/YYYY'),
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
                        'color'       => ['rgb' => $color],
                    ],
                ],
            ]);
        }

        // ── Auto-ancho de columnas ───────────────────────────────────────
        for ($i = 1; $i <= $colCount; $i++) {
            $col = self::colLetter($i);
            $dim = $sheet->getColumnDimension($col);
            if (isset($columnWidths[$i - 1])) {
                $dim->setAutoSize(false)->setWidth($columnWidths[$i - 1]);
            } else {
                $dim->setAutoSize(true);
            }
        }
    }

    /**
     * Mezcla el color hex con blanco al porcentaje dado (0=color puro, 1=blanco puro).
     * Produce el tono pastel para el zebra striping.
     */
    private static function lightenHex(string $hex, float $ratio): string
    {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = (int) round($r + (255 - $r) * $ratio);
        $g = (int) round($g + (255 - $g) * $ratio);
        $b = (int) round($b + (255 - $b) * $ratio);

        return sprintf('%02X%02X%02X', $r, $g, $b);
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
