<?php

namespace App\Http\Controllers;

use App\Models\AjusteSalida;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\MovimientoDetalle;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\OcRecepcion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Services\ExcelExporter;


class ExploreController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $obraActualId = $user->obra_actual_id;
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;
        $obras = Obra::orderBy('nombre')->get(['id', 'nombre']);

        return view('explore.explore', compact('obraActual', 'obras'));
    }

    /**
     * Resuelve nombres legibles de destino desde la tabla ERP PROYECTOS.
     * Devuelve [IdProyecto => "Tipo / Proyecto"]
     */
    private function resolverNombresDestino(array $ids): array
    {
        if (empty($ids)) return [];

        try {
            $rows = DB::connection('erp')
                ->table('PROYECTOS as Proy')
                ->join('AOTipoProyectos as TProy', 'Proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
                ->whereIn('Proy.IdProyecto', $ids)
                ->select('Proy.IdProyecto', 'Proy.Proyecto', 'TProy.Texto as Tipo')
                ->get();

            $tipoLabel = [
                '100 Obra'   => 'Obra',
                'Almacen'    => 'Almacén',
                'Desarrollo' => 'Desarrollo',
            ];

            $map = [];
            foreach ($rows as $row) {
                $map[$row->IdProyecto] = $row->Proyecto;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

   /**
 * MOVIMIENTOS (local) -> en Explore se llama "Salidas"
 * ? Ahora regresa tambi�n: obra (nombre)
 */
public function movimientos(Request $request)
{
    $obraId = Auth::user()?->obra_actual_id;

    $q     = trim((string) $request->get('q', ''));
    $desde = $request->get('desde');
    $hasta = $request->get('hasta');

    $rows = Movimiento::query()
        ->leftJoin('obras as o', 'o.id', '=', 'movimientos.obra_id')
        ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('movimientos.nombre_cabo', 'like', "%{$q}%")
                  ->orWhere('movimientos.destino', 'like', "%{$q}%")
                  ->orWhere('o.nombre', 'like', "%{$q}%");
            });
        })
        ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
        ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
        ->orderByDesc('movimientos.fecha')
        ->get([
            'movimientos.id',
            'movimientos.obra_id',
            'movimientos.user_id',
            'movimientos.fecha',
            'movimientos.destino',
            'movimientos.nombre_cabo',
            'movimientos.estatus',
            'movimientos.observaciones',
            DB::raw('o.nombre as obra'), // ? nombre de obra
        ]);

    // Resolver nombres legibles de destino desde ERP
    $erpNombres = $this->resolverNombresDestino(
        $rows->pluck('destino')->filter()->unique()->values()->toArray()
    );

    // Contar ajustes por movimiento
    $ids = $rows->pluck('id')->toArray();
    $ajustesCounts = AjusteSalida::whereIn('movimiento_id', $ids)
        ->selectRaw('movimiento_id, COUNT(*) as total, SUM(cantidad_devuelta) as total_devuelto')
        ->groupBy('movimiento_id')
        ->get()
        ->keyBy('movimiento_id');

    $rows = $rows->map(function ($row) use ($erpNombres, $ajustesCounts) {
        $row->destino_nombre   = $erpNombres[$row->destino] ?? $row->destino;
        $ajuste = $ajustesCounts->get($row->id);
        $row->tiene_ajustes    = $ajuste ? true : false;
        $row->num_ajustes      = $ajuste ? (int)$ajuste->total : 0;
        $row->total_devuelto   = $ajuste ? (float)$ajuste->total_devuelto : 0;
        return $row;
    });

    return response()->json($rows->values());
}


/**
 * DETALLES de un movimiento
 * ? Ahora regresa:
 *  - obra_id, obra (nombre) en el JSON
 *  - detalles como antes
 */
