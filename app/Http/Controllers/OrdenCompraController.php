<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\OcRecepcion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Auth;

class OrdenCompraController extends Controller
{
   public function index(Request $request)
{
    // ✅ Usuario autenticado (sin auth()->user())
    $user = Auth::user();
    if (!$user) {
        abort(401);
    }

    // ✅ Obra actual del usuario (LOCAL)
    $obraLocalId = (int) ($user->obra_actual_id ?? 0);

    if ($obraLocalId <= 0) {
        return back()->withErrors(['obra_id' => 'El usuario no tiene una obra asignada.']);
    }

    // ✅ Traer el “id” que enlaza con ERP (UN.IdUnidadNegocio)
    $obra = Obra::select('id', 'nombre', 'erp_unidad_negocio_id')
        ->findOrFail($obraLocalId);

    if (!(int) ($obra->erp_unidad_negocio_id ?? 0)) {
        return back()->withErrors([
            'erp_unidad_negocio_id' => 'La obra actual no tiene erp_unidad_negocio_id configurado.'
        ]);
    }

    $erpUnidadNegocioId = (int) $obra->erp_unidad_negocio_id;

    // filtros UI
    $q = trim((string) $request->get('q', ''));
    $soloParcial = $request->boolean('solo_parcial');

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
            PD.ParcialPralmacen,
            CASE
                WHEN P.PorcentajeIVA > 0
                THEN (PD.Costo * P.PorcentajeIVA) / 100
                ELSE PD.Costo
            END AS PU
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
        ORDER BY P.idPedido, I.INSUMO
    ";

    $rows = collect(DB::connection('erp')->select($sql, [$erpUnidadNegocioId]));

    // ocultar detalles completos (ya recibidos al 100%)
    $rows = $rows->filter(function ($r) {
        return (float)($r->ParcialPralmacen ?? 0) < (float)($r->Cantidad ?? 0);
    });

    // filtro texto
    if ($q !== '') {
        $qLower = mb_strtolower($q);

        $rows = $rows->filter(function ($r) use ($qLower) {
            return str_contains(mb_strtolower((string)($r->INSUMO ?? '')), $qLower)
                || str_contains(mb_strtolower((string)($r->DescripcionLarga ?? '')), $qLower)
                || str_contains(mb_strtolower((string)($r->RazonSocial ?? '')), $qLower);
        });
    }

    // solo parciales
    if ($soloParcial) {
        $rows = $rows->filter(function ($r) {
            $rec = (float)($r->ParcialPralmacen ?? 0);
            $ped = (float)($r->Cantidad ?? 0);
            return $rec > 0 && $rec < $ped;
        });
    }

    // agrupar por pedido
    $ordenes = $rows->groupBy('idPedido')->map(function ($items, $idPedido) {
        $fecha = Carbon::parse($items->first()->FechaPedido);

        return [
            'idPedido' => (int) $idPedido,
            'fecha'    => $fecha,
            'proveedor' => [
                'id' => (int) $items->first()->IdProveedor,
                'razon' => (string) $items->first()->RazonSocial,
            ],
            'items' => $items->map(function ($r) use ($fecha) {
                $pedida   = round((float)($r->Cantidad ?? 0), 4);
                $recibida = round((float)($r->ParcialPralmacen ?? 0), 4);
                $faltante = round(max(0, $pedida - $recibida), 4);

                return [
                    'idPedido'      => (int) $r->idPedido,
                    'pedido_det_id' => (int) $r->idPedidoDet,

                    'id'     => (string) $r->INSUMO,
                    'insumo' => (string) $r->INSUMO,

                    'descripcion' => (string) $r->DescripcionLarga,
                    'unidad'      => (string) $r->Unidad,

                    'cantidad'       => $pedida,
                    'parcial_actual' => $recibida,
                    'faltante'       => $faltante,

                    'idProveedor'  => (int) $r->IdProveedor,
                    'razonSocial'  => (string) $r->RazonSocial,

                    'pu' => round((float)($r->PU ?? 0), 6),

                    'es_parcial' => ($recibida > 0 && $recibida < $pedida),
                    'vencida'    => $fecha->lt(now()->subDays(14)),
                ];
            })->values(),
        ];
    })->values();

    return view('ordenes_compra.listado', [
        'obra' => $obra,
        'ordenes' => $ordenes,
        'q' => $q,
        'soloParcial' => $soloParcial,
    ]);
}

