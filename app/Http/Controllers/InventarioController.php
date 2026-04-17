<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class InventarioController extends Controller
{
    /**
     * ✅ CONEXIÓN ALMACÉN
     * Si tu conexión real NO es la default, cámbiala aquí.
     */
    private function almacenConn()
    {
        // return DB::connection('sqlsrv_almacen');
        return DB::connection(config('database.default'));
    }

    public function index(Request $request)
    {
        $user = Auth::user();
    if (!$user) {
        abort(401);
    }
        
        $obraActualId = (int) ($user->obra_actual_id ?? 0);
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        // Obras disponibles para el selector (solo multiobra)
        $isMultiobra = (int) ($user->is_multiobra ?? 0) === 1;
        $obras = $isMultiobra ? Obra::orderBy('nombre')->get(['id', 'nombre']) : collect();

        $q         = trim((string) $request->get('q', ''));
        $obsoleto  = $request->boolean('obsoleto');   // true = solo obsoletos

        /* =====================================================
         * INVENTARIO
         * ===================================================== */
        $inventariosQ = Inventario::query()
            ->with('obra:id,nombre')
            ->when($obraActualId > 0, fn ($qq) => $qq->where('obra_id', $obraActualId))
            ->when($obsoleto, fn ($qq) => $qq->where('obsoleto', 1))   // filtro solo cuando ?obsoleto=1
            ->when($q !== '', function ($qq) use ($q) {
                $clean = str_starts_with($q, '#') ? trim(substr($q, 1)) : $q;

                $qq->where(function ($w) use ($clean) {
                    $w->where('insumo_id', 'like', "%{$clean}%")
                      ->orWhere('descripcion', 'like', "%{$clean}%")
                      ->orWhere('descripcionauxiliar', 'like', "%{$clean}%")
                      ->orWhere('familia', 'like', "%{$clean}%")
                      ->orWhere('subfamilia', 'like', "%{$clean}%")
                      ->orWhere('proveedor', 'like', "%{$clean}%");
                });
            })
            ->orderBy('insumo_id');

        $inventarios = $inventariosQ->paginate(20)->withQueryString();

        /* =====================================================
         * DESTINOS (ERP)
         * ===================================================== */
        $destinos = [];

        if ($obraActual && $obraActual->erp_unidad_negocio_id) {
            try {
                $destinos = DB::connection('erp')
                    ->table('PROYECTOS as Proy')
                    ->join('AcUnidadesNegocio as UN', 'Proy.IdUnidadNegocio', '=', 'UN.IdUnidadNegocio')
                    ->join('AOTipoProyectos as TProy', 'Proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
                    ->select('Proy.IdProyecto', 'Proy.Proyecto', 'TProy.Texto as Tipo')
                    ->whereIn('TProy.Texto', ['Almacen', '100 Obra', 'Desarrollo'])
                    ->where('Proy.Cerrado', 0)
		    ->where('UN.IdUnidadNegocio', (int) $obraActual->erp_unidad_negocio_id)
                    ->orderBy('TProy.Texto')
                    ->orderBy('Proy.Proyecto')
                    ->get();
            } catch (\Throwable $e) {
    try {
        Log::error('Destinos ERP falló: ' . $e->getMessage());
    } catch (\Throwable $ignore) {
        // si logging no puede escribir, no tumbar la pantalla
    }
    $destinos = [];
}

        }

        /* =====================================================
         * RESPONSABLES (ALMACÉN → ERP fallback)
         * ===================================================== */
        $responsables = [];

        if ($obraActual && $obraActual->erp_unidad_negocio_id) {
            $unidadNegocioId = (int) $obraActual->erp_unidad_negocio_id;

            $queryResponsables = function ($conn) use ($unidadNegocioId) {
                return $conn
                    ->table('ACResponsables as r')
->join('Proyectos as proy', 'r.IdProyecto', '=', 'proy.IdProyecto')
->join('AcUnidadesNegocio as un', 'proy.IdUnidadNegocio', '=', 'un.IdUnidadNegocio')
->join('AOTipoProyectos as TProy', 'proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
->select(
    'r.IdResponsable',
    'r.Responsable',
    'r.Nombre',
    'r.Cargo',
    'r.Telefono',
    'r.Mail',
    'proy.Proyecto',
    'proy.IdProyecto'
)
->whereIn('TProy.Texto', ['Almacen', '100 Obra', 'Desarrollo'])
->where('proy.Cerrado', 0)
->orderBy('proy.Proyecto')
->orderBy('r.Nombre')
->get();

            };

            // 1️⃣ intentar ALMACÉN
            try {
                $responsables = $queryResponsables($this->almacenConn());
            } catch (\Throwable $e) {
                try {
                    Log::error('Responsables ALMACÉN falló: ' . $e->getMessage());
                } catch (\Throwable $ignore) {}
                $responsables = collect();
            }

            // 2️⃣ fallback ERP
            if (!$responsables || $responsables->count() === 0) {
                try {
                    $responsables = $queryResponsables(DB::connection('erp'));
                } catch (\Throwable $e) {
                    try {
                        Log::error('Responsables ERP falló: ' . $e->getMessage());
                    } catch (\Throwable $ignore) {}
                    $responsables = collect();
                }
            }
        }

        $puedeEditarAuxiliar = $user->is_admin || $user->puede_editar_desc_auxiliar;

        return view('inventario.index', compact(
            'obraActual',
            'inventarios',
            'destinos',
            'responsables',
            'isMultiobra',
            'obras',
            'obsoleto',
            'puedeEditarAuxiliar'
        ));
    }

    /* =====================================================
     * CRUD BÁSICO
     * ===================================================== */

    public function create()
    {
        $obras = Obra::orderBy('nombre')->get(['id', 'nombre']);
        $isMultiobra = (int) (Auth::user()->is_multiobra ?? 0) === 1;
        return view('inventario.create', compact('obras', 'isMultiobra'));
    }

    /**
     * ✅ Ejecuta el SP del ERP: sp_InsertarInsumo
     * Regresa: ['ok'=>bool,'return_value'=>int,'mensaje'=>string]
     */
    private function erpInsertarInsumo(array $p): array
    {
        $sql = "
            DECLARE @return_value int, @Mensaje nvarchar(200);

            EXEC @return_value = sp_InsertarInsumo
                @Insumo = ?,
                @Descripcion = ?,
                @Unidad = ?,
                @Familia = ?,
                @Subfamilia = ?,
                @CorreoUsuario = ?,
                @Mensaje = @Mensaje OUTPUT;

            SELECT
                @return_value AS return_value,
                @Mensaje AS mensaje;
        ";

        $rows = DB::connection('erp')->select($sql, [
            (string)($p['Insumo'] ?? ''),
            (string)($p['Descripcion'] ?? ''),
            (string)($p['Unidad'] ?? ''),
            (string)($p['Familia'] ?? ''),
            (string)($p['Subfamilia'] ?? ''),
            (string)($p['CorreoUsuario'] ?? ''),
        ]);

        $row = $rows[0] ?? null;

        $returnValue = (int)($row->return_value ?? -1);
        $mensaje = (string)($row->mensaje ?? 'Sin mensaje del ERP');

        return [
            'ok' => ($returnValue === 0),
            'return_value' => $returnValue,
            'mensaje' => $mensaje,
        ];
    }

    public function store(Request $request)
    {
        // ✅ viene del checkbox (si no viene, lo tomamos como 0)
        $guardarEnErp = $request->boolean('guardar_en_erp', false);

        // ✅ Reglas base
        $rules = [
            'obra_id'        => ['required','integer'],
            'insumo_id'      => ['nullable','string','max:100'],
            'familia'        => ['nullable','string','max:150'],
            'subfamilia'     => ['nullable','string','max:150'],
            'descripcion'    => ['required','string','max:255'],
            'unidad'         => ['nullable','string','max:50'],
            'proveedor'      => ['nullable','string','max:255'],
            'cantidad'       => ['required','numeric'],
            'costo_promedio' => ['nullable','numeric','min:0'],
            'destino'        => ['nullable','string','max:255'],
            'devolvible'     => ['nullable'],
            'guardar_en_erp' => ['nullable'], // checkbox
        ];

        // ✅ Si se va a guardar en ERP, exigimos los campos que el SP necesita
        if ($guardarEnErp) {
            $rules['insumo_id']  = ['required','string','max:100'];
            $rules['familia']    = ['required','string','max:150'];
            $rules['subfamilia'] = ['required','string','max:150'];
            $rules['unidad']     = ['required','string','max:50'];
        }

        Log::info('INVENTARIO STORE DEBUG', [
    'user_id' => Auth::id(),
    'email' => Auth::user()?->email,
    'obra_actual_id' => Auth::user()?->obra_actual_id,
    'obra_id_request' => $request->input('obra_id'),
    'guardar_en_erp' => $request->boolean('guardar_en_erp', false),
]);


        $data = $request->validate($rules);

        // ✅ FIX: tu BD NO permite destino NULL → ponemos default
        $data['destino'] = trim((string)($data['destino'] ?? ''));
        if ($data['destino'] === '') {
            $data['destino'] = 'SIN DESTINO';
        }

        // ✅ VALIDACIÓN: evitar duplicado (obra_id + insumo_id)
        $obraId = (int)($data['obra_id'] ?? 0);
        $insumoId = trim((string)($data['insumo_id'] ?? ''));

        // Solo validamos si viene insumo_id (en tu flujo casi siempre viene)
        if ($obraId > 0 && $insumoId !== '') {
            $existe = Inventario::query()
                ->where('obra_id', $obraId)
                ->where('insumo_id', $insumoId)
                ->first();

            if ($existe) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'insumo_id' => "Ese insumo ya existe en esta obra. Abre el registro y edítalo (ID {$existe->id}).",
                    ])
                    ->with('highlight_id', (string)$existe->id)
                    ->with('highlight_type', 'duplicate');
            }
        }

        DB::beginTransaction();
        try {
            // 1) Guardar local
            $inv = Inventario::create([
                ...$data,
                'devolvible' => (int) ((bool) ($data['devolvible'] ?? false)),
            ]);

            // 2) Guardar en ERP (si aplica)
            if ($guardarEnErp) {
               $correo = (string) (Auth::user()?->email ?? 'sistemas@kotica.com.mx');

                $resp = $this->erpInsertarInsumo([
                    'Insumo'        => $data['insumo_id'],
                    'Descripcion'   => $data['descripcion'],
                    'Unidad'        => $data['unidad'],
                    'Familia'       => $data['familia'],
                    'Subfamilia'    => $data['subfamilia'],
                    'CorreoUsuario' => 'sistemas@kotica.com.mx',
                ]);

                if (!$resp['ok']) {
                    DB::rollBack();

                    Log::error('SP sp_InsertarInsumo falló', [
                        'return_value' => $resp['return_value'],
                        'mensaje' => $resp['mensaje'],
                        'insumo_id' => $data['insumo_id'] ?? null,
                        'obra_id' => $data['obra_id'] ?? null,
                    ]);

                    return back()
                        ->withInput()
                        ->withErrors([
                            'guardar_en_erp' => 'No se pudo guardar en ERP: ' . $resp['mensaje'],
                        ]);
                }
            }

            DB::commit();

            $msg = $guardarEnErp
                ? 'Producto creado y enviado al ERP.'
                : 'Producto creado.';

            return redirect()
                ->route('inventario.index')
                ->with('success', $msg)
                ->with('highlight_id', (string) $inv->id)
                ->with('highlight_type', 'created');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Inventario store falló: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'Error al guardar: ' . $e->getMessage(),
                ]);
        }
    }

    public function edit(Inventario $inventario, Request $request)
    {
        if ($inventario->obsoleto) {
            return redirect()->route('inventario.index')
                ->with('error', "El insumo \"{$inventario->descripcion}\" es obsoleto y no puede editarse.");
        }

        $obras = Obra::orderBy('nombre')->get(['id','nombre']);
        $familias = config('familias');
        $isMultiobra = (int) (Auth::user()->is_multiobra ?? 0) === 1;
        $page = (int) $request->query('page', 1);

        $unidades = Inventario::query()
            ->select('unidad')
            ->whereNotNull('unidad')
            ->where('unidad', '!=', '')
            ->distinct()
            ->orderBy('unidad')
            ->pluck('unidad')
            ->toArray();

        return view('inventario.edit', compact(
            'inventario',
            'obras',
            'familias',
            'unidades',
            'isMultiobra',
            'page'
        ));
    }

    public function update(Request $request, Inventario $inventario)
    {
        $data = $request->validate([
            'obra_id'     => ['required','integer'],
            'insumo_id'   => ['nullable','string','max:100'],
            'familia'     => ['nullable','string','max:150'],
            'subfamilia'  => ['nullable','string','max:150'],
            'descripcion' => ['required','string','max:255'],
            'unidad'      => ['nullable','string','max:50'],
            'proveedor'   => ['nullable','string','max:255'],
            'cantidad'       => ['required','numeric'],
            'costo_promedio' => ['nullable','numeric','min:0'],
            'destino'        => ['nullable','string','max:255'],
            'devolvible'     => ['nullable'],
            'obsoleto'       => ['nullable'],
        ]);

        // Preservar destino existente si no viene en el form
        $destino = trim((string)($data['destino'] ?? ''));
        $data['destino'] = $destino !== '' ? $destino : ($inventario->destino ?: 'SIN DESTINO');

        $inventario->update([
            ...$data,
            'devolvible' => $inventario->devolvible, // preservar — no hay campo en el form
            'obsoleto'   => (int) ((bool) ($data['obsoleto'] ?? false)),
        ]);

        $page     = (int) $request->input('_page', 1);
        $obsoleto = (bool) ($data['obsoleto'] ?? false);

        $params = array_filter([
            'page'    => $page > 1 ? $page : null,
            'obsoleto' => $obsoleto ? 1 : null,
        ]);

        return redirect()
            ->route('inventario.index', $params)
            ->with('success', 'Producto actualizado.')
            ->with('highlight_id', (string) $inventario->id)
            ->with('highlight_type', 'updated');
    }

    public function destroy(Inventario $inventario)
    {
        if ($inventario->obsoleto) {
            return redirect()->route('inventario.index')
                ->with('error', "El insumo \"{$inventario->descripcion}\" es obsoleto y no puede eliminarse.");
        }

        $id          = $inventario->id;
        $descripcion = $inventario->descripcion;

        // Bloquear si el producto tiene movimientos (salidas) registrados
        $enMovimientos = \DB::table('movimiento_detalles')
            ->where('inventario_id', $id)
            ->exists();

        if ($enMovimientos) {
            return redirect()
                ->route('inventario.index')
                ->with('error', "No se puede eliminar '{$descripcion}' porque tiene salidas registradas en el historial.");
        }

        $inventario->delete();

        return redirect()
            ->route('inventario.index')
            ->with('success', "Producto '{$descripcion}' eliminado correctamente.");
    }

    /**
     * PATCH /inventario/{inventario}/desc-auxiliar
     * Actualización inline de descripcionauxiliar — solo para usuarios con permiso.
     */
    public function actualizarDescAuxiliar(Request $request, Inventario $inventario)
    {
        $user = Auth::user();

        if (! $user || (! $user->is_admin && ! $user->puede_editar_desc_auxiliar)) {
            return response()->json(['error' => 'Sin permiso para editar descripción auxiliar.'], 403);
        }

        $request->validate([
            'descripcionauxiliar' => ['nullable', 'string', 'max:450'],
        ]);

        $inventario->descripcionauxiliar = $request->input('descripcionauxiliar') ?? '';
        $inventario->save();

        return response()->json([
            'ok'                  => true,
            'descripcionauxiliar' => $inventario->descripcionauxiliar,
        ]);
    }

    public function historial(Inventario $inventario)
    {
        $eventos = collect();

        // 1) Creación
        $eventos->push([
            'tipo'     => 'creacion',
            'fecha'    => (string) $inventario->created_at,
            'icono'    => '🟢',
            'titulo'   => 'Insumo creado en inventario',
            'detalle'  => 'Registro dado de alta en el sistema',
            'cantidad' => 0,
            'usuario'  => null,
            'via'      => 'Sistema',
            'alertas'  => [],
        ]);

        // 2) Entradas (OC recibidas)
        $entradas = \App\Models\OcRecepcion::where('insumo', $inventario->insumo_id)
            ->where('obra_id', $inventario->obra_id)
            ->orderBy('fecha_recibido')
            ->get();

        // Agrupar por fecha_recibido truncada al segundo para detectar duplicados
        $dupGroups = $entradas->groupBy(fn($e) => substr((string)$e->fecha_recibido, 0, 19));

        // Timestamps de entradas para cruzar con updated_at después
        $sistemaTs = collect();

        foreach ($entradas as $e) {
            $pu      = $e->precio_unitario ? ' | PU: $' . number_format((float)$e->precio_unitario, 2) : '';
            $titulo  = ($e->id_pedido && (int)$e->id_pedido > 0)
                ? 'Entrada (OC ' . $e->id_pedido . ')'
                : 'Entrada directa (sin OC)';

            // Detectar alertas
            $alertas     = [];
            $fechaRec    = strtotime((string)$e->fecha_recibido);
            $fechaCreate = strtotime((string)$e->created_at);
            $diffDias    = abs($fechaRec - $fechaCreate) / 86400;

            $esDirectaBD = empty($e->user_id);
            $esBackdate  = $diffDias > 0.1; // más de ~2.4 horas de diferencia
            $esCeroQty   = (float)$e->cantidad_llego == 0;
            $esDuplic    = $dupGroups->get(substr((string)$e->fecha_recibido, 0, 19))->count() > 1;

            if ($esDirectaBD) $alertas[] = 'BD_DIRECTA';
            if ($esBackdate)  $alertas[] = 'BACKDATEADA';
            if ($esCeroQty)   $alertas[] = 'CANTIDAD_CERO';
            if ($esDuplic)    $alertas[] = 'DUPLICADA';

            $via = $esDirectaBD ? 'Inserción directa en BD' : 'Sistema';

            $sistemaTs->push($fechaCreate);

            $eventos->push([
                'tipo'         => 'entrada',
                'fecha'        => (string) $e->fecha_recibido,
                'fecha_real'   => (string) $e->created_at,
                'icono'        => '📦',
                'titulo'       => $titulo,
                'detalle'      => "Cantidad: {$e->cantidad_llego} {$e->unidad}{$pu}",
                'cantidad'     => (float) $e->cantidad_llego,
                'usuario'      => null,
                'via'          => $via,
                'alertas'      => $alertas,
            ]);
        }

        // 3) Salidas (movimientos)
        $salidas = DB::table('movimiento_detalles as md')
            ->join('movimientos as m', 'm.id', '=', 'md.movimiento_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('md.inventario_id', $inventario->id)
            ->orderBy('m.fecha')
            ->get([
                'md.cantidad', 'md.unidad', 'md.devolvible',
                'm.fecha', 'm.nombre_cabo', 'm.id as mov_id',
                'm.created_at as mov_created',
                'u.name as usuario',
            ]);

        foreach ($salidas as $s) {
            $ret = $s->devolvible ? ' (retornable)' : '';
            $sistemaTs->push(strtotime((string)$s->mov_created));
            $eventos->push([
                'tipo'     => 'salida',
                'fecha'    => (string) $s->fecha,
                'icono'    => '🔴',
                'titulo'   => "Salida #" . $s->mov_id . " — " . $s->nombre_cabo,
                'detalle'  => "Cantidad: {$s->cantidad} {$s->unidad}{$ret}",
                'cantidad' => (float) $s->cantidad,
                'usuario'  => $s->usuario,
                'via'      => 'Sistema',
                'alertas'  => [],
            ]);
        }

        // 4) Ajustes / devoluciones
        $ajustes = DB::table('ajustes_salida as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.inventario_id', $inventario->id)
            ->orderBy('a.created_at')
            ->get([
                'a.cantidad_devuelta', 'a.unidad', 'a.observaciones',
                'a.created_at', 'a.movimiento_id',
                'u.name as usuario',
            ]);

        foreach ($ajustes as $a) {
            $obs = $a->observaciones ? " — {$a->observaciones}" : '';
            $sistemaTs->push(strtotime((string)$a->created_at));
            $eventos->push([
                'tipo'     => 'ajuste',
                'fecha'    => (string) $a->created_at,
                'icono'    => '↩️',
                'titulo'   => "Devolución (salida #{$a->movimiento_id})",
                'detalle'  => "Devuelto: {$a->cantidad_devuelta} {$a->unidad}{$obs}",
                'cantidad' => (float) $a->cantidad_devuelta,
                'usuario'  => $a->usuario,
                'via'      => 'Sistema',
                'alertas'  => [],
            ]);
        }

        // 5) Detectar ediciones manuales via formulario
        // Si updated_at del inventario NO coincide con ningún evento del sistema (±10 seg)
        // y es posterior a created_at → alguien editó la ficha desde el formulario
        $updatedTs = strtotime((string)$inventario->updated_at);
        $createdTs = strtotime((string)$inventario->created_at);

        if ($updatedTs > $createdTs + 30) {
            $matchaSistema = $sistemaTs->contains(fn($t) => abs($t - $updatedTs) <= 10);
            if (!$matchaSistema) {
                $eventos->push([
                    'tipo'     => 'edicion',
                    'fecha'    => (string) $inventario->updated_at,
                    'icono'    => '✏️',
                    'titulo'   => 'Edición vía formulario del sistema',
                    'detalle'  => 'Cantidad u otros datos modificados — valor anterior no registrado',
                    'cantidad' => 0,
                    'usuario'  => null,
                    'via'      => 'Sistema (form edit)',
                    'alertas'  => ['EDICION_MANUAL'],
                ]);
            }
        }

        $eventos = $eventos->sortBy('fecha')->values();

        return response()->json([
            'insumo'      => $inventario->insumo_id,
            'descripcion' => $inventario->descripcion,
            'unidad'      => $inventario->unidad,
            'cantidad'    => $inventario->cantidad,
            'eventos'     => $eventos,
        ]);
    }

    public function cambiarObra(Request $request): \Illuminate\Http\RedirectResponse
{
    $request->validate([
        'obra_id' => ['required', 'integer', 'exists:obras,id'],
    ]);

    $user = Auth::user();
    $obraId = (int) $request->input('obra_id');

    // Actualizar obra actual en users
    $user->obra_actual_id = $obraId;
    $user->save();

    // Asegurar que la obra esté en obra_user para este usuario
    $user->obras()->syncWithoutDetaching([$obraId]);

    return redirect()->route('inventario.index');
}

public function buscarPorInsumo(Request $request)
{
    $codigo = trim((string) $request->query('codigo', ''));

    if ($codigo === '') {
        return response()->json([
            'ok' => false,
            'found' => false,
            'msg' => 'Código vacío'
        ], 422);
    }

    $sql = "
        SELECT  FI.idFamilia, FI.FamiliaPrincipal AS Familia, FI.Familia AS SubFamilia, 
I.idInsumo, I.INSUMO, I.DescripcionLarga,
'INVENTARIO INICIAL' AS PROVEDOR,
U.IdUnidad, U.Unidad, TI.tipo
FROM AcCatInsumos I 
INNER JOIN AcFamilias FI ON I.idFamilia = FI.idFamilia
INNER JOIN AcCatUnidades U ON I.idUnidad = U.IdUnidad
INNER JOIN ACtiposInsumos TI on I.idTipoInsumo=TI.idTipoInsumo
        WHERE TI.tipo IN (1,3)
          AND I.INSUMO = ?
        ORDER BY I.idInsumo DESC
    ";

    try {
        $row = collect(DB::connection('erp')->select($sql, [$codigo]))->first();
    } catch (\Throwable $e) {
        Log::error('Autocomplete ERP falló', [
            'codigo' => $codigo,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'found' => false,
            'msg' => 'Error consultando ERP'
        ], 500);
    }

    if (!$row) {
        return response()->json(['ok' => true, 'found' => false]);
    }

    $descripcion = (string) ($row->DescripcionLarga ?: $row->INSUMO);

    return response()->json([
        'ok' => true,
        'found' => true,
        'data' => [
            'insumo_id'   => (string) $row->INSUMO,
            'descripcion' => $descripcion,
            'unidad'      => (string) ($row->Unidad ?? 'PZA'),
            'proveedor'   => 'INVENTARIO INICIAL',
            'familia'     => (string) ($row->Familia ?? ''),
            'subfamilia'  => (string) ($row->SubFamilia ?? ''),
            'tipo'        => (int) ($row->tipo ?? 0),
        ],
    ]);
}





}
