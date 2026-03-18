<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar usuario</h2>
                <p class="text-sm text-gray-600 mt-1">{{ $user->email }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                <form method="POST" action="{{ route('users.update', $user) }}" class="p-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre -->
                        <div>
                            <x-input-label for="name" value="Nombre" />
                            <x-text-input id="name" class="block mt-2 w-full h-12 text-base"
                                type="text" name="name" :value="old('name', $user->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Email -->
                        <div>
                            <x-input-label for="email" value="Correo" />
                            <x-text-input id="email" class="block mt-2 w-full h-12 text-base"
                                type="email" name="email" :value="old('email', $user->email)" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Nueva contraseña (opcional) -->
                        <div>
                            <x-input-label for="password" value="Nueva contraseña (dejar vacío para no cambiar)" />
                            <x-text-input id="password" class="block mt-2 w-full h-12 text-base"
                                type="password" name="password" autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Confirmar contraseña -->
                        <div>
                            <x-input-label for="password_confirmation" value="Confirmar nueva contraseña" />
                            <x-text-input id="password_confirmation" class="block mt-2 w-full h-12 text-base"
                                type="password" name="password_confirmation" autocomplete="new-password"
                                placeholder="Repite la contraseña" />
                        </div>
                    </div>

                    <!-- Obras -->
                    <div class="mt-7">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800">Obras</h3>
                            <p class="text-sm text-gray-600">Selecciona al menos una. La primera será la obra actual.</p>
                        </div>

                        <div id="obras-grid" class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($obras as $obra)
                                <label class="obra-label flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 active:bg-gray-100 transition cursor-pointer select-none">
                                    <input type="checkbox" name="obras[]" value="{{ $obra->id }}"
                                        class="obra-check h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        {{ in_array($obra->id, old('obras', $obrasSeleccionadas)) ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-800 leading-snug">{{ $obra->nombre }}</span>
                                </label>
                            @endforeach
                        </div>

                        <x-input-error :messages="$errors->get('obras')" class="mt-2" />
                    </div>

                    <!-- Rol -->
                    <div class="mt-7">
                        <div class="p-5 rounded-xl border border-gray-200 bg-gray-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-800">Rol</h3>
                                    <p class="text-sm text-gray-600 mt-1">Activa solo si debe administrar el sistema.</p>
                                </div>

                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="checkbox" id="is_admin_toggle" name="is_admin" value="1" class="sr-only peer"
                                        {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                                    <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-200 rounded-full peer peer-checked:bg-indigo-600 relative transition">
                                        <div class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full transition peer-checked:translate-x-6"></div>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-800">Administrador</span>
                                </label>
                            </div>

                            <x-input-error :messages="$errors->get('is_admin')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Solo Explore -->
                    <div class="mt-4">
                        <div class="p-5 rounded-xl border border-amber-200 bg-amber-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-800">Solo Explore</h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Perfil de consulta. Solo puede acceder al módulo Explore.<br>
                                        <span class="text-amber-700 font-medium">Incompatible con Administrador.</span>
                                    </p>
                                </div>

                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="checkbox" id="solo_explore_toggle" name="solo_explore" value="1" class="sr-only peer"
                                        {{ old('solo_explore', $user->solo_explore) ? 'checked' : '' }}>
                                    <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-200 rounded-full peer peer-checked:bg-amber-500 relative transition">
                                        <div class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full transition peer-checked:translate-x-6"></div>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-800">Solo Explore</span>
                                </label>
                            </div>

                            <x-input-error :messages="$errors->get('solo_explore')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Multiobra -->
                    <div class="mt-4">
                        <div class="p-5 rounded-xl border border-gray-200 bg-gray-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-800">Multiobra</h3>
                                    <p class="text-sm text-gray-600 mt-1">Activa para dar acceso a todas las obras. No requiere selección manual.</p>
                                </div>

                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="checkbox" id="is_multiobra_toggle" name="is_multiobra" value="1"
                                        class="sr-only peer"
                                        {{ old('is_multiobra', $user->is_multiobra) ? 'checked' : '' }}>
                                    <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-200 rounded-full peer peer-checked:bg-emerald-600 relative transition">
                                        <div class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full transition peer-checked:translate-x-6"></div>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-800">Multiobra</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
                        <a class="text-center px-4 py-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700"
                           href="{{ route('users.index') }}">
                            Cancelar
                        </a>

                        <x-primary-button class="justify-center py-3 px-6 text-base">
                            Guardar cambios
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
(function () {
    // ── Exclusión mutua: Administrador ↔ Solo Explore ──────────────
    const adminToggle      = document.getElementById('is_admin_toggle');
    const soloExploreToggle = document.getElementById('solo_explore_toggle');

    function syncExclusion(changed, other) {
        if (changed.checked) {
            other.checked  = false;
            other.disabled = true;
            other.closest('label').classList.add('opacity-40', 'cursor-not-allowed');
            other.closest('label').classList.remove('cursor-pointer');
        } else {
            other.disabled = false;
            other.closest('label').classList.remove('opacity-40', 'cursor-not-allowed');
            other.closest('label').classList.add('cursor-pointer');
        }
    }

    // Estado inicial
    if (adminToggle.checked)       syncExclusion(adminToggle, soloExploreToggle);
    if (soloExploreToggle.checked) syncExclusion(soloExploreToggle, adminToggle);

    adminToggle.addEventListener('change', function () {
        syncExclusion(adminToggle, soloExploreToggle);
    });
    soloExploreToggle.addEventListener('change', function () {
        syncExclusion(soloExploreToggle, adminToggle);
    });

    // ── Multiobra ───────────────────────────────────────────────────
    const toggle = document.getElementById('is_multiobra_toggle');
    const obrasGrid = document.getElementById('obras-grid');

    // uncheckOnDeactivate: solo desmarcar cuando el usuario cambia el toggle OFF manualmente
    function applyMultiobra(active, uncheckOnDeactivate) {
        const checks = obrasGrid.querySelectorAll('.obra-check');
        const labels = obrasGrid.querySelectorAll('.obra-label');

        checks.forEach(function (chk) {
            if (active) {
                chk.checked = true;
                chk.disabled = true;
            } else {
                chk.disabled = false;
                if (uncheckOnDeactivate) chk.checked = false;
            }
        });

        labels.forEach(function (lbl) {
            if (active) {
                lbl.classList.add('opacity-60', 'cursor-not-allowed');
                lbl.classList.remove('cursor-pointer', 'hover:bg-gray-50', 'active:bg-gray-100');
            } else {
                lbl.classList.remove('opacity-60', 'cursor-not-allowed');
                lbl.classList.add('cursor-pointer', 'hover:bg-gray-50', 'active:bg-gray-100');
            }
        });
    }

    // Carga inicial: no desmarcar (respetar selección guardada)
    applyMultiobra(toggle.checked, false);

    // Cambio manual: sí desmarcar al pasar a OFF
    toggle.addEventListener('change', function () {
        applyMultiobra(this.checked, true);
    });
})();
</script>

</x-app-layout>