   public function recibir(Request $request)
{
    $user = Auth::user();
if (!$user) abort(401);

$obraLocalId = (int) ($user->obra_actual_id ?? 0);
if ($obraLocalId <= 0) abort(403);
    if (!$obraLocalId) {
        return back()->withErrors(['obra_id' => 'El usuario no tiene una obra asignada.']);
    }

    // ✅ DEBUG (ponlo en false cuando termines de probar)
   $debug = false;

        if ($debug) {
            dd([
                'content_type'       => $request->header('content-type'),
                'hasFile_items0foto' => $request->hasFile('items.0.foto'),
                'items_files'        => $request->file('items'),
                'allFiles'           => $request->allFiles(),
                'all'                => $request->all(),
            ]);
        }
    // ✅ Validación (una sola vez)
    $data = $request->validate([
        'items' => ['required', 'array', 'size:1'],

        'items.0.idPedido'       => ['required', 'integer', 'min:1'],
        'items.0.pedido_det_id'  => ['required', 'integer', 'min:1'],

        'items.0.idInsumo'       => ['required', 'string', 'max:50'],
        'items.0.descripcion'    => ['required', 'string'],
        'items.0.unidad'         => ['required', 'string'],

        'items.0.cantidad_pedida'=> ['required', 'numeric', 'min:0.01'],
        'items.0.parcial_actual' => ['required', 'numeric', 'min:0'],

        'items.0.llego'          => ['required', 'numeric', 'min:0.01'],
        'items.0.razonSocial'    => ['nullable', 'string'],
        'items.0.pu'             => ['nullable', 'numeric', 'min:0'],

        'items.0.fecha_oc'       => ['required', 'date'],

        // ✅ Foto
        'items.0.foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:51200'],

    ]);

    $it = $data['items'][0];

    $pedidoDetId  = (int) $it['pedido_det_id'];
    $insumoId     = (string) $it['idInsumo'];

    $cantidadPedida = round((float) $it['cantidad_pedida'], 4);
    $parcialActual  = round((float) $it['parcial_actual'], 4);
    $llego          = round((float) $it['llego'], 4);

    $faltante = round($cantidadPedida - $parcialActual, 4);

    if ($llego > $faltante) {
        return back()->withErrors([
            'items.0.llego' => 'No puedes recibir más de lo faltante. Faltan: ' . number_format($faltante, 2),
        ]);
    }

    $parcialNuevo = $parcialActual + $llego;

   $userId = Auth::id();
    $idPedido = (int) $it['idPedido'];

    // ✅ Crear carpeta (opcional)
    Storage::disk('public')->makeDirectory("oc_recepciones/obra_{$obraLocalId}/pedido_{$idPedido}");

    // ✅ Obtener archivo correctamente (forma recomendada)
    $fotoFile = data_get($request->file('items'), '0.foto');


    if (!$fotoFile) {
        return back()->withErrors(['items.0.foto' => 'No llegó la foto al servidor.']);
    }

    // ✅ Guardar foto
    $fotoPath = $fotoFile->store(
        "oc_recepciones/obra_{$obraLocalId}/pedido_{$idPedido}",
        'public'
    );

    // ✅ Lookup ERP familia/subfamilia
    $familia = 'SIN FAMILIA';
    $subfamilia = 'SIN SUBFAMILIA';

