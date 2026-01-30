<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Nuevo producto
                </h2>

                <div class="text-sm text-gray-600 mt-1">
                    Obra actual:
                    <strong>{{ auth()->user()->obraActual->nombre ?? 'Sin obra asignada' }}</strong>
                </div>
            </div>

            <a href="{{ route('inventario.index') }}"
               class="px-3 py-2 border rounded bg-white hover:bg-gray-50">
                Volver
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

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

                <form method="POST"
                      action="{{ route('inventario.store') }}"
                      x-data="inventarioForm()"
                      class="space-y-4">
                    @csrf

                    {{-- Obra --}}
                    <input type="hidden" name="obra_id"
                           value="{{ auth()->user()->obra_actual_id }}">

                    {{-- Destino default --}}
                    <input type="hidden" name="destino" value="SIN DESTINO">

                    {{-- 🔴 IMPORTANTE: hidden + checkbox CORRECTO --}}
                    <input type="hidden" name="guardar_en_erp" value="0">

                    <div class="p-3 border rounded bg-gray-50">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                name="guardar_en_erp"
                                value="1"
                                {{ old('guardar_en_erp', 1) ? 'checked' : '' }}
                                style="width:18px;height:18px;appearance:auto;-webkit-appearance:auto;"
                            >
                            <span class="text-sm font-medium text-gray-800">
                                Guardar también en ERP
                            </span>
                        </label>

                        <div class="text-xs text-gray-500 mt-1">
                            Si lo desmarcas, solo se guarda en inventario local.
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        {{-- Familia --}}
                        <div>
                            <label class="text-sm">Familia</label>
                            <select name="familia"
                                    class="w-full border rounded px-3 py-2"
                                    x-model="familia"
                                    @change="subfamilia=''"
                                    required>
                                <option value="">-- Selecciona --</option>
                                <template x-for="(subs, fam) in familias" :key="fam">
                                    <option :value="fam" x-text="fam"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Subfamilia --}}
                        <div>
                            <label class="text-sm">Subfamilia</label>
                            <select name="subfamilia"
                                    class="w-full border rounded px-3 py-2"
                                    x-model="subfamilia"
                                    required>
                                <option value="">-- Selecciona --</option>
                                <template x-for="s in (familias[familia] ?? [])" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Insumo --}}
                        <div>
                            <label class="text-sm">ID Insumo (ERP)</label>
                            <input name="insumo_id"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('insumo_id') }}"
                                   required>
                        </div>

                        {{-- Unidad --}}
                        <div>
                            <label class="text-sm">Unidad</label>
                            <select name="unidad"
                                    class="w-full border rounded px-3 py-2"
                                    required>
                                @php
                                    $unidades = [
                                        '$','%','BOLSA','BULTO','CAJA','CIL','CUBETA',
                                        'HOR','HR','HRS','JGO','JOR','KG','KIT','KW/H',
                                        'LOTE','LT','M','M2','M3','MES','MIL','PAQ',
                                        'PZA','RENTAXDIA','ROLLO','SACO','SAL',
                                        'TON','TRAMO','VIAJE'
                                    ];
                                @endphp

                                @foreach($unidades as $u)
                                    <option value="{{ $u }}"
                                        {{ old('unidad','PZA') === $u ? 'selected' : '' }}>
                                        {{ $u }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Descripción --}}
                        <div class="md:col-span-2">
                            <label class="text-sm">Descripción</label>
                            <input name="descripcion"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('descripcion') }}"
                                   required>
                        </div>

                        {{-- Proveedor --}}
                        <div>
                            <label class="text-sm">Proveedor</label>
                            <input name="proveedor"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('proveedor') }}"
                                   required>
                        </div>

                        {{-- Cantidad --}}
                        <div>
                            <label class="text-sm">Cantidad</label>
                            <input name="cantidad"
                                   type="number"
                                   step="0.01"
                                   x-model.number="cantidad"
                                   @input="sync()"
                                   class="w-full border rounded px-3 py-2"
                                   required>
                        </div>

                        {{-- Teórica --}}
                        <div>
                            <label class="text-sm">Cantidad Teórica</label>
                            <input type="number"
                                   step="0.01"
                                   x-model.number="cantidad_teorica"
                                   readonly
                                   class="w-full border rounded px-3 py-2 bg-gray-100">
                        </div>

                        {{-- Espera --}}
                        <div>
                            <label class="text-sm">En espera</label>
                            <input type="number"
                                   step="0.01"
                                   x-model.number="en_espera"
                                   readonly
                                   class="w-full border rounded px-3 py-2 bg-gray-100">
                        </div>

                        {{-- Costo --}}
                        <div>
                            <label class="text-sm">Costo promedio</label>
                            <input name="costo_promedio"
                                   type="number"
                                   step="0.01"
                                   class="w-full border rounded px-3 py-2"
                                   value="{{ old('costo_promedio',0) }}"
                                   required>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end gap-2">
                        <a href="{{ route('inventario.index') }}"
                           class="px-4 py-2 border rounded">
                            Cancelar
                        </a>
                        <button class="px-4 py-2 bg-black text-white rounded">
                            Guardar
                        </button>
                    </div>
                </form>

                <script>
                    function inventarioForm() {
                        return {
                            familias: @js(config('familias')),
                            familia: @js(old('familia','')),
                            subfamilia: @js(old('subfamilia','')),
                            cantidad: Number(@js(old('cantidad',0))),
                            cantidad_teorica: Number(@js(old('cantidad',0))),
                            en_espera: Number(@js(old('cantidad',0))),
                            sync() {
                                this.cantidad_teorica = this.cantidad;
                                this.en_espera = this.cantidad;
                            }
                        }
                    }
                </script>

            </div>
        </div>
    </div>
</x-app-layout>
