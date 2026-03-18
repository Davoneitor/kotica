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
        $isMultiobra   = $request->boolean('is_multiobra');
        $isSoloExplore = $request->boolean('solo_explore');
        $isAdmin       = $request->boolean('is_admin');

        if ($isSoloExplore && $isAdmin) {
            return back()
                ->withErrors(['solo_explore' => 'Un usuario no puede ser Administrador y Solo Explore al mismo tiempo.'])
                ->withInput();
        }

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'is_admin' => ['nullable', 'boolean'],
            'obras'    => [$isMultiobra ? 'nullable' : 'required', 'array', $isMultiobra ? 'min:0' : 'min:1'],
            'obras.*'  => ['integer', 'exists:obras,id'],
        ]);

        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => Hash::make($validated['password']),
            'is_admin'       => $isSoloExplore ? false : $isAdmin,
            'is_multiobra'   => $isMultiobra ? 1 : 0,
            'solo_explore'   => $isSoloExplore,
            'obra_actual_id' => null,
        ]);

        if ($isMultiobra) {
            // Asignar todas las obras disponibles
            $todasLasObras = \App\Models\Obra::pluck('id')->toArray();
            $user->obras()->sync($todasLasObras);
            // Toma la primera obra de obra_user como obra actual
            $primeraObra = $user->obras()->orderBy('obra_id')->first();
            $user->obra_actual_id = $primeraObra ? $primeraObra->id : null;
        } else {
            // Asignar obras seleccionadas y guardar la primera como actual
            $user->obras()->sync($validated['obras']);
            $user->obra_actual_id = $validated['obras'][0];
        }

        $user->save();

        event(new Registered($user));

        // ✅ Importante: si solo tú debes registrar, NO conviene loguear al usuario nuevo
        // Auth::login($user);

        return redirect()->route('inventario.index');
    }
}
