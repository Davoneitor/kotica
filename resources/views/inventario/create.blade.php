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

            <a href="{{ route('inventario.index') }}" class="px-3 py-2 border rounded bg-white hover:bg-gray-50">
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

                <form method="POST" action="{{ route('inventario.store') }}"
                      x-data="inventarioForm()"
                      class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Familia --}}
                        <div>
                            <label class="text-sm">Familia</label>
                            <select name="familia" class="w-full border rounded px-3 py-2"
                                    x-model="familia" @change="onFamiliaChange()" required>
                                <option value="">-- Selecciona --</option>
                                <template x-for="(subs, fam) in familias" :key="fam">
                                    <option :value="fam" x-text="fam"></option>
                                </template>
                            </select>
                            @error('familia')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Subfamilia (dependiente) --}}
                        <div>
                            <label class="text-sm">Subfamilia</label>
                            <select name="subfamilia" class="w-full border rounded px-3 py-2"
                                    x-model="subfamilia" required>
                                <option value="">-- Selecciona --</option>
                                <template x-for="s in subfamiliasDisponibles" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                            @error('subfamilia')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- ✅ NUEVO: ID de Insumo (ERP) --}}
                        <div>
                            <label class="text-sm">ID Insumo (ERP)</label>
                            <input name="insumo_id" type="text"
       value="{{ old('insumo_id') }}"
       class="w-full border rounded px-3 py-2"
       placeholder="Ej. RP-80-12 / 02ONVAR-001"
       required>
