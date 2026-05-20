<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ActualizarReportesJob;

class ActualizarReportesController extends Controller
{
    private const ADMIN_ID    = 14;
    private const ADMIN_EMAIL = 'david.berumen.lozano@gmail.com';

    /**
     * Tablas soportadas con su configuración de join, filtro de tipo y campos disponibles.
     * 'campos' = columnas que existen en la tabla y pueden sincronizarse desde ERP.
     */
    private const TABLAS = [
        'salidas' => [
            'label'      => 'Salidas',
            'grupo'      => 'salidas',
            'tabla'      => 'movimiento_detalles',
            'insumo_col' => 'insumo_id',
            'obra_via'   => 'movimientos',
            'tipo'       => null,
            'campos'     => ['descripcion', 'unidad', 'familia', 'subfamilia', 'precio_unitario'],
        ],
        'transferencias_env' => [
            'label'      => 'Transferencias Enviadas',
            'grupo'      => 'salidas',
            'tabla'      => 'transferencias_entre_obras_detalle',
            'insumo_col' => 'insumo_id',
            'obra_via'   => 'transferencias',
            'tipo'       => null,
            'campos'     => ['descripcion', 'unidad', 'precio_unitario'],
        ],
        'ordenes_compra' => [
            'label'      => 'Órdenes de Compra',
            'grupo'      => 'entradas',
            'tabla'      => 'oc_recepciones',
            'insumo_col' => 'insumo',
            'obra_via'   => 'directa',
            'tipo'       => 'oc',
            'campos'     => ['descripcion', 'unidad', 'familia', 'subfamilia', 'precio_unitario'],
        ],
        'transferencias_rec' => [
            'label'      => 'Transferencias Recibidas',
            'grupo'      => 'entradas',
            'tabla'      => 'oc_recepciones',
            'insumo_col' => 'insumo',
            'obra_via'   => 'directa',
            'tipo'       => 'transferencia',
            'campos'     => ['descripcion', 'unidad', 'familia', 'subfamilia', 'precio_unitario'],
        ],
        'finiquitadas' => [
            'label'      => 'Finiquitadas',
            'grupo'      => 'entradas',
            'tabla'      => 'oc_finiquitos',
            'insumo_col' => 'insumo',
            'obra_via'   => 'directa',
            'tipo'       => null,
            'campos'     => ['descripcion', 'unidad'],
        ],
    ];

    private function auth(): void
    {
        $u = Auth::user();
        if (! $u || ($u->id !== self::ADMIN_ID && $u->email !== self::ADMIN_EMAIL)) {
            abort(403);
        }
    }

    // GET /admin/actualizar-reportes
    public function index()
    {
        $this->auth();
        $tablasConfig = collect(self::TABLAS)->map(fn($v, $k) => [
            'key'    => $k,
            'label'  => $v['label'],
            'grupo'  => $v['grupo'],
            'campos' => $v['campos'],
        ]);
        return view('admin.actualizar-reportes', compact('tablasConfig'));
    }

    // GET /admin/actualizar-reportes/obras
    public function obras()
    {
        $this->auth();
        return response()->json(
            DB::table('obras')->select('id', 'nombre')->orderBy('nombre')->get()
        );
    }

