<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    // ── Guard ────────────────────────────────────────────────────────────────────

    private function checkPermiso(): ?User
    {
        $me = Auth::user();
        if (!$me || !$me->gestion_usuarios) {
            return null;
        }
        return $me;
    }

    private function userPayload(User $u): array
    {
        return [
            'id'                       => $u->id,
            'name'                     => $u->name,
            'email'                    => $u->email,
            'rol'                      => $u->rol,
            'estatus'                  => (int) $u->estatus,
            'is_admin'                 => (bool) $u->is_admin,
            'is_multiobra'             => (int) $u->is_multiobra,
            'solo_explore'             => (bool) $u->solo_explore,
            'gestion_usuarios'         => (bool) $u->gestion_usuarios,
            'explore'                  => (bool) $u->explore,
            'puede_editar_desc_auxiliar' => (bool) $u->puede_editar_desc_auxiliar,
            'obra_actual_id'           => $u->obra_actual_id,
            'obra_actual_nombre'       => $u->obraActual?->nombre,
            'obras'                    => $u->obras()->select('obras.id', 'obras.nombre')->get(),
        ];
    }

    // ── GET /api/mobile/usuarios ──────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$this->checkPermiso()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $q       = trim((string) $request->get('q', ''));
        $rol     = $request->get('rol', '');
        $estatus = $request->get('estatus', '');   // '' | '0' | '1'
        $obraId  = $request->get('obra_id', '');

        $query = User::query()
            ->with(['obras:obras.id,obras.nombre', 'obraActual:id,nombre'])
            ->when($q !== '', fn($qq) =>
                $qq->where(fn($w) =>
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                )
            )
            ->when($rol !== '', fn($qq) => $qq->where('rol', $rol))
            ->when($estatus !== '', fn($qq) => $qq->where('estatus', (int) $estatus))
            ->when($obraId !== '', fn($qq) =>
                $qq->whereHas('obras', fn($w) => $w->where('obras.id', (int) $obraId))
            )
            ->orderBy('name');

        $usuarios = $query->get()->map(fn($u) => $this->userPayload($u));

        return response()->json(['data' => $usuarios]);
    }

    // ── POST /api/mobile/usuarios ─────────────────────────────────────────────

    public function store(Request $request)
    {
        if (!$this->checkPermiso()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $data = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'email'                    => ['required', 'email', 'unique:users,email'],
            'password'                 => ['required', 'string', 'min:6'],
            'rol'                      => ['nullable', 'string', 'max:50'],
            'is_admin'                 => ['boolean'],
            'is_multiobra'             => ['integer', 'min:0', 'max:2'],
            'solo_explore'             => ['boolean'],
            'gestion_usuarios'         => ['boolean'],
            'explore'                  => ['boolean'],
            'puede_editar_desc_auxiliar' => ['boolean'],
            'obra_actual_id'           => ['nullable', 'integer', 'exists:obras,id'],
            'obras'                    => ['array'],
            'obras.*'                  => ['integer', 'exists:obras,id'],
        ]);

        $user = User::create([
            'name'                       => $data['name'],
            'email'                      => $data['email'],
            'password'                   => Hash::make($data['password']),
            'rol'                        => $data['rol'] ?? null,
            'estatus'                    => 1,
            'is_admin'                   => $data['is_admin'] ?? false,
            'is_multiobra'               => $data['is_multiobra'] ?? 0,
            'solo_explore'               => $data['solo_explore'] ?? false,
            'gestion_usuarios'           => $data['gestion_usuarios'] ?? false,
            'explore'                    => $data['explore'] ?? false,
            'puede_editar_desc_auxiliar' => $data['puede_editar_desc_auxiliar'] ?? false,
            'obra_actual_id'             => $data['obra_actual_id'] ?? null,
        ]);

        if (!empty($data['obras'])) {
            $user->obras()->sync($data['obras']);
        }

        return response()->json(['ok' => true, 'usuario' => $this->userPayload($user->fresh(['obras', 'obraActual']))], 201);
    }

    // ── PUT /api/mobile/usuarios/{id} ─────────────────────────────────────────

    public function update(Request $request, $id)
    {
        if (!$this->checkPermiso()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'                     => ['sometimes', 'string', 'max:255'],
            'email'                    => ['sometimes', 'email', 'unique:users,email,' . $id],
            'password'                 => ['sometimes', 'nullable', 'string', 'min:6'],
            'rol'                      => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_admin'                 => ['sometimes', 'boolean'],
            'is_multiobra'             => ['sometimes', 'integer', 'min:0', 'max:2'],
            'solo_explore'             => ['sometimes', 'boolean'],
            'gestion_usuarios'         => ['sometimes', 'boolean'],
            'explore'                  => ['sometimes', 'boolean'],
            'puede_editar_desc_auxiliar' => ['sometimes', 'boolean'],
            'obra_actual_id'           => ['sometimes', 'nullable', 'integer', 'exists:obras,id'],
            'obras'                    => ['sometimes', 'array'],
            'obras.*'                  => ['integer', 'exists:obras,id'],
        ]);

        $fill = collect($data)->except(['password', 'obras'])->toArray();
        if (!empty($data['password'])) {
            $fill['password'] = Hash::make($data['password']);
        }

        $user->fill($fill)->save();

        if (array_key_exists('obras', $data)) {
            $user->obras()->sync($data['obras']);
        }

        return response()->json(['ok' => true, 'usuario' => $this->userPayload($user->fresh(['obras', 'obraActual']))]);
    }

    // ── PUT /api/mobile/usuarios/{id}/estatus ─────────────────────────────────

    public function toggleEstatus(Request $request, $id)
    {
        if (!$this->checkPermiso()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $me = Auth::user();
        if ($me->id == $id) {
            return response()->json(['message' => 'No puedes desactivarte a ti mismo.'], 422);
        }

        $user = User::findOrFail($id);
        $user->estatus = $user->estatus ? 0 : 1;
        $user->save();

        // Revocar tokens si se desactiva
        if (!$user->estatus) {
            $user->tokens()->delete();
        }

        return response()->json(['ok' => true, 'estatus' => (int) $user->estatus]);
    }

    // ── DELETE /api/mobile/usuarios/{id} ─────────────────────────────────────

    public function destroy($id)
    {
        if (!$this->checkPermiso()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $me = Auth::user();
        if ($me->id == $id) {
            return response()->json(['message' => 'No puedes eliminarte a ti mismo.'], 422);
        }

        $user = User::findOrFail($id);
        $user->tokens()->delete();
        $user->obras()->detach();
        $user->delete();

        return response()->json(['ok' => true]);
    }

    // ── GET /api/mobile/obras-lista ───────────────────────────────────────────
    // Todas las obras disponibles (para el selector en crear/editar usuario)

    public function obrasList()
    {
        if (!$this->checkPermiso()) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }

        $obras = Obra::select('id', 'nombre')->orderBy('nombre')->get();
        return response()->json($obras);
    }
}
