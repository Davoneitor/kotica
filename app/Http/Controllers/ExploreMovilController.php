<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\MovimientoDetalle;
use App\Models\Obra;
use App\Models\OcRecepcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExploreMovilController extends Controller
{
    // ── Salidas ───────────────────────────────────────────────────────────────

    public function movimientos(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $q      = trim((string) $request->get('q', ''));
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');

        $rows = Movimiento::query()
            ->leftJoin('obras as o', 'o.id', '=', 'movimientos.obra_id')
            ->when($obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($q !== '', fn($qq) =>
                $qq->where(fn($w) =>
                    $w->where('movimientos.nombre_cabo', 'like', "%{$q}%")
                      ->orWhere('movimientos.destino',   'like', "%{$q}%")
                      ->orWhere('o.nombre',              'like', "%{$q}%")
                )
            )
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->orderByDesc('movimientos.fecha')
            ->limit(80)
            ->get([
                'movimientos.id',
                'movimientos.obra_id',
                'movimientos.fecha',
                'movimientos.destino',
                'movimientos.nombre_cabo',
                'movimientos.estatus',
                DB::raw('o.nombre as obra'),
            ]);

        return response()->json($rows);
    }

    public function movimientoDetalles($id)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $movimiento = Movimiento::findOrFail($id);

        if ($obraId && (int) $movimiento->obra_id !== (int) $obraId) {
            return response()->json(['message' => 'Sin acceso a este movimiento.'], 403);
        }

        $obraNombre = $movimiento->obra_id
            ? Obra::where('id', $movimiento->obra_id)->value('nombre')
            : null;

        $detalles = MovimientoDetalle::where('movimiento_id', $movimiento->id)
            ->orderBy('id')
            ->get(['id','movimiento_id','inventario_id','familia','subfamilia',
                   'descripcion','unidad','cantidad','devolvible','clasificacion','clasificacion_d']);

        return response()->json([
            'movimiento' => [
                'id'          => (int) $movimiento->id,
                'obra_id'     => (int) $movimiento->obra_id,
                'obra'        => $obraNombre,
                'destino'     => $movimiento->destino,
                'fecha'       => (string) $movimiento->fecha,
                'nombre_cabo' => $movimiento->nombre_cabo,
                'estatus'     => $movimiento->estatus,
            ],
            'detalles' => $detalles,
        ]);
    }

    // ── Entradas ──────────────────────────────────────────────────────────────

    public function entradas(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $q      = trim((string) $request->get('q', ''));
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');

        $rows = OcRecepcion::query()
            ->where('obra_id', $obraId)
            ->when($q !== '', fn($qq) =>
                $qq->where(fn($w) =>
                    $w->where('insumo',      'like', "%{$q}%")
                      ->orWhere('descripcion','like', "%{$q}%")
                      ->orWhere('id_pedido',  'like', "%{$q}%")
                )
            )
            ->when($desde, fn($qq) => $qq->whereDate('fecha_recibido', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('fecha_recibido', '<=', $hasta))
            ->orderByDesc('fecha_recibido')
            ->limit(200)
            ->get();

        return response()->json($rows->map(fn($r) => [
            'id'             => (int) $r->id,
            'id_pedido'      => (string) $r->id_pedido,
            'pedido_det_id'  => (int) $r->pedido_det_id,
            'insumo'         => (string) $r->insumo,
            'descripcion'    => (string) $r->descripcion,
            'unidad'         => (string) $r->unidad,
            'cantidad_llego' => (float) $r->cantidad_llego,
            'fecha_oc'       => $r->fecha_oc ? (string) $r->fecha_oc : null,
            'fecha_recibido' => $r->fecha_recibido ? (string) $r->fecha_recibido : null,
            'tiene_foto'     => !empty($r->foto_path),
        ])->values());
    }

    public function entradaDetalles($id)
    {
        $obraId = (int) (Auth::user()?->obra_actual_id ?? 0);
        $r = OcRecepcion::where('obra_id', $obraId)->findOrFail($id);

        $path = ltrim((string) ($r->foto_path ?? ''), '/');
        $path = preg_replace('#^(public/|storage/)#', '', $path);
        $fotoUrl = ($path !== '' && Storage::disk('public')->exists($path))
            ? url('storage/' . $path)
            : null;

        return response()->json([
            'id'             => (int) $r->id,
            'id_pedido'      => (string) $r->id_pedido,
            'pedido_det_id'  => (int) $r->pedido_det_id,
            'insumo'         => (string) $r->insumo,
            'descripcion'    => (string) $r->descripcion,
            'unidad'         => (string) $r->unidad,
            'cantidad_llego' => (float) $r->cantidad_llego,
            'fecha_oc'       => $r->fecha_oc ? (string) $r->fecha_oc : null,
            'fecha_recibido' => $r->fecha_recibido ? (string) $r->fecha_recibido : null,
            'foto_url'       => $fotoUrl,
        ]);
    }

    // ── Inventario ────────────────────────────────────────────────────────────

    public function inventario(Request $request)
    {
        $obraId = (int) (Auth::user()?->obra_actual_id ?? 0);
        $q = trim((string) $request->get('q', ''));
        if (str_starts_with($q, '#')) $q = trim(substr($q, 1));

        $rows = Inventario::query()
            ->when($obraId, fn($qq) => $qq->where('obra_id', $obraId))
            ->when($q !== '', fn($qq) =>
                $qq->where(fn($w) =>
                    $w->where('insumo_id',   'like', "%{$q}%")
                      ->orWhere('descripcion','like', "%{$q}%")
                )
            )
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get(['id','insumo_id','familia','subfamilia','descripcion','unidad',
                   'cantidad','destino','proveedor','devolvible','updated_at']);

        return response()->json($rows);
    }

    // ── Transferencias ────────────────────────────────────────────────────────

    public function transferencias(Request $request)
    {
        $obraId = Auth::user()?->obra_actual_id;
        $q      = trim((string) $request->get('q', ''));
        $desde  = $request->get('desde');
        $hasta  = $request->get('hasta');

        $rows = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->join('users as u',  'u.id',  '=', 't.user_id')
            ->when($obraId, fn($q2) => $q2->where('t.obra_origen_id', $obraId))
            ->when($q !== '', fn($q2) =>
                $q2->where(fn($w) =>
                    $w->where('oo.nombre', 'like', "%{$q}%")
                      ->orWhere('od.nombre','like', "%{$q}%")
                      ->orWhere('u.name',   'like', "%{$q}%")
                )
            )
            ->when($desde, fn($q2) => $q2->whereDate('t.fecha', '>=', $desde))
            ->when($hasta, fn($q2) => $q2->whereDate('t.fecha', '<=', $hasta))
            ->orderByDesc('t.fecha')->orderByDesc('t.id')
            ->limit(80)
            ->select([
                't.id', 't.fecha', 't.observaciones',
                DB::raw('oo.nombre as obra_origen'),
                DB::raw('od.nombre as obra_destino'),
                DB::raw('u.name as usuario'),
                DB::raw('(SELECT COUNT(*) FROM transferencias_entre_obras_detalle WHERE transferencia_id = t.id) as total_insumos'),
                DB::raw('(SELECT ISNULL(SUM(cantidad),0) FROM transferencias_entre_obras_detalle WHERE transferencia_id = t.id) as total_piezas'),
            ])
            ->get();

        return response()->json($rows);
    }

    public function transferenciaDetalles($id)
    {
        $obraId = Auth::user()?->obra_actual_id;

        $t = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 'oo.id', '=', 't.obra_origen_id')
            ->join('obras as od', 'od.id', '=', 't.obra_destino_id')
            ->join('users as u',  'u.id',  '=', 't.user_id')
            ->where('t.id', $id)
            ->select(['t.id','t.fecha','t.observaciones',
                      DB::raw('oo.nombre as obra_origen'),
                      DB::raw('od.nombre as obra_destino'),
                      DB::raw('u.name as usuario'),
                      't.obra_origen_id','t.obra_destino_id'])
            ->first();

        if (!$t) return response()->json(['message' => 'No encontrado.'], 404);

        if ($obraId && (int) $t->obra_origen_id !== (int) $obraId) {
            return response()->json(['message' => 'Sin acceso.'], 403);
        }

        $detalles = DB::table('transferencias_entre_obras_detalle')
            ->where('transferencia_id', $id)
            ->orderBy('id')
            ->get();

        return response()->json(['transferencia' => $t, 'detalles' => $detalles]);
    }

    // ── Órdenes de Compra (ERP) ───────────────────────────────────────────────

    private function erpFindUpdatedExpr(string $alias, string $table): ?string
    {
        $candidates = ['FechaModificacion','FechaActualizacion','FechaUpdate',
                       'FechaCambio','UpdatedAt','updated_at','LastUpdate','LastUpdated'];
        foreach ($candidates as $col) {
            $exists = DB::connection('erp')->selectOne(
                "SELECT 1 AS ok FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME=?",
                [$table, $col]
            );
            if ($exists) {
                $type = DB::connection('erp')->selectOne(
                    "SELECT DATA_TYPE AS t FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=? AND COLUMN_NAME=?",
                    [$table, $col]
                );
                $t = strtolower((string)($type->t ?? ''));
                if (in_array($t, ['timestamp','rowversion','binary','varbinary'], true)) continue;
                return "{$alias}.{$col}";
            }
        }
        return null;
    }

    public function ordenesCompra(Request $request)
    {
        $user    = Auth::user();
        $obraId  = (int) ($user->obra_actual_id ?? 0);
        $obra    = $obraId ? Obra::find($obraId) : null;

        if (!$obra || !$obra->erp_unidad_negocio_id) {
            return response()->json([]);
        }

        $unidadId = (int) $obra->erp_unidad_negocio_id;
        $q        = trim((string) $request->get('q', ''));

        try {
            $ultimaExpr = $this->erpFindUpdatedExpr('PD','AcPedidosDet')
                ?? $this->erpFindUpdatedExpr('P','AcPedidos')
                ?? 'P.FechaPedido';

            $sql = "
                SELECT P.idPedido, P.Pedido, P.FechaPedido,
                       Prov.RazonSocial,
                       PD.idPedidoDet, PD.Cantidad,
                       ISNULL(PD.ParcialPralmacen,0) AS ParcialPralmacen,
                       {$ultimaExpr} AS FechaUltimaActualizacion,
                       PD.FechaUltimaEntrada,
                       I.INSUMO, I.DescripcionLarga,
                       U.Unidad
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
                INNER JOIN AcUnidadesNegocio UN ON Proy.idUnidadNegocio = UN.IdUnidadNegocio
                WHERE P.Cancelado=0 AND Proy.Cerrado=0 AND UN.IdUnidadNegocio=?
            ";
            $params = [$unidadId];

            if ($q !== '') {
                $sql .= " AND (I.INSUMO LIKE ? OR I.DescripcionLarga LIKE ? OR Prov.RazonSocial LIKE ?)";
                $like = "%{$q}%";
                array_push($params, $like, $like, $like);
            }
            $sql .= " ORDER BY P.FechaPedido DESC, P.idPedido DESC, PD.idPedidoDet DESC";

            $rows = collect(DB::connection('erp')->select($sql, $params));

            return response()->json($rows->map(function ($r) {
                $pedida   = (float) $r->Cantidad;
                $recibida = (float) ($r->ParcialPralmacen ?? 0);
                $faltante = max(0, $pedida - $recibida);
                $estado   = $recibida <= 0 ? 'pendiente' : ($recibida >= $pedida ? 'finalizada' : 'parcial');
                return [
                    'idPedido'           => (int) $r->idPedido,
                    'idPedidoDet'        => (int) $r->idPedidoDet,
                    'insumo'             => (string) $r->INSUMO,
                    'descripcion'        => (string) $r->DescripcionLarga,
                    'unidad'             => (string) $r->Unidad,
                    'razon'              => (string) $r->RazonSocial,
                    'fecha'              => (string) $r->FechaPedido,
                    'FechaUltimaEntrada' => $r->FechaUltimaEntrada ? (string)$r->FechaUltimaEntrada : null,
                    'pedida'             => $pedida,
                    'recibida'           => $recibida,
                    'faltante'           => $faltante,
                    'estado'             => $estado,
                ];
            })->values());

        } catch (\Throwable $e) {
            return response()->json(['error' => 'ERP no disponible: ' . $e->getMessage()], 503);
        }
    }

    // ── Gráficas ──────────────────────────────────────────────────────────────

    public function graficas(Request $request)
    {
        $user     = Auth::user();
        $obraId   = (int) ($user?->obra_actual_id ?? 0);
        $q        = trim((string) $request->get('q', ''));
        $desde    = $request->get('desde');
        $hasta    = $request->get('hasta');
        $soloObra = $request->get('solo_obra_actual') === '1';

        $familiasQ = MovimientoDetalle::query()
            ->selectRaw("COALESCE(movimiento_detalles.familia,'SIN FAMILIA') AS familia, SUM(movimiento_detalles.cantidad) AS total")
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->when($soloObra && $obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($q !== '', fn($qq) =>
                $qq->where(fn($w) =>
                    $w->where('movimiento_detalles.familia',      'like', "%{$q}%")
                      ->orWhere('movimiento_detalles.descripcion','like', "%{$q}%")
                )
            )
            ->groupBy(DB::raw("COALESCE(movimiento_detalles.familia,'SIN FAMILIA')"))
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $insumosQ = MovimientoDetalle::query()
            ->selectRaw("movimiento_detalles.inventario_id, MAX(movimiento_detalles.descripcion) AS descripcion, MAX(movimiento_detalles.unidad) AS unidad, SUM(movimiento_detalles.cantidad) AS total")
            ->join('movimientos', 'movimientos.id', '=', 'movimiento_detalles.movimiento_id')
            ->when($soloObra && $obraId, fn($qq) => $qq->where('movimientos.obra_id', $obraId))
            ->when($desde, fn($qq) => $qq->whereDate('movimientos.fecha', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('movimientos.fecha', '<=', $hasta))
            ->when($q !== '', fn($qq) =>
                $qq->where(fn($w) =>
                    $w->where('movimiento_detalles.descripcion','like', "%{$q}%")
                      ->orWhere('movimiento_detalles.inventario_id','like', "%{$q}%")
                )
            )
            ->groupBy('movimiento_detalles.inventario_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'familias' => $familiasQ->map(fn($r) => [
                'familia' => (string) $r->familia,
                'total'   => (float)  $r->total,
            ])->values(),
            'insumos' => $insumosQ->map(fn($r) => [
                'inventario_id' => (string) $r->inventario_id,
                'descripcion'   => (string) $r->descripcion,
                'unidad'        => (string) $r->unidad,
                'total'         => (float)  $r->total,
            ])->values(),
        ]);
    }
}
