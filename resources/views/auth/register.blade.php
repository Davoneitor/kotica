<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crear usuario</h2>
                <p class="text-sm text-gray-600 mt-1">Registro rápido para almacén o administrador.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                <form method="POST" action="{{ route('register') }}" class="p-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre -->
                        <div>
                            <x-input-label for="name" value="Nombre" />
                            <x-text-input id="name" class="block mt-2 w-full h-12 text-base"
                                type="text" name="name" :value="old('name')" required autofocus autocomplete="name"
                                placeholder="Ej. Juan Pérez" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Email -->
                        <div>
                            <x-input-label for="email" value="Correo" />
                            <x-text-input id="email" class="block mt-2 w-full h-12 text-base"
                                type="email" name="email" :value="old('email')" required autocomplete="username"
                                placeholder="Ej. usuario@kotica.com" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Password -->
                        <div>
                            <x-input-label for="password" value="Contraseña" />
                            <x-text-input id="password" class="block mt-2 w-full h-12 text-base"
                                type="password" name="password" required autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Confirm -->
                        <div>
                            <x-input-label for="password_confirmation" value="Confirmar contraseña" />
                            <x-text-input id="password_confirmation" class="block mt-2 w-full h-12 text-base"
                                type="password" name="password_confirmation" required autocomplete="new-password"
                                placeholder="Repite la contraseña" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Obras -->
                    <div class="mt-7">
                        <div>
                            <h3 class="text-base font-semibold text-gray-800">Obras</h3>
                            <p class="text-sm text-gray-600">Selecciona al menos una. La primera será la obra actual.</p>
                        </div>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($obras as $obra)
                                <label class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 active:bg-gray-100 transition cursor-pointer select-none">
                                    <input type="checkbox" name="obras[]" value="{{ $obra->id }}"
                                        class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        {{ (is_array(old('obras')) && in_array($obra->id, old('obras'))) ? 'checked' : '' }}>
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
                                    <input type="checkbox" name="is_admin" value="1" class="sr-only peer"
                                        {{ old('is_admin') ? 'checked' : '' }}>
                                    <div class="w-14 h-8 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-200 rounded-full peer peer-checked:bg-indigo-600 relative transition">
                                        <div class="absolute top-1 left-1 w-6 h-6 bg-white rounded-full transition peer-checked:translate-x-6"></div>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-800">Administrador</span>
                                </label>
                            </div>

                            <x-input-error :messages="$errors->get('is_admin')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3">
                        <a class="text-center px-4 py-3 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700"
                           href="{{ route('inventario.index') }}">
                            Cancelar
                        </a>

                        <x-primary-button class="justify-center py-3 px-6 text-base">
                            Crear usuario
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
