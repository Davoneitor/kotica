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

                    {{-- 🔴 IMPORTANTE: hidden + checkbox CORRECTO (ERP) --}}
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

                    
                    {{-- ✅ NUEVO: Devolvible (retornable) --}}
<input type="hidden" name="devolvible" value="0">
<div class="p-3 border rounded bg-gray-50">
    <label class="flex items-center gap-3 cursor-pointer">
        <input
            type="checkbox"
            name="devolvible"
            value="1"
            x-model="devolvible"
            @change="devolvibleAuto = false"
            style="width:18px;height:18px;appearance:auto;-webkit-appearance:auto;"
        >
        <span class="text-sm font-medium text-gray-800">
            Producto devolvible / retornable
        </span>
    </label>

    <div class="text-xs text-gray-500 mt-1">
        Marca esto si el insumo se debe regresar (retornable). Si no, quedará como no retornable.
    </div>

    {{-- ✅ Explicación de la regla --}}
    <div class="text-xs text-gray-500 mt-1">
        Si el tipo del insumo en ERP es 3, se marcará automáticamente como devolvible.
    </div>

    {{-- (Opcional) aviso cuando se aplicó automáticamente --}}
    <div class="text-xs text-amber-700 mt-1" x-show="devolvibleAuto">
        Se marcó automáticamente porque el tipo es 3.
    </div>
</div>


                    @php $bloqueado = !$isMultiobra; @endphp
                    @if($bloqueado)
                        <div class="mb-3 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                            Solo puedes ingresar el Código Insumo (ERP) y la Cantidad. Los demás campos se llenan automáticamente.
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        {{-- Insumo --}}
                        <div>
                            <label class="text-sm">Código Insumo (ERP)</label>
                            <input name="insumo_id"
                                   class="w-full border rounded px-3 py-2"
                                   x-model="insumo_id"
                                   @input="onInsumoInput"
                                   value="{{ old('insumo_id') }}"
                                   autocomplete="off"
                                   required>

                            <p class="text-xs text-gray-500 mt-1" x-show="loading">Buscando en ERP…</p>
                            <p class="text-xs text-red-600 mt-1" x-show="notFound">No se encontró ese insumo.</p>
                        </div>

                        {{-- Unidad --}}
                        <div>
                            <label class="text-sm">Unidad</label>
                            <select name="unidad"
                                    class="w-full border rounded px-3 py-2 {{ $bloqueado ? 'bg-gray-100 text-gray-500' : '' }}"
                                    x-model="unidad"
                                    @if($bloqueado) style="pointer-events:none;" tabindex="-1" @endif
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

                        {{-- Familia --}}
                        <div>
                            <label class="text-sm">Familia</label>
                            <select name="familia"
                                    class="w-full border rounded px-3 py-2 {{ $bloqueado ? 'bg-gray-100 text-gray-500' : '' }}"
                                    x-model="familia"
                                    @change="subfamilia=''"
                                    @if($bloqueado) style="pointer-events:none;" tabindex="-1" @endif
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
                                    class="w-full border rounded px-3 py-2 {{ $bloqueado ? 'bg-gray-100 text-gray-500' : '' }}"
                                    x-model="subfamilia"
                                    @if($bloqueado) style="pointer-events:none;" tabindex="-1" @endif
                                    required>
                                <option value="">-- Selecciona --</option>
                                <template x-for="s in (familias[familia] ?? [])" :key="s">
                                    <option :value="s" x-text="s"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Descripción --}}
                        <div class="md:col-span-2">
                            <label class="text-sm">Descripción</label>
                            <input name="descripcion"
                                   class="w-full border rounded px-3 py-2 {{ $bloqueado ? 'bg-gray-100' : '' }}"
                                   x-model="descripcion"
                                   value="{{ old('descripcion') }}"
                                   @if($bloqueado) readonly @endif
                                   required>
                        </div>

                        {{-- Proveedor --}}
                        <div>
                            <label class="text-sm">Proveedor</label>
                            <input name="proveedor"
                                   class="w-full border rounded px-3 py-2 {{ $bloqueado ? 'bg-gray-100' : '' }}"
                                   x-model="proveedor"
                                   value="{{ old('proveedor') }}"
                                   @if($bloqueado) readonly @endif
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
                                   class="w-full border rounded px-3 py-2 {{ $bloqueado ? 'bg-gray-100' : '' }}"
                                   value="{{ old('costo_promedio',0) }}"
                                   @if($bloqueado) readonly @endif
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
        // --- lo que ya tenías ---
        familias: @js(config('familias')),
        familia: @js(old('familia','')),
        subfamilia: @js(old('subfamilia','')),
        cantidad: Number(@js(old('cantidad',0))),
        cantidad_teorica: Number(@js(old('cantidad',0))),
        en_espera: Number(@js(old('cantidad',0))),

        // --- bindings para autocomplete ---
        insumo_id: @js(old('insumo_id','')),
        descripcion: @js(old('descripcion','')),
        proveedor: @js(old('proveedor','')),
        unidad: @js(old('unidad','PZA')),
        tipo: Number(@js(old('tipo', 0))), // ✅ AQUÍ

        // ✅ devolvible automático por código "13"
        devolvible: Boolean(Number(@js(old('devolvible', 0)))),
        devolvibleAuto: false,

        loading: false,
        notFound: false,
        timer: null,

        init() {
  this.aplicarReglaDevolviblePorTipo(this.tipo);
},


        sync() {
            this.cantidad_teorica = this.cantidad;
            this.en_espera = this.cantidad;
        },

        aplicarReglaDevolviblePorTipo(tipo) {
    const t = Number(tipo || 0);

    if (t === 3) {
        this.devolvible = true;
        this.devolvibleAuto = true;
        return;
    }

    if (this.devolvibleAuto) {
        this.devolvible = false;
        this.devolvibleAuto = false;
    }
},


        onInsumoInput() {
            clearTimeout(this.timer);

            const code = (this.insumo_id || '').trim();
            this.notFound = false;

            

            if (code.length < 2) return;

            this.timer = setTimeout(() => this.buscarInsumo(code), 300);
        },

        async buscarInsumo(code) {
            this.loading = true;
            this.notFound = false;

            try {
                const url = `{{ route('inventario.buscarPorInsumo') }}?codigo=${encodeURIComponent(code)}`;
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

                const json = await res.json();

                if (json?.ok && json?.found) {
                    const d = json.data || {};

                    if (d.insumo_id !== undefined) this.insumo_id = d.insumo_id ?? this.insumo_id;

                  
                    if (d.descripcion !== undefined) this.descripcion = d.descripcion ?? '';
                    if (d.unidad !== undefined) this.unidad = d.unidad ?? 'PZA';
                    if (d.proveedor !== undefined) this.proveedor = d.proveedor ?? '';

                    if (d.familia) this.familia = d.familia;
                    if (d.subfamilia) this.subfamilia = d.subfamilia;
                    if (d.tipo !== undefined) {
    this.tipo = Number(d.tipo || 0);
    this.aplicarReglaDevolviblePorTipo(this.tipo);
}


                } else {
                    this.notFound = true;
                }
            } catch (e) {
                console.error(e);
                this.notFound = true;
            } finally {
                this.loading = false;
            }
        }
        
    }
}
</script>


            </div>
        </div>
    </div>
</x-app-layout>