    try {
        $erpRow = DB::connection('erp')
            ->table('AcCatInsumos as I')
            ->join('AcFamilias as FI', 'I.idFamilia', '=', 'FI.idFamilia')
            ->select('FI.FamiliaPrincipal as Familia', 'FI.Familia as SubFamilia')
            ->where('I.INSUMO', $insumoId)
            ->first();

        if ($erpRow) {
            $familia = $erpRow->Familia ?: 'SIN FAMILIA';
            $subfamilia = $erpRow->SubFamilia ?: 'SIN SUBFAMILIA';
        }
    } catch (\Throwable $e) {
        report($e);
        session()->flash('warning', '⚠️ No se pudo leer familia/subfamilia del ERP; se guardó como SIN FAMILIA.');
    }

    $pu     = (float) ($it['pu'] ?? 0);
    $accion = 'updated';

    // 1) Inventario local
    DB::transaction(function () use ($insumoId, $it, $llego, $pu, $obraLocalId, $familia, $subfamilia, &$accion) {

        $inv = Inventario::where('insumo_id', $insumoId)
            ->where('obra_id', $obraLocalId)
            ->lockForUpdate()
            ->first();

        if (!$inv) {
            // Caso 1: producto nuevo — costo_promedio = PU
            Inventario::create([
                'insumo_id'       => $insumoId,
                'descripcion'     => $it['descripcion'],
                'unidad'          => $it['unidad'],
                'proveedor'       => $it['razonSocial'] ?? null,
                'cantidad'        => $llego,
                'cantidad_teorica'=> 0,
                'en_espera'       => 0,
                'costo_promedio'  => $pu,
                'destino'         => 'SIN DESTINO',
                'obra_id'         => $obraLocalId,
                'familia'         => $familia,
                'subfamilia'      => $subfamilia,
                'devolvible'      => 0,
            ]);

            $accion = 'created';
        } else {
            // Caso 2: producto existente — promedio ponderado
            $cantActual  = (float) ($inv->cantidad ?? 0);
            $costoActual = (float) ($inv->costo_promedio ?? 0);
            $cantNueva   = $cantActual + $llego;

            if ($cantNueva > 0) {
                $inv->costo_promedio = round(
                    ($cantActual * $costoActual + $llego * $pu) / $cantNueva,
                    6
                );
            }

            $inv->cantidad = $cantNueva;

            if (($inv->familia === 'SIN FAMILIA' || empty($inv->familia)) && $familia !== 'SIN FAMILIA') {
                $inv->familia = $familia;
            }
            if (($inv->subfamilia === 'SIN SUBFAMILIA' || empty($inv->subfamilia)) && $subfamilia !== 'SIN SUBFAMILIA') {
                $inv->subfamilia = $subfamilia;
            }

            $inv->save();
            $accion = 'updated';
        }
    });

    // 2) Guardar bitácora recepción
    OcRecepcion::create([
        'obra_id'        => $obraLocalId,
        'user_id'        => $userId,
        'id_pedido'      => $idPedido,
        'pedido_det_id'  => $pedidoDetId,
        'insumo'         => $insumoId,
        'descripcion'    => $it['descripcion'],
        'unidad'         => $it['unidad'],
        'fecha_oc'       => $it['fecha_oc'],
        'fecha_recibido'  => now(),
        'cantidad_llego'  => (float) $it['llego'],
        'precio_unitario' => $pu > 0 ? $pu : null,
        'foto_path'       => $fotoPath,
    ]);

    // 3) ERP update
    try {
        DB::connection('erp')->transaction(function () use ($pedidoDetId, $parcialNuevo) {
            DB::connection('erp')->statement(
                "EXEC dbo.spActualizarParcialPralmacen @IdPedidoDet = ?, @ParcialPralmacen = ?",
                [$pedidoDetId, $parcialNuevo]
            );
        });
    } catch (\Throwable $e) {
        report($e);
        session()->flash('warning', '⚠️ Se aplicó en inventario local, pero no se pudo actualizar ERP (SP).');
    }

    return redirect()
        ->route('inventario.index', ['focus' => $insumoId])
        ->with('highlight_id', $insumoId)
        ->with('highlight_type', $accion)
        ->with('success', 'Recepción aplicada: ' . number_format($llego, 2));
}

}
