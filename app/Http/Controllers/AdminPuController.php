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
        $vacios = $forzado ? '' : "AND (precio_unitario IS NULL OR precio_unitario = 0)";

        $insumoIds = DB::table('transferencias_entre_obras_detalle')
            ->whereNotNull('insumo_id')->where('insumo_id', '!=', '')
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->pluck('insumo_id')->unique()->values()->toArray();

        $costos = $this->erpCostos($insumoIds);
        $sinCoincidencia = count(array_diff($insumoIds, array_keys($costos)));
        if (empty($costos)) return ['actualizados' => 0, 'sinCoincidencia' => $sinCoincidencia];

        $oldRows = DB::table('transferencias_entre_obras_detalle')
            ->whereIn('insumo_id', array_keys($costos))
            ->when(!$forzado, fn($q) => $q->where(fn($q2) => $q2->whereNull('precio_unitario')->orWhere('precio_unitario', 0)))
            ->select('insumo_id as insumo', 'descripcion', 'precio_unitario')->get()->groupBy('insumo');

        $actualizados = $this->caseWhenUpdate('transferencias_entre_obras_detalle', 'insumo_id', $costos,
            $vacios ? "AND {$vacios}" : '');

        foreach ($costos as $insumoId => $puNuevo) {
            $rows = $oldRows->get($insumoId);
            if ($rows && $rows->isNotEmpty()) {
                $cambios[] = $this->buildCambio('Enviadas', $insumoId,
                    (string)($rows->first()->descripcion ?? ''),
                    $rows->pluck('precio_unitario'), $puNuevo, $rows->count());
            }
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
}
