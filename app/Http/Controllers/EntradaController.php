<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Obra;
use App\Models\OcRecepcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EntradaController extends Controller
{
    /**
     * GET /api/entradas/ordenes-compra
     * Lista de OC pendientes/parciales para la obra actual del usuario.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->obra_actual_id) {
            return response()->json([]);
        }

        $obra = Obra::select('id', 'nombre', 'erp_unidad_negocio_id')
            ->find((int) $user->obra_actual_id);

        if (!$obra || !(int) ($obra->erp_unidad_negocio_id ?? 0)) {
            return response()->json([]);
        }

        $erpUnidadNegocioId = (int) $obra->erp_unidad_negocio_id;

        $sql = "
            SELECT
                P.idPedido, P.Pedido, P.FechaPedido,
                Prov.IdProveedor, Prov.RazonSocial,
                PD.idPedidoDet,
                FI.FamiliaPrincipal AS Familia, FI.Familia AS SubFamilia,
                I.idInsumo, I.INSUMO, I.DescripcionLarga,
                U.Unidad,
                PD.Cantidad,
                PD.ParcialPralmacen,
                PD.CostoNeto AS PU
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
            WHERE P.Cancelado = 0
              AND Proy.Cerrado = 0
              AND UN.IdUnidadNegocio = ?
            ORDER BY P.idPedido DESC, I.INSUMO
        ";

        try {
            $rows = collect(DB::connection('erp')->select($sql, [$erpUnidadNegocioId]));
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo conectar al ERP: ' . $e->getMessage()], 500);
        }

        // Solo items no completados
        $rows = $rows->filter(fn($r) =>
            (float)($r->ParcialPralmacen ?? 0) < (float)($r->Cantidad ?? 0)
        );

        // Agrupar por pedido
        $ordenes = $rows->groupBy('idPedido')->map(function ($items, $idPedido) {
            $first = $items->first();
            return [
                'idPedido'  => (int) $idPedido,
                'pedido'    => (string) $first->Pedido,
                'fechaPedido' => substr((string) $first->FechaPedido, 0, 10),
                'proveedor' => [
                    'id'    => (int) $first->IdProveedor,
                    'razon' => (string) $first->RazonSocial,
                ],
                'items' => $items->map(function ($r) {
                    $pedida   = (float)($r->Cantidad ?? 0);
                    $recibida = (float)($r->ParcialPralmacen ?? 0);
                    return [
                        'idPedido'      => (int) $r->idPedido,
                        'pedido_det_id' => (int) $r->idPedidoDet,
                        'idInsumo'      => (string) $r->INSUMO,
                        'descripcion'   => (string) $r->DescripcionLarga,
                        'unidad'        => (string) $r->Unidad,
                        'familia'       => (string) ($r->Familia ?? ''),
                        'subfamilia'    => (string) ($r->SubFamilia ?? ''),
                        'razonSocial'   => (string) $r->RazonSocial,
                        'cantidad'      => $pedida,
                        'parcial_actual'=> $recibida,
                        'faltante'      => max(0, $pedida - $recibida),
                        'pu'            => (float)($r->PU ?? 0),
                        'fecha_oc'      => substr((string) $r->FechaPedido, 0, 10),
                        'es_parcial'    => ($recibida > 0 && $recibida < $pedida),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($ordenes);
    }

    /**
     * POST /api/entradas/recibir
     * Recibe un item de OC. Foto llega como base64 en JSON.
     */
    public function recibir(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->obra_actual_id) {
            return response()->json(['ok' => false, 'message' => 'Sin obra asignada.'], 422);
        }

        $obraId = (int) $user->obra_actual_id;

        $request->validate([
            'idPedido'       => ['required', 'integer', 'min:1'],
            'pedido_det_id'  => ['required', 'integer', 'min:1'],
            'idInsumo'       => ['required', 'string', 'max:50'],
            'descripcion'    => ['required', 'string'],
            'unidad'         => ['required', 'string'],
            'familia'        => ['nullable', 'string'],
            'subfamilia'     => ['nullable', 'string'],
            'cantidad_pedida'=> ['required', 'numeric', 'min:0.001'],
            'parcial_actual' => ['required', 'numeric', 'min:0'],
            'llego'          => ['required', 'numeric', 'min:0.001'],
            'razonSocial'    => ['nullable', 'string'],
            'pu'             => ['nullable', 'numeric', 'min:0'],
            'fecha_oc'       => ['required', 'date'],
            'foto_base64'    => ['required', 'string'],
        ]);

        $cantidadPedida = (float) $request->cantidad_pedida;
        $parcialActual  = (float) $request->parcial_actual;
        $llego          = (float) $request->llego;
        $faltante       = $cantidadPedida - $parcialActual;

        if ($llego > $faltante + 0.0001) {
            return response()->json([
                'ok' => false,
                'message' => "No puedes recibir más de lo faltante. Faltan: " . number_format($faltante, 4),
            ], 422);
        }

        // ── Guardar foto ──────────────────────────────────────────────────────
        $dataUrl = (string) $request->foto_base64;
        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl)) {
            return response()->json(['ok' => false, 'message' => 'Foto inválida.'], 422);
        }

        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($base64, true);

        if ($binary === false || strlen($binary) > 10 * 1024 * 1024) {
            return response()->json(['ok' => false, 'message' => 'Foto demasiado grande (máx 10 MB).'], 422);
        }

        $ext      = str_contains($dataUrl, 'image/png') ? 'png' : 'jpg';
        $filename = 'oc_' . $request->idPedido . '_' . $request->pedido_det_id . '_' . time() . '.' . $ext;
        $dir      = "oc_recepciones/obra_{$obraId}/pedido_{$request->idPedido}";
        $fotoPath = "{$dir}/{$filename}";

        Storage::disk('public')->makeDirectory($dir);
        Storage::disk('public')->put($fotoPath, $binary);

        if (!Storage::disk('public')->exists($fotoPath)) {
            return response()->json(['ok' => false, 'message' => 'No se pudo guardar la foto.'], 500);
        }

        $insumoId    = (string) $request->idInsumo;
        $pu          = (float) ($request->pu ?? 0);
        $familia     = (string) ($request->familia ?? 'SIN FAMILIA') ?: 'SIN FAMILIA';
        $subfamilia  = (string) ($request->subfamilia ?? 'SIN SUBFAMILIA') ?: 'SIN SUBFAMILIA';
        $parcialNuevo = $parcialActual + $llego;

        // ── Inventario local ──────────────────────────────────────────────────
        DB::transaction(function () use ($request, $insumoId, $llego, $pu, $obraId, $familia, $subfamilia) {
            $inv = Inventario::where('insumo_id', $insumoId)
                ->where('obra_id', $obraId)
                ->lockForUpdate()
                ->first();

            if (!$inv) {
                Inventario::create([
                    'insumo_id'        => $insumoId,
                    'descripcion'      => $request->descripcion,
                    'unidad'           => $request->unidad,
                    'proveedor'        => $request->razonSocial ?? null,
                    'cantidad'         => $llego,
                    'cantidad_teorica' => 0,
                    'en_espera'        => 0,
                    'costo_promedio'   => $pu,
                    'destino'          => 'SIN DESTINO',
                    'obra_id'          => $obraId,
                    'familia'          => $familia,
                    'subfamilia'       => $subfamilia,
                    'devolvible'       => 0,
                ]);
            } else {
                // Costo promedio ponderado
                $cantNueva = (float) $inv->cantidad + $llego;
                if ($cantNueva > 0 && $pu > 0) {
                    $inv->costo_promedio = round(
                        ((float) $inv->cantidad * (float) $inv->costo_promedio + $llego * $pu) / $cantNueva,
                        6
                    );
                }
                $inv->cantidad = $cantNueva;

                if (empty($inv->familia) || $inv->familia === 'SIN FAMILIA') {
                    $inv->familia = $familia;
                }
                if (empty($inv->subfamilia) || $inv->subfamilia === 'SIN SUBFAMILIA') {
                    $inv->subfamilia = $subfamilia;
                }
                $inv->save();
            }
        });

        // ── Bitácora ──────────────────────────────────────────────────────────
        OcRecepcion::create([
            'obra_id'         => $obraId,
            'user_id'         => auth()->id(),
            'id_pedido'       => (int) $request->idPedido,
            'pedido_det_id'   => (int) $request->pedido_det_id,
            'insumo'          => $insumoId,
            'descripcion'     => $request->descripcion,
            'unidad'          => $request->unidad,
            'familia'         => $familia,
            'subfamilia'      => $subfamilia,
            'fecha_oc'        => $request->fecha_oc,
            'fecha_recibido'  => now(),
            'cantidad_llego'  => $llego,
            'precio_unitario' => $pu > 0 ? $pu : null,
            'foto_path'       => $fotoPath,
        ]);

        // ── ERP: actualizar parcial ───────────────────────────────────────────
        $erpWarning = null;
        try {
            DB::connection('erp')->statement(
                "EXEC dbo.spActualizarParcialPralmacen @IdPedidoDet = ?, @ParcialPralmacen = ?",
                [(int) $request->pedido_det_id, $parcialNuevo]
            );
        } catch (\Throwable $e) {
            report($e);
            $erpWarning = 'Inventario actualizado, pero no se pudo actualizar el ERP.';
        }

        return response()->json([
            'ok'      => true,
            'warning' => $erpWarning,
        ]);
    }
}
