<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminMasivoController extends Controller
{
    private const ADMIN_EMAIL = 'david.berumen.lozano@gmail.com';
    private const ADMIN_ID    = 14;

    private const CAMPOS_VALIDOS = ['descripcion', 'familia', 'subfamilia', 'unidad', 'proveedor', 'costo_promedio'];

    private function authorize(): void
    {
        $user = Auth::user();
        if (!$user || ($user->id !== self::ADMIN_ID && $user->email !== self::ADMIN_EMAIL)) {
            abort(403, 'Acceso no autorizado.');
        }
    }

    // GET /admin/actualizar-masivo/obras
    public function obras()
    {
        $this->authorize();
        return response()->json(
            DB::table('obras')->select('id', 'nombre')->orderBy('nombre')->get()
        );
    }

    // GET /admin/actualizar-masivo/analizar
    public function analizar(Request $request)
    {
        $this->authorize();
        set_time_limit(120);

        $campos  = array_intersect((array) $request->input('campos', []), self::CAMPOS_VALIDOS);
        $obraIds = array_map('intval', (array) $request->input('obras', []));

        if (empty($campos) || empty($obraIds)) {
            return response()->json(['error' => 'Selecciona al menos un campo y una obra.'], 422);
        }

        // 1) Obtener inventarios de las obras seleccionadas con insumo_id válido
        $inventarios = DB::table('inventarios as i')
            ->join('obras as o', 'o.id', '=', 'i.obra_id')
            ->whereIn('i.obra_id', $obraIds)
            ->whereNotNull('i.insumo_id')
            ->where('i.insumo_id', '!=', '')
            ->select(
                'i.id as inv_id', 'i.obra_id', 'o.nombre as obra_nombre', 'i.insumo_id',
                'i.descripcion', 'i.familia', 'i.subfamilia',
                'i.unidad', 'i.proveedor', 'i.costo_promedio'
            )
            ->get();

        if ($inventarios->isEmpty()) {
            return response()->json(['total' => 0, 'discrepancias' => 0, 'rows' => []]);
        }

        // 2) Fetch ERP data for all insumo_ids
        $insumoIds = $inventarios->pluck('insumo_id')->unique()->values()->toArray();
        $erpData   = $this->fetchErpData($insumoIds);

        // 3) Build comparison rows
        $rows          = [];
        $discrepancias = 0;

        foreach ($inventarios as $inv) {
            $erp = $erpData[$inv->insumo_id] ?? null;
            if (!$erp) continue; // skip insumos not found in ERP

            $diffs = [];
            foreach ($campos as $campo) {
                $actual = (string) ($inv->$campo ?? '');
                $nuevo  = (string) ($erp[$campo] ?? '');
                $diffs[$campo] = [
                    'actual'    => $actual,
                    'nuevo'     => $nuevo,
                    'diferente' => $this->esDiferente($actual, $nuevo, $campo),
                ];
            }

            $tieneDiff = collect($diffs)->some(fn($d) => $d['diferente']);
            if ($tieneDiff) $discrepancias++;

            $rows[] = [
                'inv_id'       => (int)  $inv->inv_id,
                'obra_id'      => (int)  $inv->obra_id,
                'obra_nombre'  => (string) $inv->obra_nombre,
                'insumo_id'    => (string) $inv->insumo_id,
                'campos'       => $diffs,
                'discrepancia' => $tieneDiff,
            ];
        }

        // Sort: discrepancias primero
        usort($rows, fn($a, $b) => $b['discrepancia'] <=> $a['discrepancia']);

        return response()->json([
            'total'         => count($rows),
            'discrepancias' => $discrepancias,
            'rows'          => $rows,
        ]);
    }

    // POST /admin/actualizar-masivo/ejecutar
    public function ejecutar(Request $request)
    {
        $this->authorize();
        set_time_limit(300);

        $campos  = array_intersect((array) $request->input('campos', []), self::CAMPOS_VALIDOS);
        $obraIds = array_map('intval', (array) $request->input('obras', []));

        if (empty($campos) || empty($obraIds)) {
            return response()->json(['error' => 'Selecciona al menos un campo y una obra.'], 422);
        }

        $t0 = microtime(true);

        // 1) Get all insumo_ids in scope
        $inventarios = DB::table('inventarios')
            ->whereIn('obra_id', $obraIds)
            ->whereNotNull('insumo_id')->where('insumo_id', '!=', '')
            ->select('id', 'obra_id', 'insumo_id')
            ->get();

        if ($inventarios->isEmpty()) {
            return response()->json(['actualizados' => 0, 'omitidos' => 0, 'tiempo_ms' => 0]);
        }

        $insumoIds = $inventarios->pluck('insumo_id')->unique()->values()->toArray();
        $erpData   = $this->fetchErpData($insumoIds);

        // 2) Build per-campo CASE WHEN maps: campo → [insumo_id => nuevo_valor]
        $mapas = [];
        foreach ($campos as $campo) {
            $mapas[$campo] = [];
        }
        foreach ($erpData as $insumoId => $erp) {
            foreach ($campos as $campo) {
                if (isset($erp[$campo]) && $erp[$campo] !== '') {
                    $mapas[$campo][$insumoId] = $erp[$campo];
                }
            }
        }

        // 3) Para cada campo, hacer UPDATE masivo con CASE WHEN por insumo_id
        $actualizados = 0;
        $omitidos     = 0;

        DB::transaction(function () use ($campos, $obraIds, $mapas, $inventarios, $erpData, &$actualizados, &$omitidos) {
            foreach ($campos as $campo) {
                $mapa = $mapas[$campo];
                if (empty($mapa)) continue;

                // Chunk por 100 insumos (evitar límite 2100 parámetros de SQL Server)
                foreach (array_chunk($mapa, 100, true) as $chunk) {
                    $cases  = '';
                    $binds  = [];
                    foreach ($chunk as $insumoId => $valor) {
                        $cases   .= ' WHEN ? THEN ?';
                        $binds[]  = $insumoId;
                        $binds[]  = $valor;
                    }
                    $inList  = implode(',', array_fill(0, count($chunk), '?'));
                    $inBinds = array_keys($chunk);
                    // Agregar obra_ids al IN de obras
                    $obraList  = implode(',', array_fill(0, count($obraIds), '?'));
                    $obraBinds = $obraIds;

                    $sql = "UPDATE [inventarios]
                            SET [{$campo}] = CASE [insumo_id]{$cases} END,
                                [updated_at] = GETDATE()
                            WHERE [insumo_id] IN ({$inList})
                              AND [obra_id]   IN ({$obraList})";

                    $n = DB::affectingStatement($sql, array_merge($binds, $inBinds, $obraBinds));
                    $actualizados += $n;
                }
            }

            // Omitidos = insumos sin coincidencia en ERP
            $sinErp   = array_diff($inventarios->pluck('insumo_id')->unique()->toArray(), array_keys($erpData));
            $omitidos = $inventarios->whereIn('insumo_id', $sinErp)->count();
        });

        Log::info('AdminMasivo ejecutado', [
            'user_id'      => Auth::id(),
            'campos'       => $campos,
            'obras'        => $obraIds,
            'actualizados' => $actualizados,
            'omitidos'     => $omitidos,
            'tiempo_ms'    => round((microtime(true) - $t0) * 1000),
        ]);

        return response()->json([
            'actualizados' => $actualizados,
            'omitidos'     => $omitidos,
            'tiempo_ms'    => round((microtime(true) - $t0) * 1000),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Fetch ERP catalog data for a list of insumo IDs, in chunks. */
    private function fetchErpData(array $insumoIds): array
    {
        $result = [];
        foreach (array_chunk($insumoIds, 500) as $chunk) {
            try {
                $rows = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->join('AcFamilias as FI',    'I.idFamilia',    '=', 'FI.idFamilia')
                    ->join('AcCatUnidades as U',  'I.idUnidad',     '=', 'U.IdUnidad')
                    ->whereIn('I.INSUMO', $chunk)
                    ->select(
                        'I.INSUMO        as insumo_id',
                        'I.DescripcionLarga as descripcion',
                        'I.Costo         as costo_promedio',
                        'U.Unidad        as unidad',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia      as subfamilia'
                    )
                    ->get();

                foreach ($rows as $row) {
                    $result[(string) $row->insumo_id] = [
                        'descripcion'    => trim((string) ($row->descripcion    ?? '')),
                        'costo_promedio' => $row->costo_promedio !== null ? (float) $row->costo_promedio : null,
                        'unidad'         => trim((string) ($row->unidad         ?? '')),
                        'familia'        => trim((string) ($row->familia        ?? '')),
                        'subfamilia'     => trim((string) ($row->subfamilia     ?? '')),
                        'proveedor'      => '', // ERP no tiene proveedor por insumo
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('AdminMasivo fetchErpData error', ['error' => $e->getMessage()]);
            }
        }
        return $result;
    }

    private function esDiferente(string $actual, string $nuevo, string $campo): bool
    {
        if ($campo === 'costo_promedio') {
            return abs((float) $actual - (float) $nuevo) > 0.001;
        }
        return mb_strtolower(trim($actual)) !== mb_strtolower(trim($nuevo));
    }
}