@error('insumo_id')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror

                        </div>

                        {{-- Unidad --}}
                        <div>
                            <label class="text-sm">Unidad</label>
                            @php
                                $unidades = ['Pieza','Metro','Litros','Kilos','Sacos','Toneladas','Paquete','Caja','Rollo','Cubeta'];
                            @endphp
                            <select name="unidad" class="w-full border rounded px-3 py-2" required>
                                <option value="">-- Selecciona --</option>
                                @foreach($unidades as $u)
                                    <option value="{{ $u }}" @selected(old('unidad')===$u)>{{ $u }}</option>
                                @endforeach
                            </select>
                            @error('unidad')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Descripción --}}
                        <div class="md:col-span-2">
                            <label class="text-sm">Descripción</label>
                            <input name="descripcion" value="{{ old('descripcion') }}"
                                   class="w-full border rounded px-3 py-2"
                                   placeholder="Ej. Pintura blanca 19L / Tubo PVC 1/2" required>
                            @error('descripcion')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Proveedor --}}
                        <div>
                            <label class="text-sm">Proveedor</label>
                            <input name="proveedor" value="{{ old('proveedor') }}"
                                   class="w-full border rounded px-3 py-2"
                                   placeholder="Ej. Comex / Proveedor Central" required>
                            @error('proveedor')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Cantidades --}}
                        <div>
                            <label class="text-sm">Cantidad</label>
                            <input name="cantidad" type="number" step="0.01" value="{{ old('cantidad', 0) }}"
                                   class="w-full border rounded px-3 py-2" required>
                            @error('cantidad')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="text-sm">Cantidad Teórica</label>
                            <input name="cantidad_teorica" type="number" step="0.01" value="{{ old('cantidad_teorica', 0) }}"
                                   class="w-full border rounded px-3 py-2" required>
                            @error('cantidad_teorica')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="text-sm">En espera</label>
                            <input name="en_espera" type="number" step="0.01" value="{{ old('en_espera', 0) }}"
                                   class="w-full border rounded px-3 py-2" required>
                            @error('en_espera')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="text-sm">Costo promedio</label>
                            <input name="costo_promedio" type="number" step="0.01" value="{{ old('costo_promedio', 0) }}"
                                   class="w-full border rounded px-3 py-2" required>
                            @error('costo_promedio')<div class="text-red-600 text-xs mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end gap-2">
                        <a href="{{ route('inventario.index') }}" class="px-4 py-2 border rounded">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 rounded bg-black text-white">
                            Guardar
                        </button>
                    </div>

                </form>

                <script>
                    function inventarioForm() {
                        return {
                           familias: {
  '02 ONA Varilla': ['02ONVAR', '02ONGEN'],

  '03 ONA Trefilados': ['03ONMLL', '03ONALM', '03ONESC', '03ONARM', '03ONGEN'],

  '04 ONA Concreto': ['04ONCON', '04ONADH', '04ONCLZ', '04ONCMR', '04ONEXT', '04ONGEN'],

  '05 ONA Bombeo': ['05ONBOM', '05ONGEN'],

  '06 ONA Cimbra': ['06ONCIM', '06ONTIR', '06ONSLL', '06ONREN', '06ONMAD', '06ONEQU', '06ONGEN'],

  '07 ONA Poliestireno': ['07ONCAS', '07ONPLA', '07ONBOV', '07ONGEN'],

  '08 ONA Prefabricados': ['08ONADO', '08ONLAD', '08ONPLA', '08ONTAB', '08ONTUB', '08ONVIG', '08ONBLO', '08ONGEN'],

  '09 ONA Cementantes': ['09ONMOR', '09ONADI', '09ONCAL', '09ONCEM', '09ONDES', '09ONGEN'],

  '10 ONA Clavos': ['10ONCLA', '10ONGEN'],

  '11 ONA Agregados': ['12ONAGR', '12ONGEN'],

  '12 ONA Combustibles': ['12ONCOM', '12GENGEN'],

  '13 ONA Hta y Equipo': [
    '13ONHTA', '13ONBRO', '13ONCEP', '13ONCIN', '13ONCOP', '13ONDIS', '13ONEQM',
    '13ONPIN', '13ONESC', '13ONFLE', '13ONFUM', '13ONMRR', '13ONMRT', '13ONPAL',
    '13ONPIS', '13ONPLO', '13ONCOR', '13ONESP', '13ONLLV', '13ONJGO', '13ONLLA',
    '13ONNIV', '13ONRAS', '13ONPUL', '13ONCAN', '13ONCAR', '13ONLLN', '13ONVOL',
    '13ONREN', '13ONGEN'
  ],

  '15 ONA Otros': ['15ONOAL', '15ONOTR', '15ONAME', '15ONVIG'],

  'ONA Ferret': ['16ONFRR'],

  '20 Recs': ['20RCACC', '20RCADH', '20RCGEN', '20RCMOS', '20RCCER', '20RCCAN'],

  '22 Yesos y TR': ['22YTADH', '22YTACC', '22YTYES', '22YTPAN', '22YTPER', '22YTFIJ', '22YTREF', '22YTSEL', '22YTGEN'],

  '24 Pinturas e Imper': ['24PNAPL', '24PNSEL', '24PNPIN', '24PNSOL', '24PNIMP', '24PNGEN'],

  '26 Acce Sanit': [
    '26ACSCAL', '26ACSREG', '26ACSMON', '26ACSLAV', '26ACSWCC', '26ACSFLX',
    '26ACSMIN', '26ACSTAR', '26ACSCES', '26ACSCOL', '26ACSGEN', '26ACSTOA',
    '26ACSGAN', '26ACSPOR'
  ],

  '32 Limpieza': ['32LMLIM', '32LMGEN'],

  '40 Instalaciones': [
    '40IECAB', '40IEVAR', '40IECON', '40IEACC', '40IEPLA', '40IHCON', '40IELUM',
    '40IECCA', '40IESOP', '40ISSEL', '40IECAJ', '40IGTUB', '40IGACC', '40IGCON',
    '40IGSEG', '40IGVAL', '40IHVAL', '40IHACC', '40IHTUB', '40IHSOP', '40IHMED',
    '40IHBOM', '40ISSOP', '40ISREG', '40ISACC', '40ISCON', '40ISTUB', '40GEINS'
  ],

  '60 Seg e Higiene': ['60SHDOR', '60SHEPP', '60SHEQS', '60SHACC', '60SHCAP', '60SHCON', '60SHGEN'],
},

                            familia: @js(old('familia', '')),
                            subfamilia: @js(old('subfamilia', '')),

                            get subfamiliasDisponibles() {
                                return this.familias[this.familia] ?? [];
                            },

                            onFamiliaChange() {
                                this.subfamilia = '';
                            }
                        }
                    }
                </script>

            </div>
        </div>
    </div>
</x-app-layout>
