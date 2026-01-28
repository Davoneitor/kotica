{{-- resources/views/inventario/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Editar producto (Insumo: {{ $inventario->insumo_id }})
            </h2>

            <a href="{{ route('inventario.index') }}"
               class="px-3 py-2 border rounded bg-white hover:bg-gray-50">
                ← Volver
            </a>
        </div>
    </x-slot>

    @php
        $oldInsumoId = old('insumo_id', $inventario->insumo_id);

        $rawFamilia = old('familia', $inventario->familia);
        $rawSubfamilia = old('subfamilia', $inventario->subfamilia);

        // Detectar familia aunque no coincida exacto
        $familiaSeleccionada = '';
        if (isset($familias[$rawFamilia])) {
            $familiaSeleccionada = $rawFamilia;
        } else {
            foreach ($familias as $fam => $subs) {
                if (in_array($rawSubfamilia, $subs, true)) {
                    $familiaSeleccionada = $fam;
                    break;
                }
            }
        }

        $subfamiliaSeleccionada = $rawSubfamilia;
        $subsIniciales = $familias[$familiaSeleccionada] ?? [];
    @endphp

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                {{-- ERRORES --}}
                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <div class="font-semibold mb-1">Revisa los errores:</div>
                        <ul class="list-disc ml-5 text-sm">
                            @foreach ($errors->all() as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('inventario.update', $inventario) }}">
                    @csrf
                    @method('PUT')

                    {{-- ✅ OBRA ID (CLAVE PARA QUE GUARDE) --}}
                    <input type="hidden"
                           name="obra_id"
                           value="{{ old('obra_id', $inventario->obra_id) }}">

                    {{-- INSUMO ID --}}
                    <div class="mb-4">
                        <label class="block text-sm text-gray-700 mb-1">
                            Insumo ID (RP)
                        </label>

                        <input type="text"
                               name="insumo_id"
                               class="w-full border rounded px-3 py-2"
                               value="{{ $oldInsumoId }}"
                               required>

                        @error('insumo_id')
                            <div class="text-red-600 text-xs mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- FAMILIA / SUBFAMILIA --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Familia</label>
                            <select id="familiaSelect"
                                    name="familia"
                                    class="w-full border rounded px-3 py-2"
                                    required>
                                <option value="">-- Selecciona --</option>
                                @foreach($familias as $fam => $subs)
                                    <option value="{{ $fam }}" @selected($familiaSeleccionada === $fam)>
                                        {{ $fam }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Subfamilia</label>
                            <select id="subfamiliaSelect"
                                    name="subfamilia"
                                    class="w-full border rounded px-3 py-2"
                                    required>
                                <option value="">-- Selecciona --</option>
                                @foreach($subsIniciales as $s)
                                    <option value="{{ $s }}" @selected($subfamiliaSeleccionada === $s)>
                                        {{ $s }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- UNIDAD (LIBRE, SIN CATÁLOGO) --}}
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Unidad</label>
                            <input type="text"
                                   name="unidad"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('unidad', $inventario->unidad) }}"
                                   required>
                        </div>

                        {{-- PROVEEDOR --}}
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Proveedor</label>
                            <input type="text"
                                   name="proveedor"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('proveedor', $inventario->proveedor) }}"
                                   required>
                        </div>
                    </div>

                    {{-- DESCRIPCIÓN --}}
                    <div class="mt-4">
                        <label class="block text-sm text-gray-700 mb-1">Descripción</label>
                        <input type="text"
                               name="descripcion"
                               class="w-full border rounded px-3 py-2"
                               value="{{ old('descripcion', $inventario->descripcion) }}"
                               required>
                    </div>

                    {{-- CANTIDADES --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Cantidad</label>
                            <input id="cantidadInput"
                                   type="number" step="0.01"
                                   name="cantidad"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('cantidad', $inventario->cantidad) }}"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-700 mb-1">Cantidad teórica</label>
                            <input id="cantidadTeoricaInput"
                                   type="number" step="0.01"
                                   name="cantidad_teorica"
                                   class="w-full border rounded px-3 py-2 bg-gray-100"
                                   value="{{ old('cantidad_teorica', $inventario->cantidad_teorica) }}"
                                   readonly>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-700 mb-1">En espera</label>
                            <input id="enEsperaInput"
                                   type="number" step="0.01"
                                   name="en_espera"
                                   class="w-full border rounded px-3 py-2 bg-gray-100"
                                   value="{{ old('en_espera', $inventario->en_espera) }}"
                                   readonly>
                        </div>
                    </div>

                    {{-- COSTO (SOLO LECTURA) --}}
                    <div class="mt-4">
                        <label class="block text-sm text-gray-700 mb-1">Costo promedio</label>
                        <input type="number" step="0.01"
                               class="w-full border rounded px-3 py-2 bg-gray-100"
                               value="{{ $inventario->costo_promedio }}"
                               readonly>
                    </div>

                    {{-- BOTONES --}}
                    <div class="flex justify-end gap-2 mt-6">
                        <a href="{{ route('inventario.index') }}"
                           class="px-4 py-2 border rounded bg-white hover:bg-gray-50">
                            Cancelar
                        </a>

                        <button type="submit"
                                class="px-4 py-2 rounded bg-black text-white">
                            Guardar cambios
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>

{{-- JS: subfamilias + sync cantidades --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const familias = @json($familias, JSON_UNESCAPED_UNICODE);

    const familiaSelect = document.getElementById('familiaSelect');
    const subSelect = document.getElementById('subfamiliaSelect');

    function renderSubs(fam) {
        const subs = familias[fam] || [];
        const current = subSelect.value;

        subSelect.innerHTML = '<option value="">-- Selecciona --</option>';

        subs.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            subSelect.appendChild(opt);
        });

        if (subs.includes(current)) {
            subSelect.value = current;
        }
    }

    if (familiaSelect && subSelect) {
        familiaSelect.addEventListener('change', () => {
            renderSubs(familiaSelect.value);
        });
        renderSubs(familiaSelect.value);
    }

    const cantidadInput = document.getElementById('cantidadInput');
    const teorica = document.getElementById('cantidadTeoricaInput');
    const espera = document.getElementById('enEsperaInput');

    function sync() {
        teorica.value = cantidadInput.value;
        espera.value = cantidadInput.value;
    }

    cantidadInput?.addEventListener('input', sync);
    sync();
});
</script>
