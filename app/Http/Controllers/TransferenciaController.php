<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransferenciaController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // MÓDULO ORIGEN — Crear transferencia
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        $user         = Auth::user();
        $obraOrigenId = (int) $user->obra_actual_id;
        $obraOrigen   = Obra::findOrFail($obraOrigenId);

        $obras = Obra::where('id', '!=', $obraOrigenId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('transferencias.index', compact('obraOrigen', 'obras'));
    }

    public function buscar(Request $request)
    {
        $q      = trim((string) $request->get('q', ''));
        $obraId = (int) Auth::user()->obra_actual_id;

        $insumos = DB::table('inventarios')
            ->where('obra_id', $obraId)
            ->where('cantidad', '>', 0)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('descripcion', 'like', "%{$q}%")
                      ->orWhere('insumo_id', 'like', "%{$q}%")
                      ->orWhere('descripcionauxiliar', 'like', "%{$q}%");
                });
            })
            ->select(['id', 'insumo_id', 'descripcion', 'unidad', 'cantidad', 'familia'])
            ->orderBy('descripcion')
            ->limit(20)
            ->get();

        return response()->json($insumos);
    }

    public function stockDestino(Request $request)
    {
        $inventarioOrigenId = (int) $request->get('inventario_id');
        $obraDestinoId      = (int) $request->get('obra_destino_id');

        $origen = DB::table('inventarios')->where('id', $inventarioOrigenId)->first();

        if (! $origen || ! $origen->insumo_id) {
            return response()->json(['cantidad' => 0]);
        }

        $destino = DB::table('inventarios')
            ->where('obra_id', $obraDestinoId)
            ->where('insumo_id', $origen->insumo_id)
            ->first();

        return response()->json(['cantidad' => $destino ? (float) $destino->cantidad : 0.0]);
    }

    /**
     * POST /transferencias
     * Genera la salida en origen y deja la transferencia en estado "pendiente".
     * La obra destino debe confirmar la recepción por separado.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'obra_destino_id' => ['required', 'integer', 'exists:obras,id'],
            'observaciones'   => ['nullable', 'string', 'max:500'],
            'items'           => ['required', 'string'],
            'firma_base64'    => ['required', 'string'],
        ]);

        $user          = Auth::user();
        $obraOrigenId  = (int) $user->obra_actual_id;
        $obraDestinoId = (int) $validated['obra_destino_id'];

        if ($obraOrigenId === $obraDestinoId) {
            return back()->withErrors(['obra_destino_id' => 'La obra destino no puede ser igual a la obra origen.']);
        }

        $items = json_decode($validated['items'], true);

        if (empty($items) || ! is_array($items)) {
            return back()->withErrors(['items' => 'Agrega al menos un insumo.']);
        }

        foreach ($items as $item) {
            if ((float) ($item['cantidad'] ?? 0) <= 0) {
                return back()->withErrors(['items' => 'Todas las cantidades deben ser mayores a cero.']);
            }
        }

        // ── Firma ─────────────────────────────────────────────────────────
        $dataUrl = (string) $validated['firma_base64'];

        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            return back()->withErrors(['firma_base64' => 'Firma inválida.']);
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || strlen($binary) > 300 * 1024) {
            return back()->withErrors(['firma_base64' => 'No se pudo procesar la firma.']);
        }

        $ext       = str_contains($dataUrl, 'image/jpeg') ? 'jpg' : 'png';
        $filename  = 'firma_transferencia_' . date('Ymd_His') . '_' . substr(hash('sha256', $binary), 0, 10) . '.' . $ext;
        $firmaPath = 'firmas/' . $filename;

        Storage::disk('public')->put($firmaPath, $binary);

        if (! Storage::disk('public')->exists($firmaPath)) {
            return back()->withErrors(['firma_base64' => 'No se pudo guardar la firma.']);
        }

        // ── Transacción: solo descuenta origen, deja destino pendiente ────
        try {
            DB::transaction(function () use ($obraOrigenId, $obraDestinoId, $items, $validated, $user, $firmaPath) {

                $transferenciaId = DB::table('transferencias_entre_obras')->insertGetId([
                    'obra_origen_id'  => $obraOrigenId,
                    'obra_destino_id' => $obraDestinoId,
                    'user_id'         => $user->id,
                    'fecha'           => now()->toDateString(),
                    'observaciones'   => $validated['observaciones'] ?? null,
                    'firma_path'      => $firmaPath,
                    'estatus'         => 'pendiente',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                foreach ($items as $item) {
                    $inventarioOrigenId = (int) $item['inventario_id'];
                    $cantidad           = (float) $item['cantidad'];

                    $origen = DB::table('inventarios')
                        ->where('id', $inventarioOrigenId)
                        ->where('obra_id', $obraOrigenId)
                        ->lockForUpdate()
                        ->first();

                    if (! $origen) {
                        throw new \Exception("Insumo no encontrado en obra origen (id: {$inventarioOrigenId}).");
                    }

                    if ((float) $origen->cantidad < $cantidad) {
                        throw new \Exception("Stock insuficiente para «{$origen->descripcion}». Disponible: {$origen->cantidad}, solicitado: {$cantidad}.");
                    }

                    // Descontar de origen
                    DB::table('inventarios')
                        ->where('id', $inventarioOrigenId)
                        ->update([
                            'cantidad'   => (float) $origen->cantidad - $cantidad,
                            'updated_at' => now(),
                        ]);

                    DB::table('transferencias_entre_obras_detalle')->insert([
                        'transferencia_id'     => $transferenciaId,
                        'insumo_id'            => $origen->insumo_id,
                        'descripcion'          => $origen->descripcion,
                        'unidad'               => $origen->unidad,
                        'cantidad'             => $cantidad,
                        'cantidad_recibida'    => null,
                        'origen_stock_antes'   => (float) $origen->cantidad,
                        'origen_stock_despues' => (float) $origen->cantidad - $cantidad,
                        'destino_stock_antes'  => 0,
                        'destino_stock_despues'=> 0,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                }
            });
        } catch (\Exception $e) {
            Storage::disk('public')->delete($firmaPath);
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('transferencias.index')
            ->with('success', 'Transferencia enviada. La obra destino debe confirmar la recepción.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // MÓDULO DESTINO — Recepción de transferencias pendientes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /transferencias/pendientes
     * Lista de transferencias pendientes para la obra actual (como destino).
     */
    public function pendientes()
    {
        $obraId = (int) Auth::user()->obra_actual_id;

        $rows = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 't.obra_origen_id', '=', 'oo.id')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('t.obra_destino_id', $obraId)
            ->where('t.estatus', 'pendiente')
            ->select([
                't.id',
                't.fecha',
                't.observaciones',
                't.created_at',
                'oo.nombre as obra_origen',
                'u.name as usuario_envia',
            ])
            ->orderByDesc('t.created_at')
            ->get()
            ->map(function ($r) {
                $items = DB::table('transferencias_entre_obras_detalle')
                    ->where('transferencia_id', $r->id)
                    ->count();
                return [
                    'id'            => (int) $r->id,
                    'fecha'         => substr((string) $r->fecha, 0, 10),
                    'created_at'    => (string) $r->created_at,
                    'obra_origen'   => (string) $r->obra_origen,
                    'usuario_envia' => (string) $r->usuario_envia,
                    'observaciones' => (string) ($r->observaciones ?? ''),
                    'total_items'   => $items,
                ];
            });

        return response()->json($rows);
    }

    /**
     * GET /transferencias/{id}/detalles-pendientes
     * Detalle de una transferencia específica para el modal de recepción.
     */
    public function detallesPendientes($id)
    {
        $obraId = (int) Auth::user()->obra_actual_id;

        $transfer = DB::table('transferencias_entre_obras as t')
            ->join('obras as oo', 't.obra_origen_id', '=', 'oo.id')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('t.id', (int) $id)
            ->where('t.obra_destino_id', $obraId)
            ->where('t.estatus', 'pendiente')
            ->select(['t.id', 't.fecha', 't.observaciones', 'oo.nombre as obra_origen', 'u.name as usuario_envia'])
            ->first();

        if (! $transfer) {
            return response()->json(['error' => 'No encontrada o ya procesada.'], 404);
        }

        $detalles = DB::table('transferencias_entre_obras_detalle')
            ->where('transferencia_id', (int) $id)
            ->get()
            ->map(fn ($d) => [
                'id'              => (int) $d->id,
                'insumo_id'       => (string) ($d->insumo_id ?? ''),
                'descripcion'     => (string) $d->descripcion,
                'unidad'          => (string) ($d->unidad ?? ''),
                'cantidad'        => (float)  $d->cantidad,
                'cantidad_recibida' => (float) $d->cantidad, // default = enviado
            ]);

        return response()->json([
            'id'            => (int)    $transfer->id,
            'fecha'         => substr((string) $transfer->fecha, 0, 10),
            'obra_origen'   => (string) $transfer->obra_origen,
            'usuario_envia' => (string) $transfer->usuario_envia,
            'observaciones' => (string) ($transfer->observaciones ?? ''),
            'detalles'      => $detalles,
        ]);
    }

    /**
     * POST /transferencias/{id}/recibir
     * Confirma la recepción: acredita inventario destino.
     */
    public function recibir(Request $request, $id)
    {
        $user   = Auth::user();
        $obraId = (int) $user->obra_actual_id;

        $request->validate([
            'items'                      => ['required', 'array'],
            'items.*.detalle_id'         => ['required', 'integer'],
            'items.*.cantidad_recibida'  => ['required', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($id, $obraId, $request, $user) {

                $transfer = DB::table('transferencias_entre_obras')
                    ->where('id', (int) $id)
                    ->where('obra_destino_id', $obraId)
                    ->where('estatus', 'pendiente')
                    ->lockForUpdate()
                    ->first();

                if (! $transfer) {
                    throw new \Exception('Transferencia no encontrada o ya procesada.');
                }

                foreach ($request->items as $item) {
                    $detalleId    = (int)   $item['detalle_id'];
                    $cantRecibida = (float) $item['cantidad_recibida'];

                    $detalle = DB::table('transferencias_entre_obras_detalle')
                        ->where('id', $detalleId)
                        ->where('transferencia_id', (int) $id)
                        ->first();

                    if (! $detalle) continue;

                    // Actualizar cantidad_recibida en detalle
                    DB::table('transferencias_entre_obras_detalle')
                        ->where('id', $detalleId)
                        ->update(['cantidad_recibida' => $cantRecibida, 'updated_at' => now()]);

                    if ($cantRecibida <= 0) continue;

                    // Datos del inventario origen para crear/actualizar en destino
                    $origenInv = DB::table('inventarios')
                        ->where('insumo_id', $detalle->insumo_id)
                        ->where('obra_id', $transfer->obra_origen_id)
                        ->first();

                    $destInv = DB::table('inventarios')
                        ->where('obra_id', $obraId)
                        ->where('insumo_id', $detalle->insumo_id)
                        ->lockForUpdate()
                        ->first();

                    if ($destInv) {
                        DB::table('inventarios')
                            ->where('id', $destInv->id)
                            ->update([
                                'cantidad'         => (float) $destInv->cantidad + $cantRecibida,
                                'cantidad_teorica' => (float) ($destInv->cantidad_teorica ?? 0) + $cantRecibida,
                                'updated_at'       => now(),
                            ]);
                    } else {
                        DB::table('inventarios')->insert([
                            'insumo_id'        => $detalle->insumo_id,
                            'familia'          => $origenInv->familia          ?? 'SIN FAMILIA',
                            'subfamilia'       => $origenInv->subfamilia       ?? 'SIN SUBFAMILIA',
                            'descripcion'      => $detalle->descripcion,
                            'unidad'           => $detalle->unidad             ?? '',
                            'obra_id'          => $obraId,
                            'proveedor'        => $origenInv->proveedor        ?? null,
                            'cantidad'         => $cantRecibida,
                            'cantidad_teorica' => $cantRecibida,
                            'en_espera'        => 0,
                            'costo_promedio'   => $origenInv->costo_promedio   ?? 0,
                            'destino'          => 'SIN DESTINO',
                            'devolvible'       => 0,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }

                    // Bitácora en oc_recepciones para que aparezca en Explore → Entradas
                    DB::table('oc_recepciones')->insert([
                        'obra_id'         => $obraId,
                        'user_id'         => $user->id,
                        'id_pedido'       => 0,
                        'pedido_det_id'   => 0,
                        'insumo'          => (string) ($detalle->insumo_id ?? ''),
                        'descripcion'     => $detalle->descripcion,
                        'unidad'          => (string) ($detalle->unidad ?? ''),
                        'fecha_oc'        => $transfer->fecha,
                        'fecha_recibido'  => now(),
                        'cantidad_llego'  => $cantRecibida,
                        'precio_unitario' => null,
                        'foto_path'       => '',
                        'tipo'            => 'transferencia',
                        'observaciones'   => 'Transferencia #' . $id . ' desde ' . (DB::table('obras')->find($transfer->obra_origen_id)?->nombre ?? 'obra'),
                        'familia'         => $origenInv->familia    ?? 'SIN FAMILIA',
                        'subfamilia'      => $origenInv->subfamilia ?? 'SIN SUBFAMILIA',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }

                DB::table('transferencias_entre_obras')
                    ->where('id', (int) $id)
                    ->update([
                        'estatus'          => 'recibida',
                        'user_receptor_id' => $user->id,
                        'fecha_recepcion'  => now(),
                        'updated_at'       => now(),
                    ]);
            });
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Transferencia recibida correctamente.']);
    }

    /**
     * POST /transferencias/{id}/rechazar
     * Rechaza la transferencia y devuelve stock al origen.
     */
    public function rechazar(Request $request, $id)
    {
        $user   = Auth::user();
        $obraId = (int) $user->obra_actual_id;

        try {
            DB::transaction(function () use ($id, $obraId, $user) {

                $transfer = DB::table('transferencias_entre_obras')
                    ->where('id', (int) $id)
                    ->where('obra_destino_id', $obraId)
                    ->where('estatus', 'pendiente')
                    ->lockForUpdate()
                    ->first();

                if (! $transfer) {
                    throw new \Exception('Transferencia no encontrada o ya procesada.');
                }

                $detalles = DB::table('transferencias_entre_obras_detalle')
                    ->where('transferencia_id', (int) $id)
                    ->get();

                // Devolver cantidades al inventario origen
                foreach ($detalles as $d) {
                    DB::table('inventarios')
                        ->where('obra_id', $transfer->obra_origen_id)
                        ->where('insumo_id', $d->insumo_id)
                        ->increment('cantidad', (float) $d->cantidad, ['updated_at' => now()]);
                }

                DB::table('transferencias_entre_obras')
                    ->where('id', (int) $id)
                    ->update([
                        'estatus'          => 'rechazada',
                        'user_receptor_id' => $user->id,
                        'fecha_recepcion'  => now(),
                        'updated_at'       => now(),
                    ]);
            });
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Transferencia rechazada. Stock devuelto al origen.']);
    }
}
