<?php

namespace App\Http\Controllers;

use App\Models\EntradaManual;
use App\Models\Inventario;
use App\Models\OcRecepcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EntradaManualController extends Controller
{
    /**
     * POST /entradas-manuales
     * Registra una entrada de inventario sin orden de compra.
     * Actualiza inventarios y guarda bitácora en oc_recepciones (tipo='manual')
     * para que aparezca en el módulo Explore → Entradas.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->obra_actual_id) {
            return back()
                ->withErrors(['obra_id' => 'No tienes una obra asignada.'])
                ->withInput();
        }

        $obraId = (int) $user->obra_actual_id;

        $data = $request->validate([
            'insumo_id'      => ['nullable', 'string', 'max:50'],
            'descripcion'    => ['required', 'string', 'max:200'],
            'unidad'         => ['required', 'string', 'max:50'],
            'proveedor'      => ['nullable', 'string', 'max:150'],
            'cantidad'       => ['required', 'numeric', 'min:0.001'],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
            'fecha_entrada'  => ['required', 'date'],
            'observaciones'  => ['nullable', 'string', 'max:500'],
            'familia'        => ['nullable', 'string', 'max:80'],
            'subfamilia'     => ['nullable', 'string', 'max:80'],
        ]);

        $insumoId    = trim((string) ($data['insumo_id'] ?? '')) ?: null;
        $descripcion = (string) $data['descripcion'];
        $unidad      = (string) $data['unidad'];
        $cantidad    = (float)  $data['cantidad'];
        $pu          = (float)  $data['costo_unitario'];
        $familia     = trim((string) ($data['familia']    ?? '')) ?: 'SIN FAMILIA';
        $subfamilia  = trim((string) ($data['subfamilia'] ?? '')) ?: 'SIN SUBFAMILIA';

        DB::transaction(function () use (
            $insumoId, $descripcion, $unidad, $cantidad, $pu,
            $obraId, $familia, $subfamilia, $data, $user
        ) {
            // ── 1) Actualizar inventario ──────────────────────────────────
            $baseQuery = Inventario::where('obra_id', $obraId)->lockForUpdate();

            $inv = $insumoId
                ? $baseQuery->where('insumo_id', $insumoId)->first()
                : $baseQuery->whereRaw('LOWER(descripcion) = LOWER(?)', [$descripcion])->first();

            if (!$inv) {
                Inventario::create([
                    'insumo_id'        => $insumoId,
                    'descripcion'      => $descripcion,
                    'unidad'           => $unidad,
                    'proveedor'        => $data['proveedor'] ?? null,
                    'cantidad'         => $cantidad,
                    'cantidad_teorica' => $cantidad,
                    'en_espera'        => 0,
                    'costo_promedio'   => $pu,
                    'destino'          => 'SIN DESTINO',
                    'obra_id'          => $obraId,
                    'familia'          => $familia,
                    'subfamilia'       => $subfamilia,
                    'devolvible'       => 0,
                ]);
            } else {
                // Cantidades siempre se acumulan
                $inv->cantidad         = (float) ($inv->cantidad       ?? 0) + $cantidad;
                $inv->cantidad_teorica = (float) ($inv->cantidad_teorica ?? 0) + $cantidad;

                // Precio: la entrada manual reemplaza el costo_promedio cuando se proporciona
                if ($pu > 0) {
                    $inv->costo_promedio = $pu;
                }

                // Todos los campos se actualizan si vienen con datos
                if (!empty($data['proveedor'])) {
                    $inv->proveedor = $data['proveedor'];
                }
                if ($familia !== 'SIN FAMILIA') {
                    $inv->familia = $familia;
                }
                if ($subfamilia !== 'SIN SUBFAMILIA') {
                    $inv->subfamilia = $subfamilia;
                }

                $inv->save();
            }

            // ── 2) Bitácora en oc_recepciones (tipo='manual') ─────────────
            // Esto hace que aparezca en Explore → Entradas automáticamente.
            OcRecepcion::create([
                'obra_id'        => $obraId,
                'user_id'        => $user->id,
                'id_pedido'      => 0,
                'pedido_det_id'  => 0,
                'insumo'         => $insumoId ?? '',
                'descripcion'    => $descripcion,
                'unidad'         => $unidad,
                'fecha_oc'       => $data['fecha_entrada'],
                'fecha_recibido' => now(),
                'cantidad_llego' => $cantidad,
                'precio_unitario'=> $pu > 0 ? $pu : null,
                'foto_path'      => '',
                'tipo'           => 'manual',
                'observaciones'  => $data['observaciones'] ?? null,
                'familia'        => $familia,
                'subfamilia'     => $subfamilia,
            ]);

            // ── 3) Bitácora completa en entradas_manuales (incluye proveedor) ──
            EntradaManual::create([
                'obra_id'        => $obraId,
                'user_id'        => $user->id,
                'insumo_id'      => $insumoId,
                'descripcion'    => $descripcion,
                'unidad'         => $unidad,
                'proveedor'      => $data['proveedor'] ?? null,
                'cantidad'       => $cantidad,
                'costo_unitario' => $pu,
                'fecha_entrada'  => $data['fecha_entrada'],
                'observaciones'  => $data['observaciones'] ?? null,
                'familia'        => $familia,
                'subfamilia'     => $subfamilia,
            ]);
        });

        return redirect()
            ->route('ordenes-compra.index', ['tab' => 'manual'])
            ->with('success', 'Entrada manual registrada: '
                . number_format($cantidad, 2) . ' ' . $unidad
                . ' — ' . $descripcion);
    }
}
