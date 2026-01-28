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

    public function store(Request $request)
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

        $inv = Inventario::create([
            ...$data,
            'devolvible' => (int) ((bool) ($data['devolvible'] ?? false)),
        ]);

        return redirect()
            ->route('inventario.index')
            ->with('success', 'Producto creado.')
            ->with('highlight_id', (string) $inv->id)
            ->with('highlight_type', 'created');
    }

 public function edit(Inventario $inventario)
{
    $obras = Obra::orderBy('nombre')->get(['id','nombre']);

    // Familias (ya lo tienes)
    $familias = config('familias');

    // ✅ UNIDADES DINÁMICAS DESDE BD
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