    // GET /admin/actualizar-reportes/comparar
    public function comparar(Request $request)
    {
        $this->auth();
        set_time_limit(180);

        $tablasKeys = array_values(array_intersect(
            (array) $request->input('tablas', []),
            array_keys(self::TABLAS)
        ));
        $obraIds = array_map('intval', (array) $request->input('obras', []));
        $campos  = array_values(array_intersect(
            (array) $request->input('campos', ['descripcion','unidad','familia','subfamilia','precio_unitario']),
            ['descripcion','unidad','familia','subfamilia','precio_unitario']
        ));

        if (empty($tablasKeys) || empty($obraIds) || empty($campos)) {
            return response()->json(['error' => 'Selecciona tablas, obras y campos.'], 422);
        }

        // 1) Recopilar insumos únicos de todas las tablas seleccionadas
        $merged = []; // insumo_id(raw) => [tablas[], n, local{...}]

        foreach ($tablasKeys as $key) {
            $cfg  = self::TABLAS[$key];
            $rows = $this->fetchInsumosPorTabla($cfg, $obraIds);

            foreach ($rows as $row) {
                $id = (string) ($row['insumo_id'] ?? '');
                if ($id === '') continue;

                if (! isset($merged[$id])) {
                    $merged[$id] = [
                        'insumo_id'      => $id,
                        'tablas'         => [],
                        'campos_ok'      => [],  // union de campos soportados por las tablas donde aparece
                        'n'              => 0,
                        'local'          => [
                            'descripcion'     => '',
                            'unidad'          => '',
                            'familia'         => '',
                            'subfamilia'      => '',
                            'precio_unitario' => null,
                        ],
                    ];
                }

                $merged[$id]['tablas'][]  = $key;
                $merged[$id]['n']        += (int) ($row['n'] ?? 1);
                // Acumular campos que sí existen en esta tabla
                $merged[$id]['campos_ok'] = array_values(array_unique(
                    array_merge($merged[$id]['campos_ok'], $cfg['campos'])
                ));

                // Rellenar local con primer valor no vacío encontrado
                foreach (['descripcion', 'unidad', 'familia', 'subfamilia'] as $f) {
                    if ($merged[$id]['local'][$f] === '' && ($row[$f] ?? '') !== '') {
                        $merged[$id]['local'][$f] = $row[$f];
                    }
                }
                if ($merged[$id]['local']['precio_unitario'] === null && ($row['precio_unitario'] ?? null) !== null) {
                    $merged[$id]['local']['precio_unitario'] = $row['precio_unitario'];
                }
            }
        }

        if (empty($merged)) {
            return response()->json(['items' => [], 'total' => 0, 'con_diffs' => 0, 'sin_erp' => 0]);
        }

        // 2) Fetch ERP
        $erpData = $this->fetchErp(array_keys($merged));

        // 3) Comparar
        $items    = [];
        $conDiffs = 0;
        $sinErp   = 0;

        foreach ($merged as $id => $data) {
            $erp   = $erpData[$id] ?? null;
            $diffs = [];

            if ($erp) {
                $camposOk = $data['campos_ok'];  // solo campos que existen en las tablas del insumo
                foreach (['descripcion', 'unidad', 'familia', 'subfamilia'] as $f) {
                    if (in_array($f, $campos) && in_array($f, $camposOk) && ! $this->strMatch($data['local'][$f], $erp[$f] ?? '')) {
                        $diffs[] = $f;
                    }
                }
                if (in_array('precio_unitario', $campos) && in_array('precio_unitario', $camposOk) && ! $this->numMatch($data['local']['precio_unitario'], $erp['precio_unitario'] ?? null)) {
                    $diffs[] = 'precio_unitario';
                }
            } else {
                $sinErp++;
            }

            if (! empty($diffs)) $conDiffs++;

            $items[] = [
                'insumo_id' => $id,
                'tablas'    => array_values(array_unique($data['tablas'])),
                'campos_ok' => $data['campos_ok'],
                'n'         => $data['n'],
                'en_erp'    => $erp !== null,
                'diffs'     => $diffs,
                'local'     => $data['local'],
                'erp'       => $erp,
            ];
        }

        // Primero los que tienen diffs, luego por insumo_id
        usort($items, function ($a, $b) {
            $da = count($a['diffs']);
            $db = count($b['diffs']);
            return $da !== $db ? $db <=> $da : strcmp($a['insumo_id'], $b['insumo_id']);
        });

        return response()->json([
            'items'     => $items,
            'total'     => count($items),
            'con_diffs' => $conDiffs,
            'sin_erp'   => $sinErp,
        ]);
    }

