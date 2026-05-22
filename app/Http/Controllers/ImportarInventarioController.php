<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\Inventario;
use App\Models\Obra;

class ImportarInventarioController extends Controller
{
    private function requireAdmin(): void
    {
        if (! Auth::user()?->is_admin) abort(403);
    }

    public function index()
    {
        $this->requireAdmin();
        $obras = Obra::orderBy('nombre')->get(['id', 'nombre']);
        return view('importar-inventario.index', compact('obras'));
    }

    // ── Descargar plantilla Excel ──────────────────────────────────────────
    public function plantilla()
    {
        $this->requireAdmin();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plantilla');

        $cols = [
            'A' => 'codigo_insumo',
            'B' => 'cantidad',
            'C' => 'descripcion',
            'D' => 'unidad',
            'E' => 'pu',
            'F' => 'familia',
            'G' => 'subfamilia',
        ];

        foreach ($cols as $letter => $label) {
            $sheet->setCellValue("{$letter}1", $label);
        }

        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $examples = [
            ['13ON-001', 100,   'VARILLA CORRUGADA 3/8"',   'TON', 18500.00, 'ACERO',      'VARILLA'],
            ['13ON-002', 50,    'CEMENTO GRIS SACO 50KG',   'SAC',   145.00, 'MATERIALES', 'CEMENTO'],
            ['13ON-003', 200,   'GRAVA 3/4"',               'M3',    280.00, 'MATERIALES', 'PETREOS'],
        ];
        foreach ($examples as $ri => $row) {
            foreach (array_values($row) as $ci => $val) {
                $sheet->setCellValue(chr(65 + $ci) . ($ri + 2), $val);
            }
            if ($ri % 2 === 0) {
                $sheet->getStyle('A' . ($ri + 2) . ':G' . ($ri + 2))
                    ->applyFromArray(['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']]]);
            }
        }

        $sheet->getStyle('A1:G' . (count($examples) + 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7D2FE']]],
        ]);

        foreach (array_keys($cols) as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'plantilla_importar_inventario.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ── Paso 1: Leer encabezados + preview ────────────────────────────────
    public function analizar(Request $request)
    {
        $this->requireAdmin();
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv|max:20480']);

        $spreadsheet = IOFactory::load($request->file('archivo')->getPathname());
        $sheet       = $spreadsheet->getActiveSheet();
        $allRows     = $sheet->toArray(null, true, true, false);

        if (empty($allRows)) {
            return response()->json(['error' => 'El archivo está vacío.'], 422);
        }

        $headers = array_map(fn($h) => trim((string) ($h ?? '')), $allRows[0]);

        while (!empty($headers) && end($headers) === '') array_pop($headers);

        if (empty(array_filter($headers))) {
            return response()->json(['error' => 'No se detectaron encabezados en la primera fila.'], 422);
        }

        $dataRows   = array_slice($allRows, 1);
        $totalFilas = count(array_filter($dataRows, fn($r) => count(array_filter(array_map('strval', $r))) > 0));

        $preview = [];
        foreach (array_slice($dataRows, 0, 6) as $row) {
            $preview[] = array_slice(array_values($row), 0, count($headers));
        }

        return response()->json([
            'columnas'    => array_values($headers),
            'preview'     => $preview,
            'total_filas' => $totalFilas,
        ]);
    }

    // ── Paso 2: Validar filas + detectar conflictos ───────────────────────
    public function validar(Request $request)
    {
        $this->requireAdmin();
        $request->validate([
            'obra_id'             => 'required|integer|exists:obras,id',
            'archivo'             => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'mapeo.codigo_insumo' => 'required|integer|min:0',
            'mapeo.cantidad'      => 'required|integer|min:0',
        ]);

        $obraId = (int) $request->input('obra_id');
        $mapeo  = $request->input('mapeo', []);

        $allRows = IOFactory::load($request->file('archivo')->getPathname())
                            ->getActiveSheet()
                            ->toArray(null, true, true, false);

        if (count($allRows) < 2) {
            return response()->json(['error' => 'El archivo no contiene datos.'], 422);
        }

        $mapped = $this->buildMapped(array_slice($allRows, 1), $mapeo);
        if (empty($mapped)) {
            return response()->json(['error' => 'No se encontraron filas con código de insumo.'], 422);
        }

        // Detectar duplicados dentro del Excel
        $codigoCounts = array_count_values(array_column($mapped, 'codigo'));

        $erpMap = $this->fetchErp(array_unique(array_column($mapped, 'codigo')));

        // Cargar inventario existente para la obra seleccionada
        $codigos    = array_unique(array_column($mapped, 'codigo'));
        $invExist   = Inventario::where('obra_id', $obraId)
                        ->whereIn('insumo_id', $codigos)
                        ->get(['insumo_id', 'cantidad'])
                        ->keyBy('insumo_id');

        $resultados = [];
        foreach ($mapped as $row) {
            $resultados[] = $this->validateRow($row, $erpMap, $mapeo, $invExist, $codigoCounts);
        }

        $counts = [
            'total'       => count($resultados),
            'ok'          => count(array_filter($resultados, fn($r) => $r['estado'] === 'ok')),
            'advertencia' => count(array_filter($resultados, fn($r) => $r['estado'] === 'advertencia')),
            'error'       => count(array_filter($resultados, fn($r) => $r['estado'] === 'error')),
            'conflicto'   => count(array_filter($resultados, fn($r) => $r['conflicto'] === true)),
            'duplicado'   => count(array_filter($resultados, fn($r) => $r['duplicado_excel'] === true)),
        ];

        return response()->json(['resultados' => $resultados, 'counts' => $counts]);
    }

    // ── Paso 3: Importar ──────────────────────────────────────────────────
    public function importar(Request $request)
    {
        $this->requireAdmin();
        $request->validate([
            'obra_id'             => 'required|integer|exists:obras,id',
            'archivo'             => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'mapeo.codigo_insumo' => 'required|integer|min:0',
            'mapeo.cantidad'      => 'required|integer|min:0',
            'conflict_resolution' => 'required|in:sumar,sobrescribir,ignorar,manual',
        ]);

        $user              = Auth::user();
        $obraId            = (int) $request->input('obra_id');
        $conflictRes       = $request->input('conflict_resolution', 'ignorar');
        $overrides         = json_decode($request->input('overrides', '{}'), true) ?? [];

        $obra = Obra::find($obraId);
        if (! $obra) {
            return response()->json(['error' => 'Obra no encontrada.'], 422);
        }

        $mapeo   = $request->input('mapeo', []);
        $allRows = IOFactory::load($request->file('archivo')->getPathname())
                            ->getActiveSheet()
                            ->toArray(null, true, true, false);

        $mapped = $this->buildMapped(array_slice($allRows, 1), $mapeo);
        if (empty($mapped)) {
            return response()->json(['error' => 'No hay filas válidas para importar.'], 422);
        }

        $erpMap = $this->fetchErp(array_unique(array_column($mapped, 'codigo')));

        // Para detectar duplicados dentro del Excel, solo procesar la primera ocurrencia
        $seenCodigos = [];

        $insertados = $actualizados = $omitidos = 0;
        $now        = now();

        DB::beginTransaction();
        try {
            foreach ($mapped as $row) {
                $erp = $erpMap->get($row['codigo']);
                if (! $erp || ! is_numeric($row['cantidad']) || (float) $row['cantidad'] < 0) {
                    $omitidos++;
                    continue;
                }

                // Si es duplicado en Excel, solo procesar la primera ocurrencia
                if (in_array($row['codigo'], $seenCodigos)) {
                    $omitidos++;
                    continue;
                }
                $seenCodigos[] = $row['codigo'];

                $cantidad = (float) $row['cantidad'];
                $pu       = is_numeric($row['pu']) && (float) $row['pu'] >= 0 ? (float) $row['pu'] : null;

                $existing = Inventario::where('obra_id', $obraId)
                    ->where('insumo_id', $row['codigo'])
                    ->first();

                if ($existing) {
                    // Determinar resolución para este código
                    $resolucion = $overrides[$row['codigo']] ?? $conflictRes;

                    if ($resolucion === 'ignorar') {
                        $omitidos++;
                        continue;
                    }

                    if ($resolucion === 'sumar') {
                        $existing->cantidad         = $existing->cantidad + $cantidad;
                        $existing->cantidad_teorica = $existing->cantidad_teorica + $cantidad;
                    } else {
                        // sobrescribir (default for manual overrides too)
                        $existing->cantidad         = $cantidad;
                        $existing->cantidad_teorica = $cantidad;
                    }

                    if ($pu !== null) $existing->costo_promedio = $pu;
                    $existing->save();
                    $actualizados++;
                } else {
                    Inventario::create([
                        'obra_id'          => $obraId,
                        'insumo_id'        => $row['codigo'],
                        'descripcion'      => (string) ($erp->descripcion ?? ''),
                        'unidad'           => (string) ($erp->unidad      ?? ''),
                        'familia'          => (string) ($erp->familia     ?? ''),
                        'subfamilia'       => (string) ($erp->subfamilia  ?? ''),
                        'cantidad'         => $cantidad,
                        'cantidad_teorica' => $cantidad,
                        'en_espera'        => 0,
                        'costo_promedio'   => $pu ?? 0,
                    ]);
                    $insertados++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Importar Inventario - error', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            return response()->json(['error' => 'Error interno al importar. Intenta de nuevo.'], 500);
        }

        Log::info('Importar Inventario', [
            'user_id'            => $user->id,
            'user_name'          => $user->name,
            'obra_id'            => $obraId,
            'obra_nombre'        => $obra->nombre,
            'conflict_resolution'=> $conflictRes,
            'insertados'         => $insertados,
            'actualizados'       => $actualizados,
            'omitidos'           => $omitidos,
            'timestamp'          => $now->toIso8601String(),
        ]);

        return response()->json([
            'ok'           => true,
            'obra_nombre'  => $obra->nombre,
            'insertados'   => $insertados,
            'actualizados' => $actualizados,
            'omitidos'     => $omitidos,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function buildMapped(array $dataRows, array $mapeo): array
    {
        $idxCodigo = (int) $mapeo['codigo_insumo'];
        $idxCant   = (int) $mapeo['cantidad'];
        $idxDesc   = isset($mapeo['descripcion']) ? (int) $mapeo['descripcion'] : null;
        $idxUnidad = isset($mapeo['unidad'])      ? (int) $mapeo['unidad']      : null;
        $idxPu     = isset($mapeo['pu'])           ? (int) $mapeo['pu']          : null;
        $idxFam    = isset($mapeo['familia'])      ? (int) $mapeo['familia']     : null;
        $idxSubfam = isset($mapeo['subfamilia'])   ? (int) $mapeo['subfamilia']  : null;

        $mapped = [];
        foreach ($dataRows as $i => $raw) {
            $row    = array_values($raw);
            $codigo = trim((string) ($row[$idxCodigo] ?? ''));
            if ($codigo === '') continue;
            $mapped[] = [
                'fila'        => $i + 2,
                'codigo'      => $codigo,
                'cantidad'    => (string) ($row[$idxCant]   ?? ''),
                'descripcion' => $idxDesc   !== null ? trim((string) ($row[$idxDesc]   ?? '')) : '',
                'unidad'      => $idxUnidad !== null ? trim((string) ($row[$idxUnidad] ?? '')) : '',
                'pu'          => $idxPu     !== null ? trim((string) ($row[$idxPu]     ?? '')) : '',
                'familia'     => $idxFam    !== null ? trim((string) ($row[$idxFam]    ?? '')) : '',
                'subfamilia'  => $idxSubfam !== null ? trim((string) ($row[$idxSubfam] ?? '')) : '',
            ];
        }
        return $mapped;
    }

    private function fetchErp(array $codigos): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach (array_chunk($codigos, 800) as $chunk) {
            $batch = DB::connection('erp')
                ->table('AcCatInsumos as I')
                ->leftJoin('AcFamilias as FI',   'I.idFamilia', '=', 'FI.idFamilia')
                ->leftJoin('AcCatUnidades as U', 'I.idUnidad',  '=', 'U.IdUnidad')
                ->whereIn('I.INSUMO', $chunk)
                ->get([
                    'I.INSUMO as codigo',
                    'I.DescripcionLarga as descripcion',
                    'U.Unidad as unidad',
                    DB::raw('FI.FamiliaPrincipal as familia'),
                    DB::raw('FI.Familia as subfamilia'),
                ]);
            $result = $result->concat($batch);
        }
        return $result->keyBy('codigo');
    }

    private function validateRow(array $row, $erpMap, array $mapeo, $invExist, array $codigoCounts): array
    {
        $erp    = $erpMap->get($row['codigo']);
        $existe = $erp !== null;

        $cantOk   = is_numeric($row['cantidad']) && (float) $row['cantidad'] >= 0;
        $puOk     = null;
        $unitOk   = null;
        $descOk   = null;
        $famOk    = null;

        if (isset($mapeo['pu']) && $row['pu'] !== '') {
            $puOk = is_numeric($row['pu']) && (float) $row['pu'] >= 0;
        }

        if ($existe) {
            if (isset($mapeo['unidad']) && $row['unidad'] !== '') {
                $unitOk = mb_strtoupper(trim($row['unidad'])) === mb_strtoupper(trim($erp->unidad ?? ''));
            }
            if (isset($mapeo['descripcion']) && $row['descripcion'] !== '') {
                $haystack = mb_strtolower(trim($erp->descripcion ?? ''));
                $needle   = mb_strtolower(trim($row['descripcion']));
                $descOk   = str_contains($haystack, $needle) || str_contains($needle, $haystack);
            }
            if (isset($mapeo['familia']) && $row['familia'] !== '') {
                $famOk = mb_strtoupper(trim($row['familia'])) === mb_strtoupper(trim($erp->familia ?? ''));
            }
        }

        // Conflicto: insumo ya existe en el inventario de la obra
        $conflicto         = false;
        $cantidad_actual   = null;
        if ($existe && $invExist->has($row['codigo'])) {
            $conflicto       = true;
            $cantidad_actual = (float) $invExist->get($row['codigo'])->cantidad;
        }

        // Duplicado dentro del Excel
        $duplicado_excel = ($codigoCounts[$row['codigo']] ?? 1) > 1;

        $estado  = 'ok';
        $errores = [];

        if (! $existe) {
            $estado    = 'error';
            $errores[] = 'Código no encontrado en ERP';
        }
        if (! $cantOk) {
            $estado    = 'error';
            $errores[] = 'Cantidad inválida: "' . $row['cantidad'] . '"';
        }
        if ($puOk === false) {
            $estado    = 'error';
            $errores[] = 'P.U. inválido: "' . $row['pu'] . '" (debe ser número ≥ 0)';
        }
        if ($unitOk === false) {
            $estado    = 'error';
            $errores[] = 'Unidad no coincide — Excel: "' . $row['unidad'] . '" / ERP: "' . ($erp->unidad ?? '') . '"';
        }
        if ($descOk === false) {
            if ($estado === 'ok') $estado = 'advertencia';
            $errores[] = 'Descripción difiere del ERP';
        }
        if ($famOk === false) {
            if ($estado === 'ok') $estado = 'advertencia';
            $errores[] = 'Familia difiere — Excel: "' . $row['familia'] . '" / ERP: "' . ($erp->familia ?? '') . '"';
        }
        if ($duplicado_excel && $estado === 'ok') {
            $estado    = 'advertencia';
            $errores[] = 'Código duplicado en el archivo — solo se procesará la primera ocurrencia';
        }

        return [
            'fila'            => $row['fila'],
            'codigo'          => $row['codigo'],
            'cantidad'        => $cantOk ? (float) $row['cantidad'] : null,
            'pu'              => (isset($mapeo['pu']) && is_numeric($row['pu']) && (float) $row['pu'] >= 0) ? (float) $row['pu'] : null,
            'descripcion_erp' => $existe ? ($erp->descripcion ?? '') : null,
            'unidad_erp'      => $existe ? ($erp->unidad      ?? '') : null,
            'familia_erp'     => $existe ? ($erp->familia     ?? '') : null,
            'subfamilia_erp'  => $existe ? ($erp->subfamilia  ?? '') : null,
            'erp_existe'      => $existe,
            'unidad_ok'       => $unitOk,
            'desc_ok'         => $descOk,
            'fam_ok'          => $famOk,
            'cantidad_ok'     => $cantOk,
            'pu_ok'           => $puOk,
            'estado'          => $estado,
            'errores'         => $errores,
            'conflicto'       => $conflicto,
            'cantidad_actual' => $cantidad_actual,
            'duplicado_excel' => $duplicado_excel,
        ];
    }
}