public function movimientoDetalles(Movimiento $movimiento)
{
    $obraId = Auth::user()?->obra_actual_id;

    if ($obraId && (int)$movimiento->obra_id !== (int)$obraId) {
        abort(403);
    }

    // ? obtener nombre de obra (sin depender de relaci�n)
    $obraNombre = null;
    if ($movimiento->obra_id) {
        $obraNombre = Obra::where('id', $movimiento->obra_id)->value('nombre');
    }

    $det = MovimientoDetalle::where('movimiento_id', $movimiento->id)
        ->with('destinos')
        ->orderBy('id')
        ->get([
            'id','movimiento_id','inventario_id','familia','subfamilia',
            'descripcion','unidad','cantidad','devolvible','clasificacion','clasificacion_d'
        ]);

    $erpNombres = $this->resolverNombresDestino(
        array_filter([$movimiento->destino])
    );

    $detallesConDestinos = $det->map(function ($d) {
        return [
            'id'            => (int) $d->id,
            'movimiento_id' => (int) $d->movimiento_id,
            'inventario_id' => $d->inventario_id,
            'familia'       => $d->familia,
            'subfamilia'    => $d->subfamilia,
            'descripcion'   => $d->descripcion,
            'unidad'        => $d->unidad,
            'cantidad'      => $d->cantidad,
            'devolvible'    => $d->devolvible,
            'clasificacion'   => $d->clasificacion,
            'clasificacion_d' => $d->clasificacion_d,
            'destinos' => $d->destinos->map(fn ($dest) => [
                'id'           => (int) $dest->id,
                'nivel'        => $dest->nivel,
                'departamento' => $dest->departamento,
                'cantidad'     => $dest->cantidad,
            ])->values(),
        ];
    });

    return response()->json([
        'movimiento' => [
            'id' => (int) $movimiento->id,
            'obra_id' => (int) $movimiento->obra_id,
            'obra' => $obraNombre,
            'destino' => $movimiento->destino,
            'destino_nombre' => $erpNombres[$movimiento->destino] ?? $movimiento->destino,
            'fecha' => (string) $movimiento->fecha,
            'nombre_cabo' => $movimiento->nombre_cabo,
            'estatus' => $movimiento->estatus,
        ],
        'detalles' => $detallesConDestinos,
    ]);
}


    /**
     * TABLA DE SALIDAS: detalles planos agrupables por insumo con P.U. de inventario
     */
    public function salidasTabla(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');
        $q      = trim((string) $request->get('q', ''));
        $soloH  = $request->boolean('solo_h');

        $rows = MovimientoDetalle::query()
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->leftJoin('inventarios', 'inventarios.id', '=', 'movimiento_detalles.inventario_id')
            ->leftJoin('users', 'users.id', '=', 'movimientos.user_id')
            ->leftJoin('obras as obs_s', 'obs_s.id', '=', 'movimientos.obra_id')
            ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($soloH, fn($qq) => $qq->where('movimiento_detalles.devolvible', 1))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('movimiento_detalles.descripcion',     'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.inventario_id',  'like', "%{$q}%")
                      ->orWhere('inventarios.insumo_id',              'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.clasificacion',   'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.clasificacion_d', 'like', "%{$q}%")
                      ->orWhere('movimientos.destino',                 'like', "%{$q}%")
                      ->orWhere('movimientos.nombre_cabo',             'like', "%{$q}%");
                });
            })
            ->orderByDesc('movimientos.fecha')
            ->orderByDesc('movimientos.id')
            ->get([
                'movimiento_detalles.id',
                'movimiento_detalles.movimiento_id',
                'movimiento_detalles.inventario_id',
                'movimiento_detalles.familia',
                'movimiento_detalles.subfamilia',
                'movimiento_detalles.descripcion',
                'movimiento_detalles.unidad',
                'movimiento_detalles.cantidad',
                'movimiento_detalles.precio_unitario',
                'movimiento_detalles.clasificacion',
                'movimiento_detalles.clasificacion_d',
                'movimiento_detalles.devolvible',
                'movimientos.fecha',
                'movimientos.destino',
                'movimientos.nombre_cabo',
                'movimientos.observaciones',
                DB::raw('inventarios.insumo_id as codigo_insumo'),
                DB::raw('users.name as usuario'),
                DB::raw('obs_s.nombre as obra_nombre'),
            ]);

        return response()->json($rows->map(fn($r) => [
            'id'              => $r->id,
            'movimiento_id'   => (int) $r->movimiento_id,
            'fecha'           => (string) $r->fecha,
            'obra'            => (string) ($r->obra_nombre  ?? 'SIN OBRA'),
            'destino'         => (string) ($r->destino      ?? ''),
            'nombre_cabo'     => (string) ($r->nombre_cabo  ?? ''),
            'usuario'         => (string) ($r->usuario      ?? ''),
            'familia'         => (string) ($r->familia       ?? 'SIN FAMILIA'),
            'subfamilia'      => (string) ($r->subfamilia    ?? ''),
            'insumo_id'       => (string) ($r->codigo_insumo ?? $r->inventario_id ?? ''),
            'descripcion'     => (string) $r->descripcion,
            'unidad'          => (string) $r->unidad,
            'cantidad'        => (float)  $r->cantidad,
            'precio_unitario' => $r->precio_unitario !== null ? (float) $r->precio_unitario : null,
            'importe'         => $r->precio_unitario !== null
                                    ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                                    : null,
            'devolvible'      => (int) ($r->devolvible ?? 0),
            'observaciones'   => (string) ($r->observaciones ?? ''),
        ])->values());
    }

    /**
     * Retornables pendientes (devolvible=1, no recuperados) para la obra actual.
     * JSON — usado por explore y módulo retornables.
     */
    public function retornables(Request $request)
    {
        $obraId   = Auth::user()?->obra_actual_id;
        $desde    = $request->get('desde');
        $hasta    = $request->get('hasta');
        $qPersona = trim((string) $request->get('persona', ''));
        $qInsumo  = trim((string) $request->get('insumo', ''));

        $rows = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->join('inventarios as i', 'i.id', '=', 'md.inventario_id')
            ->where('md.devolvible', 1)
            ->when($obraId, fn($q) => $q->where('m.obra_id', $obraId))
            ->when($qPersona !== '', fn($q) => $q->where('m.nombre_cabo', 'like', "%{$qPersona}%"))
            ->when($qInsumo !== '', function ($q) use ($qInsumo) {
                $q->where(function ($w) use ($qInsumo) {
                    $w->where('md.descripcion', 'like', "%{$qInsumo}%")
                      ->orWhere('i.insumo_id',  'like', "%{$qInsumo}%");
                });
            })
            ->when($desde, fn($q) => $q->whereDate('m.fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('m.fecha', '<=', $hasta))
            ->select([
                'md.id as detalle_id',
                'i.insumo_id',
                'md.descripcion',
                'md.unidad',
                'md.cantidad',
                'm.nombre_cabo',
                'md.movimiento_id',
                'm.fecha',
                DB::raw('DATEDIFF(day, m.fecha, GETDATE()) as dias'),
            ])
            ->orderByDesc('m.fecha')
            ->get();

        return response()->json($rows->map(fn($r) => [
            'detalle_id'   => $r->detalle_id,
            'insumo_id'    => (string) ($r->insumo_id   ?? ''),
            'descripcion'  => (string)  $r->descripcion,
            'unidad'       => (string)  $r->unidad,
            'cantidad'     => (float)   $r->cantidad,
            'nombre_cabo'  => (string) ($r->nombre_cabo ?? ''),
            'movimiento_id'=> $r->movimiento_id,
            'fecha'        => (string)  $r->fecha,
            'dias'         => (int)     ($r->dias ?? 0),
        ])->values());
    }

    /**
     * Transferencias ENVIADAS en formato detalle-por-insumo (para tabla de salidas).
     */
    public function transferenciasEnviadasTabla(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');
        $q      = trim((string) $request->get('q', ''));

        $rows = DB::table('transferencias_entre_obras_detalle as d')
            ->join('transferencias_entre_obras as t',   't.id',   '=', 'd.transferencia_id')
            ->join('obras as od',  'od.id',  '=', 't.obra_destino_id')
            ->join('obras as oo',  'oo.id',  '=', 't.obra_origen_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->leftJoin('inventarios as inv', function ($join) use ($obraId) {
                $join->on('inv.insumo_id', '=', 'd.insumo_id')
                     ->where('inv.obra_id', '=', $obraId);
            })
            ->where('t.obra_origen_id', $obraId)
            ->when($desde, fn($q2) => $q2->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('t.fecha', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('d.descripcion',  'like', "%{$q}%")
                      ->orWhere('d.insumo_id',  'like', "%{$q}%")
                      ->orWhere('od.nombre',    'like', "%{$q}%");
                });
            })
            ->orderByDesc('t.fecha')
            ->orderByDesc('t.id')
            ->select([
                'd.id',
                't.id as transferencia_id',
                't.fecha',
                'd.insumo_id',
                'd.descripcion',
                'd.unidad',
                'd.cantidad',
                'd.precio_unitario',
                DB::raw('od.nombre as obra_destino'),
                DB::raw('oo.nombre as obra_origen'),
                DB::raw('u.name as usuario'),
                DB::raw("ISNULL(inv.familia, 'SIN FAMILIA') as familia"),
                't.observaciones',
            ])
            ->get();

        return response()->json($rows->map(fn($r) => [
            'id'              => $r->id,
            'transferencia_id'=> (int)    $r->transferencia_id,
            'fecha'           => (string) $r->fecha,
            'insumo_id'       => (string) ($r->insumo_id    ?? ''),
            'descripcion'     => (string)  $r->descripcion,
            'unidad'          => (string)  $r->unidad,
            'cantidad'        => (float)   $r->cantidad,
            'precio_unitario' => $r->precio_unitario !== null ? (float) $r->precio_unitario : null,
            'importe'         => $r->precio_unitario !== null
                                    ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                                    : null,
            'familia'         => (string) ($r->familia      ?? 'SIN FAMILIA'),
            'obra_destino'    => (string) ($r->obra_destino ?? ''),
            'obra_origen'     => (string) ($r->obra_origen  ?? ''),
            'usuario'         => (string) ($r->usuario      ?? ''),
            'observaciones'   => (string) ($r->observaciones ?? ''),
        ])->values());
    }

    /**
     * INVENTARIO (local) búsqueda por id o descripción
     */
    public function inventario(Request $request)
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        $q        = trim((string) $request->get('q', ''));
        $soloH    = $request->boolean('solo_h');
        $agrupado = $request->boolean('agrupado');

        // Soporta "#RP-80-12"
        if (str_starts_with($q, '#')) {
            $q = trim(substr($q, 1));
        }

        $query = Inventario::query()
            ->when($obraId, fn($qq) => $qq->where('obra_id', $obraId))
            ->when($soloH, fn($qq) => $qq->where('devolvible', 1))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('insumo_id', 'like', "%{$q}%")
                      ->orWhere('descripcion', 'like', "%{$q}%");
                });
            });

        if ($agrupado) {
            $rows = $query
                ->orderBy('familia')
                ->orderBy('subfamilia')
                ->orderBy('descripcion')
                ->get([
                    'id','insumo_id','familia','subfamilia','descripcion','descripcionauxiliar',
                    'unidad','cantidad','cantidad_teorica','en_espera','costo_promedio',
                    'destino','proveedor','devolvible','obsoleto','updated_at',
                ]);
        } else {
            $rows = $query
                ->orderByDesc('updated_at')
                ->get([
                    'id','insumo_id','familia','subfamilia','descripcion','descripcionauxiliar',
                    'unidad','cantidad','cantidad_teorica','en_espera','costo_promedio',
                    'destino','proveedor','devolvible','obsoleto','updated_at',
                ]);
        }

        // Total importe real de TODOS los registros (sin limit) para mostrar en la UI
        $totalImporte = (float) Inventario::query()
            ->when($obraId, fn($qq) => $qq->where('obra_id', $obraId))
            ->when($soloH,  fn($qq) => $qq->where('devolvible', 1))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('insumo_id',  'like', "%{$q}%")
                      ->orWhere('descripcion', 'like', "%{$q}%");
                });
            })
            ->whereNotNull('costo_promedio')
            ->selectRaw('SUM(cantidad * costo_promedio) as total')
            ->value('total') ?? 0;

        return response()->json([
            'rows'          => $rows->map(fn($r) => [
                'id'                  => $r->id,
                'insumo_id'           => (string) ($r->insumo_id ?? ''),
                'familia'             => (string) ($r->familia ?? ''),
                'subfamilia'          => (string) ($r->subfamilia ?? ''),
                'descripcion'         => (string) ($r->descripcion ?? ''),
                'descripcionauxiliar' => (string) ($r->descripcionauxiliar ?? ''),
                'unidad'              => (string) ($r->unidad ?? ''),
                'cantidad'            => (float)  ($r->cantidad ?? 0),
                'cantidad_teorica'    => (float)  ($r->cantidad_teorica ?? 0),
                'en_espera'           => (float)  ($r->en_espera ?? 0),
                'costo_promedio'      => $r->costo_promedio !== null ? (float) $r->costo_promedio : null,
                'importe'             => $r->costo_promedio !== null
                                            ? round((float) $r->cantidad * (float) $r->costo_promedio, 2)
                                            : null,
                'destino'             => (string) ($r->destino ?? ''),
                'proveedor'           => (string) ($r->proveedor ?? ''),
                'devolvible'          => $r->devolvible,
                'obsoleto'            => (bool)   ($r->obsoleto ?? false),
                'updated_at'          => (string) ($r->updated_at ?? ''),
            ])->values(),
            'total_importe' => round($totalImporte, 2),
        ]);
    }

    /**
     * ✅ Busca una columna "fecha última actualización" en una tabla del ERP.
     * Regresa una expresión SQL (ej: "PD.updated_at") o null si no encuentra.
     */
    private function erpFindUpdatedExpr(string $tableAlias, string $tableName): ?string
    {
        $cacheKey = "erp_updated_col_{$tableName}";

        $col = Cache::remember($cacheKey, 3600, function () use ($tableName) {
            $candidates = [
                'FechaModificacion', 'FechaActualizacion', 'FechaUpdate',
                'FechaCambio', 'UpdatedAt', 'updated_at',
                'LastUpdate', 'LastUpdated', 'TimeStamp', 'timestamp',
            ];

            $rows = DB::connection('erp')->select(
                "SELECT COLUMN_NAME, DATA_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ?
                   AND COLUMN_NAME IN ('" . implode("','", $candidates) . "')",
                [$tableName]
            );

            $found = collect($rows)->keyBy('COLUMN_NAME');

            foreach ($candidates as $c) {
                if (! $found->has($c)) continue;
                $t = strtolower((string) $found[$c]->DATA_TYPE);
                if (in_array($t, ['timestamp', 'rowversion', 'binary', 'varbinary'], true)) continue;
                return $c;
            }

            return null;
        });

        return $col !== null ? "{$tableAlias}.{$col}" : null;
    }

    /**
     * ✅ La fecha que usaremos como “Última actualización del registro (sistema)”
     */
    private function erpUltimaActualizacionExpr(): string
    {
        $pd = $this->erpFindUpdatedExpr('PD', 'AcPedidosDet');
        if ($pd) return $pd;

        $p = $this->erpFindUpdatedExpr('P', 'AcPedidos');
        if ($p) return $p;

        return 'P.FechaPedido';
    }

    /**
     * Ejecuta la consulta ERP (historial OC) y agrega FechaUltimaActualizacion + FechaUltimaEntrada
     */
    private function erpFetchOrdenesCompra(int $unidadNegocioId, string $q): \Illuminate\Support\Collection
    {
        $ultimaExpr = $this->erpUltimaActualizacionExpr();

        $sql = "
            SELECT
                UN.IdUnidadNegocio, UN.UnidadNegocio, UN.Descripcion AS Desarrollo,
                Proy.IdProyecto, Proy.Proyecto,
                Req.idRequisicion, Req.Requisicion, Req.Fecha as FechaRequisicion,
                P.idPedido, P.Pedido, P.FechaPedido, P.EntradaTotal,
                Prov.IdProveedor, Prov.RazonSocial,
                PD.idPedidoDet,
                FI.idFamilia, FI.FamiliaPrincipal AS Familia, FI.Familia AS SubFamilia,
                I.idInsumo, I.INSUMO, I.DescripcionLarga,
                U.IdUnidad, U.Unidad,
                PD.Cantidad,
                ISNULL(PD.ParcialPralmacen,0) AS ParcialPralmacen,

                -- ✅ última actualización (sistema) del registro
                {$ultimaExpr} AS FechaUltimaActualizacion,

                -- ✅ la que necesitas para mostrar en Explore
                PD.FechaUltimaEntrada AS FechaUltimaEntrada

            FROM AcPedidosDet PD
            INNER JOIN AcPedidos P ON PD.idPedido = P.idPedido
            INNER JOIN AcRequisicionDet RD ON PD.idRequisicionDet = RD.idRequisicionDet
            INNER JOIN AcRequisiciones Req ON RD.idRequisicion = Req.idRequisicion
            INNER JOIN AcExplosionInsumos EI ON RD.idExplosionInsumos = EI.idExplosionInsumos
            INNER JOIN AcCatInsumos I ON EI.idInsumo = I.idInsumo
            INNER JOIN AcFamilias FI ON I.idFamilia = FI.idFamilia
            INNER JOIN AcCatUnidades U ON I.idUnidad = U.IdUnidad
            INNER JOIN AcProveedores Prov ON P.IdProveedor = Prov.IdProveedor
            INNER JOIN Proyectos Proy ON P.idProyecto = Proy.IdProyecto
            INNER JOIN AOTipoProyectos TProy ON Proy.IdTipoProyecto = TProy.IdTipoProyecto
            INNER JOIN AcUnidadesNegocio UN ON Proy.idUnidadNegocio = UN.IdUnidadNegocio
            WHERE
                P.Cancelado = 0
                AND Proy.Cerrado = 0
                AND UN.IdUnidadNegocio = ?
        ";

        $params = [$unidadNegocioId];

        if ($q !== '') {
            $sql .= "
                AND (
                    I.INSUMO LIKE ?
                    OR I.DescripcionLarga LIKE ?
                    OR Prov.RazonSocial LIKE ?
                )
            ";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like);
        }

        $sql .= " ORDER BY P.FechaPedido DESC, P.idPedido DESC, PD.idPedidoDet DESC";

        return collect(DB::connection('erp')->select($sql, $params));
    }

    /**
     * ORDENES COMPRA (ERP) - HISTORIAL JSON
     */
    public function ordenesCompra(Request $request)
    {
       

$user = Auth::user();

       
        $user = Auth::user();
        $obraActualId = (int) ($user->obra_actual_id ?? 0);
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        if (!$obraActual || !$obraActual->erp_unidad_negocio_id) {
            return response()->json([]);
        }

        $unidadNegocioId = (int) $obraActual->erp_unidad_negocio_id;
        $q = trim((string) $request->get('q', ''));

        $rows = $this->erpFetchOrdenesCompra($unidadNegocioId, $q);

        $data = $rows->map(function ($r) {
            $pedida   = (float) $r->Cantidad;
            $recibida = (float) ($r->ParcialPralmacen ?? 0);
            $faltante = max(0, $pedida - $recibida);

            $estado = ($recibida <= 0)
                ? 'pendiente'
                : (($recibida >= $pedida) ? 'finalizada' : 'parcial');

            return [
                'idPedido'       => (int) $r->idPedido,
                'idPedidoDet'    => (int) $r->idPedidoDet,
                'insumo'         => (string) $r->INSUMO,
                'descripcion'    => (string) $r->DescripcionLarga,
                'unidad'         => (string) $r->Unidad,
                'razon'          => (string) $r->RazonSocial,

                // Fecha de creación OC
                'fecha'          => (string) $r->FechaPedido,

                // Última actualización (sistema)
                'fecha_evento'   => (string) ($r->FechaUltimaActualizacion ?? $r->FechaPedido),

                // ✅ FechaUltimaEntrada (la que vas a mostrar en el blade)
                'FechaUltimaEntrada' => $r->FechaUltimaEntrada ? (string) $r->FechaUltimaEntrada : null,

                'pedida'         => $pedida,
                'recibida'       => $recibida,
                'faltante'       => $faltante,
                'estado'         => $estado,
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * ✅ PDF: OC pendientes + parciales
     */
  public function ordenesCompraReportePdf(Request $request)
{
    try {
       
      $user = Auth::user();
        $obraActualId = (int) ($user->obra_actual_id ?? 0);
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        if (!$obraActual || !$obraActual->erp_unidad_negocio_id) {
            abort(404);
        }

        $unidadNegocioId = (int) $obraActual->erp_unidad_negocio_id;
        $q = trim((string) $request->get('q', ''));

        // ✅ ERP
        $rows = $this->erpFetchOrdenesCompra($unidadNegocioId, $q);

        $data = $rows->map(function ($r) {
            $pedida   = (float) $r->Cantidad;
            $recibida = (float) ($r->ParcialPralmacen ?? 0);
            $faltante = max(0, $pedida - $recibida);

            $estado = ($recibida <= 0)
                ? 'pendiente'
                : (($recibida >= $pedida) ? 'finalizada' : 'parcial');

            return [
                'estado'        => $estado,
                'idPedido'      => (int) $r->idPedido,
                'idPedidoDet'   => (int) $r->idPedidoDet,
                'insumo'        => (string) $r->INSUMO,
                'descripcion'   => (string) $r->DescripcionLarga,
                'razon'         => (string) $r->RazonSocial,
                'unidad'        => (string) $r->Unidad,
                'pedida'        => $pedida,
                'recibida'      => $recibida,
                'faltante'      => $faltante,
                'fecha_oc'      => (string) $r->FechaPedido,
                'fecha_sistema' => (string) ($r->FechaUltimaActualizacion ?? $r->FechaPedido),
            ];
        })
        ->filter(fn($r) => in_array($r['estado'], ['pendiente', 'parcial'], true))
        ->values();

        // ✅ PDF
        $pdf = Pdf::loadView('pdf.oc_pendientes_parciales', [
            'obra' => $obraActual,
            'q'    => $q,
            'rows' => $data,
            'fecha_generacion' => now()->format('Y-m-d H:i'),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('OC_pendientes_parciales.pdf');

    } catch (\Throwable $e) {

        // ✅ Log completo para PRODUCCIÓN
        Log::error('Explore PDF OC falló (ordenesCompraReportePdf)', [
            'msg' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => Auth::id(),
            'obra_actual_id' => Auth::user()?->obra_actual_id,
            'q' => $request->get('q'),
        ]);

        // ✅ Respuesta clara para tu modal (fetch)
        return response(
            'Error interno al generar el PDF: ' . $e->getMessage(),
            500
        );
    }
}

    /**
     * GRAFICAS (local)
     */
    public function graficas(Request $request)
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);


        $q = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $soloObra = $request->get('solo_obra_actual') === '1';

        $familiasQ = MovimientoDetalle::query()
            ->selectRaw("COALESCE(movimiento_detalles.familia,'SIN FAMILIA') AS familia, SUM(movimiento_detalles.cantidad) AS total")
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->when($soloObra && $obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('movimiento_detalles.familia', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.subfamilia', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.descripcion', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.inventario_id', 'like', "%{$q}%");
                });
            })
            ->groupBy(DB::raw("COALESCE(movimiento_detalles.familia,'SIN FAMILIA')"))
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $insumosQ = MovimientoDetalle::query()
            ->selectRaw("
                movimiento_detalles.inventario_id,
                MAX(movimiento_detalles.descripcion) AS descripcion,
                MAX(movimiento_detalles.unidad) AS unidad,
                SUM(movimiento_detalles.cantidad) AS total
            ")
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->when($soloObra && $obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('movimiento_detalles.familia', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.subfamilia', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.descripcion', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.inventario_id', 'like', "%{$q}%");
                });
            })
            ->groupBy('movimiento_detalles.inventario_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'familias' => $familiasQ->map(fn($r) => [
                'familia' => (string) $r->familia,
                'total'   => (float) $r->total,
            ])->values(),

            'insumos' => $insumosQ->map(fn($r) => [
                'inventario_id' => (string) $r->inventario_id,
                'descripcion'   => (string) $r->descripcion,
                'unidad'        => (string) $r->unidad,
                'total'         => (float) $r->total,
            ])->values(),
        ]);
    }

    public function entradas(Request $request)
{
    $obraId = Auth::user()?->obra_actual_id;

    $q     = trim((string) $request->get('q', ''));
    $desde = $request->get('desde');
    $hasta = $request->get('hasta');
    $tipo  = $request->get('tipo');
    $soloH = $request->boolean('solo_h');

    // For searching transferencias by obra origen name
    $transIdsByOrigen = [];
    if ($q !== '') {
        $transIdsByOrigen = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->where('oo.nombre', 'like', "%{$q}%")
            ->pluck('t.id')
            ->map(fn($id) => (string) $id)
            ->toArray();
    }

    $rows = OcRecepcion::query()
        ->leftJoin('obras as obs_e', 'obs_e.id', '=', 'oc_recepciones.obra_id')
        ->select(['oc_recepciones.*', DB::raw('obs_e.nombre as obra_nombre')])
        ->where('oc_recepciones.obra_id', $obraId)
        ->when($soloH, function ($qq) use ($obraId) {
            $qq->whereExists(function ($sub) use ($obraId) {
                $sub->from('inventarios')
                    ->whereColumn('inventarios.insumo_id', 'oc_recepciones.insumo')
                    ->where('inventarios.obra_id', $obraId)
                    ->where('inventarios.devolvible', 1);
            });
        })
        ->when($q !== '', function ($qq) use ($q, $transIdsByOrigen) {
            $qq->where(function ($w) use ($q, $transIdsByOrigen) {
                $w->where('oc_recepciones.insumo', 'like', "%{$q}%")
                  ->orWhere('oc_recepciones.descripcion', 'like', "%{$q}%")
                  ->orWhere('oc_recepciones.id_pedido', 'like', "%{$q}%");
                if (!empty($transIdsByOrigen)) {
                    $w->orWhereIn('id_pedido', $transIdsByOrigen);
                }
            });
        })
        ->when($desde, fn($qq) => $qq->whereDate('oc_recepciones.fecha_recibido', '>=', $desde))
        ->when($hasta, fn($qq) => $qq->whereDate('oc_recepciones.fecha_recibido', '<=', $hasta))
        ->when($tipo, function ($qq) use ($tipo) {
            if ($tipo === 'oc') {
                $qq->where(fn($w) => $w->where('oc_recepciones.tipo', 'oc')->orWhereNull('oc_recepciones.tipo'));
            } else {
                $qq->where('oc_recepciones.tipo', $tipo);
            }
        })
        ->orderByDesc('oc_recepciones.fecha_recibido')
        ->get();

    // Lookup obra_origen + transferencia_id for transferencias by matching insumo + obra_destino_id
    // (id_pedido is 0 on all transfer receipts, so we match by insumo and pick closest date)
    $transIds   = $rows->filter(fn($r) => ($r->tipo ?? 'oc') === 'transferencia')
        ->pluck('id')->values()->toArray();
    $transMatchMap = []; // ocr.id → ['trans_id' => int, 'obra_origen' => string]
    if (!empty($transIds)) {
        $matched = DB::select("
            SELECT ocr_id, trans_id, origen_nombre
            FROM (
                SELECT
                    ocr.id AS ocr_id,
                    te.id  AS trans_id,
                    oo.nombre AS origen_nombre,
                    ROW_NUMBER() OVER (
                        PARTITION BY ocr.id
                        ORDER BY ABS(DATEDIFF(day, CAST(ocr.fecha_recibido AS DATE), te.fecha))
                    ) AS rn
                FROM oc_recepciones ocr
                INNER JOIN transferencias_entre_obras_detalle ted ON ted.insumo_id = ocr.insumo
                INNER JOIN transferencias_entre_obras te
                    ON te.id = ted.transferencia_id AND te.obra_destino_id = ocr.obra_id
                INNER JOIN obras oo ON oo.id = te.obra_origen_id
                WHERE ocr.id IN (" . implode(',', array_map('intval', $transIds)) . ")
            ) ranked
            WHERE rn = 1
        ");
        foreach ($matched as $m) {
            $transMatchMap[(int)$m->ocr_id] = [
                'trans_id'    => (int) $m->trans_id,
                'obra_origen' => (string) $m->origen_nombre,
            ];
        }
    }
    $origenMap = []; // kept for backward compat but unused below

    // Lookup de familia desde inventarios (fallback para registros anteriores a la migración)
    $insumosList = $rows->pluck('insumo')->unique()->filter()->values()->toArray();
    $familiasMap = [];
    if (!empty($insumosList)) {
        $familiasMap = Inventario::whereIn('insumo_id', $insumosList)
            ->whereNotNull('familia')
            ->where('familia', '!=', '')
            ->pluck('familia', 'insumo_id')
            ->toArray();
    }

    $subfamiliaToFamilia = [];
    foreach (config('familias', []) as $fam => $subs) {
        foreach ($subs as $sub) {
            $subfamiliaToFamilia[$sub] = $fam;
        }
    }
    foreach ($insumosList as $ins) {
        if (!isset($familiasMap[$ins]) || $familiasMap[$ins] === '') {
            $parts = explode('-', (string) $ins);
            $subPrefix = count($parts) >= 2 ? $parts[0] . '-' . $parts[1] : '';
            if ($subPrefix !== '' && isset($subfamiliaToFamilia[$subPrefix])) {
                $familiasMap[$ins] = $subfamiliaToFamilia[$subPrefix];
            }
        }
    }

    // Lookup de usuarios por user_id
    $userIds   = $rows->pluck('user_id')->unique()->filter()->values()->toArray();
    $usersMap  = [];
    if (!empty($userIds)) {
        $usersMap = \App\Models\User::whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->toArray();
    }

    return $rows->map(function ($r) use ($familiasMap, $usersMap, $transMatchMap) {
        // Preferir familia almacenada en el registro; caer al lookup si está vacía
        $familia = (string) ($r->familia ?? '');
        if ($familia === '' || $familia === 'SIN FAMILIA') {
            $familia = (string) ($familiasMap[$r->insumo] ?? 'SIN FAMILIA');
        }

        return [
            'id'               => (int) $r->id,
            'id_pedido'        => (string) $r->id_pedido,
            'pedido_det_id'    => (int) $r->pedido_det_id,
            'tipo'             => (string) ($r->tipo ?? 'oc'),
            'familia'          => $familia,
            'subfamilia'       => (string) ($r->subfamilia ?? ''),
            'insumo'           => (string) $r->insumo,
            'descripcion'      => (string) $r->descripcion,
            'unidad'           => (string) $r->unidad,
            'cantidad_llego'   => (float) $r->cantidad_llego,
            'precio_unitario'  => $r->precio_unitario !== null ? (float) $r->precio_unitario : null,
            'importe'          => $r->precio_unitario !== null
                                     ? round((float) $r->cantidad_llego * (float) $r->precio_unitario, 2)
                                     : null,
            'fecha_oc'         => $r->fecha_oc ? (string) $r->fecha_oc : null,
            'fecha_recibido'   => $r->fecha_recibido ? (string) $r->fecha_recibido : null,
            'tiene_foto'       => !empty($r->foto_path),
            'usuario'          => (string) ($usersMap[$r->user_id] ?? ''),
            'observaciones'    => (string) ($r->observaciones ?? ''),
            'revertida'        => !empty($r->revertida_at),
            'revertida_at'     => $r->revertida_at ? (string) $r->revertida_at : null,
            'motivo_reversion' => (string) ($r->motivo_reversion ?? ''),
            'revertida_por'    => (string) ($usersMap[$r->revertida_por] ?? ''),
            'obra'             => (string) ($r->obra_nombre ?? 'SIN OBRA'),
            'obra_origen'      => ($r->tipo ?? 'oc') === 'transferencia'
                                     ? (string) ($transMatchMap[(int)$r->id]['obra_origen'] ?? '')
                                     : '',
            'transferencia_id' => ($r->tipo ?? 'oc') === 'transferencia'
                                     ? (int) ($transMatchMap[(int)$r->id]['trans_id'] ?? 0)
                                     : 0,
        ];
    });

    return response()->json($rows->values());
}

public function entradaDetalles($id)
{
    // ? Usuario autenticado
    $user = Auth::user();
    if (!$user) {
        abort(401, 'No autenticado');
    }

    // ? Obra actual
    $obraId = (int) ($user->obra_actual_id ?? 0);
    if ($obraId <= 0) {
        abort(403, 'Sin obra actual asignada');
    }

    // ? Buscar recepci�n SOLO de esa obra
    $r = OcRecepcion::where('obra_id', $obraId)->findOrFail($id);

    // ? Normalizar foto_path para formar URL p�blica correcta
    $path = (string) ($r->foto_path ?? '');
    $path = ltrim($path, '/'); // quita "/" al inicio
    $path = preg_replace('#^(public/|storage/)#', '', $path); // quita prefijos si vienen guardados

    $fotoUrl = $path !== '' ? asset('storage/' . $path) : null;

    return response()->json([
        'id'            => (int) $r->id,
        'id_pedido'     => (string) $r->id_pedido,
        'pedido_det_id' => (int) $r->pedido_det_id,
        'insumo'        => (string) $r->insumo,
        'descripcion'   => (string) $r->descripcion,
        'unidad'        => (string) $r->unidad,
        'cantidad_llego'=> (float) $r->cantidad_llego,
        'fecha_oc'      => $r->fecha_oc ? (string) $r->fecha_oc : null,
        'fecha_recibido'=> $r->fecha_recibido ? (string) $r->fecha_recibido : null,

        'foto_url' => $r->foto_path ? route('explore.entradas.foto', ['id' => $r->id]) : null,

    ]);
}

public function entradaFoto($id)
{
    $user = \Illuminate\Support\Facades\Auth::user();
    if (!$user) abort(401);

    $obraId = (int) ($user->obra_actual_id ?? 0);
    if ($obraId <= 0) abort(403);

    $r = \App\Models\OcRecepcion::where('obra_id', $obraId)->findOrFail($id);

    $path = (string) ($r->foto_path ?? '');
    $path = ltrim($path, '/');
    $path = preg_replace('#^(public/|storage/)#', '', $path);

    if ($path === '' || !Storage::disk('public')->exists($path)) {
        abort(404, 'Archivo no encontrado en disco public');
    }

            // Esto entrega la imagen correctamente aunque NO exista public/storage
            /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->response($path);
}

    // ════════════════════════════════════════════════════════════
    // REVERSO DE ENTRADA MANUAL
    // ════════════════════════════════════════════════════════════

    public function revertirEntrada(Request $request, $id)
    {
        $user   = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        if (!$user || $obraId <= 0) {
            return response()->json(['ok' => false, 'message' => 'Sin obra asignada.'], 403);
        }

        if (!$user->is_admin) {
            return response()->json(['ok' => false, 'message' => 'Solo administradores pueden revertir entradas.'], 403);
        }

        $motivo = trim((string) $request->get('motivo', ''));

        $entrada = OcRecepcion::where('obra_id', $obraId)
            ->where('tipo', 'manual')
            ->whereNull('revertida_at')
            ->find($id);

        if (!$entrada) {
            return response()->json([
                'ok'      => false,
                'message' => 'Entrada no encontrada, no es manual, o ya fue revertida.',
            ], 422);
        }

        $cantidad = (float) $entrada->cantidad_llego;
        $insumoId = (string) $entrada->insumo;

        DB::transaction(function () use ($entrada, $obraId, $cantidad, $insumoId, $user, $motivo) {
            // 1) Ajustar inventario: restar la cantidad que entró
            $inv = Inventario::where('obra_id', $obraId)
                ->where('insumo_id', $insumoId)
                ->lockForUpdate()
                ->first();

            if ($inv) {
                $nuevaCantidad        = max(0, (float) ($inv->cantidad        ?? 0) - $cantidad);
                $nuevaCantidadTeorica = max(0, (float) ($inv->cantidad_teorica ?? 0) - $cantidad);
                $inv->cantidad         = $nuevaCantidad;
                $inv->cantidad_teorica = $nuevaCantidadTeorica;
                $inv->save();
            }

            // 2) Marcar la entrada como revertida
            $entrada->revertida_at     = now();
            $entrada->revertida_por    = $user->id;
            $entrada->motivo_reversion = $motivo ?: null;
            $entrada->save();
        });

        Log::info('Entrada manual revertida', [
            'entrada_id' => $entrada->id,
            'insumo'     => $insumoId,
            'cantidad'   => $cantidad,
            'obra_id'    => $obraId,
            'user_id'    => $user->id,
            'motivo'     => $motivo,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Entrada revertida. Se descontaron ' . number_format($cantidad, 2)
                       . ' ' . $entrada->unidad . ' del inventario.',
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // TRANSFERENCIAS ENTRE OBRAS
    // ════════════════════════════════════════════════════════════

    /**
     * Lista de transferencias (JSON) para la pestaña Explore.
     * Incluye tanto las enviadas (obra_origen) como las recibidas (obra_destino).
     * Cada registro incluye el campo "direccion": "enviada" | "recibida".
     */
    public function transferencias(Request $request)
    {
        $user          = Auth::user();
        $obraActualId  = $user?->obra_actual_id;
        $obraFiltroId  = ($request->get('obra_id') && $user?->is_multiobra)
                         ? (int) $request->get('obra_id')
                         : null;
        // Si el usuario seleccionó una obra específica (y es multiobra) la usamos; si no, la obra actual
        $obraId        = $obraFiltroId ?? $obraActualId;

        $q     = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $rows = $this->queryTransferencias($obraId, $q, $desde, $hasta)->get();

        return response()->json($rows->map(fn($r) => array_merge((array) $r, [
            'direccion' => ($obraId && (int) $r->obra_origen_id === (int) $obraId)
                          ? 'enviada'
                          : 'recibida',
        ]))->values());
    }

    /**
     * Construcción reutilizable de la query de transferencias.
     */
    private function queryTransferencias(?int $obraId, string $q, ?string $desde, ?string $hasta)
    {
        return DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->join('users as u',  'u.id',  '=', 't.user_id')
            ->when($obraId, function ($q2) use ($obraId) {
                $q2->where(function ($w) use ($obraId) {
                    $w->where('t.obra_origen_id', $obraId)
                      ->orWhere('t.obra_destino_id', $obraId);
                });
            })
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('oo.nombre', 'like', "%{$q}%")
                      ->orWhere('od.nombre', 'like', "%{$q}%")
                      ->orWhere('u.name',    'like', "%{$q}%");
                });
            })
            ->when($desde, fn($q2) => $q2->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('t.fecha', '<=', $hasta))
            ->orderByDesc('t.fecha')
            ->orderByDesc('t.id')
            ->select([
                't.id',
                't.fecha',
                't.observaciones',
                't.obra_origen_id',
                't.obra_destino_id',
                DB::raw('oo.nombre as obra_origen'),
                DB::raw('od.nombre as obra_destino'),
                DB::raw('u.name as usuario'),
                DB::raw('(SELECT COUNT(*) FROM transferencias_entre_obras_detalle WHERE transferencia_id = t.id) as total_insumos'),
                DB::raw('(SELECT ISNULL(SUM(cantidad), 0) FROM transferencias_entre_obras_detalle WHERE transferencia_id = t.id) as total_piezas'),
                DB::raw('(SELECT ISNULL(SUM(cantidad * precio_unitario), 0) FROM transferencias_entre_obras_detalle WHERE transferencia_id = t.id AND precio_unitario IS NOT NULL) as total_importe'),
            ]);
    }

    /**
     * Exportar Transferencias a Excel (nivel detalle: 1 fila por insumo).
     */
    public function exportarTransferencias(Request $request)
    {
        $user       = Auth::user();
        $obraId     = $user?->obra_actual_id;
        $obra       = $obraId ? Obra::find($obraId) : null;

        $q          = trim((string) $request->get('q', ''));
        $desde      = $request->get('desde');
        $hasta      = $request->get('hasta');
        $obraNombre = trim((string) $request->get('obra_nombre', ''));
        $dir        = $request->get('dir'); // 'enviada' | 'recibida' | null = todas

        $rows = DB::table('transferencias_entre_obras_detalle as d')
            ->join('transferencias_entre_obras as t', 't.id', '=', 'd.transferencia_id')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->when($obraId, function ($qq) use ($obraId, $dir) {
                if ($dir === 'enviada') {
                    $qq->where('t.obra_origen_id', $obraId);
                } elseif ($dir === 'recibida') {
                    $qq->where('t.obra_destino_id', $obraId);
                } else {
                    $qq->where(function ($w) use ($obraId) {
                        $w->where('t.obra_origen_id', $obraId)
                          ->orWhere('t.obra_destino_id', $obraId);
                    });
                }
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('d.insumo_id',   'like', "%{$q}%")
                      ->orWhere('d.descripcion','like', "%{$q}%")
                      ->orWhere('oo.nombre',    'like', "%{$q}%")
                      ->orWhere('od.nombre',    'like', "%{$q}%");
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('t.fecha', '<=', $hasta))
            ->when($obraNombre !== '', function ($qq) use ($obraNombre) {
                $qq->where(function ($w) use ($obraNombre) {
                    $w->where('oo.nombre', $obraNombre)
                      ->orWhere('od.nombre', $obraNombre);
                });
            })
            ->orderByDesc('t.fecha')
            ->orderByDesc('t.id')
            ->orderBy('d.id')
            ->get([
                't.fecha',
                't.id as transferencia_id',
                DB::raw('oo.nombre as obra_origen'),
                DB::raw('od.nombre as obra_destino'),
                DB::raw("ISNULL(d.insumo_id, '') as codigo"),
                DB::raw("ISNULL(d.descripcion, '') as descripcion"),
                DB::raw("ISNULL(d.unidad, '') as unidad"),
                'd.cantidad',
                DB::raw("ISNULL(d.cantidad_recibida, 0) as cantidad_recibida"),
                'd.precio_unitario',
            ]);

        $fmtDate = fn($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';

        $data = $rows->map(fn($r) => [
            $fmtDate($r->fecha),                                                       // 0
            (int) $r->transferencia_id,                                                // 1 integer
            (string) $r->obra_origen,                                                  // 2
            (string) $r->obra_destino,                                                 // 3
            (string) $r->codigo,                                                       // 4
            (string) $r->descripcion,                                                  // 5
            (string) $r->unidad,                                                       // 6
            (float) $r->cantidad,                                                      // 7 number
            (float) $r->cantidad_recibida,                                             // 8 number
            $r->precio_unitario !== null ? (float) $r->precio_unitario : null,         // 9 currency
            $r->precio_unitario !== null
                ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                : null,                                                                // 10 currency
        ])->values()->toArray();

        $dirLabel = match($dir) {
            'enviada'  => 'Enviadas',
            'recibida' => 'Recibidas',
            default    => null,
        };

        $filters = array_filter([
            $obra       ? 'Obra actual: ' . $obra->nombre : null,
            $obraNombre ? 'Filtro obra: ' . $obraNombre   : null,
            $dirLabel   ? 'Dirección: '   . $dirLabel     : null,
            $q          ? 'Búsqueda: '    . $q            : null,
            $desde      ? 'Desde: '       . $desde        : null,
            $hasta      ? 'Hasta: '       . $hasta        : null,
        ]);

        Log::info('Excel export: Transferencias', [
            'user_id'   => Auth::id(),
            'obra_id'   => $obraId,
            'registros' => count($data),
            'filtros'   => $filters,
        ]);

        return ExcelExporter::download(
            filename:    'transferencias',
            moduleName:  'Transferencias',
            headers:     [
                'Fecha', '# Trans.', 'Obra Origen', 'Obra Destino',
                'Código', 'Descripción', 'Unidad',
                'Cantidad', 'Cant. Recibida', 'P.U.', 'Importe',
            ],
            rows:        $data,
            columnTypes: [1 => 'integer', 7 => 'number', 8 => 'number', 9 => 'currency', 10 => 'currency'],
            filters:     $filters,
            color:       'EA580C',  // naranja — igual que Trans. Recibidas en el sistema
            columnWidths: [5 => 42],  // Descripción
        );
    }

    /**
     * Detalle de una transferencia (JSON).
     */
    public function transferenciaDetalles($id)
    {
        $user   = Auth::user();
        $obraId = $user?->obra_actual_id;

        $t = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->join('users as u',  'u.id',  '=', 't.user_id')
            ->where('t.id', $id)
            ->select([
                't.id', 't.fecha', 't.observaciones',
                DB::raw('oo.nombre as obra_origen'),
                DB::raw('od.nombre as obra_destino'),
                DB::raw('u.name as usuario'),
                't.obra_origen_id', 't.obra_destino_id',
            ])
            ->first();

        if (! $t) abort(404);

        // Permiso: la obra origen O la obra destino pueden ver el detalle
        if ($obraId
            && (int) $t->obra_origen_id  !== (int) $obraId
            && (int) $t->obra_destino_id !== (int) $obraId) {
            abort(403);
        }

        $detalles = DB::table('transferencias_entre_obras_detalle')
            ->where('transferencia_id', $id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'transferencia' => $t,
            'detalles'      => $detalles,
        ]);
    }

    // ════════════════════════════════════════════════════════════
    // EXPORTACIONES EXCEL
    // ════════════════════════════════════════════════════════════

    /**
     * Exportar Entradas (OcRecepcion) a Excel.
     * Aplica los mismos filtros que el endpoint entradas() pero sin límite de registros.
     */
    public function exportarEntradas(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $obra   = $obraId ? Obra::find($obraId) : null;

        $q     = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $tipo  = $request->get('tipo');

        // Subquery: familia/subfamilia consolidada de inventarios (sin restricción de obra)
        // Necesario porque registros tipo=finiquito no guardan familia en oc_recepciones
        $invFamilias = DB::table('inventarios')
            ->select('insumo_id',
                DB::raw("MAX(CASE WHEN familia    IS NOT NULL AND familia    <> '' THEN familia    END) as familia"),
                DB::raw("MAX(CASE WHEN subfamilia IS NOT NULL AND subfamilia <> '' THEN subfamilia END) as subfamilia"),
                DB::raw("MAX(descripcionauxiliar) as descripcionauxiliar")
            )
            ->groupBy('insumo_id');

        $rows = DB::table('oc_recepciones as r')
            ->leftJoinSub($invFamilias, 'inv', 'inv.insumo_id', '=', 'r.insumo')
            ->where('r.obra_id', $obraId)
            ->whereNull('r.revertida_at')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('r.insumo',      'like', "%{$q}%")
                      ->orWhere('r.descripcion','like', "%{$q}%")
                      ->orWhere('r.id_pedido',  'like', "%{$q}%");
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('r.fecha_recibido', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('r.fecha_recibido', '<=', $hasta))
            ->when($tipo, function ($qq) use ($tipo) {
                if ($tipo === 'oc') {
                    $qq->where(fn($w) => $w->where('r.tipo', 'oc')->orWhereNull('r.tipo'));
                } else {
                    $qq->where('r.tipo', $tipo);
                }
            })
            ->orderByDesc('r.fecha_recibido')
            ->limit(10000)
            ->get([
                'r.fecha_recibido', 'r.fecha_oc', 'r.tipo',
                'r.id_pedido', 'r.pedido_det_id',
                DB::raw("COALESCE(r.familia,    inv.familia,    '') as familia"),
                DB::raw("COALESCE(r.subfamilia, inv.subfamilia, '') as subfamilia"),
                'r.insumo as codigo', 'r.descripcion',
                DB::raw("ISNULL(inv.descripcionauxiliar, '') as descripcionauxiliar"),
                'r.unidad', 'r.cantidad_llego', 'r.precio_unitario',
                DB::raw("ISNULL(r.observaciones, '') as observaciones"),
            ]);

        $fmtDate = fn($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';

        // Agrupar por tipo — cada tipo va en su propia hoja coloreada
        $byTipo = ['oc' => [], 'manual' => [], 'transferencia' => [], 'finiquito' => []];

        // Cabeceras y tipos por hoja: OC lleva Fecha OC primero; Manual y Transferencia no manejan OC
        $cfgPorTipo = [
            'oc' => [
                'headers'      => ['Fecha OC', 'Fecha Recibido', 'OC #', 'Det #', 'Familia', 'Subfamilia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe', 'Observaciones'],
                'columnTypes'  => [3 => 'integer', 9 => 'number', 10 => 'currency', 11 => 'currency'],
                'columnWidths' => [7 => 42, 12 => 35],
            ],
            'manual' => [
                'headers'      => ['Fecha Recibido', 'Familia', 'Subfamilia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe', 'Observaciones'],
                'columnTypes'  => [6 => 'number', 7 => 'currency', 8 => 'currency'],
                'columnWidths' => [4 => 42, 9 => 35],
            ],
            'transferencia' => [
                'headers'      => ['Fecha Recibido', 'Familia', 'Subfamilia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe', 'Observaciones'],
                'columnTypes'  => [6 => 'number', 7 => 'currency', 8 => 'currency'],
                'columnWidths' => [4 => 42, 9 => 35],
            ],
            'finiquito' => [
                'headers'      => ['Fecha Recibido', 'Fecha OC', 'OC #', 'Det #', 'Familia', 'Subfamilia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe', 'Observaciones'],
                'columnTypes'  => [3 => 'integer', 9 => 'number', 10 => 'currency', 11 => 'currency'],
                'columnWidths' => [7 => 42, 12 => 35],
            ],
        ];

        $importe = fn($r) => $r->precio_unitario !== null
            ? round((float) $r->cantidad_llego * (float) $r->precio_unitario, 2)
            : null;

        foreach ($rows as $r) {
            $t = $r->tipo ?? 'oc';
            if (!array_key_exists($t, $byTipo)) $t = 'oc';

            if ($t === 'oc') {
                $byTipo[$t][] = [
                    $fmtDate($r->fecha_oc),                                                  // 0 Fecha OC
                    $fmtDate($r->fecha_recibido),                                            // 1 Fecha Recibido
                    (string) $r->id_pedido,                                                  // 2 OC #
                    (int)    $r->pedido_det_id,                                              // 3 Det # integer
                    (string) ($r->familia    ?? ''),                                         // 4
                    (string) ($r->subfamilia ?? ''),                                         // 5
                    (string) $r->codigo,                                                     // 6
                    (string) $r->descripcion,                                                // 7
                    (string) $r->unidad,                                                     // 8
                    (float)  $r->cantidad_llego,                                             // 9 number
                    $r->precio_unitario !== null ? (float) $r->precio_unitario : null,       // 10 currency
                    $importe($r),                                                             // 11 currency
                    (string) ($r->observaciones ?? ''),                                      // 12
                ];
            } elseif ($t === 'manual' || $t === 'transferencia') {
                $byTipo[$t][] = [
                    $fmtDate($r->fecha_recibido),                                            // 0 Fecha Recibido
                    (string) ($r->familia    ?? ''),                                         // 1
                    (string) ($r->subfamilia ?? ''),                                         // 2
                    (string) $r->codigo,                                                     // 3
                    (string) $r->descripcion,                                                // 4
                    (string) $r->unidad,                                                     // 5
                    (float)  $r->cantidad_llego,                                             // 6 number
                    $r->precio_unitario !== null ? (float) $r->precio_unitario : null,       // 7 currency
                    $importe($r),                                                             // 8 currency
                    (string) ($r->observaciones ?? ''),                                      // 9
                ];
            } else {
                // finiquito
                $byTipo[$t][] = [
                    $fmtDate($r->fecha_recibido),                                            // 0
                    $fmtDate($r->fecha_oc),                                                  // 1
                    (string) $r->id_pedido,                                                  // 2
                    (int)    $r->pedido_det_id,                                              // 3 integer
                    (string) ($r->familia    ?? ''),                                         // 4
                    (string) ($r->subfamilia ?? ''),                                         // 5
                    (string) $r->codigo,                                                     // 6
                    (string) $r->descripcion,                                                // 7
                    (string) $r->unidad,                                                     // 8
                    (float)  $r->cantidad_llego,                                             // 9 number
                    $r->precio_unitario !== null ? (float) $r->precio_unitario : null,       // 10 currency
                    $importe($r),                                                             // 11 currency
                    (string) ($r->observaciones ?? ''),                                      // 12
                ];
            }
        }

        $tipoLabels = [
            'oc'           => 'Compra',
            'manual'       => 'Manual',
            'transferencia'=> 'Transferencia',
            'finiquito'    => 'Finiquitada',
        ];

        $filters = array_filter([
            $obra  ? 'Obra: '     . $obra->nombre                 : null,
            $tipo  ? 'Tipo: '     . ($tipoLabels[$tipo] ?? $tipo) : null,
            $q     ? 'Búsqueda: ' . $q                            : null,
            $desde ? 'Desde: '    . $desde                        : null,
            $hasta ? 'Hasta: '    . $hasta                        : null,
        ]);

        // Configuración por tipo: [título, color hex]
        $sheetConfig = [
            'oc'           => ['Compras OC',      '4F46E5'],
            'manual'       => ['Entradas Manual',  '16A34A'],
            'transferencia'=> ['Transferencias',   'D97706'],
            'finiquito'    => ['Finiquitadas',     '475569'],
        ];

        $mainTipo = $tipo ?? 'oc';
        $mainCfg  = $sheetConfig[$mainTipo];
        $mainFmt  = $cfgPorTipo[$mainTipo];

        // Índice de la columna "Importe" según tipo
        $importeIdx = ['oc' => 11, 'manual' => 8, 'transferencia' => 8, 'finiquito' => 11];

        $calcTotal = fn(array $filas, int $idx) =>
            round(array_sum(array_column(array_filter($filas, fn($r) => $r[$idx] !== null), $idx)), 2);

        $extraSheets = [];
        foreach ($sheetConfig as $t => $cfg) {
            if ($t === $mainTipo) continue;
            if (empty($byTipo[$t])) continue;
            $fmt   = $cfgPorTipo[$t];
            $idx   = $importeIdx[$t];
            $total = $calcTotal($byTipo[$t], $idx);
            $extraSheets[] = [
                'title'        => $cfg[0],
                'headers'      => $fmt['headers'],
                'rows'         => $byTipo[$t],
                'columnTypes'  => $fmt['columnTypes'],
                'color'        => $cfg[1],
                'columnWidths' => $fmt['columnWidths'],
                'totalRow'     => [0 => 'TOTAL', $idx => $total],
            ];
        }

        $mainIdx   = $importeIdx[$mainTipo];
        $mainTotal = $calcTotal($byTipo[$mainTipo], $mainIdx);

        Log::info('Excel export: Entradas', [
            'user_id'   => Auth::id(),
            'obra_id'   => $obraId,
            'por_tipo'  => array_map('count', $byTipo),
            'filtros'   => $filters,
        ]);

        return ExcelExporter::download(
            filename:    'entradas',
            moduleName:  $mainCfg[0],
            headers:     $mainFmt['headers'],
            rows:        $byTipo[$mainTipo],
            columnTypes: $mainFmt['columnTypes'],
            filters:     $filters,
            extraSheets: $extraSheets,
            color:       $mainCfg[1],
            columnWidths: $mainFmt['columnWidths'],
            totalRow:    [0 => 'TOTAL', $mainIdx => $mainTotal],
        );
    }

    /**
     * Exportar Salidas (movimiento_detalles) a Excel.
     * Mismos filtros que salidasTabla() pero sin límite.
     */
    public function exportarSalidas(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $obra   = $obraId ? Obra::find($obraId) : null;

        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $q     = trim((string) $request->get('q', ''));

        $rows = DB::table('movimiento_detalles as d')
            ->join('movimientos as m', 'm.id', '=', 'd.movimiento_id')
            ->when($obraId, fn($qq) => $qq->where('m.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('m.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('m.fecha', '<=', $hasta))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('d.descripcion', 'like', "%{$q}%")
                      ->orWhere('d.insumo_id',  'like', "%{$q}%");
                });
            })
            ->orderByDesc('m.fecha')
            ->orderByDesc('m.id')
            ->orderBy('d.id')
            ->limit(10000)
            ->get([
                'm.fecha',
                'd.familia',
                'd.insumo_id as codigo',
                'd.descripcion',
                'd.unidad',
                'd.cantidad',
                'd.precio_unitario',
            ]);

        $fmtDate = fn($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';

        $data = $rows->map(fn($r) => [
            $fmtDate($r->fecha),                                                     // 0
            (string) ($r->familia ?? ''),                                            // 1
            (string) ($r->codigo ?? ''),                                             // 2
            (string) $r->descripcion,                                                // 3
            (string) $r->unidad,                                                     // 4
            (float) $r->cantidad,                                                    // 5 number
            $r->precio_unitario !== null ? (float) $r->precio_unitario : null,       // 6 currency
            $r->precio_unitario !== null
                ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                : null,                                                              // 7 currency
        ])->values()->toArray();

        $filters = array_filter([
            $obra  ? 'Obra: ' . $obra->nombre : null,
            $q     ? 'Búsqueda: ' . $q        : null,
            $desde ? 'Desde: ' . $desde        : null,
            $hasta ? 'Hasta: ' . $hasta         : null,
        ]);

        // ── Hoja 2: Transferencias Enviadas ──────────────────────────────
        $transRows = DB::table('transferencias_entre_obras_detalle as d')
            ->join('transferencias_entre_obras as t', 't.id', '=', 'd.transferencia_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->where('t.obra_origen_id', $obraId)
            ->when($desde, fn($qq) => $qq->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('t.fecha', '<=', $hasta))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('d.descripcion', 'like', "%{$q}%")
                      ->orWhere('d.insumo_id',  'like', "%{$q}%")
                      ->orWhere('od.nombre',    'like', "%{$q}%");
                });
            })
            ->orderByDesc('t.fecha')
            ->orderByDesc('t.id')
            ->orderBy('d.id')
            ->get([
                't.fecha',
                DB::raw('od.nombre as obra_destino'),
                'd.insumo_id as codigo',
                DB::raw("ISNULL(d.descripcion, '') as descripcion"),
                DB::raw("ISNULL(d.unidad, '') as unidad"),
                'd.cantidad',
                'd.precio_unitario',
            ]);

        $transData = $transRows->map(fn($r) => [
            $fmtDate($r->fecha),                                                        // 0
            (string) ($r->obra_destino ?? ''),                                          // 1
            (string) ($r->codigo ?? ''),                                                // 2
            (string) $r->descripcion,                                                   // 3
            (string) $r->unidad,                                                        // 4
            (float) $r->cantidad,                                                       // 5 number
            $r->precio_unitario !== null ? (float) $r->precio_unitario : null,          // 6 currency
            $r->precio_unitario !== null
                ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                : null,                                                                 // 7 currency
        ])->values()->toArray();

        Log::info('Excel export: Salidas', [
            'user_id'     => Auth::id(),
            'obra_id'     => $obraId,
            'salidas'     => count($data),
            'transferidas'=> count($transData),
            'filtros'     => $filters,
        ]);

        $totalSalidas = round(array_sum(array_column(array_filter($data, fn($r) => $r[7] !== null), 7)), 2);
        $totalTrans   = round(array_sum(array_column(array_filter($transData, fn($r) => $r[7] !== null), 7)), 2);

        return ExcelExporter::download(
            filename:    'salidas',
            moduleName:  'Salidas',
            headers:     ['Fecha', 'Familia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe'],
            rows:        $data,
            columnTypes: [5 => 'number', 6 => 'currency', 7 => 'currency'],
            filters:     $filters,
            extraSheets: [[
                'title'       => 'Trans. Enviadas',
                'headers'     => ['Fecha', 'Obra Destino', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe'],
                'rows'        => $transData,
                'columnTypes' => [5 => 'number', 6 => 'currency', 7 => 'currency'],
                'color'       => 'EA580C',
                'columnWidths'=> [3 => 42],
                'totalRow'    => [0 => 'TOTAL', 7 => $totalTrans],
            ]],
            color:        '4338CA',
            columnWidths: [3 => 42],
            totalRow:     [0 => 'TOTAL', 7 => $totalSalidas],
        );
    }

    /**
     * Exportar Inventario a Excel — agrupado por familia/subfamilia con subtotales.
     */
    public function exportarInventario(Request $request)
    {
        $user   = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);
        $obra   = $obraId ? Obra::find($obraId) : null;

        $q = trim((string) $request->get('q', ''));
        if (str_starts_with($q, '#')) {
            $q = trim(substr($q, 1));
        }

        $rows = Inventario::query()
            ->when($obraId, fn($qq) => $qq->where('obra_id', $obraId))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('insumo_id', 'like', "%{$q}%")
                      ->orWhere('descripcion', 'like', "%{$q}%");
                });
            })
            ->orderBy(DB::raw("CASE WHEN ISNULL(familia,'')='' THEN 1 ELSE 0 END"))
            ->orderBy('familia')
            ->orderBy(DB::raw("CASE WHEN ISNULL(subfamilia,'')='' THEN 1 ELSE 0 END"))
            ->orderBy('subfamilia')
            ->orderBy('descripcion')
            ->get([
                'id', 'insumo_id', 'familia', 'subfamilia', 'descripcion',
                'unidad', 'cantidad', 'costo_promedio', 'obsoleto',
            ]);

        $filters = array_filter([
            $obra ? 'Obra: ' . $obra->nombre : null,
            $q    ? 'Búsqueda: ' . $q        : null,
        ]);

        // ── Construir filas con subtotales ───────────────────────────────
        $headers = ['Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe'];
        // col indices: 0=codigo,1=desc,2=unidad,3=qty(number),4=pu(currency),5=importe(currency)

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');
        $sheet->getTabColor()->setRGB('D97706');

        $colCount   = count($headers);
        $lastColLtr = chr(64 + $colCount); // F

        $userName   = $user?->name ?? 'Sistema';
        $now        = now()->format('d/m/Y H:i');
        $filtersText= empty($filters) ? 'Sin filtros' : implode('  ·  ', array_filter($filters));
        $infoText   = "Sistema Almacén  |  Inventario Agrupado  |  Generado: {$now}  |  Usuario: {$userName}  |  {$filtersText}";

        $sheet->setCellValue('A1', $infoText);
        $sheet->mergeCells("A1:{$lastColLtr}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'FFFFFF']],
            'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'111827']],
            'alignment' => ['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(20);

        foreach ($headers as $i => $label) {
            $sheet->setCellValue(chr(65+$i).'2', $label);
        }
        $sheet->getStyle("A2:{$lastColLtr}2")->applyFromArray([
            'font' => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
            'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'D97706']],
            'alignment' => ['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,'vertical'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->freezePane('A3');

        $excelRow      = 3;
        $totalGeneral  = 0.0;

        // Agrupar en PHP por familia → subfamilia
        $byFamilia = [];
        foreach ($rows as $r) {
            $fam  = trim($r->familia  ?? '') ?: 'SIN FAMILIA';
            $sub  = trim($r->subfamilia ?? '') ?: 'SIN SUBFAMILIA';
            $byFamilia[$fam][$sub][] = $r;
        }
        ksort($byFamilia);

        foreach ($byFamilia as $familia => $subFamilias) {
            // Subtotales de familia
            $famCant   = 0.0;
            $famImport = 0.0;
            foreach ($subFamilias as $sub => $items) {
                foreach ($items as $r) {
                    $famCant   += (float)($r->cantidad ?? 0);
                    $pu         = $r->costo_promedio !== null ? (float)$r->costo_promedio : 0;
                    $famImport += round((float)($r->cantidad ?? 0) * $pu, 2);
                }
            }

            // Fila familia
            $sheet->setCellValue("A{$excelRow}", $familia);
            $sheet->mergeCells("A{$excelRow}:C{$excelRow}");
            $sheet->setCellValue("D{$excelRow}", $famCant);
            $sheet->setCellValue("F{$excelRow}", $famImport);
            $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")->applyFromArray([
                'font' => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'92400E']],
                'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'FEF3C7']],
                'borders' => ['top'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,'color'=>['rgb'=>'D97706']]],
            ]);
            $sheet->getStyle("D{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("F{$excelRow}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
            $sheet->getRowDimension($excelRow)->setRowHeight(18);
            $totalGeneral += $famImport;
            $excelRow++;

            ksort($subFamilias);
            foreach ($subFamilias as $subfamilia => $items) {
                $subCant   = 0.0;
                $subImport = 0.0;
                foreach ($items as $r) {
                    $subCant   += (float)($r->cantidad ?? 0);
                    $pu         = $r->costo_promedio !== null ? (float)$r->costo_promedio : 0;
                    $subImport += round((float)($r->cantidad ?? 0) * $pu, 2);
                }

                // Fila subfamilia
                $sheet->setCellValue("A{$excelRow}", '  ' . $subfamilia);
                $sheet->mergeCells("A{$excelRow}:C{$excelRow}");
                $sheet->setCellValue("D{$excelRow}", $subCant);
                $sheet->setCellValue("F{$excelRow}", $subImport);
                $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'1E3A5F']],
                    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'EFF6FF']],
                    'borders' => ['top'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,'color'=>['rgb'=>'93C5FD']]],
                ]);
                $sheet->getStyle("D{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("F{$excelRow}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
                $sheet->getRowDimension($excelRow)->setRowHeight(16);
                $excelRow++;

                // Filas detalle
                foreach ($items as $idx => $r) {
                    $cantidad = (float)($r->cantidad ?? 0);
                    $pu       = $r->costo_promedio !== null ? (float)$r->costo_promedio : null;
                    $importe  = $pu !== null ? round($cantidad * $pu, 2) : null;

                    $sheet->setCellValue("A{$excelRow}", (string)($r->insumo_id ?? ''));
                    $sheet->setCellValue("B{$excelRow}", (string)($r->descripcion ?? ''));
                    $sheet->setCellValue("C{$excelRow}", (string)($r->unidad ?? ''));
                    $sheet->setCellValue("D{$excelRow}", $cantidad);
                    if ($pu !== null) $sheet->setCellValue("E{$excelRow}", $pu);
                    if ($importe !== null) $sheet->setCellValue("F{$excelRow}", $importe);

                    $sheet->getStyle("D{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("E{$excelRow}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
                    $sheet->getStyle("F{$excelRow}")->getNumberFormat()->setFormatCode('"$"#,##0.00');

                    if ($idx % 2 === 0) {
                        $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('FFFFFF');
                    } else {
                        $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")
                            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F9FAFB');
                    }
                    $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")->applyFromArray([
                        'borders' => ['allBorders'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]],
                    ]);
                    $excelRow++;
                }
            }
        }

        // Fila total general
        $sheet->setCellValue("A{$excelRow}", 'TOTAL GENERAL');
        $sheet->mergeCells("A{$excelRow}:E{$excelRow}");
        $sheet->setCellValue("F{$excelRow}", $totalGeneral);
        $sheet->getStyle("A{$excelRow}:{$lastColLtr}{$excelRow}")->applyFromArray([
            'font' => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
            'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'111827']],
            'borders' => ['top'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,'color'=>['rgb'=>'000000']]],
        ]);
        $sheet->getStyle("F{$excelRow}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
        $sheet->getRowDimension($excelRow)->setRowHeight(20);

        // Anchos de columna
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(16);

        Log::info('Excel export: Inventario Agrupado', [
            'user_id'   => Auth::id(),
            'obra_id'   => $obraId,
            'registros' => $rows->count(),
            'filtros'   => $filters,
        ]);

        $xlsxFilename = 'inventario_agrupado_' . now()->format('Ymd_Hi') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

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
     * Exportar Inventario Profesional — outline grouping Familia → Subfamilia → Insumo.
     * Estilo limpio tabla plana con outline nativo de Excel.
     */
    public function exportarInventarioProfesional(Request $request)
    {
        $F = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $A = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $B = \PhpOffice\PhpSpreadsheet\Style\Border::class;

        $user   = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);
        $obra   = $obraId ? Obra::find($obraId) : null;
        $q      = trim((string) $request->get('q', ''));

        $rows = Inventario::query()
            ->when($obraId, fn($qq) => $qq->where('obra_id', $obraId))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('insumo_id',   'like', "%{$q}%")
                      ->orWhere('descripcion','like', "%{$q}%");
                });
            })
            ->orderBy(DB::raw("CASE WHEN ISNULL(familia,'')='' THEN 1 ELSE 0 END"))
            ->orderBy('familia')
            ->orderBy(DB::raw("CASE WHEN ISNULL(subfamilia,'')='' THEN 1 ELSE 0 END"))
            ->orderBy('subfamilia')
            ->orderBy('insumo_id')
            ->orderBy('descripcion')
            ->get(['id','insumo_id','familia','subfamilia','descripcion','unidad','cantidad','costo_promedio']);

        $byFamilia = [];
        foreach ($rows as $r) {
            $fam = trim($r->familia   ?? '') ?: 'SIN FAMILIA';
            $sub = trim($r->subfamilia ?? '') ?: 'SIN SUBFAMILIA';
            $byFamilia[$fam][$sub][] = $r;
        }
        ksort($byFamilia);
        foreach ($byFamilia as &$subs) { ksort($subs); }
        unset($subs);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // ── Hoja 1: Inventario ───────────────────────────────
        $sh = $spreadsheet->getActiveSheet();
        $sh->setTitle('Inventario');
        $sh->getTabColor()->setRGB('1D4ED8');
        $sh->setShowSummaryBelow(false);
        $sh->setShowSummaryRight(false);

        $userName   = $user?->name ?? 'Sistema';
        $now        = now()->format('d/m/Y H:i');
        $obraNombre = $obra?->nombre ?? 'Sin obra';

        // Fila 1 — barra de info
        $sh->setCellValue('A1', "Inventario  |  Obra: {$obraNombre}  |  Generado: {$now}  |  Usuario: {$userName}");
        $sh->mergeCells('A1:G1');
        $sh->getStyle('A1')->applyFromArray([
            'font'      => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'111827']],
            'alignment' => ['horizontal'=>$A::HORIZONTAL_LEFT,'vertical'=>$A::VERTICAL_CENTER],
        ]);
        $sh->getRowDimension(1)->setRowHeight(20);

        // Fila 2 — encabezados de columna (azul oscuro, texto blanco, bold)
        foreach (['Familia','Subfamilia','Código','Descripción','Cantidad','P.U. (Costo)','Total'] as $i => $h) {
            $sh->setCellValue(chr(65+$i).'2', $h);
        }
        $sh->getStyle('A2:G2')->applyFromArray([
            'font'      => ['bold'=>true,'size'=>10,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'1E3A8A']],
            'alignment' => ['horizontal'=>$A::HORIZONTAL_CENTER,'vertical'=>$A::VERTICAL_CENTER,
                            'wrapText'=>false],
            'borders'   => ['bottom'=>['borderStyle'=>$B::BORDER_MEDIUM,'color'=>['rgb'=>'1D4ED8']]],
        ]);
        $sh->getRowDimension(2)->setRowHeight(20);
        $sh->setAutoFilter('A2:G2');
        $sh->freezePane('A3');

        $row = 3;
        $totalGeneral = 0.0;
        $cntFamilias = $cntSubs = $cntInsumos = 0;

        foreach ($byFamilia as $familia => $subFamilias) {
            $cntFamilias++;
            $esPrimeraSubDeFamilia = true;

            foreach ($subFamilias as $subfamilia => $items) {
                $cntSubs++;
                $esPrimeraFilaDeSub = true;

                foreach ($items as $r) {
                    $cntInsumos++;
                    $cantidad = (float)($r->cantidad ?? 0);
                    $pu       = $r->costo_promedio !== null ? (float)$r->costo_promedio : null;
                    $total    = $pu !== null ? round($cantidad * $pu, 2) : null;
                    $totalGeneral += $total ?? 0;

                    // Col A: familia solo en la primera fila de cada familia
                    // Col B: subfamilia solo en la primera fila de cada subfamilia
                    $sh->setCellValue("A{$row}", ($esPrimeraSubDeFamilia && $esPrimeraFilaDeSub) ? $familia : '');
                    $sh->setCellValue("B{$row}", $esPrimeraFilaDeSub ? $subfamilia : '');
                    $sh->setCellValue("C{$row}", (string)($r->insumo_id ?? ''));
                    $sh->setCellValue("D{$row}", (string)($r->descripcion ?? ''));
                    $sh->setCellValue("E{$row}", $cantidad);
                    if ($pu !== null)    $sh->setCellValue("F{$row}", $pu);
                    if ($total !== null) $sh->setCellValue("G{$row}", $total);

                    $sh->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sh->getStyle("F{$row}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
                    $sh->getStyle("G{$row}")->getNumberFormat()->setFormatCode('"$"#,##0.00');

                    // ── Outline level ─────────────────────────────────────
                    // Primera fila del familia + primera subfamilia → sin nivel (resumen padre)
                    // Primera fila de subfamilias siguientes           → nivel 1 (resumen sub)
                    // Resto de filas de detalle                        → nivel 2 (detalle)
                    $rd = $sh->getRowDimension($row);
                    $rd->setRowHeight(15);
                    if ($esPrimeraSubDeFamilia && $esPrimeraFilaDeSub) {
                        // Resumen de nivel superior — sin outline
                    } elseif ($esPrimeraFilaDeSub) {
                        $rd->setOutlineLevel(1);
                    } else {
                        $rd->setOutlineLevel(2);
                    }

                    // ── Estilo ─────────────────────────────────────────────
                    if ($esPrimeraSubDeFamilia && $esPrimeraFilaDeSub) {
                        // Primera fila de familia: fondo muy claro
                        $sh->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font'    => ['size'=>9,'bold'=>true,'color'=>['rgb'=>'111827']],
                            'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'F3F4F6']],
                            'borders' => ['allBorders'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'D1D5DB']]],
                        ]);
                        $sh->getStyle("A{$row}")->applyFromArray([
                            'font' => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'111827']],
                        ]);
                        $sh->getStyle("B{$row}")->applyFromArray([
                            'font' => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'374151']],
                        ]);
                    } elseif ($esPrimeraFilaDeSub) {
                        // Primera fila de subfamilia: fondo levemente distinto
                        $sh->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font'    => ['size'=>9,'color'=>['rgb'=>'374151']],
                            'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'F9FAFB']],
                            'borders' => ['allBorders'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]],
                            'borders' => ['top'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'9CA3AF']],
                                          'bottom'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'D1D5DB']],
                                          'left'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'D1D5DB']],
                                          'right'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'D1D5DB']]],
                        ]);
                        $sh->getStyle("B{$row}")->applyFromArray([
                            'font' => ['bold'=>true,'size'=>9,'color'=>['rgb'=>'374151']],
                        ]);
                    } else {
                        // Fila de detalle normal
                        $sh->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font'    => ['size'=>9,'color'=>['rgb'=>'374151']],
                            'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'FFFFFF']],
                            'borders' => ['allBorders'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'E5E7EB']]],
                        ]);
                    }

                    $esPrimeraFilaDeSub    = false;
                    $esPrimeraSubDeFamilia = false;
                    $row++;
                }
            }
        }

        // ── Total General ────────────────────────────────────
        $sh->setCellValue("A{$row}", 'TOTAL GENERAL');
        $sh->mergeCells("A{$row}:F{$row}");
        $sh->setCellValue("G{$row}", $totalGeneral);
        $sh->getStyle("A{$row}:G{$row}")->applyFromArray([
            'font'    => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
            'fill'    => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'111827']],
            'borders' => ['top'=>['borderStyle'=>$B::BORDER_MEDIUM,'color'=>['rgb'=>'000000']]],
        ]);
        $sh->getStyle("G{$row}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
        $sh->getRowDimension($row)->setRowHeight(22);

        // Anchos de columna
        $sh->getColumnDimension('A')->setWidth(28);
        $sh->getColumnDimension('B')->setWidth(20);
        $sh->getColumnDimension('C')->setWidth(20);
        $sh->getColumnDimension('D')->setWidth(46);
        $sh->getColumnDimension('E')->setWidth(13);
        $sh->getColumnDimension('F')->setWidth(15);
        $sh->getColumnDimension('G')->setWidth(16);

        // ── Hoja 2: Resumen ──────────────────────────────────
        $rs = $spreadsheet->createSheet();
        $rs->setTitle('Resumen');
        $rs->getTabColor()->setRGB('059669');

        $rsRows = [
            ['Reporte',                  'Inventario Detallado Agrupado'],
            ['Obra',                     $obraNombre],
            ['Fecha de generación',      $now],
            ['Usuario',                  $userName],
            ['',                         ''],
            ['Total de familias',        $cntFamilias],
            ['Total de subfamilias',     $cntSubs],
            ['Total de insumos',         $cntInsumos],
            ['Importe total inventario', $totalGeneral],
        ];

        $rs->setCellValue('A1', 'Resumen del Inventario');
        $rs->mergeCells('A1:B1');
        $rs->getStyle('A1')->applyFromArray([
            'font'      => ['bold'=>true,'size'=>12,'color'=>['rgb'=>'FFFFFF']],
            'fill'      => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'1E3A8A']],
            'alignment' => ['horizontal'=>$A::HORIZONTAL_CENTER,'vertical'=>$A::VERTICAL_CENTER],
        ]);
        $rs->getRowDimension(1)->setRowHeight(26);

        foreach ($rsRows as $i => $rd) {
            $rn = $i + 2;
            $rs->setCellValue("A{$rn}", $rd[0]);
            $rs->setCellValue("B{$rn}", $rd[1]);
            if ($rd[0] === 'Importe total inventario') {
                $rs->getStyle("B{$rn}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
                $rs->getStyle("A{$rn}:B{$rn}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>11],
                    'fill' => ['fillType'=>$F::FILL_SOLID,'startColor'=>['rgb'=>'DCFCE7']],
                ]);
            } elseif (str_starts_with($rd[0], 'Total')) {
                $rs->getStyle("A{$rn}:B{$rn}")->getFont()->setBold(true);
            }
            $rs->getRowDimension($rn)->setRowHeight(18);
        }
        $rs->getStyle('A2:B10')->applyFromArray([
            'borders' => ['allBorders'=>['borderStyle'=>$B::BORDER_THIN,'color'=>['rgb'=>'D1D5DB']]],
        ]);
        $rs->getColumnDimension('A')->setWidth(32);
        $rs->getColumnDimension('B')->setWidth(34);

        // ── Stream ────────────────────────────────────────────
        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'inventario_' . now()->format('Ymd_Hi') . '.xlsx';
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        Log::info('Excel export: Inventario Profesional', [
            'user_id'  => Auth::id(),
            'obra_id'  => $obraId,
            'insumos'  => $cntInsumos,
            'familias' => $cntFamilias,
        ]);

        return response()->stream(
            fn() => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'max-age=0, no-cache, no-store',
                'Pragma'              => 'no-cache',
                'Expires'             => '0',
            ]
        );
    }

    /**
     * Exportar Finiquitadas (oc_finiquitos) a Excel.
     */
    public function exportarFiniquitadas(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $obra   = $obraId ? Obra::find($obraId) : null;

        $q     = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $invFamilias = DB::table('inventarios')
            ->select('insumo_id',
                DB::raw("MAX(CASE WHEN familia    IS NOT NULL AND familia    <> '' THEN familia    END) as familia"),
                DB::raw("MAX(CASE WHEN subfamilia IS NOT NULL AND subfamilia <> '' THEN subfamilia END) as subfamilia")
            )
            ->groupBy('insumo_id');

        $rows = DB::table('oc_finiquitos as f')
            ->leftJoinSub($invFamilias, 'inv', 'inv.insumo_id', '=', 'f.insumo')
            ->when($obraId, fn($qq) => $qq->where('f.obra_id', $obraId))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('f.insumo',       'like', "%{$q}%")
                      ->orWhere('f.descripcion', 'like', "%{$q}%")
                      ->orWhere('f.id_pedido',   'like', "%{$q}%");
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('f.created_at', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('f.created_at', '<=', $hasta))
            ->orderByDesc('f.created_at')
            ->limit(10000)
            ->get([
                'f.created_at',
                'f.id_pedido',
                'f.pedido_det_id',
                DB::raw("ISNULL(f.insumo, '') as codigo"),
                DB::raw("ISNULL(f.descripcion, '') as descripcion"),
                DB::raw("ISNULL(f.unidad, '') as unidad"),
                DB::raw("COALESCE(inv.familia,    '') as familia"),
                DB::raw("COALESCE(inv.subfamilia, '') as subfamilia"),
                'f.cantidad_pedida',
                'f.cantidad_recibida',
                DB::raw("ISNULL(f.diferencia, 0) as diferencia"),
            ]);

        $fmtDate = fn($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';

        $data = $rows->map(fn($r) => [
            $fmtDate($r->created_at),              // 0
            (int) $r->id_pedido,                   // 1 integer
            (int) $r->pedido_det_id,               // 2 integer
            (string) $r->familia,                  // 3
            (string) $r->subfamilia,               // 4
            (string) $r->codigo,                   // 5
            (string) $r->descripcion,              // 6
            (string) $r->unidad,                   // 7
            (float) $r->cantidad_pedida,           // 8 number
            (float) $r->cantidad_recibida,         // 9 number
            (float) $r->diferencia,                // 10 number
        ])->values()->toArray();

        $filters = array_filter([
            $obra  ? 'Obra: ' . $obra->nombre : null,
            $q     ? 'Búsqueda: ' . $q        : null,
            $desde ? 'Desde: ' . $desde        : null,
            $hasta ? 'Hasta: ' . $hasta         : null,
        ]);

        Log::info('Excel export: Finiquitadas', [
            'user_id'   => Auth::id(),
            'obra_id'   => $obraId,
            'registros' => count($data),
            'filtros'   => $filters,
        ]);

        return ExcelExporter::download(
            filename:    'finiquitadas',
            moduleName:  'Finiquitadas',
            headers:     [
                'Fecha', 'OC #', 'Det #',
                'Familia', 'Subfamilia', 'Código', 'Descripción', 'Unidad',
                'Cant. Pedida', 'Cant. Recibida', 'Diferencia',
            ],
            rows:        $data,
            columnTypes:  [1 => 'integer', 2 => 'integer', 8 => 'number', 9 => 'number', 10 => 'number'],
            filters:      $filters,
            color:        '475569',
            columnWidths: [6 => 42],   // Descripción
        );
    }

    /**
     * PDF de una transferencia.
     */
    public function transferenciaPdf($id)
    {
        $user   = Auth::user();
        $obraId = $user?->obra_actual_id;

        $t = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->join('users as u',  'u.id',  '=', 't.user_id')
            ->where('t.id', $id)
            ->select([
                't.*',
                DB::raw('oo.nombre as obra_origen'),
                DB::raw('od.nombre as obra_destino'),
                DB::raw('u.name as usuario'),
            ])
            ->first();

        if (! $t) abort(404);

        // Permiso: la obra origen O la obra destino pueden descargar el PDF
        if ($obraId
            && (int) $t->obra_origen_id  !== (int) $obraId
            && (int) $t->obra_destino_id !== (int) $obraId) {
            abort(403);
        }

        $detalles = DB::table('transferencias_entre_obras_detalle')
            ->where('transferencia_id', $id)
            ->orderBy('id')
            ->get();

        // Resolver ruta local de la firma para dompdf
        $firmaLocal = null;
        $firmaPath  = $t->firma_path ?? null;
        if (! empty($firmaPath)) {
            $abs = storage_path('app/public/' . ltrim($firmaPath, '/'));
            if (is_file($abs)) $firmaLocal = $abs;
        }

        $pdf = Pdf::loadView('pdf.transferencia', compact('t', 'detalles', 'firmaLocal'));

        return $pdf->download("transferencia_{$id}.pdf");
    }

    /* ─── AJUSTES DE SALIDA ─────────────────────────────────────────────── */

    public function ajustarSalida(Request $request, Movimiento $movimiento)
    {
        $obraId = Auth::user()?->obra_actual_id;
        if ($obraId && (int)$movimiento->obra_id !== (int)$obraId) {
            abort(403);
        }

        $items = $request->input('items', []);
        if (empty($items)) {
            return response()->json(['error' => 'No hay items para ajustar.'], 422);
        }

        $errores = [];
        $registros = [];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $detalleId  = (int)($item['detalle_id'] ?? 0);
                $cantAjuste = (float)($item['cantidad'] ?? 0);

                if ($cantAjuste <= 0) continue;

                $detalle = MovimientoDetalle::find($detalleId);
                if (!$detalle || (int)$detalle->movimiento_id !== (int)$movimiento->id) {
                    $errores[] = "Detalle {$detalleId} no encontrado.";
                    continue;
                }

                // Calcular cuánto ya se ha devuelto de este detalle
                $yaDevuelto = AjusteSalida::where('movimiento_detalle_id', $detalleId)
                    ->sum('cantidad_devuelta');

                $disponible = (float)$detalle->cantidad - (float)$yaDevuelto;

                if ($cantAjuste > $disponible) {
                    $errores[] = "{$detalle->descripcion}: máximo a devolver es {$disponible} {$detalle->unidad}.";
                    continue;
                }

                // Registrar ajuste
                $ajuste = AjusteSalida::create([
                    'movimiento_id'        => $movimiento->id,
                    'movimiento_detalle_id'=> $detalle->id,
                    'inventario_id'        => $detalle->inventario_id,
                    'user_id'              => Auth::id(),
                    'descripcion'          => $detalle->descripcion,
                    'unidad'               => $detalle->unidad,
                    'cantidad_devuelta'    => $cantAjuste,
                    'observaciones'        => $request->input('observaciones'),
                ]);

                // Reintegrar al inventario con lock para evitar condición de carrera
                if ($detalle->inventario_id) {
                    $invAjuste = Inventario::where('id', $detalle->inventario_id)
                        ->lockForUpdate()
                        ->first();
                    if ($invAjuste) {
                        $invAjuste->cantidad = (float) $invAjuste->cantidad + $cantAjuste;
                        $invAjuste->save();
                    }
                }

                $registros[] = $ajuste->id;
            }

            if (!empty($errores) && empty($registros)) {
                DB::rollBack();
                return response()->json(['error' => implode(' | ', $errores)], 422);
            }

            DB::commit();

            return response()->json([
                'ok'       => true,
                'ajustes'  => count($registros),
                'errores'  => $errores,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ajustarSalida: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al guardar ajuste.'], 500);
        }
    }

    public function historialAjustes(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');
        $userId = $request->get('user_id');
        $q      = trim((string)$request->get('q', ''));

        $rows = AjusteSalida::query()
            ->join('movimientos', 'movimientos.id', '=', 'ajustes_salida.movimiento_id')
            ->join('users', 'users.id', '=', 'ajustes_salida.user_id')
            ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde,  fn($qq) => $qq->whereDate('ajustes_salida.created_at', '>=', $desde))
            ->when($hasta,  fn($qq) => $qq->whereDate('ajustes_salida.created_at', '<=', $hasta))
            ->when($userId, fn($qq) => $qq->where('ajustes_salida.user_id', $userId))
            ->when($q !== '', fn($qq) => $qq->where('ajustes_salida.descripcion', 'like', "%{$q}%"))
            ->orderByDesc('ajustes_salida.created_at')
            ->get([
                'ajustes_salida.id',
                'ajustes_salida.movimiento_id',
                'ajustes_salida.descripcion',
                'ajustes_salida.unidad',
                'ajustes_salida.cantidad_devuelta',
                'ajustes_salida.observaciones',
                'ajustes_salida.created_at',
                DB::raw('users.name as usuario'),
            ]);

        return response()->json($rows->values());
    }

    public function detallesParaAjuste(Movimiento $movimiento)
    {
        $obraId = Auth::user()?->obra_actual_id;
        if ($obraId && (int)$movimiento->obra_id !== (int)$obraId) {
            abort(403);
        }

        $detalles = MovimientoDetalle::where('movimiento_id', $movimiento->id)
            ->orderBy('id')
            ->get(['id','inventario_id','descripcion','unidad','cantidad','devolvible']);

        // Agregar cuánto ya se devolvió por detalle
        $detalles = $detalles->map(function ($d) {
            $yaDevuelto = AjusteSalida::where('movimiento_detalle_id', $d->id)
                ->sum('cantidad_devuelta');
            $d->ya_devuelto  = (float)$yaDevuelto;
            $d->disponible   = max(0, (float)$d->cantidad - (float)$yaDevuelto);
            return $d;
        });

        return response()->json($detalles->values());
    }

    /**
     * MOVIMIENTOS DETALLADOS: entradas + salidas unificadas en orden cronológico.
     * Entradas → cantidad positiva. Salidas → cantidad negativa.
     */
    public function movimientosDetallados(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');
        $q      = trim((string) $request->get('q', ''));

        $rows = collect();

        // ── 1. Entradas (oc_recepciones) ──────────────────────────────────
        $entradas = DB::table('oc_recepciones as e')
            ->where('e.obra_id', $obraId)
            ->whereNull('e.revertida_at')
            ->when($desde, fn($q2) => $q2->whereDate('e.fecha_recibido', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('e.fecha_recibido', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('e.descripcion', 'like', "%{$q}%")
                      ->orWhere('e.insumo',    'like', "%{$q}%");
                });
            })
            ->orderByDesc('e.fecha_recibido')
            ->select(['e.id', 'e.tipo', 'e.insumo', 'e.descripcion', 'e.unidad',
                      'e.cantidad_llego as cantidad', 'e.precio_unitario',
                      'e.fecha_recibido as fecha', 'e.observaciones'])
            ->get();

        // Match transferencia entries to their source obra
        $transIds = $entradas->where('tipo', 'transferencia')->pluck('id')->toArray();
        $transMatchMap = [];
        if (!empty($transIds)) {
            $matched = DB::select("
                SELECT ocr_id, origen_nombre
                FROM (
                    SELECT ocr.id AS ocr_id, oo.nombre AS origen_nombre,
                           ROW_NUMBER() OVER (
                               PARTITION BY ocr.id
                               ORDER BY ABS(DATEDIFF(day, CAST(ocr.fecha_recibido AS DATE), te.fecha))
                           ) AS rn
                    FROM oc_recepciones ocr
                    INNER JOIN transferencias_entre_obras_detalle ted ON ted.insumo_id = ocr.insumo
                    INNER JOIN transferencias_entre_obras te
                        ON te.id = ted.transferencia_id AND te.obra_destino_id = ocr.obra_id
                    INNER JOIN obras oo ON oo.id = te.obra_origen_id
                    WHERE ocr.id IN (" . implode(',', array_map('intval', $transIds)) . ")
                ) ranked WHERE rn = 1
            ");
            foreach ($matched as $m) {
                $transMatchMap[(int)$m->ocr_id] = (string) $m->origen_nombre;
            }
        }

        foreach ($entradas as $e) {
            $tipoE = $e->tipo ?: 'oc';
            [$tipoLabel, $origen] = match ($tipoE) {
                'manual'        => ['Entrada Manual',        'Manual'],
                'transferencia' => ['Entrada Transferencia', $transMatchMap[(int)$e->id] ?? 'Transferencia'],
                default         => ['Entrada OC',            'ERP / OC'],
            };

            $cantidad = (float) $e->cantidad;
            $pu       = $e->precio_unitario !== null ? (float) $e->precio_unitario : null;

            $rows->push([
                'id'             => 'e_' . $e->id,
                'fecha'          => (string) $e->fecha,
                'tipo'           => $tipoLabel,
                'tipo_key'       => 'entrada',
                'origen_destino' => $origen,
                'codigo'         => (string) $e->insumo,
                'descripcion'    => (string) $e->descripcion,
                'unidad'         => (string) ($e->unidad ?? ''),
                'cantidad'       => $cantidad,
                'precio_unitario'=> $pu,
                'importe'        => $pu !== null ? round($cantidad * $pu, 2) : null,
            ]);
        }

        // ── 2. Salidas (movimiento_detalles) ──────────────────────────────
        $salidas = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->leftJoin('inventarios as inv', 'inv.id', '=', 'md.inventario_id')
            ->where('m.obra_id', $obraId)
            ->when($desde, fn($q2) => $q2->whereDate('m.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('m.fecha', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('md.descripcion',    'like', "%{$q}%")
                      ->orWhere('md.inventario_id', 'like', "%{$q}%")
                      ->orWhere('inv.insumo_id',    'like', "%{$q}%");
                });
            })
            ->orderByDesc('m.fecha')->orderByDesc('m.id')
            ->select(['md.id', 'm.fecha', 'm.destino', 'm.nombre_cabo',
                      DB::raw('inv.insumo_id as codigo_insumo'),
                      'md.inventario_id', 'md.descripcion', 'md.unidad',
                      'md.cantidad', 'md.precio_unitario'])
            ->get();

        $obraNombre = \App\Models\Obra::where('id', $obraId)->value('nombre') ?? '';

        foreach ($salidas as $s) {
            $cantidad = -(float) $s->cantidad;
            $pu       = $s->precio_unitario !== null ? (float) $s->precio_unitario : null;
            $cabo     = (string) ($s->nombre_cabo ?? '');
            $destino  = $cabo !== '' ? "{$obraNombre} / {$cabo}" : $obraNombre;

            $rows->push([
                'id'             => 's_' . $s->id,
                'fecha'          => (string) $s->fecha,
                'tipo'           => 'Salida',
                'tipo_key'       => 'salida',
                'origen_destino' => $destino,
                'codigo'         => (string) ($s->codigo_insumo ?? $s->inventario_id ?? ''),
                'descripcion'    => (string) $s->descripcion,
                'unidad'         => (string) ($s->unidad ?? ''),
                'cantidad'       => $cantidad,
                'precio_unitario'=> $pu,
                'importe'        => $pu !== null ? round($cantidad * $pu, 2) : null,
            ]);
        }

        // ── 3. Transferencias enviadas ─────────────────────────────────────
        $transferencias = DB::table('transferencias_entre_obras_detalle as d')
            ->join('transferencias_entre_obras as t', 't.id', '=', 'd.transferencia_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->where('t.obra_origen_id', $obraId)
            ->when($desde, fn($q2) => $q2->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('t.fecha', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('d.descripcion', 'like', "%{$q}%")
                      ->orWhere('d.insumo_id',  'like', "%{$q}%")
                      ->orWhere('od.nombre',    'like', "%{$q}%");
                });
            })
            ->orderByDesc('t.fecha')->orderByDesc('t.id')
            ->select(['d.id', 't.fecha', 'd.insumo_id', 'd.descripcion', 'd.unidad',
                      'd.cantidad', 'd.precio_unitario',
                      DB::raw('od.nombre as obra_destino')])
            ->get();

        foreach ($transferencias as $tr) {
            $cantidad = -(float) $tr->cantidad;
            $pu       = $tr->precio_unitario !== null ? (float) $tr->precio_unitario : null;

            $rows->push([
                'id'             => 'tr_' . $tr->id,
                'fecha'          => (string) $tr->fecha,
                'tipo'           => 'Salida Transferencia',
                'tipo_key'       => 'salida_transferencia',
                'origen_destino' => (string) ($tr->obra_destino ?? ''),
                'codigo'         => (string) ($tr->insumo_id ?? ''),
                'descripcion'    => (string) $tr->descripcion,
                'unidad'         => (string) ($tr->unidad ?? ''),
                'cantidad'       => $cantidad,
                'precio_unitario'=> $pu,
                'importe'        => $pu !== null ? round($cantidad * $pu, 2) : null,
            ]);
        }

        return response()->json(
            $rows->sortByDesc('fecha')->values()
        );
    }

    /**
     * GET /explore/exportar/movimientos-detallados
     * Exporta Entradas + Salidas + Transferencias enviadas en una sola hoja Excel.
     */
    public function exportarMovimientosDetallados(Request $request)
    {
        $user   = Auth::user();
        $obraId = $user?->obra_actual_id;
        $obra   = $obraId ? Obra::find($obraId) : null;

        $q     = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $rows = collect();

        // ── 1. Entradas ───────────────────────────────────────────────────
        $entradas = DB::table('oc_recepciones as e')
            ->where('e.obra_id', $obraId)
            ->whereNull('e.revertida_at')
            ->when($desde, fn($q2) => $q2->whereDate('e.fecha_recibido', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('e.fecha_recibido', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('e.descripcion', 'like', "%{$q}%")
                      ->orWhere('e.insumo',    'like', "%{$q}%");
                });
            })
            ->orderByDesc('e.fecha_recibido')
            ->select(['e.id', 'e.tipo', 'e.insumo', 'e.descripcion', 'e.unidad',
                      'e.cantidad_llego as cantidad', 'e.precio_unitario',
                      'e.fecha_recibido as fecha', 'e.observaciones'])
            ->get();

        $fmtDate = fn($d) => $d ? date('d/m/Y', strtotime((string) $d)) : '';

        foreach ($entradas as $e) {
            $tipoE = $e->tipo ?: 'oc';
            [$tipoLabel, $origenLabel] = match ($tipoE) {
                'manual'        => ['Entrada Manual',        'Entrada directa'],
                'transferencia' => ['Entrada Transferencia', 'Transferencia recibida'],
                default         => ['Entrada OC',            'ERP / OC'],
            };
            $cantidad = (float) $e->cantidad;
            $pu       = $e->precio_unitario !== null ? (float) $e->precio_unitario : null;
            $rows->push([
                $fmtDate($e->fecha),                                               // 0 fecha
                $tipoLabel,                                                        // 1 tipo
                $origenLabel,                                                      // 2 origen/destino
                (string) $e->insumo,                                               // 3 código
                (string) $e->descripcion,                                          // 4 descripción
                (string) ($e->unidad ?? ''),                                       // 5 unidad
                $cantidad,                                                         // 6 cantidad
                $pu,                                                               // 7 p.u.
                $pu !== null ? round($cantidad * $pu, 2) : null,                   // 8 importe
            ]);
        }

        // ── 2. Salidas ────────────────────────────────────────────────────
        $obraNombre = $obra?->nombre ?? '';
        $salidas = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->leftJoin('inventarios as inv', 'inv.id', '=', 'md.inventario_id')
            ->where('m.obra_id', $obraId)
            ->when($desde, fn($q2) => $q2->whereDate('m.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('m.fecha', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('md.descripcion', 'like', "%{$q}%")
                      ->orWhere('inv.insumo_id', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('m.fecha')->orderByDesc('m.id')
            ->select(['md.id', 'm.fecha', 'm.destino', 'm.nombre_cabo',
                      DB::raw('inv.insumo_id as codigo_insumo'),
                      'md.descripcion', 'md.unidad', 'md.cantidad', 'md.precio_unitario'])
            ->get();

        foreach ($salidas as $s) {
            $cantidad    = -(float) $s->cantidad;
            $pu          = $s->precio_unitario !== null ? (float) $s->precio_unitario : null;
            $destinoProy = trim((string) ($s->destino     ?? ''));
            $cabo        = trim((string) ($s->nombre_cabo ?? ''));
            $destino     = implode(' / ', array_filter([$obraNombre, $destinoProy, $cabo]));
            $rows->push([
                $fmtDate($s->fecha),                                               // 0 fecha
                'Salida',                                                          // 1 tipo
                $destino,                                                          // 2 origen/destino
                (string) ($s->codigo_insumo ?? ''),                                // 3 código
                (string) $s->descripcion,                                          // 4 descripción
                (string) ($s->unidad ?? ''),                                       // 5 unidad
                $cantidad,                                                         // 6 cantidad (negativa)
                $pu,                                                               // 7 p.u.
                $pu !== null ? round(abs($cantidad) * $pu, 2) : null,              // 8 importe
            ]);
        }

        // ── 3. Transferencias enviadas ────────────────────────────────────
        $transferencias = DB::table('transferencias_entre_obras_detalle as d')
            ->join('transferencias_entre_obras as t', 't.id', '=', 'd.transferencia_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->where('t.obra_origen_id', $obraId)
            ->when($desde, fn($q2) => $q2->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('t.fecha', '<=', $hasta))
            ->when($q !== '', function ($q2) use ($q) {
                $q2->where(function ($w) use ($q) {
                    $w->where('d.descripcion', 'like', "%{$q}%")
                      ->orWhere('d.insumo_id',  'like', "%{$q}%");
                });
            })
            ->orderByDesc('t.fecha')->orderByDesc('t.id')
            ->select(['d.id', 't.fecha', 'd.insumo_id', 'd.descripcion', 'd.unidad',
                      'd.cantidad', 'd.precio_unitario', DB::raw('od.nombre as obra_destino')])
            ->get();

        foreach ($transferencias as $tr) {
            $cantidad = -(float) $tr->cantidad;
            $pu       = $tr->precio_unitario !== null ? (float) $tr->precio_unitario : null;
            $rows->push([
                $fmtDate($tr->fecha),                                              // 0 fecha
                'Salida Transferencia',                                            // 1 tipo
                (string) ($tr->obra_destino ?? ''),                                // 2 origen/destino
                (string) ($tr->insumo_id ?? ''),                                   // 3 código
                (string) $tr->descripcion,                                         // 4 descripción
                (string) ($tr->unidad ?? ''),                                      // 5 unidad
                $cantidad,                                                         // 6 cantidad
                $pu,                                                               // 7 p.u.
                $pu !== null ? round(abs($cantidad) * $pu, 2) : null,              // 8 importe
            ]);
        }

        $data = $rows->sortByDesc(fn($r) => $r[0])->values()->toArray();

        $filters = array_filter([
            $obra  ? 'Obra: ' . $obra->nombre : null,
            $q     ? 'Búsqueda: ' . $q        : null,
            $desde ? 'Desde: ' . $desde        : null,
            $hasta ? 'Hasta: ' . $hasta         : null,
        ]);

        Log::info('Excel export: Movimientos Detallados', [
            'user_id'   => Auth::id(),
            'obra_id'   => $obraId,
            'registros' => count($data),
            'filtros'   => $filters,
        ]);

        $total = round(array_sum(array_column(array_filter($data, fn($r) => $r[8] !== null), 8)), 2);

        return ExcelExporter::download(
            filename:    'movimientos_detallados',
            moduleName:  'Movimientos Detallados',
            headers:     ['Fecha', 'Tipo', 'Origen / Destino', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe'],
            rows:        $data,
            columnTypes: [6 => 'number', 7 => 'currency', 8 => 'currency'],
            filters:     $filters,
            color:       '4338CA',
            columnWidths: [4 => 42],
            totalRow:    [0 => 'TOTAL', 8 => $total],
        );
    }
}
