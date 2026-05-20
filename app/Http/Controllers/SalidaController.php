<?php

namespace App\Http\Controllers;

use App\Models\Familia;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SalidaController extends Controller
{
    /**
     * ✅ 0) Página principal del módulo Salidas (web)
     */
    public function index()
    {
        $user = auth()->user();
        $obraActualId = (int) ($user->obra_actual_id ?? 0);
        $obras = Obra::where('id', '!=', $obraActualId)->orderBy('nombre')->get(['id', 'nombre']);
        return view('salidas.index', compact('obras'));
    }

    /**
     * ✅ CONEXIÓN PARA "ALMACÉN"
     */
    private function almacenConn()
    {
        return DB::connection(config('database.default'));
    }

    /**
     * ✅ 1) Destinos desde ERP filtrados por unidad de negocio de la obra actual
     */
    public function destinos()
    {
        $user = auth()->user();

        if (!$user || !$user->obra_actual_id) {
            return response()->json([]);
        }

        $obra = Obra::find($user->obra_actual_id);
        if (!$obra || !$obra->erp_unidad_negocio_id) {
            return response()->json([]);
        }

        $unidadNegocioId = (int) $obra->erp_unidad_negocio_id;

        $destinos = DB::connection('erp')
            ->table('PROYECTOS as Proy')
            ->join('AcUnidadesNegocio as UN', 'Proy.IdUnidadNegocio', '=', 'UN.IdUnidadNegocio')
            ->join('AOTipoProyectos as TProy', 'Proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
            ->select('Proy.IdProyecto', 'Proy.Proyecto', 'TProy.Texto as Tipo')
            ->whereIn('TProy.Texto', ['Almacen', '100 Obra', 'Desarrollo'])
            ->where('Proy.Cerrado', 0)
            ->where('UN.IdUnidadNegocio', $unidadNegocioId)
            ->orderBy('TProy.Texto')
            ->orderBy('Proy.Proyecto')
            ->get();

        return response()->json($destinos);
    }

    /**
     * ✅ 1.1) Responsables para WEB — devuelve array de strings
     */
    public function responsables(Request $request)
    {
        try {
            $nombres = DB::connection('erp')
                ->table('ACResponsables as r')
                ->join('Proyectos as proy', 'r.IdProyecto', '=', 'proy.IdProyecto')
                ->join('AcUnidadesNegocio as un', 'proy.idUnidadNegocio', '=', 'un.IdUnidadNegocio')
                ->join('AOTipoProyectos as TProy', 'Proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
                ->whereIn('TProy.Texto', ['Almacen', '100 Obra', 'Desarrollo'])
                ->where('Proy.Cerrado', 0)
                ->whereNotNull('r.Nombre')
                ->where('r.Nombre', '!=', '')
                ->orderBy('r.Nombre')
                ->distinct()
                ->pluck('r.Nombre');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Responsables ERP falló: ' . $e->getMessage());
            $nombres = collect();
        }

        return response()->json($nombres->values());
    }

    /**
     * ✅ 1.2) Responsables para MÓVIL — devuelve objetos completos
     */
    public function responsablesMovil(Request $request)
    {
        try {
            $rows = DB::connection('erp')
                ->table('ACResponsables as r')
                ->join('Proyectos as proy', 'r.IdProyecto', '=', 'proy.IdProyecto')
                ->join('AcUnidadesNegocio as un', 'proy.idUnidadNegocio', '=', 'un.IdUnidadNegocio')
                ->join('AOTipoProyectos as TProy', 'Proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
                ->whereIn('TProy.Texto', ['Almacen', '100 Obra', 'Desarrollo'])
                ->where('Proy.Cerrado', 0)
                ->whereNotNull('r.Nombre')
                ->where('r.Nombre', '!=', '')
                ->select(
                    'un.IdUnidadNegocio',
                    'un.UnidadNegocio',
                    'un.Descripcion',
                    'proy.IdProyecto',
                    'proy.Proyecto',
                    'r.IdResponsable',
                    'r.Responsable',
                    'r.Nombre',
                    'r.Cargo',
                    'r.Telefono',
                    'r.Mail'
                )
                ->orderBy('r.Nombre')
                ->get();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Responsables ERP (móvil) falló: ' . $e->getMessage());
            $rows = collect();
        }

        return response()->json($rows);
    }

    /**
     * ✅ 2) Buscar productos por ID o descripción (solo de la obra actual)
     */
    public function buscarProductos(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $user = auth()->user();

        if (!$user || !$user->obra_actual_id || $q === '') {
            return response()->json([]);
        }

        $obraId = (int) $user->obra_actual_id;
        \Log::info('buscarProductos', ['user_id' => $user->id, 'obra_actual_id' => $user->obra_actual_id, 'obraId' => $obraId, 'q' => $q, 'mode' => $request->input('mode')]);
        $soloH  = $request->boolean('solo_h');

        if (str_starts_with($q, '#')) {
            $q = trim(substr($q, 1));
        }

        $mode   = $request->input('mode', 'both'); // 'desc' | 'code' | 'both'
        $srcErp = $request->input('src') === 'erp'; // entrada manual: buscar siempre en ERP

        // ── Búsqueda directa en ERP (entrada manual) ─────────────────────────
        if ($srcErp && in_array($mode, ['code', 'desc']) && $q !== '') {
            try {
                $erpQuery = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->join('AcFamilias as FI',    'I.idFamilia',    '=', 'FI.idFamilia')
                    ->join('AcCatUnidades as U',  'I.idUnidad',     '=', 'U.IdUnidad')
                    ->join('ACtiposInsumos as TI','I.idTipoInsumo', '=', 'TI.idTipoInsumo')
                    ->select(
                        'I.INSUMO as insumo_id',
                        'I.DescripcionLarga as descripcion',
                        'I.Costo as costo_promedio',
                        'U.Unidad as unidad',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia as subfamilia'
                    )
                    ->whereIn('TI.tipo', [1, 3]);

                if ($mode === 'code') {
                    $erpQuery->where('I.INSUMO', 'like', "%{$q}%");
                } else {
                    $erpQuery->where('I.DescripcionLarga', 'like', "%{$q}%");
                }

                $erpRows = $erpQuery->orderBy('I.INSUMO')->limit(15)->get();

                $erpRows->each(fn($r) => Familia::registrarSiNuevo(
                    trim((string) $r->familia),
                    trim((string) $r->subfamilia)
                ));

                return response()->json($erpRows->map(fn($r) => [
                    'id'             => null,
                    'insumo_id'      => (string) $r->insumo_id,
                    'descripcion'    => (string) $r->descripcion,
                    'unidad'         => (string) $r->unidad,
                    'cantidad'       => null,
                    'devolvible'     => 0,
                    'familia'        => (string) ($r->familia    ?? ''),
                    'subfamilia'     => (string) ($r->subfamilia ?? ''),
                    'proveedor'      => null,
                    'costo_promedio' => (float)  ($r->costo_promedio ?? 0),
                    'from_erp'       => true,
                ]));
            } catch (\Throwable) {
                return response()->json([]);
            }
        }

        $query = Inventario::query()
            ->where('obra_id', $obraId)
            ->when($soloH, fn ($qq) => $qq->where('devolvible', 1));

        if (ctype_digit($q) && $mode !== 'desc') {
            $query->where('id', (int) $q);
        } elseif ($mode === 'desc') {
            $query->where('descripcion', 'like', "%{$q}%");
        } elseif ($mode === 'code') {
            $query->where('insumo_id', 'like', "%{$q}%");
        } else {
            $query->where(function ($sub) use ($q) {
                $sub->where('descripcion', 'like', "%{$q}%")
                    ->orWhere('insumo_id',   'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('id', 'desc')
            ->limit(15)
            ->get([
                'id',
                'insumo_id',
                'descripcion',
                'unidad',
                'cantidad',
                'devolvible',
                'familia',
                'subfamilia',
                'proveedor',
                'costo_promedio',
            ]);

        // Fallback al ERP cuando no hay resultados locales (búsqueda por código o descripción)
        if ($items->isEmpty() && in_array($mode, ['code', 'desc']) && $q !== '') {
            try {
                $erpQuery = DB::connection('erp')
                    ->table('AcCatInsumos as I')
                    ->join('AcFamilias as FI', 'I.idFamilia', '=', 'FI.idFamilia')
                    ->join('AcCatUnidades as U', 'I.idUnidad', '=', 'U.IdUnidad')
                    ->join('ACtiposInsumos as TI', 'I.idTipoInsumo', '=', 'TI.idTipoInsumo')
                    ->select(
                        'I.INSUMO as insumo_id',
                        'I.DescripcionLarga as descripcion',
                        'I.Costo as costo_promedio',
                        'U.Unidad as unidad',
                        'FI.FamiliaPrincipal as familia',
                        'FI.Familia as subfamilia'
                    )
                    ->whereIn('TI.tipo', [1, 3]);

                if ($mode === 'code') {
                    $erpQuery->where('I.INSUMO', 'like', "%{$q}%");
                } else {
                    $erpQuery->where('I.DescripcionLarga', 'like', "%{$q}%");
                }

                $erpRows = $erpQuery->limit(10)->get();

                if ($erpRows->isNotEmpty()) {
                    $erpRows->each(fn($r) => Familia::registrarSiNuevo(
                        trim((string) $r->familia),
                        trim((string) $r->subfamilia)
                    ));

                    return response()->json($erpRows->map(fn($r) => [
                        'id'             => null,
                        'insumo_id'      => (string) $r->insumo_id,
                        'descripcion'    => (string) $r->descripcion,
                        'unidad'         => (string) $r->unidad,
                        'cantidad'       => null,
                        'devolvible'     => 0,
                        'familia'        => (string) ($r->familia ?? ''),
                        'subfamilia'     => (string) ($r->subfamilia ?? ''),
                        'proveedor'      => null,
                        'costo_promedio' => (float) ($r->costo_promedio ?? 0),
                        'from_erp'       => true,
                    ]));
                }
            } catch (\Throwable) {
                // Si el ERP falla, devolvemos vacío normalmente
            }
        }

        // Para resultados locales, enriquecer con Costo del ERP
        if ($items->isNotEmpty()) {
            $insumoIds = $items->pluck('insumo_id')->filter()->values()->toArray();
            $erpCostos = [];
            try {
                $erpCostos = DB::connection('erp')
                    ->table('AcCatInsumos')
                    ->whereIn('INSUMO', $insumoIds)
                    ->pluck('Costo', 'INSUMO')
                    ->map(fn($c) => (float) $c)
                    ->toArray();
            } catch (\Throwable) {}

            return response()->json($items->map(function ($item) use ($erpCostos) {
                $arr = $item->toArray();
                if (isset($erpCostos[$item->insumo_id])) {
                    $arr['costo_promedio'] = $erpCostos[$item->insumo_id];
                }
                return $arr;
            }));
        }

        return response()->json($items);
    }

    /**
     * ✅ 2.1) Catálogo completo para caché offline (móvil)
     */
    public function catalogoProductos(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->obra_actual_id) {
            return response()->json([]);
        }

        $soloH = $request->boolean('solo_h');

        $items = Inventario::where('obra_id', (int) $user->obra_actual_id)
            ->when($soloH, fn ($qq) => $qq->where('devolvible', 1))
            ->orderBy('descripcion')
            ->get(['id', 'insumo_id', 'descripcion', 'unidad', 'cantidad', 'devolvible']);

        return response()->json($items);
    }

    /**
     * ✅ 3) Guardar salida
     */
    public function store(Request $request)
    {
        $request->merge([
            'observaciones' => Str::limit((string) $request->input('observaciones', ''), 500, '')
        ]);

        $user = auth()->user();
        $obraId = $user?->obra_actual_id;

        if (!$user || !$obraId) {
            return response()->json([
                'ok' => false,
                'message' => 'No tienes obra actual asignada. Selecciona una obra antes de registrar la salida.'
            ], 422);
        }

        // ✅ Anti-duplicado por UUID (móvil offline)
        $uuid = $request->input('uuid');
        if ($uuid) {
            $existente = \App\Models\Movimiento::where('uuid', $uuid)->first();
            if ($existente) {
                return response()->json([
                    'ok'      => true,
                    'pdf_url' => route('salidas.pdf', $existente->id),
                    'duplicado' => true,
                ]);
            }
        }

        $request->validate([
            'nombre_cabo' => ['required', 'string', 'max:255'],
            'destino_proyecto_id' => ['nullable'],
            'observaciones' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.inventario_id' => ['required'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.unidad' => ['nullable', 'string', 'max:50'],
            'items.*.devolvible' => ['nullable'],
            'items.*.destinos' => ['nullable', 'array'],
            'items.*.destinos.*.nivel' => ['nullable', 'string', 'max:50'],
            'items.*.destinos.*.departamento' => ['nullable', 'string', 'max:100'],
            'items.*.destinos.*.cantidad' => ['required_with:items.*.destinos', 'numeric', 'gt:0'],

            // ✅ firma obligatoria
            'firma_base64' => ['required', 'string'],
        ]);

        return DB::transaction(function () use ($request, $obraId, $uuid) {

            /* =========================
               1) GUARDAR FIRMA (PNG)
               ========================= */
            $dataUrl = (string) $request->input('firma_base64');

            // ✅ Acepta png (y si te llega jpeg por alguna razón, también lo permitimos)
            if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Firma inválida (debe ser PNG o JPEG).'
                ], 422);
            }

            $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
            $binary = base64_decode($base64, true);

            if ($binary === false) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo procesar la firma.'
                ], 422);
            }

            if (strlen($binary) > 300 * 1024) {
                return response()->json([
                    'ok' => false,
                    'message' => 'La firma es muy grande. Limpia y firma de nuevo.'
                ], 422);
            }

            // ✅ extensión según mime del dataURL
            $ext = str_contains($dataUrl, 'image/jpeg') ? 'jpg' : 'png';

            $hash = hash('sha256', $binary);
            $filename = 'firma_recibe_' . date('Ymd_His') . '_' . substr($hash, 0, 12) . '.' . $ext;
            $path = 'firmas/' . $filename;

            Storage::disk('public')->put($path, $binary);

            // ✅ Verificación extra: si por permisos no se guardó, detén todo
            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No se pudo guardar la firma (permiso/almacenamiento).'
                ], 500);
            }

            /* =========================
               2) CREAR MOVIMIENTO
               ========================= */
            $mov = Movimiento::create([
                'uuid'              => $uuid ?: null,
                'obra_id'           => (int) $obraId,
                'user_id'           => auth()->id(),
                'fecha'             => now(),
                'destino'           => $request->destino_proyecto_id ?: 'SIN DESTINO',
                'nombre_cabo'       => $request->nombre_cabo,
                'estatus'           => 1,
                'observaciones'     => $request->observaciones,
                'firma_recibe_path' => $path,
            ]);

            /* =========================
               3) GUARDAR ITEMS + DESCONTAR INVENTARIO
               ========================= */
            foreach ($request->items as $it) {
                $inventarioId = (int) $it['inventario_id'];
                $cantidad = (float) $it['cantidad'];
                $devolvible = (int) ($it['devolvible'] ?? 0);
                $destinos = $it['destinos'] ?? [];

                // When no distribution provided, treat the full quantity as one entry with no nivel/depto
                if (empty($destinos)) {
                    $destinos = [['nivel' => '', 'departamento' => '', 'cantidad' => $cantidad]];
                }

                // Validate destinos sum equals item cantidad (only when distribution was explicitly provided)
                $sumaDestinos = array_sum(array_column($destinos, 'cantidad'));
                if (count($it['destinos'] ?? []) > 0 && abs($sumaDestinos - $cantidad) > 0.01) {
                    return response()->json([
                        'ok' => false,
                        'message' => "La distribución ({$sumaDestinos}) no coincide con la cantidad total ({$cantidad}) del producto #{$inventarioId}."
                    ], 422);
                }

                $primerNivel = (string) ($destinos[0]['nivel'] ?? '');
                $primerDepto = (string) ($destinos[0]['departamento'] ?? '');

                $inv = Inventario::where('obra_id', (int) $obraId)
                    ->where('id', $inventarioId)
                    ->lockForUpdate()
                    ->first();

                if (!$inv) {
                    return response()->json([
                        'ok' => false,
                        'message' => "No existe el producto #{$inventarioId} en esta obra."
                    ], 422);
                }

                if ((float) $inv->cantidad < $cantidad) {
                    return response()->json([
                        'ok' => false,
                        'message' => "No hay suficiente existencia para {$inv->insumo_id}. Solo hay {$inv->cantidad}."
                    ], 422);
                }

                // Obtener PU del ERP; si falla, usar costo_promedio local
                $erpPrecio = null;
                try {
                    $erpCosto = DB::connection('erp')
                        ->table('AcCatInsumos')
                        ->where('INSUMO', $inv->insumo_id)
                        ->value('Costo');
                    $erpPrecio = $erpCosto !== null ? (float) $erpCosto : null;
                } catch (\Throwable) {}

                $precioFinal = $erpPrecio ?? ($inv->costo_promedio > 0 ? (float) $inv->costo_promedio : null);

                $detalleId = DB::table('movimiento_detalles')->insertGetId([
                    'movimiento_id'   => $mov->id,
                    'inventario_id'   => $inv->id,
                    'insumo_id'       => $inv->insumo_id   ?? '',
                    'familia'         => $inv->familia      ?? '',
                    'subfamilia'      => $inv->subfamilia   ?? '',
                    'descripcion'     => $inv->descripcion,
                    'unidad'          => $inv->unidad       ?? '',
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precioFinal,
                    'devolvible'      => $devolvible,
                    'clasificacion'   => $primerNivel,
                    'clasificacion_d' => $primerDepto,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                foreach ($destinos as $dest) {
                    DB::table('movimiento_destinos')->insert([
                        'detalle_id'   => $detalleId,
                        'cantidad'     => (float) $dest['cantidad'],
                        'nivel'        => (string) ($dest['nivel'] ?? ''),
                        'departamento' => (string) ($dest['departamento'] ?? ''),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }

                $inv->cantidad = (float) $inv->cantidad - $cantidad;
                $inv->save();
            }

            /* =========================
               4) URL PDF
               ========================= */
            $pdfUrl = route('salidas.pdf', $mov->id);

            return response()->json([
                'ok' => true,
                'pdf_url' => $pdfUrl,
            ]);
        });
    }

    /**
     * ✅ 4-A) Actualizar destinos (nivel/departamento) de un detalle ya guardado
     */
    public function updateDetalleDestinos(Request $request, \App\Models\MovimientoDetalle $detalle)
    {
        $obraId = auth()->user()?->obra_actual_id;
        $mov = Movimiento::find($detalle->movimiento_id);

        if (!$mov || ($obraId && (int)$mov->obra_id !== (int)$obraId)) {
            abort(403);
        }

        $request->validate([
            'destinos'                    => ['required', 'array', 'min:1'],
            'destinos.*.nivel'            => ['nullable', 'string', 'max:50'],
            'destinos.*.departamento'     => ['nullable', 'string', 'max:100'],
            'destinos.*.cantidad'         => ['required', 'numeric', 'gt:0'],
        ]);

        $suma = array_sum(array_column($request->destinos, 'cantidad'));
        if (abs($suma - (float) $detalle->cantidad) > 0.01) {
            return response()->json([
                'ok'      => false,
                'message' => "La suma de distribuciones ({$suma}) no coincide con la cantidad del producto ({$detalle->cantidad}).",
            ], 422);
        }

        DB::transaction(function () use ($request, $detalle) {
            DB::table('movimiento_destinos')->where('detalle_id', $detalle->id)->delete();

            foreach ($request->destinos as $dest) {
                DB::table('movimiento_destinos')->insert([
                    'detalle_id'   => $detalle->id,
                    'cantidad'     => (float) $dest['cantidad'],
                    'nivel'        => (string) $dest['nivel'],
                    'departamento' => (string) ($dest['departamento'] ?? ''),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Keep backward-compat fields in sync with first destino
            $primero = $request->destinos[0];
            $detalle->clasificacion   = $primero['nivel'];
            $detalle->clasificacion_d = $primero['departamento'] ?? '';
            $detalle->save();
        });

        return response()->json(['ok' => true]);
    }

    /**
     * ✅ 4) PDF
     */
    public function pdf(Movimiento $movimiento)
    {
        $movimiento->load(['detalles.destinos', 'user']);

        // Encargado desde el registro en BD, no desde la sesión actual
        $encargado = $movimiento->user?->name ?? 'Encargado de almacén';

        // Nombre del destino desde ERP (destino guarda el IdProyecto)
        $destinoNombre = (string) $movimiento->destino;
        try {
            $proy = DB::connection('erp')
                ->table('PROYECTOS')
                ->where('IdProyecto', $movimiento->destino)
                ->value('Proyecto');
            if ($proy) {
                $destinoNombre = (string) $proy;
            }
        } catch (\Throwable $e) {
            // Si el ERP no responde, se muestra el ID como fallback
        }

        // DomPDF necesita ruta local absoluta para imágenes
        $firmaAbsPath = null;

        if (!empty($movimiento->firma_recibe_path)) {
            $firmaAbsPath = public_path('storage/' . ltrim($movimiento->firma_recibe_path, '/'));

            if (!file_exists($firmaAbsPath)) {
                $alt = storage_path('app/public/' . ltrim($movimiento->firma_recibe_path, '/'));
                if (file_exists($alt)) {
                    $firmaAbsPath = $alt;
                } else {
                    $firmaAbsPath = null;
                }
            }
        }

        $pdf = \PDF::loadView('pdf.salida', [
            'movimiento'     => $movimiento,
            'encargado'      => $encargado,
            'destinoNombre'  => $destinoNombre,
            'firma_abs_path' => $firmaAbsPath,
        ])->setPaper('letter', 'portrait');

        return $pdf->download('salida_' . $movimiento->id . '.pdf');
    }
}