    // POST /admin/actualizar-reportes/aplicar — despacha un job, retorna token inmediatamente
    public function aplicar(Request $request)
    {
        $this->auth();

        $tablasKeys = array_values(array_intersect(
            (array) $request->input('tablas', []),
            array_keys(self::TABLAS)
        ));
        $obraIds = array_map('intval', (array) $request->input('obras', []));
        $campos  = array_values(array_intersect(
            (array) $request->input('campos', []),
            ['descripcion','unidad','familia','subfamilia','precio_unitario']
        ));
        $insumosSel = (array) $request->input('insumos', []);

        if (empty($tablasKeys) || empty($obraIds) || empty($campos) || empty($insumosSel)) {
            return response()->json(['error' => 'Parámetros insuficientes.'], 422);
        }

        $token = bin2hex(random_bytes(16));

        DB::table('masivo_procesos')->insert([
            'token'      => $token,
            'user_id'    => Auth::id(),
            'status'     => 'pendiente',
            'parametros' => json_encode([
                'tablas'  => $tablasKeys,
                'obras'   => $obraIds,
                'campos'  => $campos,
                'n_ins'   => count($insumosSel),
            ]),
            'total'       => 0,
            'procesados'  => 0,
            'actualizados'=> 0,
            'omitidos'    => 0,
            'tiempo_ms'   => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        ActualizarReportesJob::dispatch(
            $token, $tablasKeys, $obraIds, $campos, $insumosSel, Auth::id()
        )->onQueue('masivo');

        return response()->json(['token' => $token]);
    }

    // GET /admin/actualizar-reportes/estado/{token}
    public function estado(string $token)
    {
        $this->auth();

        $row = DB::table('masivo_procesos')
            ->where('token', $token)
            ->where('user_id', Auth::id())
            ->first();

        if (! $row) {
            return response()->json(['error' => 'Token no encontrado.'], 404);
        }

        $resp = [
            'status'      => $row->status,
            'actualizados'=> (int) $row->actualizados,
            'tiempo_ms'   => (int) $row->tiempo_ms,
            'error'       => $row->error ?? null,
        ];

        if ($row->status === 'completado' && $row->parametros) {
            $p = json_decode($row->parametros, true);
            $resp['totales'] = $p['totales'] ?? null;
        }

        return response()->json($resp);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Devuelve un array con una fila por insumo_id único (GROUP BY) para la tabla+obras dadas.
     * Usa MAX() para strings y AVG() para precio, así la query es una sola pasada.
     */
    private function fetchInsumosPorTabla(array $cfg, array $obraIds): array
    {
        $tabla     = $cfg['tabla'];
        $ic        = $cfg['insumo_col'];
        $hasFam    = in_array('familia',         $cfg['campos']);
        $hasSub    = in_array('subfamilia',      $cfg['campos']);
        $hasPu     = in_array('precio_unitario', $cfg['campos']);

        $famSel = $hasFam ? ", MAX([t].[familia]) as familia"                                       : ", NULL as familia";
        $subSel = $hasSub ? ", MAX([t].[subfamilia]) as subfamilia"                                 : ", NULL as subfamilia";
        $puSel  = $hasPu  ? ", AVG(CAST([t].[precio_unitario] AS float)) as precio_unitario"        : ", NULL as precio_unitario";

        $ph = implode(',', array_fill(0, count($obraIds), '?'));

        switch ($cfg['obra_via']) {
            case 'movimientos':
                $sql = "SELECT [t].[{$ic}] as insumo_id,
                               MAX([t].[descripcion]) as descripcion,
                               MAX([t].[unidad]) as unidad
                               {$famSel}{$subSel}{$puSel},
                               COUNT(*) as n
                        FROM [{$tabla}] t
                        JOIN [movimientos] m ON m.[id] = t.[movimiento_id]
                        WHERE [t].[{$ic}] IS NOT NULL AND [t].[{$ic}] != ''
                          AND m.[obra_id] IN ({$ph})
                        GROUP BY [t].[{$ic}]";
                break;

            case 'transferencias':
                $sql = "SELECT [t].[{$ic}] as insumo_id,
                               MAX([t].[descripcion]) as descripcion,
                               MAX([t].[unidad]) as unidad
                               {$famSel}{$subSel}{$puSel},
                               COUNT(*) as n
                        FROM [{$tabla}] t
                        JOIN [transferencias_entre_obras] te ON te.[id] = t.[transferencia_id]
                        WHERE [t].[{$ic}] IS NOT NULL AND [t].[{$ic}] != ''
                          AND te.[obra_origen_id] IN ({$ph})
                        GROUP BY [t].[{$ic}]";
                break;

            default: // directa
                $tipoWhere = match ($cfg['tipo'] ?? null) {
                    'oc'           => "AND ([t].[tipo] IS NULL OR [t].[tipo] IN ('oc','manual'))",
                    'transferencia'=> "AND [t].[tipo] = 'transferencia'",
                    default        => '',
                };
                $sql = "SELECT [t].[{$ic}] as insumo_id,
                               MAX([t].[descripcion]) as descripcion,
                               MAX([t].[unidad]) as unidad
                               {$famSel}{$subSel}{$puSel},
                               COUNT(*) as n
                        FROM [{$tabla}] t
                        WHERE [t].[{$ic}] IS NOT NULL AND [t].[{$ic}] != ''
                          AND [t].[obra_id] IN ({$ph})
                          {$tipoWhere}
                        GROUP BY [t].[{$ic}]";
                break;
        }

        try {
            return array_map(fn($r) => [
                'insumo_id'      => (string) $r->insumo_id,
                'descripcion'    => (string) ($r->descripcion ?? ''),
                'unidad'         => (string) ($r->unidad ?? ''),
                'familia'        => (string) ($r->familia ?? ''),
                'subfamilia'     => (string) ($r->subfamilia ?? ''),
                'precio_unitario'=> $r->precio_unitario !== null ? (float) $r->precio_unitario : null,
                'n'              => (int) $r->n,
            ], DB::select($sql, $obraIds));
        } catch (\Throwable $e) {
            Log::error('ActualizarReportes fetchInsumosPorTabla', ['tabla' => $tabla, 'err' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * UPDATE masivo con CASE WHEN en chunks de 100 para una tabla.
     * Filtra por obra y tipo según config. Sin transacción global.
     */
    private function updateTabla(array $cfg, array $obraIds, array $erpData, array $campos): int
    {
        $tabla = $cfg['tabla'];
        $ic    = $cfg['insumo_col'];
        $ph    = implode(',', array_fill(0, count($obraIds), '?'));

        $obraWhere = match ($cfg['obra_via']) {
            'movimientos'   => "AND [{$ic}] IN (SELECT md2.[insumo_id] FROM [movimientos] m2 JOIN [movimiento_detalles] md2 ON md2.[movimiento_id]=m2.[id] WHERE m2.[obra_id] IN ({$ph}))",
            'transferencias'=> "AND [transferencia_id] IN (SELECT [id] FROM [transferencias_entre_obras] WHERE [obra_origen_id] IN ({$ph}))",
            default         => "AND [obra_id] IN ({$ph})",
        };

        $tipoWhere = match ($cfg['tipo'] ?? null) {
            'oc'           => "AND ([tipo] IS NULL OR [tipo] IN ('oc','manual'))",
            'transferencia'=> "AND [tipo] = 'transferencia'",
            default        => '',
        };

        $dbColMap = [
            'descripcion'     => 'descripcion',
            'unidad'          => 'unidad',
            'familia'         => 'familia',
            'subfamilia'      => 'subfamilia',
            'precio_unitario' => 'precio_unitario',
        ];

        $total = 0;

        foreach ($campos as $campo) {
            $dbCol = $dbColMap[$campo] ?? $campo;

            // Construir mapa insumo_id => valor ERP
            $mapa = [];
            foreach ($erpData as $localId => $erp) {
                $val = $erp[$campo] ?? null;
                if ($val === null || $val === '') continue;
                $mapa[(string) $localId] = $val;
            }
            if (empty($mapa)) continue;

            foreach (array_chunk($mapa, 100, true) as $chunk) {
                $cases  = '';
                $binds  = [];
                foreach ($chunk as $insumoId => $val) {
                    $cases   .= ' WHEN ? THEN ?';
                    $binds[]  = $insumoId;
                    $binds[]  = $val;
                }
                $inPh    = implode(',', array_fill(0, count($chunk), '?'));
                $inBinds = array_keys($chunk);

                $sql = "UPDATE [{$tabla}]
                        SET [{$dbCol}] = CASE [{$ic}]{$cases} END,
                            [updated_at] = GETDATE()
                        WHERE [{$ic}] IN ({$inPh})
                        {$obraWhere}
                        {$tipoWhere}";

                try {
                    $total += DB::affectingStatement($sql, array_merge($binds, $inBinds, $obraIds));
                } catch (\Throwable $e) {
                    Log::error('ActualizarReportes update', ['tabla' => $tabla, 'campo' => $campo, 'err' => $e->getMessage()]);
                }
            }
        }

        return $total;
    }

    /** Fetch ERP data para una lista de local IDs (maneja normalización 4-dígito → 5-dígito). */
    private function fetchErp(array $localIds): array
    {
        if (empty($localIds)) return [];

        $erpToLocal = [];
        $erpIds     = [];
        foreach ($localIds as $id) {
            $erpId              = preg_replace('/-(\d{4})$/', '-0$1', (string) $id);
            $erpToLocal[$erpId] = (string) $id;
            $erpIds[]           = $erpId;
        }
        $erpIds = array_values(array_unique($erpIds));

        $result = [];
        foreach (array_chunk($erpIds, 500) as $chunk) {
            try {
                $rows = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->leftJoin('AcFamilias as FI',   'I.idFamilia', '=', 'FI.idFamilia')
                    ->leftJoin('AcCatUnidades as U',  'I.idUnidad',  '=', 'U.IdUnidad')
                    ->whereIn('I.INSUMO', $chunk)
                    ->select(
                        'I.INSUMO            as insumo',
                        'I.DescripcionLarga   as descripcion',
                        'I.Costo             as precio_unitario',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia          as subfamilia',
                        'U.Unidad            as unidad'
                    )
                    ->get();

                foreach ($rows as $row) {
                    $erpKey  = (string) $row->insumo;
                    $localId = $erpToLocal[$erpKey] ?? $erpKey;
                    $data    = [
                        'descripcion'    => trim((string) ($row->descripcion ?? '')),
                        'unidad'         => trim((string) ($row->unidad ?? '')),
                        'familia'        => trim((string) ($row->familia ?? '')),
                        'subfamilia'     => trim((string) ($row->subfamilia ?? '')),
                        'precio_unitario'=> $row->precio_unitario !== null ? (float) $row->precio_unitario : null,
                    ];
                    $result[$localId] = $data;
                    if ($localId !== $erpKey) $result[$erpKey] = $data;
                }
            } catch (\Throwable $e) {
                Log::error('ActualizarReportes fetchErp', ['err' => $e->getMessage()]);
            }
        }
        return $result;
    }

    private function strMatch(?string $a, ?string $b): bool
    {
        return mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b));
    }

    private function numMatch($a, $b, float $tol = 0.01): bool
    {
        if ($a === null && $b === null) return true;
        if ($a === null || $b === null) return false;
        return abs((float) $a - (float) $b) <= $tol;
    }
}
