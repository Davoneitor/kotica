<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventarioMovilController extends Controller
{
    /**
     * GET /api/inventario
     * Lista paginada del inventario de la obra actual.
     * ?q=        búsqueda en insumo_id, descripcion, descripcionauxiliar, familia, subfamilia, proveedor
     * ?obsoleto=1  solo obsoletos (por defecto activos)
     * ?page=     número de página
     * ?per_page= registros por página (default 30, max 100)
     */
    public function index(Request $request)
    {
        $user   = Auth::user();
        $obraId = (int) ($user?->obra_actual_id ?? 0);

        if (!$obraId) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $q        = trim((string) $request->get('q', ''));
        $obsoleto = $request->boolean('obsoleto');
        $perPage  = min((int) ($request->get('per_page', 30)), 100);

        $query = Inventario::query()
            ->where('obra_id', $obraId)
            ->where('obsoleto', $obsoleto ? 1 : 0)
            ->when($q !== '', function ($qq) use ($q) {
                $clean = str_starts_with($q, '#') ? trim(substr($q, 1)) : $q;
                $qq->where(function ($w) use ($clean) {
                    $w->where('insumo_id',          'like', "%{$clean}%")
                      ->orWhere('descripcion',       'like', "%{$clean}%")
                      ->orWhere('descripcionauxiliar','like', "%{$clean}%")
                      ->orWhere('familia',           'like', "%{$clean}%")
                      ->orWhere('subfamilia',        'like', "%{$clean}%")
                      ->orWhere('proveedor',         'like', "%{$clean}%");
                });
            })
            ->orderBy('insumo_id');

        $paginated = $query->paginate($perPage, [
            'id', 'insumo_id', 'descripcion', 'descripcionauxiliar',
            'familia', 'subfamilia', 'unidad', 'proveedor',
            'cantidad', 'costo_promedio', 'devolvible', 'obsoleto',
        ]);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ],
        ]);
    }

    /**
     * PUT /api/inventario/{id}
     * Solo administradores (is_admin = 1).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user?->is_admin) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos de administrador.'], 403);
        }

        $obraId = (int) ($user->obra_actual_id ?? 0);
        $insumo = Inventario::where('obra_id', $obraId)->findOrFail($id);

        $request->validate([
            'descripcion'         => ['sometimes', 'string', 'max:500'],
            'descripcionauxiliar' => ['sometimes', 'nullable', 'string', 'max:500'],
            'familia'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'subfamilia'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'unidad'              => ['sometimes', 'nullable', 'string', 'max:20'],
            'proveedor'           => ['sometimes', 'nullable', 'string', 'max:200'],
            'cantidad'            => ['sometimes', 'numeric', 'min:0'],
            'devolvible'          => ['sometimes', 'boolean'],
            'obsoleto'            => ['sometimes', 'boolean'],
        ]);

        $insumo->fill($request->only([
            'descripcion', 'descripcionauxiliar', 'familia',
            'subfamilia', 'unidad', 'proveedor', 'cantidad', 'devolvible', 'obsoleto',
        ]));
        $insumo->save();

        return response()->json(['ok' => true, 'insumo' => $insumo->only([
            'id', 'insumo_id', 'descripcion', 'descripcionauxiliar',
            'familia', 'subfamilia', 'unidad', 'proveedor',
            'cantidad', 'costo_promedio', 'devolvible', 'obsoleto',
        ])]);
    }

    /**
     * DELETE /api/inventario/{id}
     * Solo administradores. Elimina el registro permanentemente.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user?->is_admin) {
            return response()->json(['ok' => false, 'message' => 'Sin permisos de administrador.'], 403);
        }

        $obraId = (int) ($user->obra_actual_id ?? 0);
        $insumo = Inventario::where('obra_id', $obraId)->findOrFail($id);
        $insumo->delete();

        return response()->json(['ok' => true]);
    }
}
