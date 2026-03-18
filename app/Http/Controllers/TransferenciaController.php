<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransferenciaController extends Controller
{
    /**
     * Formulario principal de transferencia.
     */
    public function index()
    {
        $user         = Auth::user();
        $obraOrigenId = (int) $user->obra_actual_id;
        $obraOrigen   = Obra::findOrFail($obraOrigenId);

        // Todas las obras excepto la actual, ordenadas por nombre
        $obras = Obra::where('id', '!=', $obraOrigenId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('transferencias.index', compact('obraOrigen', 'obras'));
    }

    /**
     * Búsqueda AJAX de insumos en la obra origen del usuario.
     * GET /transferencias/buscar?q=...
     */
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
                      ->orWhere('insumo_id', 'like', "%{$q}%");
                });
            })
            ->select(['id', 'insumo_id', 'descripcion', 'unidad', 'cantidad', 'familia'])
            ->orderBy('descripcion')
            ->limit(20)
            ->get();

        return response()->json($insumos);
    }

    /**
     * Stock del insumo en la obra destino.
     * GET /transferencias/stock-destino?inventario_id=&obra_destino_id=
     */
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
     * Ejecutar la transferencia en una transacción atómica.
     * POST /transferencias
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

        // ── Procesar firma ───────────────────────────────────────────────
        $dataUrl = (string) $validated['firma_base64'];

        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            return back()->withErrors(['firma_base64' => 'Firma inválida. Por favor vuelve a firmar.']);
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($binary === false || strlen($binary) > 300 * 1024) {
            return back()->withErrors(['firma_base64' => 'No se pudo procesar la firma. Limpia y firma de nuevo.']);
        }

        $ext      = str_contains($dataUrl, 'image/jpeg') ? 'jpg' : 'png';
        $filename = 'firma_transferencia_' . date('Ymd_His') . '_' . substr(hash('sha256', $binary), 0, 10) . '.' . $ext;
        $firmaPath = 'firmas/' . $filename;

        Storage::disk('public')->put($firmaPath, $binary);

        if (! Storage::disk('public')->exists($firmaPath)) {
            return back()->withErrors(['firma_base64' => 'No se pudo guardar la firma (error de almacenamiento).']);
        }

        // ── Transacción ──────────────────────────────────────────────────
        try {
            DB::transaction(function () use ($obraOrigenId, $obraDestinoId, $items, $validated, $user, $firmaPath) {

                // 1) Cabecera
                $transferenciaId = DB::table('transferencias_entre_obras')->insertGetId([
                    'obra_origen_id'  => $obraOrigenId,
                    'obra_destino_id' => $obraDestinoId,
                    'user_id'         => $user->id,
                    'fecha'           => now()->toDateString(),
                    'observaciones'   => $validated['observaciones'] ?? null,
                    'firma_path'      => $firmaPath,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                foreach ($items as $item) {
                    $inventarioOrigenId = (int) $item['inventario_id'];
                    $cantidad           = (float) $item['cantidad'];

                    // 2) Bloquear y leer origen
                    $origen = DB::table('inventarios')
                        ->where('id', $inventarioOrigenId)
                        ->where('obra_id', $obraOrigenId)
                        ->lockForUpdate()
                        ->first();

                    if (! $origen) {
                        throw new \Exception("Insumo no encontrado en la obra origen (id: {$inventarioOrigenId}).");
                    }

                    if ((float) $origen->cantidad < $cantidad) {
                        throw new \Exception("Stock insuficiente para «{$origen->descripcion}». Disponible: {$origen->cantidad}, solicitado: {$cantidad}.");
                    }

                    $origenStockAntes   = (float) $origen->cantidad;
                    $origenStockDespues = $origenStockAntes - $cantidad;

                    // 3) Descontar del origen
                    DB::table('inventarios')
                        ->where('id', $inventarioOrigenId)
                        ->update(['cantidad' => $origenStockDespues, 'updated_at' => now()]);

                    // 4) Buscar/crear en destino
                    $destino = null;
                    if ($origen->insumo_id) {
                        $destino = DB::table('inventarios')
                            ->where('obra_id', $obraDestinoId)
                            ->where('insumo_id', $origen->insumo_id)
                            ->lockForUpdate()
                            ->first();
                    }

                    $destinoStockAntes   = $destino ? (float) $destino->cantidad : 0.0;
                    $destinoStockDespues = $destinoStockAntes + $cantidad;

                    if ($destino) {
                        DB::table('inventarios')
                            ->where('id', $destino->id)
                            ->update(['cantidad' => $destinoStockDespues, 'updated_at' => now()]);
                    } else {
                        DB::table('inventarios')->insert([
                            'insumo_id'        => $origen->insumo_id,
                            'familia'          => $origen->familia,
                            'subfamilia'       => $origen->subfamilia,
                            'descripcion'      => $origen->descripcion,
                            'unidad'           => $origen->unidad,
                            'obra_id'          => $obraDestinoId,
                            'proveedor'        => $origen->proveedor,
                            'cantidad'         => $destinoStockDespues,
                            'cantidad_teorica' => 0,
                            'en_espera'        => 0,
                            'costo_promedio'   => $origen->costo_promedio,
                            'destino'          => $origen->destino ?? 'SIN DESTINO',
                            'devolvible'       => $origen->devolvible,
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
                        'origen_stock_antes'    => $origenStockAntes,
                        'origen_stock_despues'  => $origenStockDespues,
                        'destino_stock_antes'   => $destinoStockAntes,
                        'destino_stock_despues' => $destinoStockDespues,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }
            });
        } catch (\Exception $e) {
            // Si la transacción falla, eliminar la firma ya guardada
            Storage::disk('public')->delete($firmaPath);

            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('transferencias.index')
            ->with('success', 'Transferencia realizada correctamente.');
    }
}
