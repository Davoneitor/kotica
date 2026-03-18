<?php

namespace App\Http\Controllers;

use App\Models\Obra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('obras', 'obraActual')
            ->orderBy('name')
            ->get();

        return view('users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $obras = Obra::orderBy('nombre')->get(['id', 'nombre']);
        $obrasSeleccionadas = $user->obras->pluck('id')->toArray();

        return view('users.edit', compact('user', 'obras', 'obrasSeleccionadas'));
    }

    public function update(Request $request, User $user)
    {
        $isMultiobra  = $request->boolean('is_multiobra');
        $isSoloExplore = $request->boolean('solo_explore');
        $isAdmin       = $request->boolean('is_admin');

        // Exclusión mutua: no puede ser admin Y solo_explore a la vez
        if ($isSoloExplore && $isAdmin) {
            return back()
                ->withErrors(['solo_explore' => 'Un usuario no puede ser Administrador y Solo Explore al mismo tiempo.'])
                ->withInput();
        }

        $rules = [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'is_admin' => ['nullable', 'boolean'],
            'obras'    => [$isMultiobra ? 'nullable' : 'required', 'array', $isMultiobra ? 'min:0' : 'min:1'],
            'obras.*'  => ['integer', 'exists:obras,id'],
        ];

        $validated = $request->validate($rules);

        $user->name         = $validated['name'];
        $user->email        = $validated['email'];
        $user->is_admin     = $isSoloExplore ? false : $isAdmin; // solo_explore fuerza is_admin = false
        $user->is_multiobra = $isMultiobra ? 1 : 0;
        $user->solo_explore = $isSoloExplore;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($isMultiobra) {
            $todasLasObras = Obra::pluck('id')->toArray();
            $user->obras()->sync($todasLasObras);
            // Asigna la primera obra de obra_user si no tiene asignada o si cambió a multiobra
            $primeraObra = $user->obras()->orderBy('obra_id')->first();
            $user->obra_actual_id = $primeraObra ? $primeraObra->id : null;
        } else {
            $user->obras()->sync($validated['obras']);
            $user->obra_actual_id = $validated['obras'][0];
        }

        $user->save();

        return redirect()->route('users.index')->with('success', "Usuario \"{$user->name}\" actualizado.");
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')
                ->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $nombre = $user->name;
        $user->obras()->detach();
        $user->delete();

        return redirect()->route('users.index')->with('success', "Usuario \"{$nombre}\" eliminado.");
    }
}
