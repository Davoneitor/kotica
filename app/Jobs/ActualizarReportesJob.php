<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActualizarReportesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    private const TABLAS = [
        'salidas' => [
            'label'      => 'Salidas',
            'tabla'      => 'movimiento_detalles',
            'insumo_col' => 'insumo_id',
            'obra_via'   => 'movimientos',
            'tipo'       => null,
            'campos'     => ['descripcion', 'unidad', 'familia', 'subfamilia', 'precio_unitario'],
        ],
        'transferencias_env' => [
            'label'      => 'Transferencias Enviadas',
            'tabla'      => 'transferencias_entre_obras_detalle',
            'insumo_col' => 'insumo_id',
            'obra_via'   => 'transferencias',
            'tipo'       => null,
            'campos'     => ['descripcion', 'unidad', 'precio_unitario'],
        ],
        'ordenes_compra' => [
            'label'      => 'Órdenes de Compra',
            'tabla'      => 'oc_recepciones',
            'insumo_col' => 'insumo',
            'obra_via'   => 'directa',
            'tipo'       => 'oc',
            'campos'     => ['descripcion', 'unidad', 'familia', 'subfamilia', 'precio_unitario'],
        ],
        'transferencias_rec' => [
            'label'      => 'Transferencias Recibidas',
            'tabla'      => 'oc_recepciones',
            'insumo_col' => 'insumo',
            'obra_via'   => 'directa',
            'tipo'       => 'transferencia',
            'campos'     => ['descripcion', 'unidad', 'familia', 'subfamilia', 'precio_unitario'],
        ],
        'finiquitadas' => [
            'label'      => 'Finiquitadas',
            'tabla'      => 'oc_finiquitos',
            'insumo_col' => 'insumo',
            'obra_via'   => 'directa',
            'tipo'       => null,
            'campos'     => ['descripcion', 'unidad'],
        ],
    ];

    public function __construct(
        private string $token,
        private array  $tablasKeys,
        private array  $obraIds,
        private array  $campos,
        private array  $insumosSel,
        private int    $userId
    ) {}

    public function handle(): void
    {
        $t0 = microtime(true);

        try {
            DB::table('masivo_procesos')
                ->where('token', $this->token)
                ->update(['status' => 'procesando']);

            // Si se pidieron 'todos', recolectar insumo_ids de las tablas
            $insumosSel = $this->insumosSel;
            if ($insumosSel === ['todos']) {
                $todos = [];
                foreach ($this->tablasKeys as $key) {
                    $rows = $this->fetchInsumosPorTabla(self::TABLAS[$key], $this->obraIds);
                    foreach ($rows as $r) {
                        if (($r['insumo_id'] ?? '') !== '') $todos[] = (string) $r['insumo_id'];
                    }
                }
                $insumosSel = array_values(array_unique($todos));
            }

            if (empty($insumosSel)) {
                DB::table('masivo_procesos')->where('token', $this->token)->update([
                    'status' => 'error',
                    'error'  => 'No se encontraron insumos.',
                ]);
                return;
            }

            $proyectos = DB::table('obras')
                ->whereIn('id', $this->obraIds)
                ->whereNotNull('erp_proyecto_id')
                ->pluck('erp_proyecto_id')
                ->toArray();
            $erpData = $this->fetchErp($insumosSel, $proyectos);
            if (empty($erpData)) {
                DB::table('masivo_procesos')->where('token', $this->token)->update([
                    'status' => 'error',
                    'error'  => 'Sin datos en ERP para los insumos seleccionados.',
                ]);
                return;
            }

            $totales = [];
            foreach ($this->tablasKeys as $key) {
                $cfg         = self::TABLAS[$key];
                $camposTabla = array_values(array_intersect($this->campos, $cfg['campos']));
                $totales[$key] = empty($camposTabla) ? 0 : $this->updateTabla($cfg, $this->obraIds, $erpData, $camposTabla);
            }

            $tiempoMs = round((microtime(true) - $t0) * 1000);

            Log::info('ActualizarReportesJob::done', [
                'token'     => $this->token,
                'user_id'   => $this->userId,
                'tablas'    => $this->tablasKeys,
                'obras'     => $this->obraIds,
                'campos'    => $this->campos,
                'n_insumos' => count($insumosSel),
                'totales'   => $totales,
                'tiempo_ms' => $tiempoMs,
            ]);

            DB::table('masivo_procesos')->where('token', $this->token)->update([
                'status'      => 'completado',
                'actualizados'=> array_sum($totales),
                'tiempo_ms'   => $tiempoMs,
                'parametros'  => json_encode(['totales' => $totales]),
            ]);
        } catch (\Throwable $e) {
            Log::error('ActualizarReportesJob::error', [
                'token' => $this->token,
                'err'   => $e->getMessage(),
            ]);
            DB::table('masivo_procesos')->where('token', $this->token)->update([
                'status' => 'error',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // ─── Helpers (duplicados desde el controller para correr fuera de HTTP) ───

    private function fetchInsumosPorTabla(array $cfg, array $obraIds): array
    {
        $tabla  = $cfg['tabla'];
        $ic     = $cfg['insumo_col'];
        $hasFam = in_array('familia',         $cfg['campos']);
        $hasSub = in_array('subfamilia',      $cfg['campos']);
        $hasPu  = in_array('precio_unitario', $cfg['campos']);

        $famSel = $hasFam ? ", MAX([t].[familia]) as familia"                                : ", NULL as familia";
        $subSel = $hasSub ? ", MAX([t].[subfamilia]) as subfamilia"                          : ", NULL as subfamilia";
        $puSel  = $hasPu  ? ", AVG(CAST([t].[precio_unitario] AS float)) as precio_unitario" : ", NULL as precio_unitario";

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

            default:
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
            Log::error('ActualizarReportesJob fetchInsumosPorTabla', ['tabla' => $tabla, 'err' => $e->getMessage()]);
            return [];
        }
    }

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

        $total = 0;

        foreach ($campos as $campo) {
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
                        SET [{$campo}] = CASE [{$ic}]{$cases} END,
                            [updated_at] = GETDATE()
                        WHERE [{$ic}] IN ({$inPh})
                        {$obraWhere}
                        {$tipoWhere}";

                try {
                    $total += DB::affectingStatement($sql, array_merge($binds, $inBinds, $obraIds));
                } catch (\Throwable $e) {
                    Log::error('ActualizarReportesJob update', ['tabla' => $tabla, 'campo' => $campo, 'err' => $e->getMessage()]);
                }
            }
        }

        return $total;
    }

    private function fetchErp(array $localIds, array $proyectos = []): array
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
                // 1. P.U. real desde ViewPUC filtrado por proyectos de las obras seleccionadas
                $pucRows = DB::connection('erp')
                    ->table('ViewPUC')
                    ->whereIn('Insumo', $chunk)
                    ->when(!empty($proyectos), fn($q) => $q->whereIn('Proyecto', $proyectos))
                    ->select(
                        'Insumo             as insumo',
                        'Costo_ultima_compra as precio_unitario',
                        'Fecha_ultima_compra as fecha_pu'
                    )
                    ->orderByDesc('Fecha_ultima_compra')
                    ->get();

                $pucMap = [];
                foreach ($pucRows as $row) {
                    $k = (string) $row->insumo;
                    if (!isset($pucMap[$k])) {
                        $pucMap[$k] = $row;
                    }
                }

                // 2. Descripción, familia, subfamilia y unidad desde AcCatInsumos
                $catRows = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->leftJoin('AcFamilias as FI',   'I.idFamilia', '=', 'FI.idFamilia')
                    ->leftJoin('AcCatUnidades as U',  'I.idUnidad',  '=', 'U.IdUnidad')
                    ->whereIn('I.INSUMO', $chunk)
                    ->select(
                        'I.INSUMO           as insumo',
                        'I.DescripcionLarga  as descripcion',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia         as subfamilia',
                        'U.Unidad           as unidad'
                    )
                    ->get()
                    ->keyBy('insumo');

                // 3. Combinar
                $allInsumos = array_unique(array_merge(array_keys($pucMap), $catRows->keys()->toArray()));

                foreach ($allInsumos as $erpKey) {
                    $puc = $pucMap[$erpKey] ?? null;
                    $cat = $catRows->get($erpKey);
                    $localId = $erpToLocal[$erpKey] ?? $erpKey;

                    $data = [
                        'descripcion'     => trim((string) ($cat?->descripcion ?? '')),
                        'unidad'          => trim((string) ($cat?->unidad ?? '')),
                        'familia'         => trim((string) ($cat?->familia ?? '')),
                        'subfamilia'      => trim((string) ($cat?->subfamilia ?? '')),
                        'precio_unitario' => $puc && $puc->precio_unitario !== null ? (float) $puc->precio_unitario : null,
                    ];
                    $result[$localId] = $data;
                    if ($localId !== $erpKey) $result[$erpKey] = $data;
                }
            } catch (\Throwable $e) {
                Log::error('ActualizarReportesJob fetchErp', ['err' => $e->getMessage()]);
            }
        }
        return $result;
    }
}
