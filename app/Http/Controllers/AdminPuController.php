<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminPuController extends Controller
{
    private const ADMIN_EMAIL = 'david.berumen.lozano@gmail.com';
    private const ADMIN_ID    = 14;

    private function authorize(): void
    {
        $user = Auth::user();
        if (! $user || ($user->id !== self::ADMIN_ID && $user->email !== self::ADMIN_EMAIL)) {
            abort(403, 'Acceso no autorizado.');
        }
    }

    public function index()
    {
        $this->authorize();
        return view('admin.actualizar-pu');
    }

    public function stats()
    {
        $this->authorize();

        return response()->json([
            'entradas' => $this->statsEntradas(),
            'salidas'  => $this->statsSalidas(),
            'enviadas' => $this->statsEnviadas(),
            'recibidas'=> $this->statsRecibidas(),
        ]);
    }

    public function run(Request $request)
    {
        $this->authorize();
        set_time_limit(300);

        $tipo    = $request->input('tipo', 'todos');
        $forzado = $request->boolean('forzado', false);

        $t0      = microtime(true);
        $results = [];
        $cambios = [];

        if ($tipo === 'todos' || $tipo === 'entradas') {
            $results['entradas'] = $this->actualizarEntradas($forzado, $cambios);
        }
        if ($tipo === 'todos' || $tipo === 'salidas') {
            $results['salidas'] = $this->actualizarSalidas($forzado, $cambios);
        }
        if ($tipo === 'todos' || $tipo === 'enviadas') {
            $results['enviadas'] = $this->actualizarEnviadas($forzado, $cambios);
        }
        if ($tipo === 'todos' || $tipo === 'recibidas') {
            $results['recibidas'] = $this->actualizarRecibidas($forzado, $cambios);
        }

        $results['tiempo_ms'] = round((microtime(true) - $t0) * 1000);
        $results['cambios']   = $cambios;

        return response()->json($results);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Devuelve [insumo_id => costo] para los insumos dados, consultando el ERP en chunks. */
    private function erpCostos(array $insumoIds): array
    {
        if (empty($insumoIds)) return [];
        $result = [];
        foreach (array_chunk($insumoIds, 500) as $chunk) {
            try {
                $rows = DB::connection('erp')
                    ->table('AcCatInsumos')
                    ->whereIn('INSUMO', $chunk)
                    ->where('Costo', '>', 0)
                    ->pluck('Costo', 'INSUMO');
                foreach ($rows as $k => $v) {
                    $result[$k] = (float) $v;
                }
            } catch (\Throwable) {}
        }
        return $result;
    }


    // ─── Stats ────────────────────────────────────────────────────────────────

    private function statsEntradas(): array
    {
        // Incluye: tipo NULL (OC), 'oc', 'manual' — excluye transferencia y finiquito
        $base = DB::table('oc_recepciones')
            ->where(fn($q) => $q->whereNull('tipo')->orWhereIn('tipo', ['oc', 'manual']));
        $total  = (clone $base)->count();
        $conPu  = (clone $base)->where('precio_unitario', '>', 0)->count();
        $sinPu  = (clone $base)->whereNull('precio_unitario')->count();
        $puCero = (clone $base)->where('precio_unitario', 0)->count();

        $actualizables = (clone $base)
            ->where(fn($q) => $q->whereNull('precio_unitario')->orWhere('precio_unitario', 0))
            ->whereNotNull('insumo')->where('insumo', '!=', '')
            ->count();

        $sinCoincidencia = (clone $base)
            ->where(fn($q) => $q->whereNull('precio_unitario')->orWhere('precio_unitario', 0))
            ->where(fn($q) => $q->whereNull('insumo')->orWhere('insumo', ''))
            ->count();

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    private function statsSalidas(): array
    {
        $total    = DB::table('movimiento_detalles')->count();
        $conPu    = DB::table('movimiento_detalles')->where('precio_unitario', '>', 0)->count();
        $sinPu    = DB::table('movimiento_detalles')->whereNull('precio_unitario')->count();
        $puCero   = DB::table('movimiento_detalles')->where('precio_unitario', 0)->count();

        $actualizables = DB::table('movimiento_detalles')
            ->where(fn($q) => $q->whereNull('precio_unitario')->orWhere('precio_unitario', 0))
            ->whereNotNull('insumo_id')->where('insumo_id', '!=', '')
            ->count();

        $sinCoincidencia = DB::table('movimiento_detalles')
            ->where(fn($q) => $q->whereNull('precio_unitario')->orWhere('precio_unitario', 0))
            ->where(fn($q) => $q->whereNull('insumo_id')->orWhere('insumo_id', ''))
            ->count();

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    private function statsEnviadas(): array
    {
        $total  = DB::table('transferencias_entre_obras_detalle')->count();
        $conPu  = DB::table('transferencias_entre_obras_detalle')->where('precio_unitario', '>', 0)->count();
        $sinPu  = DB::table('transferencias_entre_obras_detalle')->whereNull('precio_unitario')->count();
        $puCero = DB::table('transferencias_entre_obras_detalle')->where('precio_unitario', 0)->count();

        // Actualizables = por insumo_id O por descripción
        $actualizables = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM transferencias_entre_obras_detalle ted
            WHERE (ted.precio_unitario IS NULL OR ted.precio_unitario = 0)
              AND (
                EXISTS (
                    SELECT 1 FROM transferencias_entre_obras te
                    INNER JOIN inventarios inv ON inv.insumo_id = ted.insumo_id AND inv.obra_id = te.obra_origen_id
                    WHERE te.id = ted.transferencia_id AND inv.costo_promedio > 0
                )
                OR EXISTS (
                    SELECT 1 FROM inventarios inv
                    WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                      AND inv.costo_promedio > 0
                )
              )
        ")->cnt ?? 0);

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM transferencias_entre_obras_detalle ted
            WHERE (ted.precio_unitario IS NULL OR ted.precio_unitario = 0)
              AND NOT EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ted.descripcion)))
                  AND inv.costo_promedio > 0
              )
        ")->cnt ?? 0);

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    private function statsRecibidas(): array
    {
        $total  = DB::table('oc_recepciones')->where('tipo', 'transferencia')->count();
        $conPu  = DB::table('oc_recepciones')->where('tipo', 'transferencia')->where('precio_unitario', '>', 0)->count();
        $sinPu  = DB::table('oc_recepciones')->where('tipo', 'transferencia')->whereNull('precio_unitario')->count();
        $puCero = DB::table('oc_recepciones')->where('tipo', 'transferencia')->where('precio_unitario', 0)->count();

        $actualizables = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            WHERE ocr.tipo = 'transferencia'
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND (
                EXISTS (
                    SELECT 1 FROM inventarios inv
                    WHERE inv.insumo_id = ocr.insumo AND inv.obra_id = ocr.obra_id
                      AND inv.costo_promedio > 0
                )
                OR EXISTS (
                    SELECT 1 FROM inventarios inv
                    WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                      AND inv.costo_promedio > 0
                )
              )
        ")->cnt ?? 0);

        $sinCoincidencia = (int)(DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM oc_recepciones ocr
            WHERE ocr.tipo = 'transferencia'
              AND (ocr.precio_unitario IS NULL OR ocr.precio_unitario = 0)
              AND NOT EXISTS (
                SELECT 1 FROM inventarios inv
                WHERE LTRIM(RTRIM(LOWER(inv.descripcion))) = LTRIM(RTRIM(LOWER(ocr.descripcion)))
                  AND inv.costo_promedio > 0
              )
        ")->cnt ?? 0);

        return compact('total', 'conPu', 'sinPu', 'puCero', 'actualizables', 'sinCoincidencia');
    }

    // ─── Updates (ERP como fuente de PU) — batch con CASE WHEN ──────────────

    private function actualizarEntradas(bool $forzado, array &$cambios): array
    {
        $vacios = $forzado ? '' : "AND (precio_unitario IS NULL OR precio_unitario = 0)";
        $tipoSQL = "(tipo IS NULL OR tipo IN ('oc', 'manual'))";

        $insumoIds = DB::table('oc_recepciones')
            ->whereRaw($tipoSQL)
            ->whereNotNull('insumo')->where('insumo', '!=', '')
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->pluck('insumo')->unique()->values()->toArray();

        $costos = $this->erpCostos($insumoIds);
        $sinCoincidencia = count(array_diff($insumoIds, array_keys($costos)));
        if (empty($costos)) return ['actualizados' => 0, 'sinCoincidencia' => $sinCoincidencia];

        // Un SELECT para obtener todos los valores anteriores
        $oldRows = DB::table('oc_recepciones')
            ->whereRaw($tipoSQL)->whereIn('insumo', array_keys($costos))
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->select('insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        // UPDATEs en chunks con CASE WHEN
        $actualizados = $this->caseWhenUpdate('oc_recepciones', 'insumo', $costos,
            "AND {$tipoSQL} {$vacios}");

        foreach ($costos as $insumoId => $puNuevo) {
            $rows = $oldRows->get($insumoId);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Entradas', $insumoId,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $puNuevo, $rows->count());
            }
        }
        return compact('actualizados', 'sinCoincidencia');
    }

    private function actualizarSalidas(bool $forzado, array &$cambios): array
    {
        $vacios = $forzado ? '' : "AND (precio_unitario IS NULL OR precio_unitario = 0)";

        $insumoIds = DB::table('movimiento_detalles')
            ->whereNotNull('insumo_id')->where('insumo_id', '!=', '')
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->pluck('insumo_id')->unique()->values()->toArray();

        $costos = $this->erpCostos($insumoIds);
        $sinCoincidencia = count(array_diff($insumoIds, array_keys($costos)));
        if (empty($costos)) return ['actualizados' => 0, 'sinCoincidencia' => $sinCoincidencia];

        $oldRows = DB::table('movimiento_detalles')
            ->whereIn('insumo_id', array_keys($costos))
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->select('insumo_id as insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('movimiento_detalles', 'insumo_id', $costos,
            $vacios ? "AND {$vacios}" : '');

        foreach ($costos as $insumoId => $puNuevo) {
            $rows = $oldRows->get($insumoId);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Salidas', $insumoId,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $puNuevo, $rows->count());
            }
        }
        return compact('actualizados', 'sinCoincidencia');
    }

    private function actualizarEnviadas(bool $forzado, array &$cambios): array
    {
        $rows = DB::table('transferencias_entre_obras_detalle')
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->select('id', 'insumo_id', 'descripcion', 'precio_unitario')
            ->get();

        if ($rows->isEmpty()) return ['actualizados' => 0, 'sinCoincidencia' => 0];

        // 1) ERP lookup by insumo_id
        $insumoIds = $rows->pluck('insumo_id')
            ->filter(fn($v) => $v !== null && (string)$v !== '')
            ->unique()->values()->toArray();
        $erpCostos = $this->erpCostos($insumoIds);

        // 2) Fallback: inventarios.costo_promedio by description for rows not found in ERP
        $sinErp = $rows->filter(fn($r) => !isset($erpCostos[(string)$r->insumo_id]));
        $descCostos = [];
        if ($sinErp->isNotEmpty()) {
            $descs = $sinErp->pluck('descripcion')->filter()->unique()->values()->toArray();
            if (!empty($descs)) {
                $invRows = DB::table('inventarios')
                    ->whereIn('descripcion', $descs)
                    ->where('costo_promedio', '>', 0)
                    ->select('descripcion', DB::raw('MAX(costo_promedio) as costo'))
                    ->groupBy('descripcion')
                    ->get();
                foreach ($invRows as $inv) {
                    $descCostos[mb_strtolower(trim((string)$inv->descripcion))] = (float)$inv->costo;
                }
            }
        }

        // 3) Build id => cost map
        $idCostos  = [];
        $cambioMap = [];
        foreach ($rows as $row) {
            $costo = $erpCostos[(string)$row->insumo_id]
                ?? $descCostos[mb_strtolower(trim((string)$row->descripcion))]
                ?? null;
            if ($costo === null) continue;

            $idCostos[$row->id] = $costo;
            $key = (string)$row->insumo_id !== '' ? (string)$row->insumo_id : mb_strtolower(trim((string)$row->descripcion));
            if (!isset($cambioMap[$key])) {
                $cambioMap[$key] = ['desc' => (string)$row->descripcion, 'viejos' => collect(), 'nuevo' => $costo, 'count' => 0];
            }
            $cambioMap[$key]['viejos']->push($row->precio_unitario);
            $cambioMap[$key]['count']++;
        }

        $sinCoincidencia = $rows->count() - count($idCostos);
        if (empty($idCostos)) return ['actualizados' => 0, 'sinCoincidencia' => $sinCoincidencia];

        $actualizados = $this->caseWhenUpdate('transferencias_entre_obras_detalle', 'id', $idCostos, '');

        foreach ($cambioMap as $insumoId => $g) {
            $cambios[] = $this->buildCambio('Enviadas', $insumoId, $g['desc'], $g['viejos'], $g['nuevo'], $g['count']);
        }

        return compact('actualizados', 'sinCoincidencia');
    }

    private function actualizarRecibidas(bool $forzado, array &$cambios): array
    {
        $vacios = $forzado ? '' : "AND (precio_unitario IS NULL OR precio_unitario = 0)";

        $insumoIds = DB::table('oc_recepciones')
            ->where('tipo', 'transferencia')
            ->whereNotNull('insumo')->where('insumo', '!=', '')
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->pluck('insumo')->unique()->values()->toArray();

        $costos = $this->erpCostos($insumoIds);
        $sinCoincidencia = count(array_diff($insumoIds, array_keys($costos)));
        if (empty($costos)) return ['actualizados' => 0, 'sinCoincidencia' => $sinCoincidencia];

        $oldRows = DB::table('oc_recepciones')
            ->where('tipo', 'transferencia')->whereIn('insumo', array_keys($costos))
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->select('insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('oc_recepciones', 'insumo', $costos,
            "AND tipo = 'transferencia'" . ($vacios ? " AND {$vacios}" : ''));

        foreach ($costos as $insumoId => $puNuevo) {
            $rows = $oldRows->get($insumoId);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Recibidas', $insumoId,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $puNuevo, $rows->count());
            }
        }
        return compact('actualizados', 'sinCoincidencia');
    }

    // ─── Override manual ─────────────────────────────────────────────────────

    public function runManual(Request $request)
    {
        $this->authorize();
        set_time_limit(300);

        $precios = $request->input('precios', []);
        if (empty($precios) || !is_array($precios)) {
            return response()->json(['error' => 'No se recibieron precios válidos.'], 422);
        }

        // Validar: claves = insumo_id (string), valores = número > 0
        $costos = [];
        foreach ($precios as $insumoId => $pu) {
            $insumoId = trim((string) $insumoId);
            $pu = (float) $pu;
            if ($insumoId !== '' && $pu > 0) {
                $costos[$insumoId] = $pu;
            }
        }

        if (empty($costos)) {
            return response()->json(['error' => 'Ningún precio válido (> 0).'], 422);
        }

        $t0      = microtime(true);
        $cambios = [];

        $entradas  = $this->aplicarManualEntradas($costos, $cambios);
        $salidas   = $this->aplicarManualSalidas($costos, $cambios);
        $enviadas  = $this->aplicarManualEnviadas($costos, $cambios);
        $recibidas = $this->aplicarManualRecibidas($costos, $cambios);

        return response()->json([
            'entradas'  => $entradas,
            'salidas'   => $salidas,
            'enviadas'  => $enviadas,
            'recibidas' => $recibidas,
            'tiempo_ms' => round((microtime(true) - $t0) * 1000),
            'cambios'   => $cambios,
        ]);
    }

    private function aplicarManualEntradas(array $costos, array &$cambios): array
    {
        $tipoSQL = "(tipo IS NULL OR tipo IN ('oc', 'manual'))";
        $oldRows = DB::table('oc_recepciones')
            ->whereRaw($tipoSQL)->whereIn('insumo', array_keys($costos))
            ->select('insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('oc_recepciones', 'insumo', $costos, "AND {$tipoSQL}");

        foreach ($costos as $id => $pu) {
            $rows = $oldRows->get($id);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Entradas', $id,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $pu, $rows->count());
            }
        }
        return ['actualizados' => $actualizados];
    }

    private function aplicarManualSalidas(array $costos, array &$cambios): array
    {
        $oldRows = DB::table('movimiento_detalles')
            ->whereIn('insumo_id', array_keys($costos))
            ->select('insumo_id as insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('movimiento_detalles', 'insumo_id', $costos, '');

        foreach ($costos as $id => $pu) {
            $rows = $oldRows->get($id);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Salidas', $id,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $pu, $rows->count());
            }
        }
        return ['actualizados' => $actualizados];
    }

    private function aplicarManualEnviadas(array $costos, array &$cambios): array
    {
        $oldRows = DB::table('transferencias_entre_obras_detalle')
            ->whereIn('insumo_id', array_keys($costos))
            ->select('insumo_id as insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('transferencias_entre_obras_detalle', 'insumo_id', $costos, '');

        foreach ($costos as $id => $pu) {
            $rows = $oldRows->get($id);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Enviadas', $id,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $pu, $rows->count());
            }
        }
        return ['actualizados' => $actualizados];
    }

    private function aplicarManualRecibidas(array $costos, array &$cambios): array
    {
        $oldRows = DB::table('oc_recepciones')
            ->where('tipo', 'transferencia')->whereIn('insumo', array_keys($costos))
            ->select('insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('oc_recepciones', 'insumo', $costos,
            "AND tipo = 'transferencia'");

        foreach ($costos as $id => $pu) {
            $rows = $oldRows->get($id);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Recibidas', $id,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $pu, $rows->count());
            }
        }
        return ['actualizados' => $actualizados];
    }

    /**
     * UPDATE masivo usando CASE WHEN en chunks de 100 insumos.
     * Evita N+1 queries — una sola pasada por chunk.
     */
    private function caseWhenUpdate(string $tabla, string $col, array $costos, string $extraWhere): int
    {
        $total = 0;
        foreach (array_chunk($costos, 100, true) as $chunk) {
            $cases   = '';
            $binds   = [];
            foreach ($chunk as $insumoId => $costo) {
                $cases   .= ' WHEN ? THEN ?';
                $binds[]  = $insumoId;
                $binds[]  = $costo;
            }
            $inList  = implode(',', array_fill(0, count($chunk), '?'));
            $inBinds = array_keys($chunk);

            $sql = "UPDATE [{$tabla}]
                    SET [precio_unitario] = CASE [{$col}]{$cases} END,
                        [updated_at] = GETDATE()
                    WHERE [{$col}] IN ({$inList})
                    {$extraWhere}";

            $total += DB::affectingStatement($sql, array_merge($binds, $inBinds));
        }
        return $total;
    }

    private function buildCambio(string $tabla, string $insumoId, string $descripcion, $puViejosCol, float $puNuevo, int $registros): array
    {
        $puViejos = $puViejosCol
            ->map(fn($p) => $p !== null ? round((float)$p, 2) : null)
            ->unique()->sort()->values()->toArray();

        return [
            'tabla'       => $tabla,
            'insumo_id'   => $insumoId,
            'descripcion' => $descripcion,
            'pu_anterior' => $puViejos,
            'pu_nuevo'    => $puNuevo,
            'registros'   => $registros,
        ];
    }

    // ─── Comparison Audit Table ───────────────────────────────────────────────

    public function preview(Request $request)
    {
        $this->authorize();
        set_time_limit(180);

        $seccion = $request->input('seccion', 'entradas');
        $allowed = ['entradas', 'salidas', 'enviadas', 'recibidas'];
        if (!in_array($seccion, $allowed, true)) {
            return response()->json(['error' => 'Sección inválida.'], 422);
        }

        $camposMap = [
            'entradas'  => ['descripcion', 'unidad', 'familia', 'subfamilia', 'pu'],
            'salidas'   => ['descripcion', 'unidad', 'familia', 'subfamilia', 'pu'],
            'enviadas'  => ['descripcion', 'unidad', 'pu'],
            'recibidas' => ['descripcion', 'unidad', 'familia', 'subfamilia', 'pu'],
        ];

        $campos = $camposMap[$seccion];

        $rows = match ($seccion) {
            'entradas'  => $this->previewEntradas(),
            'salidas'   => $this->previewSalidas(),
            'enviadas'  => $this->previewEnviadas(),
            'recibidas' => $this->previewRecibidas(),
        };

        if (empty($rows)) {
            return response()->json([]);
        }

        $insumoIds = array_unique(array_filter(array_column($rows, 'insumo_id'), fn($v) => $v !== null && $v !== ''));
        $erpData   = $this->erpInsumosFull(array_values($insumoIds));

        $result = $this->buildPreviewRows($rows, $erpData, $campos);

        // Sort: rows with diffs first, then by insumo_id
        usort($result, function ($a, $b) {
            $aDiffs = count($a['diffs']);
            $bDiffs = count($b['diffs']);
            if ($aDiffs !== $bDiffs) {
                return $bDiffs <=> $aDiffs; // more diffs first
            }
            return strcmp((string)($a['insumo_id'] ?? ''), (string)($b['insumo_id'] ?? ''));
        });

        return response()->json($result);
    }

    public function aplicarSeleccionados(Request $request)
    {
        $this->authorize();
        set_time_limit(180);

        $seccion = $request->input('seccion');
        $ids     = $request->input('ids', []);
        $campos  = $request->input('campos', []);

        $tableConfig = [
            'entradas'  => ['tabla' => 'oc_recepciones',                   'insumo_col' => 'insumo',    'campos' => ['pu','descripcion','unidad','familia','subfamilia']],
            'salidas'   => ['tabla' => 'movimiento_detalles',               'insumo_col' => 'insumo_id', 'campos' => ['pu','descripcion','unidad','familia','subfamilia']],
            'enviadas'  => ['tabla' => 'transferencias_entre_obras_detalle','insumo_col' => 'insumo_id', 'campos' => ['pu','descripcion','unidad']],
            'recibidas' => ['tabla' => 'oc_recepciones',                    'insumo_col' => 'insumo',    'campos' => ['pu','descripcion','unidad','familia','subfamilia']],
        ];

        if (!isset($tableConfig[$seccion])) {
            return response()->json(['error' => 'Sección inválida.'], 422);
        }
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['error' => 'No se recibieron IDs.'], 422);
        }
        if (empty($campos) || !is_array($campos)) {
            return response()->json(['error' => 'No se recibieron campos.'], 422);
        }

        $cfg            = $tableConfig[$seccion];
        $tabla          = $cfg['tabla'];
        $insumoCol      = $cfg['insumo_col'];
        $camposValidos  = array_intersect($campos, $cfg['campos']);

        if (empty($camposValidos)) {
            return response()->json(['error' => 'Ningún campo válido para esta sección.'], 422);
        }

        $ids = array_map('intval', $ids);

        // Get insumo_id for each row id
        $queryBuilder = DB::table($tabla)->whereIn('id', $ids)->select('id', $insumoCol . ' as insumo_id');
        if ($seccion === 'entradas') {
            $queryBuilder->where(fn($q) => $q->whereNull('tipo')->orWhereIn('tipo', ['oc', 'manual']));
        } elseif ($seccion === 'recibidas') {
            $queryBuilder->where('tipo', 'transferencia');
        }

        $dbRows   = $queryBuilder->get()->keyBy('id');
        $insumoIds = $dbRows->pluck('insumo_id')->filter(fn($v) => $v !== null && (string)$v !== '')->unique()->values()->toArray();

        if (empty($insumoIds)) {
            return response()->json(['error' => 'No se encontraron insumos para los IDs dados.'], 422);
        }

        $erpData = $this->erpInsumosFull($insumoIds);

        if (empty($erpData)) {
            return response()->json(['error' => 'No se encontraron datos en el ERP para estos insumos.'], 422);
        }

        // DB column mapping
        $colMap = ['pu' => 'precio_unitario', 'descripcion' => 'descripcion', 'unidad' => 'unidad', 'familia' => 'familia', 'subfamilia' => 'subfamilia'];

        $t0             = microtime(true);
        $totalUpdated   = 0;
        $camposCount    = array_fill_keys($camposValidos, 0);

        // Build id => erp_data map for rows that have ERP data
        $idErpMap = [];
        foreach ($dbRows as $id => $row) {
            $insumoId = (string)$row->insumo_id;
            if (isset($erpData[$insumoId])) {
                $idErpMap[$id] = $erpData[$insumoId];
            }
        }

        if (empty($idErpMap)) {
            return response()->json(['ok' => false, 'error' => 'Ningún registro tiene datos ERP.']);
        }

        // Update each campo separately using CASE WHEN by id
        foreach ($camposValidos as $campo) {
            $dbCol    = $colMap[$campo];
            $erpField = $campo; // erpData keys match campo names

            $idValues = [];
            foreach ($idErpMap as $id => $erp) {
                $idValues[$id] = $erp[$erpField] ?? null;
            }
            $idValues = array_filter($idValues, fn($v) => $v !== null);

            if (empty($idValues)) continue;

            $affected = 0;
            foreach (array_chunk($idValues, 100, true) as $chunk) {
                $cases  = '';
                $binds  = [];
                foreach ($chunk as $id => $val) {
                    $cases   .= ' WHEN ? THEN ?';
                    $binds[]  = $id;
                    $binds[]  = $val;
                }
                $inList  = implode(',', array_fill(0, count($chunk), '?'));
                $inBinds = array_keys($chunk);

                $extraWhere = '';
                if ($seccion === 'entradas') {
                    $extraWhere = "AND (tipo IS NULL OR tipo IN ('oc', 'manual'))";
                } elseif ($seccion === 'recibidas') {
                    $extraWhere = "AND tipo = 'transferencia'";
                }

                $sql = "UPDATE [{$tabla}]
                        SET [{$dbCol}] = CASE [id]{$cases} END,
                            [updated_at] = GETDATE()
                        WHERE [id] IN ({$inList})
                        {$extraWhere}";

                $affected += DB::affectingStatement($sql, array_merge($binds, $inBinds));
            }

            $camposCount[$campo] = $affected;
            $totalUpdated        = max($totalUpdated, $affected);
        }

        return response()->json([
            'ok'         => true,
            'registros'  => count($idErpMap),
            'campos'     => $camposCount,
            'tiempo_ms'  => round((microtime(true) - $t0) * 1000),
        ]);
    }

    /** Normalize a local insumo_id to ERP format: pad 4-digit suffix to 5-digit. e.g. 06ON-MAD-0003 → 06ON-MAD-00003 */
    private function normalizeToErp(string $id): string
    {
        return preg_replace('/-(\d{4})$/', '-0$1', $id);
    }

    /** Fetch full ERP data for given insumo IDs in chunks of 500. Returns [local_insumo_id => [descripcion, unidad, familia, subfamilia, pu]] */
    private function erpInsumosFull(array $ids): array
    {
        if (empty($ids)) return [];

        // Build erpId → localId reverse mapping (handles 4-digit suffix locals)
        $erpToLocal = [];
        $erpIds     = [];
        foreach ($ids as $localId) {
            $erpId              = $this->normalizeToErp((string)$localId);
            $erpToLocal[$erpId] = (string)$localId;
            $erpIds[]           = $erpId;
        }
        $erpIds = array_values(array_unique($erpIds));

        $result = [];
        foreach (array_chunk($erpIds, 500) as $chunk) {
            try {
                $rows = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->leftJoin('AcFamilias as FI', 'I.idFamilia', '=', 'FI.idFamilia')
                    ->leftJoin('AcCatUnidades as U', 'I.idUnidad', '=', 'U.IdUnidad')
                    ->whereIn('I.INSUMO', $chunk)
                    ->select(
                        'I.INSUMO as insumo',
                        'I.DescripcionLarga as descripcion',
                        'I.Costo as pu',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia as subfamilia',
                        'U.Unidad as unidad'
                    )
                    ->get();

                foreach ($rows as $row) {
                    $erpKey  = (string)$row->insumo;
                    $localId = $erpToLocal[$erpKey] ?? $erpKey;
                    $data    = [
                        'descripcion' => (string)($row->descripcion ?? ''),
                        'unidad'      => (string)($row->unidad ?? ''),
                        'familia'     => (string)($row->familia ?? ''),
                        'subfamilia'  => (string)($row->subfamilia ?? ''),
                        'pu'          => $row->pu !== null ? (float)$row->pu : null,
                    ];
                    // Index by local ID so callers can look up by their own key
                    $result[$localId] = $data;
                    // Also index by ERP key in case caller uses the 5-digit form
                    if ($localId !== $erpKey) {
                        $result[$erpKey] = $data;
                    }
                }
            } catch (\Throwable) {}
        }
        return $result;
    }

    private function strMatch(?string $a, ?string $b): bool
    {
        return mb_strtolower(trim((string)$a)) === mb_strtolower(trim((string)$b));
    }

    private function numMatch($a, $b, float $tol = 0.01): bool
    {
        if ($a === null && $b === null) return true;
        if ($a === null || $b === null) return false;
        return abs((float)$a - (float)$b) <= $tol;
    }

    private function previewEntradas(): array
    {
        return DB::table('oc_recepciones as r')
            ->join('obras as o', 'o.id', '=', 'r.obra_id')
            ->where(fn($q) => $q->whereNull('r.tipo')->orWhereIn('r.tipo', ['oc', 'manual']))
            ->whereNotNull('r.insumo')->where('r.insumo', '!=', '')
            ->orderBy('r.insumo')
            ->select(
                'r.id',
                'r.insumo as insumo_id',
                'o.nombre as obra',
                'r.fecha_recibido as fecha',
                'r.descripcion',
                'r.unidad',
                'r.familia',
                'r.subfamilia',
                'r.precio_unitario as pu'
            )
            ->get()
            ->map(fn($row) => (array)$row)
            ->toArray();
    }

    private function previewSalidas(): array
    {
        return DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->join('obras as o', 'o.id', '=', 'm.obra_id')
            ->whereNotNull('md.insumo_id')->where('md.insumo_id', '!=', '')
            ->orderBy('md.insumo_id')
            ->select(
                'md.id',
                'md.insumo_id',
                'o.nombre as obra',
                DB::raw('NULL as fecha'),
                'md.descripcion',
                'md.unidad',
                'md.familia',
                'md.subfamilia',
                'md.precio_unitario as pu'
            )
            ->get()
            ->map(fn($row) => (array)$row)
            ->toArray();
    }

    private function previewEnviadas(): array
    {
        return DB::table('transferencias_entre_obras_detalle as ted')
            ->join('transferencias_entre_obras as te', 'te.id', '=', 'ted.transferencia_id')
            ->join('obras as o', 'o.id', '=', 'te.obra_origen_id')
            ->whereNotNull('ted.insumo_id')->where('ted.insumo_id', '!=', '')
            ->orderBy('ted.insumo_id')
            ->select(
                'ted.id',
                'ted.insumo_id',
                'o.nombre as obra',
                DB::raw('NULL as fecha'),
                'ted.descripcion',
                'ted.unidad',
                DB::raw('NULL as familia'),
                DB::raw('NULL as subfamilia'),
                'ted.precio_unitario as pu'
            )
            ->get()
            ->map(fn($row) => (array)$row)
            ->toArray();
    }

    private function previewRecibidas(): array
    {
        return DB::table('oc_recepciones as r')
            ->join('obras as o', 'o.id', '=', 'r.obra_id')
            ->where('r.tipo', 'transferencia')
            ->whereNotNull('r.insumo')->where('r.insumo', '!=', '')
            ->orderBy('r.insumo')
            ->select(
                'r.id',
                'r.insumo as insumo_id',
                'o.nombre as obra',
                'r.fecha_recibido as fecha',
                'r.descripcion',
                'r.unidad',
                'r.familia',
                'r.subfamilia',
                'r.precio_unitario as pu'
            )
            ->get()
            ->map(fn($row) => (array)$row)
            ->toArray();
    }

    private function buildPreviewRows(array $rows, array $erpData, array $campos): array
    {
        $result = [];
        foreach ($rows as $row) {
            $insumoId = (string)($row['insumo_id'] ?? '');
            $erp      = $erpData[$insumoId] ?? null;
            $enErp    = $erp !== null;
            $diffs    = [];

            $sData = [];
            $eData = null;

            foreach ($campos as $campo) {
                $sVal = $campo === 'pu' ? ($row['pu'] !== null ? (float)$row['pu'] : null) : ($row[$campo] ?? null);
                $sData[$campo] = $campo === 'pu' ? ($sVal !== null ? (float)$sVal : null) : (string)($sVal ?? '');
            }

            if ($enErp) {
                $eData = [];
                foreach ($campos as $campo) {
                    $eVal = $erp[$campo] ?? null;
                    $eData[$campo] = $campo === 'pu' ? ($eVal !== null ? (float)$eVal : null) : (string)($eVal ?? '');
                }

                foreach ($campos as $campo) {
                    $sVal = $sData[$campo];
                    $eVal = $eData[$campo];
                    $same = $campo === 'pu'
                        ? $this->numMatch($sVal, $eVal)
                        : $this->strMatch((string)$sVal, (string)$eVal);
                    if (!$same) {
                        $diffs[] = $campo;
                    }
                }
            }

            $result[] = [
                'id'       => (int)$row['id'],
                'insumo_id'=> $insumoId,
                'obra'     => (string)($row['obra'] ?? ''),
                'fecha'    => $row['fecha'] ? (string)$row['fecha'] : null,
                'en_erp'   => $enErp,
                'diffs'    => $diffs,
                's'        => $sData,
                'e'        => $eData,
            ];
        }
        return $result;
    }
}
