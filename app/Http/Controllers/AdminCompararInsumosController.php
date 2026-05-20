<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminCompararInsumosController extends Controller
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
        return view('admin.comparar-insumos');
    }

    public function data()
    {
        $this->authorize();
        set_time_limit(120);

        // One row per inventarios record, joined with obra name
        $records = DB::table('inventarios as inv')
            ->join('obras as o', 'inv.obra_id', '=', 'o.id')
            ->whereNotNull('inv.insumo_id')
            ->where('inv.insumo_id', '!=', '')
            ->select(
                'inv.id',
                'inv.insumo_id',
                'o.nombre as obra',
                'inv.descripcion',
                'inv.unidad',
                'inv.familia',
                'inv.subfamilia',
                'inv.costo_promedio'
            )
            ->orderBy('inv.insumo_id')
            ->orderBy('o.nombre')
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['items' => [], 'total_registros' => 0, 'total_insumos' => 0, 'con_diferencias' => 0, 'sin_erp' => 0]);
        }

        $insumoIds = $records->pluck('insumo_id')->unique()->filter()->values()->toArray();
        $erpData   = $this->erpInsumos($insumoIds);

        $items = [];
        foreach ($records as $rec) {
            $erp   = $erpData[$rec->insumo_id] ?? null;
            $diffs = [];

            if ($erp) {
                if (! $this->strMatch($rec->descripcion,    $erp['descripcion']))  $diffs[] = 'descripcion';
                if (! $this->strMatch($rec->unidad,         $erp['unidad']))        $diffs[] = 'unidad';
                if (! $this->strMatch($rec->familia,        $erp['familia']))       $diffs[] = 'familia';
                if (! $this->strMatch($rec->subfamilia,     $erp['subfamilia']))    $diffs[] = 'subfamilia';
                if (! $this->numMatch($rec->costo_promedio, $erp['costo']))         $diffs[] = 'costo_promedio';
            }

            $items[] = [
                'id'          => $rec->id,
                'insumo_id'   => $rec->insumo_id,
                'obra'        => $rec->obra,
                'en_erp'      => $erp !== null,
                'diffs'       => $diffs,
                'local' => [
                    'descripcion'    => $rec->descripcion    ?? '',
                    'unidad'         => $rec->unidad         ?? '',
                    'familia'        => $rec->familia        ?? '',
                    'subfamilia'     => $rec->subfamilia     ?? '',
                    'costo_promedio' => (float) ($rec->costo_promedio ?? 0),
                ],
                'erp' => $erp ? [
                    'descripcion'    => $erp['descripcion'] ?? '',
                    'unidad'         => $erp['unidad']      ?? '',
                    'familia'        => $erp['familia']      ?? '',
                    'subfamilia'     => $erp['subfamilia']   ?? '',
                    'costo_promedio' => (float) ($erp['costo'] ?? 0),
                ] : null,
            ];
        }

        $conDiferencias = count(array_filter($items, fn($i) => count($i['diffs']) > 0));
        $sinErp         = count(array_filter($items, fn($i) => ! $i['en_erp']));

        return response()->json([
            'items'           => $items,
            'total_registros' => count($items),
            'total_insumos'   => count($insumoIds),
            'con_diferencias' => $conDiferencias,
            'sin_erp'         => $sinErp,
        ]);
    }

    public function aplicar(Request $request)
    {
        $this->authorize();
        set_time_limit(300);

        $ids    = array_map('intval', $request->input('ids', []));
        $campos = $request->input('campos', ['descripcion', 'unidad', 'familia', 'subfamilia', 'costo_promedio']);

        if (empty($ids) || empty($campos)) {
            return response()->json(['error' => 'Nada seleccionado'], 400);
        }

        $allowed = ['descripcion', 'unidad', 'familia', 'subfamilia', 'costo_promedio'];
        $campos  = array_values(array_intersect($campos, $allowed));

        // Fetch insumo_id for each selected record
        $records = DB::table('inventarios')
            ->whereIn('id', $ids)
            ->whereNotNull('insumo_id')
            ->select('id', 'insumo_id')
            ->get()
            ->keyBy('id');

        $insumoIds = $records->pluck('insumo_id')->unique()->filter()->values()->toArray();
        $erpData   = $this->erpInsumos($insumoIds);

        if (empty($erpData)) {
            return response()->json(['error' => 'No se encontraron insumos en ERP', 'actualizados' => 0]);
        }

        $t0           = microtime(true);
        $resumenCampos = [];

        foreach ($campos as $campo) {
            $erpField = $campo === 'costo_promedio' ? 'costo' : $campo;

            // cases: inventarios.id => erp_value
            $cases = [];
            foreach ($records as $id => $rec) {
                if (! isset($erpData[$rec->insumo_id])) continue;
                $val = $erpData[$rec->insumo_id][$erpField] ?? null;
                if ($val === null || $val === '') continue;
                $cases[$id] = $val;
            }

            if (empty($cases)) {
                $resumenCampos[$campo] = 0;
                continue;
            }

            $affected = 0;
            foreach (array_chunk(array_keys($cases), 100, true) as $chunk) {
                $sql      = "UPDATE inventarios SET `{$campo}` = CASE `id`";
                $bindings = [];
                foreach ($chunk as $id) {
                    $sql       .= ' WHEN ? THEN ?';
                    $bindings[] = $id;
                    $bindings[] = $cases[$id];
                }
                $inList    = implode(',', array_fill(0, count($chunk), '?'));
                $sql      .= " END WHERE `id` IN ({$inList})";
                foreach ($chunk as $id) $bindings[] = $id;

                try {
                    $affected += DB::affectingStatement($sql, $bindings);
                } catch (\Throwable $e) {
                    return response()->json(['error' => $e->getMessage()], 500);
                }
            }
            $resumenCampos[$campo] = $affected;
        }

        return response()->json([
            'ok'        => true,
            'registros' => array_sum($resumenCampos),
            'insumos'   => count($insumoIds),
            'campos'    => $resumenCampos,
            'tiempo_ms' => round((microtime(true) - $t0) * 1000),
        ]);
    }

    // ─── ERP helpers ─────────────────────────────────────────────────────────

    private function erpInsumos(array $insumoIds): array
    {
        if (empty($insumoIds)) return [];
        $result = [];
        foreach (array_chunk($insumoIds, 500) as $chunk) {
            try {
                $rows = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->leftJoin('AcFamilias as FI',   'I.idFamilia', '=', 'FI.idFamilia')
                    ->leftJoin('AcCatUnidades as U', 'I.idUnidad',  '=', 'U.IdUnidad')
                    ->whereIn('I.INSUMO', $chunk)
                    ->select(
                        'I.INSUMO as insumo',
                        'I.DescripcionLarga as descripcion',
                        'I.Costo as costo',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia as subfamilia',
                        'U.Unidad as unidad'
                    )
                    ->get();

                foreach ($rows as $row) {
                    $result[$row->insumo] = [
                        'descripcion' => (string) ($row->descripcion ?? ''),
                        'costo'       => (float)  ($row->costo       ?? 0),
                        'familia'     => (string) ($row->familia      ?? ''),
                        'subfamilia'  => (string) ($row->subfamilia   ?? ''),
                        'unidad'      => (string) ($row->unidad       ?? ''),
                    ];
                }
            } catch (\Throwable) {}
        }
        return $result;
    }

    private function strMatch(?string $a, ?string $b): bool
    {
        return strtolower(trim($a ?? '')) === strtolower(trim($b ?? ''));
    }

    private function numMatch($a, $b, float $tol = 0.01): bool
    {
        return abs((float) $a - (float) $b) <= $tol;
    }
}
