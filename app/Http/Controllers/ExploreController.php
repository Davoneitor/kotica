<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\MovimientoDetalle;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ExploreController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $obraActualId = $user->obra_actual_id;
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        return view('explore.explore', compact('obraActual'));
    }

    /**
     * MOVIMIENTOS (local) -> en Explore se llama "Salidas"
     */
    public function movimientos(Request $request)
    {
        $obraId = auth()->user()->obra_actual_id;

        $q = trim((string) $request->get('q', ''));
        $desde = $request->get('desde');
        $hasta = $request->get('hasta');

        $rows = Movimiento::query()
            ->when($obraId, fn($qq) => $qq->where('obra_id', $obraId))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nombre_cabo', 'like', "%{$q}%")
                      ->orWhere('destino', 'like', "%{$q}%");
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->limit(80)
            ->get(['id','obra_id','user_id','fecha','destino','nombre_cabo','estatus']);

        return response()->json($rows);
    }

    /**
     * DETALLES de un movimiento
     */
    public function movimientoDetalles(Movimiento $movimiento)
    {
        $obraId = auth()->user()->obra_actual_id;
        if ($obraId && (int)$movimiento->obra_id !== (int)$obraId) {
            abort(403);
        }

        $det = MovimientoDetalle::where('movimiento_id', $movimiento->id)
            ->orderBy('id')
            ->get([
                'id','movimiento_id','inventario_id','familia','subfamilia',
                'descripcion','unidad','cantidad','devolvible','clasificacion','clasificacion_d'
            ]);

        return response()->json($det);
    }

    /**
     * INVENTARIO (local) búsqueda por id o descripción
     */
    public function inventario(Request $request)
    {
        $obraId = (int) (auth()->user()->obra_actual_id ?? 0);
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
                'destino','proveedor','devolvible','updated_at'
            ]);

        return response()->json($rows);
    }

    /**
     * ✅ Busca una columna "fecha última actualización" en una tabla del ERP.
     * Regresa una expresión SQL (ej: "PD.updated_at") o null si no encuentra.
     */
    private function erpFindUpdatedExpr(string $tableAlias, string $tableName): ?string
    {
        // Candidatos comunes en ERP
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
            'timestamp', // ojo: a veces es rowversion (no fecha)
        ];

        foreach ($candidates as $col) {
            $exists = DB::connection('erp')->selectOne(
                "SELECT 1 AS ok
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$tableName, $col]
            );

            if ($exists) {
                // rowversion/timestamp NO es fecha real, pero si tu ERP lo usa como datetime
                // lo sabrás por el tipo. Para evitar problemas, validamos tipo no-binario.
                $type = DB::connection('erp')->selectOne(
                    "SELECT DATA_TYPE AS t
                     FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$tableName, $col]
                );

                $t = strtolower((string)($type->t ?? ''));
                if (in_array($t, ['timestamp', 'rowversion', 'binary', 'varbinary'], true)) {
                    // No es fecha usable.
                    continue;
                }

                return "{$tableAlias}.{$col}";
            }
        }

        return null;
    }

    /**
     * ✅ La fecha que usaremos como “Última actualización del registro (sistema)”
     * Regla:
     * 1) PD (detalle)
     * 2) P (pedido)
     * 3) P.FechaPedido (fallback)
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
     * Ejecuta la consulta ERP (historial OC) y agrega FechaUltimaActualizacion
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

                -- ✅ NUEVO: última actualización (sistema) del registro
                {$ultimaExpr} AS FechaUltimaActualizacion

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
        $user = auth()->user();
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

                // ✅ Última actualización del registro (sistema)
                // (ideal para Parciales/Finalizadas)
                'fecha_evento'   => (string) ($r->FechaUltimaActualizacion ?? $r->FechaPedido),

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
        $user = auth()->user();
        $obraActualId = (int) ($user->obra_actual_id ?? 0);
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        if (!$obraActual || !$obraActual->erp_unidad_negocio_id) {
            abort(404);
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

        $pdf = Pdf::loadView('pdf.oc_pendientes_parciales', [
            'obra' => $obraActual,
            'q'    => $q,
            'rows' => $data,
            'fecha_generacion' => now()->format('Y-m-d H:i'),
        ])->setPaper('letter', 'landscape');

        return $pdf->download('OC_pendientes_parciales.pdf');
    }

    /**
     * GRAFICAS (local)
     */
    public function graficas(Request $request)
    {
        $obraId = (int) (auth()->user()->obra_actual_id ?? 0);

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
}
