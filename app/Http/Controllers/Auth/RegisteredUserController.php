<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Obra;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'obras' => Obra::orderBy('nombre')->get(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            // Selección de obras (many-to-many)
            'obras' => ['required', 'array', 'min:1'],
            'obras.*' => ['integer', 'exists:obras,id'],

            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // ✅ Crear usuario
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // ✅ Asignar obras (tabla pivote)
        $user->obras()->sync($validated['obras']);

        // ✅ Guardar obra actual (users.obra_actual_id)
        // Toma la primera obra seleccionada
        $user->obra_actual_id = $validated['obras'][0];
        $user->save();

        event(new Registered($user));

        // ✅ Importante: si solo tú debes registrar, NO conviene loguear al usuario nuevo
        // Auth::login($user);

        return redirect()->route('inventario.index');
    }
}
