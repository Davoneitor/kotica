<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\Obra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $user = auth()->user();
        $obraActualId = (int) ($user->obra_actual_id ?? 0);
        $obraActual = $obraActualId ? Obra::find($obraActualId) : null;

        $q = trim((string) $request->get('q', ''));

        /* =====================================================
         * INVENTARIO
         * ===================================================== */
        $inventariosQ = Inventario::query()
            ->with('obra:id,nombre')
            ->when($obraActualId > 0, fn ($qq) => $qq->where('obra_id', $obraActualId))
            ->when($q !== '', function ($qq) use ($q) {
                $clean = str_starts_with($q, '#') ? trim(substr($q, 1)) : $q;

                $qq->where(function ($w) use ($clean) {
                    $w->where('insumo_id', 'like', "%{$clean}%")
                      ->orWhere('descripcion', 'like', "%{$clean}%")
                      ->orWhere('familia', 'like', "%{$clean}%")
                      ->orWhere('subfamilia', 'like', "%{$clean}%")
                      ->orWhere('proveedor', 'like', "%{$clean}%");
                });
            })
            ->orderByDesc('updated_at');

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
                Log::error('Destinos ERP falló: ' . $e->getMessage());
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
                    ->join('AcUnidadesNegocio as un', 'proy.idUnidadNegocio', '=', 'un.IdUnidadNegocio')
                    ->join('AOTipoProyectos as TProy', 'Proy.IdTipoProyecto', '=', 'TProy.IdTipoProyecto')
                    ->select(
                        'r.IdResponsable',
                        'r.Responsable',
                        'r.Nombre',
                        'r.Cargo',
                        'r.Telefono',
                        'r.Mail',
                        'proy.Proyecto',
                        'proy.idProyecto'
                    )
                    ->whereIn('TProy.Texto', ['Almacen', '100 Obra', 'Desarrollo'])
                    ->where('Proy.Cerrado', 0)
                    ->where('un.IdUnidadNegocio', $unidadNegocioId)
                    ->orderBy('proy.Proyecto')
                    ->orderBy('r.Nombre')
                    ->get();
            };

            // 1️⃣ intentar ALMACÉN
            try {
                $responsables = $queryResponsables($this->almacenConn());
            } catch (\Throwable $e) {
                Log::error('Responsables ALMACÉN falló: ' . $e->getMessage());
                $responsables = collect();
            }

            // 2️⃣ fallback ERP
            if (!$responsables || $responsables->count() === 0) {
                try {
                    $responsables = $queryResponsables(DB::connection('erp'));
                } catch (\Throwable $e) {
                    Log::error('Responsables ERP falló: ' . $e->getMessage());
                    $responsables = collect();
                }
            }
        }

        return view('inventario.index', compact(
            'obraActual',
            'inventarios',
            'destinos',
            'responsables'
        ));
    }

    /* =====================================================
     * CRUD BÁSICO
     * ===================================================== */

    public function create()
    {
        $obras = Obra::orderBy('nombre')->get(['id', 'nombre']);
        return view('inventario.create', compact('obras'));
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
                $correo = (string)(auth()->user()->email ?? 'sistemas@kotica.com.mx');

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

    public function edit(Inventario $inventario)
    {
        $obras = Obra::orderBy('nombre')->get(['id','nombre']);
        $familias = config('familias');

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
            'unidades'
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
            'cantidad'    => ['required','numeric'],
            'destino'     => ['nullable','string','max:255'],
            'devolvible'  => ['nullable'],
        ]);

        // ✅ FIX: destino nunca NULL
        $data['destino'] = trim((string)($data['destino'] ?? ''));
        if ($data['destino'] === '') {
            $data['destino'] = 'SIN DESTINO';
        }

        $inventario->update([
            ...$data,
            'devolvible' => (int) ((bool) ($data['devolvible'] ?? false)),
        ]);

        return redirect()
            ->route('inventario.index')
            ->with('success', 'Producto actualizado.')
            ->with('highlight_id', (string) $inventario->id)
            ->with('highlight_type', 'updated');
    }

    public function destroy(Inventario $inventario)
    {
        $id = $inventario->id;
        $inventario->delete();

        return redirect()
            ->route('inventario.index')
            ->with('success', "Producto eliminado (ID {$id}).");
    }
}
