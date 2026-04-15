<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    // ── Helpers ─────────────────────────────────────────────────────────────

    private function userPayload(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'is_admin'           => (bool) $user->is_admin,
            'is_multiobra'       => (int) $user->is_multiobra,
            'solo_explore'       => (bool) $user->solo_explore,
            'obra_actual_id'     => $user->obra_actual_id,
            'obra_actual_nombre' => $user->obraActual?->nombre,
        ];
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
        } catch (\Throwable $e) {}

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user()));
    }

    // ── Obras ────────────────────────────────────────────────────────────────

    /**
     * GET /api/mobile/obras
     * Devuelve las obras a las que pertenece el usuario.
     */
    public function obras(Request $request)
    {
        $obras = $request->user()
            ->obras()
            ->select('obras.id', 'obras.nombre')
            ->orderBy('obras.nombre')
            ->get();

        return response()->json($obras);
    }

    /**
     * PUT /api/mobile/obra
     * Cambia la obra actual del usuario.
     */
    public function cambiarObra(Request $request)
    {
        $request->validate(['obra_id' => 'required|integer']);

        $user = $request->user();

        // Verificar que el usuario pertenece a la obra solicitada
        if (! $user->obras()->where('obras.id', $request->obra_id)->exists()) {
            return response()->json(['message' => 'No tienes acceso a esa obra'], 403);
        }

        $user->update(['obra_actual_id' => $request->obra_id]);
        $user->refresh();

        return response()->json($this->userPayload($user));
    }
}
