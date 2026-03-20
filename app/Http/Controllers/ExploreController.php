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
use App\Services\ExcelExporter;


class ExploreController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
     
        $obraActualId = $user->obra_actual_id;
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        return view('explore.explore', compact('obraActual'));
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

    $q = trim((string) $request->get('q', ''));
    $desde = $request->get('desde');
    $hasta = $request->get('hasta');

    $rows = Movimiento::query()
        ->leftJoin('obras as o', 'o.id', '=', 'movimientos.obra_id')
        ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('movimientos.nombre_cabo', 'like', "%{$q}%")
                  ->orWhere('movimientos.destino', 'like', "%{$q}%")
                  ->orWhere('o.nombre', 'like', "%{$q}%"); // ? tambi�n buscar por obra
            });
        })
        ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
        ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
        ->orderByDesc('movimientos.fecha')
        ->limit(80)
        ->get([
            'movimientos.id',
            'movimientos.obra_id',
            'movimientos.user_id',
            'movimientos.fecha',
            'movimientos.destino',
            'movimientos.nombre_cabo',
            'movimientos.estatus',
            DB::raw('o.nombre as obra'), // ? nombre de obra
        ]);

    // Resolver nombres legibles de destino desde ERP
    $erpNombres = $this->resolverNombresDestino(
        $rows->pluck('destino')->filter()->unique()->values()->toArray()
    );

    $rows = $rows->map(function ($row) use ($erpNombres) {
        $row->destino_nombre = $erpNombres[$row->destino] ?? $row->destino;
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
        ->orderBy('id')
        ->get([
            'id','movimiento_id','inventario_id','familia','subfamilia',
            'descripcion','unidad','cantidad','devolvible','clasificacion','clasificacion_d'
        ]);

    $erpNombres = $this->resolverNombresDestino(
        array_filter([$movimiento->destino])
    );

    return response()->json([
        'movimiento' => [
            'id' => (int) $movimiento->id,
            'obra_id' => (int) $movimiento->obra_id,
            'obra' => $obraNombre, // ?
            'destino' => $movimiento->destino,
            'destino_nombre' => $erpNombres[$movimiento->destino] ?? $movimiento->destino,
            'fecha' => (string) $movimiento->fecha,
            'nombre_cabo' => $movimiento->nombre_cabo,
            'estatus' => $movimiento->estatus,
        ],
        'detalles' => $det,
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

        $rows = MovimientoDetalle::query()
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->leftJoin('inventarios', 'inventarios.id', '=', 'movimiento_detalles.inventario_id')
            ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('movimiento_detalles.descripcion', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.inventario_id', 'like', "%{$q}%")
                      ->orWhere('inventarios.insumo_id', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('movimientos.fecha')
            ->orderByDesc('movimientos.id')
            ->limit(2000)
            ->get([
                'movimiento_detalles.id',
                'movimiento_detalles.movimiento_id',
                'movimiento_detalles.inventario_id',
                'movimiento_detalles.familia',
                'movimiento_detalles.descripcion',
                'movimiento_detalles.unidad',
                'movimiento_detalles.cantidad',
                'movimiento_detalles.precio_unitario',
                'movimientos.fecha',
                DB::raw('inventarios.insumo_id as codigo_insumo'),
            ]);

        return response()->json($rows->map(fn($r) => [
            'id'              => $r->id,
            'movimiento_id'   => $r->movimiento_id,
            'fecha'           => (string) $r->fecha,
            'familia'         => (string) ($r->familia ?? 'SIN FAMILIA'),
            'insumo_id'       => (string) ($r->codigo_insumo ?? $r->inventario_id ?? ''),
            'descripcion'     => (string) $r->descripcion,
            'unidad'          => (string) $r->unidad,
            'cantidad'        => (float)  $r->cantidad,
            'precio_unitario' => $r->precio_unitario !== null ? (float) $r->precio_unitario : null,
            'importe'         => $r->precio_unitario !== null
                                    ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                                    : null,
        ])->values());
    }

    /**
     * INVENTARIO (local) búsqueda por id o descripción
     */
    public function inventario(Request $request)
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        $q = trim((string) $request->get('q', ''));

        // Soporta "#RP-80-12"
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
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get([
                'id','insumo_id','familia','subfamilia','descripcion','unidad','cantidad',
                'costo_promedio','destino','proveedor','devolvible','updated_at'
            ]);

        return response()->json($rows->map(fn($r) => [
            'id'             => $r->id,
            'insumo_id'      => (string) ($r->insumo_id ?? ''),
            'familia'        => (string) ($r->familia ?? ''),
            'subfamilia'     => (string) ($r->subfamilia ?? ''),
            'descripcion'    => (string) ($r->descripcion ?? ''),
            'unidad'         => (string) ($r->unidad ?? ''),
            'cantidad'       => (float)  ($r->cantidad ?? 0),
            'costo_promedio' => $r->costo_promedio !== null ? (float) $r->costo_promedio : null,
            'importe'        => $r->costo_promedio !== null
                                    ? round((float) $r->cantidad * (float) $r->costo_promedio, 2)
                                    : null,
            'destino'        => (string) ($r->destino ?? ''),
            'proveedor'      => (string) ($r->proveedor ?? ''),
            'devolvible'     => $r->devolvible,
            'updated_at'     => (string) ($r->updated_at ?? ''),
        ])->values());
    }

    /**
     * ✅ Busca una columna "fecha última actualización" en una tabla del ERP.
     * Regresa una expresión SQL (ej: "PD.updated_at") o null si no encuentra.
     */
    private function erpFindUpdatedExpr(string $tableAlias, string $tableName): ?string
    {
        $candidates = [
            'FechaModificacion',
            'FechaActualizacion',
            'FechaUpdate',
            'FechaCambio',
            'UpdatedAt',
            'updated_at',
            'LastUpdate',
            'LastUpdated',
            'TimeStamp',
            'timestamp',
        ];

        foreach ($candidates as $col) {
            $exists = DB::connection('erp')->selectOne(
                "SELECT 1 AS ok
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$tableName, $col]
            );

            if ($exists) {
                $type = DB::connection('erp')->selectOne(
                    "SELECT DATA_TYPE AS t
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$tableName, $col]
                );

                $t = strtolower((string)($type->t ?? ''));
                if (in_array($t, ['timestamp', 'rowversion', 'binary', 'varbinary'], true)) {
                    continue;
                }

                return "{$tableAlias}.{$col}";
            }
        }

        return null;
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

    $q = trim((string) $request->get('q', ''));
    $desde = $request->get('desde');
    $hasta = $request->get('hasta');

    $rows = OcRecepcion::query()
        ->where('obra_id', $obraId)
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('insumo', 'like', "%{$q}%")
                  ->orWhere('descripcion', 'like', "%{$q}%")
                  ->orWhere('id_pedido', 'like', "%{$q}%");
            });
        })
        ->when($desde, fn($qq) => $qq->whereDate('fecha_recibido', '>=', $desde))
        ->when($hasta, fn($qq) => $qq->whereDate('fecha_recibido', '<=', $hasta))
        ->orderByDesc('fecha_recibido')
        ->limit(200)
        ->get();

    // Obtener familia de cada insumo buscando en inventarios de la misma obra
    $insumosList = $rows->pluck('insumo')->unique()->filter()->values()->toArray();
    $familiasMap = [];
    if (!empty($insumosList) && $obraId) {
        $familiasMap = Inventario::whereIn('insumo_id', $insumosList)
            ->where('obra_id', $obraId)
            ->pluck('familia', 'insumo_id')
            ->toArray();
    }

    // ⚠️ Nota: aquí mandamos resumen. El desglose va en /detalles
    return $rows->map(function ($r) use ($familiasMap) {
        return [
            'id' => (int) $r->id,
            'id_pedido' => (string) $r->id_pedido,
            'pedido_det_id' => (int) $r->pedido_det_id,
            'familia'         => (string) ($familiasMap[$r->insumo] ?? 'SIN FAMILIA'),
            'insumo' => (string) $r->insumo,
            'descripcion' => (string) $r->descripcion,
            'unidad' => (string) $r->unidad,
            'cantidad_llego'  => (float) $r->cantidad_llego,
            'precio_unitario' => $r->precio_unitario !== null ? (float) $r->precio_unitario : null,
            'importe'         => $r->precio_unitario !== null
                                    ? round((float) $r->cantidad_llego * (float) $r->precio_unitario, 2)
                                    : null,
            'fecha_oc'        => $r->fecha_oc ? (string) $r->fecha_oc : null,
            'fecha_recibido'  => $r->fecha_recibido ? (string) $r->fecha_recibido : null,
            'tiene_foto'      => !empty($r->foto_path),
        ];
    });
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

        // ? lista para <img :src="...">
        'foto_url'      => $fotoUrl,

        // ? debug opcional
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
    // TRANSFERENCIAS ENTRE OBRAS
    // ════════════════════════════════════════════════════════════

    /**
     * Lista de transferencias (JSON) para la pestaña Explore.
     * Incluye tanto las enviadas (obra_origen) como las recibidas (obra_destino).
     * Cada registro incluye el campo "direccion": "enviada" | "recibida".
     */
    public function transferencias(Request $request)
    {
        $user   = Auth::user();
        $obraId = $user?->obra_actual_id;

        $q     = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $rows = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->join('users as u',  'u.id',  '=', 't.user_id')
            ->when($obraId, function ($q2) use ($obraId) {
                // Muestra tanto enviadas (origen) como recibidas (destino)
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
            ->limit(200)
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
            ])
            ->get();

        return response()->json($rows->map(fn($r) => array_merge((array) $r, [
            'direccion' => ($obraId && (int) $r->obra_origen_id === (int) $obraId)
                          ? 'enviada'
                          : 'recibida',
        ])));
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

        $rows = OcRecepcion::query()
            ->where('obra_id', $obraId)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('insumo', 'like', "%{$q}%")
                      ->orWhere('descripcion', 'like', "%{$q}%")
                      ->orWhere('id_pedido', 'like', "%{$q}%");
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('fecha_recibido', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('fecha_recibido', '<=', $hasta))
            ->orderByDesc('fecha_recibido')
            ->get();

        // Lookup de familia desde inventarios
        $insumosList = $rows->pluck('insumo')->unique()->filter()->values()->toArray();
        $familiasMap = [];
        if (!empty($insumosList) && $obraId) {
            $familiasMap = Inventario::whereIn('insumo_id', $insumosList)
                ->where('obra_id', $obraId)
                ->pluck('familia', 'insumo_id')
                ->toArray();
        }

        $data = $rows->map(fn($r) => [
            $r->fecha_recibido ? (string) $r->fecha_recibido : '',        // 0 date
            (string) $r->id_pedido,                                        // 1 text
            (int) $r->pedido_det_id,                                       // 2 integer
            (string) ($familiasMap[$r->insumo] ?? 'SIN FAMILIA'),          // 3 text
            (string) $r->insumo,                                           // 4 text
            (string) $r->descripcion,                                      // 5 text
            (string) $r->unidad,                                           // 6 text
            (float) $r->cantidad_llego,                                    // 7 number
            $r->precio_unitario !== null ? (float) $r->precio_unitario : null,  // 8 currency
            $r->precio_unitario !== null
                ? round((float) $r->cantidad_llego * (float) $r->precio_unitario, 2)
                : null,                                                    // 9 currency
            $r->foto_path ? 'Sí' : 'No',                                  // 10 text
        ])->values()->toArray();

        $filters = array_filter([
            $obra   ? 'Obra: ' . $obra->nombre : null,
            $q      ? 'Búsqueda: ' . $q        : null,
            $desde  ? 'Desde: ' . $desde        : null,
            $hasta  ? 'Hasta: ' . $hasta         : null,
        ]);

        Log::info('Excel export: Entradas', [
            'user_id'  => Auth::id(),
            'obra_id'  => $obraId,
            'registros'=> count($data),
            'filtros'  => $filters,
        ]);

        return ExcelExporter::download(
            filename:    'entradas',
            moduleName:  'Entradas',
            headers:     ['Fecha Recibido', 'OC #', 'Det #', 'Familia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe', 'Tiene Foto'],
            rows:        $data,
            columnTypes: [0 => 'date', 2 => 'integer', 7 => 'number', 8 => 'currency', 9 => 'currency'],
            filters:     $filters,
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

        $rows = MovimientoDetalle::query()
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->leftJoin('inventarios', 'inventarios.id', '=', 'movimiento_detalles.inventario_id')
            ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('movimiento_detalles.descripcion', 'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.inventario_id', 'like', "%{$q}%")
                      ->orWhere('inventarios.insumo_id', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('movimientos.fecha')
            ->orderBy('movimiento_detalles.inventario_id')
            ->get([
                'movimientos.fecha',
                'movimientos.id as movimiento_id',
                'movimiento_detalles.familia',
                DB::raw('COALESCE(inventarios.insumo_id, CAST(movimiento_detalles.inventario_id AS CHAR)) as codigo'),
                'movimiento_detalles.descripcion',
                'movimiento_detalles.unidad',
                'movimiento_detalles.cantidad',
                'movimiento_detalles.precio_unitario',
            ]);

        $data = $rows->map(fn($r) => [
            $r->fecha ? (string) $r->fecha : '',                            // 0 date
            (int) $r->movimiento_id,                                        // 1 integer
            (string) ($r->familia ?? 'SIN FAMILIA'),                        // 2 text
            (string) ($r->codigo ?? ''),                                    // 3 text
            (string) $r->descripcion,                                       // 4 text
            (string) $r->unidad,                                            // 5 text
            (float) $r->cantidad,                                           // 6 number
            $r->precio_unitario !== null ? (float) $r->precio_unitario : null,   // 7 currency
            $r->precio_unitario !== null
                ? round((float) $r->cantidad * (float) $r->precio_unitario, 2)
                : null,                                                     // 8 currency
        ])->values()->toArray();

        $filters = array_filter([
            $obra  ? 'Obra: ' . $obra->nombre : null,
            $q     ? 'Búsqueda: ' . $q        : null,
            $desde ? 'Desde: ' . $desde        : null,
            $hasta ? 'Hasta: ' . $hasta         : null,
        ]);

        Log::info('Excel export: Salidas', [
            'user_id'  => Auth::id(),
            'obra_id'  => $obraId,
            'registros'=> count($data),
            'filtros'  => $filters,
        ]);

        return ExcelExporter::download(
            filename:    'salidas',
            moduleName:  'Salidas',
            headers:     ['Fecha', '# Movimiento', 'Familia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U.', 'Importe'],
            rows:        $data,
            columnTypes: [0 => 'date', 1 => 'integer', 6 => 'number', 7 => 'currency', 8 => 'currency'],
            filters:     $filters,
        );
    }

    /**
     * Exportar Inventario a Excel.
     * Mismos filtros que inventario() pero sin límite.
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
            ->orderBy('familia')
            ->orderBy('subfamilia')
            ->orderBy('descripcion')
            ->get([
                'id', 'insumo_id', 'familia', 'subfamilia', 'descripcion',
                'unidad', 'cantidad', 'costo_promedio', 'proveedor', 'devolvible', 'updated_at',
            ]);

        $data = $rows->map(fn($r) => [
            (string) ($r->familia    ?? ''),                                          // 0 text
            (string) ($r->subfamilia ?? ''),                                          // 1 text
            (string) ($r->insumo_id  ?? ''),                                          // 2 text
            (string) ($r->descripcion ?? ''),                                         // 3 text
            (string) ($r->unidad     ?? ''),                                          // 4 text
            (float)  ($r->cantidad   ?? 0),                                           // 5 number
            $r->costo_promedio !== null ? (float) $r->costo_promedio : null,          // 6 currency
            $r->costo_promedio !== null
                ? round((float) $r->cantidad * (float) $r->costo_promedio, 2)
                : null,                                                               // 7 currency
            (string) ($r->proveedor  ?? ''),                                          // 8 text
            $r->devolvible ? 'Sí' : 'No',                                            // 9 text
            $r->updated_at ? $r->updated_at->format('d/m/Y') : '',                   // 10 text
        ])->values()->toArray();

        $filters = array_filter([
            $obra ? 'Obra: ' . $obra->nombre : null,
            $q    ? 'Búsqueda: ' . $q        : null,
        ]);

        Log::info('Excel export: Inventario', [
            'user_id'  => Auth::id(),
            'obra_id'  => $obraId,
            'registros'=> count($data),
            'filtros'  => $filters,
        ]);

        return ExcelExporter::download(
            filename:    'inventario',
            moduleName:  'Inventario',
            headers:     ['Familia', 'Subfamilia', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'P.U. (Costo Prom.)', 'Importe', 'Proveedor', 'Devolvible', 'Actualizado'],
            rows:        $data,
            columnTypes: [5 => 'number', 6 => 'currency', 7 => 'currency'],
            filters:     $filters,
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

                // Reintegrar al inventario
                if ($detalle->inventario_id) {
                    Inventario::where('id', $detalle->inventario_id)
                        ->increment('cantidad', $cantAjuste);
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
            ->limit(500)
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
}
