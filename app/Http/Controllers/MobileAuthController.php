<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales invalidas'], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'is_admin'       => (bool) $user->is_admin,
                'is_multiobra'   => (int) $user->is_multiobra,
                'solo_explore'   => (bool) $user->solo_explore,
                'obra_actual_id' => $user->obra_actual_id,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
        } catch (\Throwable $e) {}

        return response()->json(['message' => 'Sesion cerrada']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'is_admin'       => (bool) $user->is_admin,
            'is_multiobra'   => (int) $user->is_multiobra,
            'solo_explore'   => (bool) $user->solo_explore,
            'obra_actual_id' => $user->obra_actual_id,
        ]);
    }
}
