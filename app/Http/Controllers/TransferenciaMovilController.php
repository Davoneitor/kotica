<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransferenciaMovilController extends Controller
{
    /**
     * GET /api/transferencias/obras
     * Obras destino disponibles (todas excepto la actual).
     */
    public function obras()
    {
        $user = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        $obras = Obra::where('id', '!=', $obraId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json($obras);
    }

    /**
     * GET /api/transferencias/buscar?q=
     * Buscar insumos en la obra origen (con stock > 0).
     */
    public function buscar(Request $request)
    {
        $q      = trim((string) $request->get('q', ''));
        $obraId = (int) (Auth::user()?->obra_actual_id ?? 0);

        if (!$obraId || strlen($q) < 2) return response()->json([]);

        $query = DB::table('inventarios')
            ->where('obra_id', $obraId)
            ->where('cantidad', '>', 0);

        if (ctype_digit($q)) {
            $query->where('id', (int) $q);
        } else {
            $query->where(function ($w) use ($q) {
                $w->where('descripcion', 'like', "%{$q}%")
                  ->orWhere('insumo_id', 'like', "%{$q}%");
            });
        }

        $items = $query->select(['id', 'insumo_id', 'descripcion', 'unidad', 'cantidad', 'devolvible'])
            ->orderBy('descripcion')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    /**
     * POST /api/transferencias
     * Ejecutar transferencia con firma base64.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $obraOrigenId = (int) ($user?->obra_actual_id ?? 0);

        if (!$obraOrigenId) {
            return response()->json(['ok' => false, 'message' => 'Sin obra asignada.'], 422);
        }

        $request->validate([
            'obra_destino_id' => ['required', 'integer', 'exists:obras,id'],
            'observaciones'   => ['nullable', 'string', 'max:500'],
            'firma_base64'    => ['required', 'string'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.inventario_id' => ['required', 'integer'],
            'items.*.cantidad'      => ['required', 'numeric', 'gt:0'],
        ]);

        $obraDestinoId = (int) $request->obra_destino_id;

        if ($obraOrigenId === $obraDestinoId) {
            return response()->json(['ok' => false, 'message' => 'La obra destino no puede ser igual a la obra origen.'], 422);
        }

        // ── Guardar firma ──────────────────────────────────────────────────
        $dataUrl = (string) $request->firma_base64;

        if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            return response()->json(['ok' => false, 'message' => 'Firma inválida.'], 422);
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || strlen($binary) > 300 * 1024) {
            return response()->json(['ok' => false, 'message' => 'No se pudo procesar la firma. Limpia y firma de nuevo.'], 422);
        }

        $ext       = str_contains($dataUrl, 'image/jpeg') ? 'jpg' : 'png';
        $filename  = 'firma_transferencia_' . date('Ymd_His') . '_' . substr(hash('sha256', $binary), 0, 10) . '.' . $ext;
        $firmaPath = 'firmas/' . $filename;

        Storage::disk('public')->put($firmaPath, $binary);

        if (!Storage::disk('public')->exists($firmaPath)) {
            return response()->json(['ok' => false, 'message' => 'No se pudo guardar la firma.'], 500);
        }

        // ── Transacción ────────────────────────────────────────────────────
        try {
            DB::transaction(function () use ($obraOrigenId, $obraDestinoId, $request, $user, $firmaPath) {

                // 1) Cabecera
                $transferenciaId = DB::table('transferencias_entre_obras')->insertGetId([
                    'obra_origen_id'  => $obraOrigenId,
                    'obra_destino_id' => $obraDestinoId,
                    'user_id'         => $user->id,
                    'fecha'           => now()->toDateString(),
                    'observaciones'   => $request->observaciones ?? null,
                    'firma_path'      => $firmaPath,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                foreach ($request->items as $item) {
                    $inventarioId = (int) $item['inventario_id'];
                    $cantidad     = (float) $item['cantidad'];

                    // 2) Bloquear origen
                    $origen = DB::table('inventarios')
                        ->where('id', $inventarioId)
                        ->where('obra_id', $obraOrigenId)
                        ->lockForUpdate()
                        ->first();

                    if (!$origen) {
                        throw new \Exception("Insumo #{$inventarioId} no encontrado en la obra origen.");
                    }

                    if ((float) $origen->cantidad < $cantidad) {
                        throw new \Exception("Stock insuficiente para «{$origen->descripcion}». Disponible: {$origen->cantidad}, solicitado: {$cantidad}.");
                    }

                    $origenAntes   = (float) $origen->cantidad;
                    $origenDespues = $origenAntes - $cantidad;

                    // 3) Descontar origen
                    DB::table('inventarios')
                        ->where('id', $inventarioId)
                        ->update(['cantidad' => $origenDespues, 'updated_at' => now()]);

                    // 4) Sumar en destino (buscar o crear)
                    $destino = $origen->insumo_id
                        ? DB::table('inventarios')
                            ->where('obra_id', $obraDestinoId)
                            ->where('insumo_id', $origen->insumo_id)
                            ->lockForUpdate()
                            ->first()
                        : null;

                    $destinoAntes   = $destino ? (float) $destino->cantidad : 0.0;
                    $destinoDespues = $destinoAntes + $cantidad;

                    if ($destino) {
                        DB::table('inventarios')
                            ->where('id', $destino->id)
                            ->update(['cantidad' => $destinoDespues, 'updated_at' => now()]);
                    } else {
                        DB::table('inventarios')->insert([
                            'insumo_id'        => $origen->insumo_id,
                            'familia'          => $origen->familia,
                            'subfamilia'       => $origen->subfamilia,
                            'descripcion'      => $origen->descripcion,
                            'unidad'           => $origen->unidad,
                            'obra_id'          => $obraDestinoId,
                            'proveedor'        => $origen->proveedor,
                            'cantidad'         => $destinoDespues,
                            'cantidad_teorica' => 0,
                            'en_espera'        => 0,
                            'costo_promedio'   => $origen->costo_promedio ?? 0,
                            'destino'          => $origen->destino ?? 'SIN DESTINO',
                            'devolvible'       => $origen->devolvible ?? 0,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }

                    // 5) Detalle
                    DB::table('transferencias_entre_obras_detalle')->insert([
                        'transferencia_id'      => $transferenciaId,
                        'insumo_id'             => $origen->insumo_id,
                        'descripcion'           => $origen->descripcion,
                        'unidad'                => $origen->unidad,
                        'cantidad'              => $cantidad,
                        'origen_stock_antes'    => $origenAntes,
                        'origen_stock_despues'  => $origenDespues,
                        'destino_stock_antes'   => $destinoAntes,
                        'destino_stock_despues' => $destinoDespues,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }
            });
        } catch (\Exception $e) {
            Storage::disk('public')->delete($firmaPath);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
