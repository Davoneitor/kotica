


{{-- resources/views/inventario/index.blade.php --}}
<x-app-layout>

    <x-slot name="header">
        @php
    $isAdmin = auth()->check() && auth()->user()->is_admin == 1;

@endphp

        {{-- ✅ x-data="{}" (NO vacío) para que Alpine habilite $store en el header --}}
        <div x-data="{}" class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Inventario
                </h2>

                <div class="text-sm text-gray-600 mt-1">
                    Obra actual:
                    <strong>
                        {{ $obraActual?->nombre ?? 'Sin obra asignada' }}
                    </strong>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if($isAdmin)
    <a href="{{ route('inventario.create') }}"
       class="px-3 py-2 border rounded bg-white hover:bg-gray-50">
         Nuevo producto
    </a>
@endif


                <button type="button"
                        class="px-3 py-2 border rounded bg-gray-800 text-white hover:bg-gray-900"
                        @click="$store.salidas.open()">
                     Salidas
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">

                {{-- 🔍 BUSCADOR INVENTARIO (amigable para tablet) --}}
                <form method="GET"
                      action="{{ route('inventario.index') }}"
                      class="mb-4 p-3 md:p-4 border rounded-lg bg-white">

                    <div class="flex flex-col md:flex-row md:items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs md:text-sm text-gray-600 mb-1">
                                Buscar por ID (INSUMO) o descripción
                            </label>

                            <input type="text"
                                   name="q"
                                   value="{{ request('q') }}"
                                   class="w-full border rounded-lg px-4 py-3 text-base md:text-sm"
                                   placeholder="Ej: 303-ARF-0201 ó varilla"
                                   inputmode="search">
                        </div>

                        <div class="flex gap-2">
                            <button type="submit"
                                    class="w-full md:w-auto px-5 py-3 rounded-lg bg-gray-800 text-white text-base md:text-sm hover:bg-gray-900">
                                Buscar
                            </button>

                            @if(request('q'))
                                <a href="{{ route('inventario.index') }}"
                                   class="w-full md:w-auto px-5 py-3 rounded-lg border bg-gray-100 text-gray-800 text-base md:text-sm hover:bg-gray-200 text-center">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Chips / ayuda (se ve bonito en tablet) --}}
                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                        <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-700">
                            Tip: puedes escribir <b>#303-ARF-0201</b>
                        </span>

                        @if(request('q'))
                            <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-900">
                                Filtro: <b>{{ request('q') }}</b>
                            </span>
                        @endif

                        <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-600">
                            Obra: <b>{{ $obraActual?->nombre ?? 'Sin obra' }}</b>
                        </span>
                    </div>
                </form>


                <div class="overflow-auto">
                    <div class="text-xs text-gray-600 mb-3">
                        Registros en esta página: {{ $inventarios->count() }}
                    </div>

                    @if(session('success'))
                        <div class="mb-3 p-3 bg-green-100 text-green-800 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-3 p-3 bg-red-100 text-red-800 rounded">
                            <div class="font-semibold mb-1">Revisa los errores:</div>
                            <ul class="list-disc ml-5 text-sm">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <table class="min-w-full text-sm">
                        <thead class="text-left border-b">
                        <tr>
                            <th class="py-2 pr-3">Insumo</th>
                            <th class="py-2 pr-3">Familia</th>
                            <th class="py-2 pr-3">Subfamilia</th>
                            <th class="py-2 pr-3">Descripción</th>
                            <th class="py-2 pr-3">Unidad</th>
                            <th class="py-2 pr-3">Obra</th>
                            <th class="py-2 pr-3">Proveedor</th>
                            <th class="py-2 pr-3">Cantidad</th>
                            <th class="py-2 pr-3">Acciones</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($inventarios as $inv)
                            @php
                                $highlightId = session('highlight_id');
                                $highlightType = session('highlight_type');

                                $style = '';

                                if ($highlightId === $inv->id) {
                                    if ($highlightType === 'created') {
                                        $style = 'background-color:#dcfce7;color:#166534;';
                                    } elseif ($highlightType === 'updated') {
                                        $style = 'background-color:#fef9c3;color:#854d0e;';
                                    }
                                }

                                // ✅ SOLO ADMIN (por correo)
                               $isAdmin = auth()->check() && auth()->user()->is_admin == 1;

                            @endphp

                            <tr class="border-b" id="inv-{{ $inv->id }}" style="{{ $style }}">
                                <td class="py-2 pr-3">{{ $inv->insumo_id }}</td>
                                <td class="py-2 pr-3">{{ $inv->familia }}</td>
                                <td class="py-2 pr-3">{{ $inv->subfamilia }}</td>
                                <td class="py-2 pr-3">{{ $inv->descripcion }}</td>
                                <td class="py-2 pr-3">{{ $inv->unidad }}</td>
                                <td class="py-2 pr-3">{{ optional($inv->obra)->nombre }}</td>
                                <td class="py-2 pr-3">{{ $inv->proveedor }}</td>
                                <td class="py-2 pr-3">{{ $inv->cantidad }}</td>

                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        @if($isAdmin)
                                            {{-- ✅ SOLO ADMIN: Editar --}}
                                            <a href="{{ route('inventario.edit', $inv) }}"
                                               class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100">
                                                Editar
                                            </a>

                                            {{-- ✅ SOLO ADMIN: Eliminar --}}
                                            <form method="POST"
                                                  action="{{ route('inventario.destroy', $inv) }}"
                                                  onsubmit="return confirm('¿Seguro que quieres eliminar este registro (ID {{ $inv->id }})?');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                        class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-gray-400">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        @if($inventarios->count() === 0)
                            <tr>
                                <td colspan="13" class="py-6 text-center text-gray-500">
                                    No hay productos en inventario todavía.
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $inventarios->links() }}
                </div>

            </div>
        </div>
    </div>

    {{-- ===================== --}}
    {{-- ✅ MODAL SALIDAS --}}
    {{-- ===================== --}}
   <div
    x-data="salidasUI()"
    x-init="init()"
    x-show="$store.salidas.show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
>
    {{-- fondo --}}
    <div class="absolute inset-0 bg-black/50" @click="$store.salidas.close()"></div>

    {{-- caja (✅ NO CRECE INFINITO: alto fijo + layout flex) --}}
    <div class="relative bg-white w-full max-w-3xl mx-4 rounded-lg shadow-lg p-5
                max-h-[90vh] overflow-hidden flex flex-col"
         @keydown.escape.window="$store.salidas.close()">

        {{-- header --}}
        <div class="flex items-center justify-between border-b pb-3 shrink-0">
            <h3 class="text-lg font-semibold">Registrar salida</h3>

            <button type="button"
                    class="px-2 py-1 text-sm border rounded bg-gray-100 hover:bg-gray-200"
                    @click="$store.salidas.close()">
                Cerrar
            </button>
        </div>

        {{-- ✅ CONTENIDO SCROLL (solo esta parte hace scroll) --}}
        <div class="mt-4 overflow-y-auto pr-1 grow">
            {{-- ✅ IMPORTANTE: x-ref="form" y submit manda el ref --}}
            <form x-ref="form"
                  method="POST"
                  action="{{ route('salidas.store') }}"
                  class="space-y-4"
                  @submit.prevent="guardarSalida($refs.form)">

                @csrf

                {{-- ✅ NUEVO: aquí viaja la firma al backend --}}
                <input type="hidden" name="firma_base64" x-ref="firmaBase64">

                {{-- datos generales --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- ✅ QUIÉN RECIBE (COMBO) --}}
                    <div>
                        <label class="block text-sm mb-1">Quién recibe</label>

                        <select name="nombre_cabo" class="w-full border rounded px-3 py-2 text-sm" required>
                            <option value="">-- Selecciona responsable --</option>
                            @foreach($responsables as $r)
                                <option value="{{ $r->Nombre }}">{{ $r->Nombre }}</option>
                            @endforeach
                        </select>

                        @if(empty($responsables) || count($responsables) === 0)
                            <div class="text-xs text-amber-700 mt-1">
                                No se pudieron cargar responsables (BD almacén).
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm mb-1">Destino</label>
                        <select name="destino_proyecto_id"
                                class="w-full border rounded px-3 py-2 text-sm"
                                required>
                            <option value="">-- Selecciona destino --</option>
                            @foreach($destinos as $d)
                                <option value="{{ $d->IdProyecto }}">{{ $d->Proyecto }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ✅ Nivel --}}
                    <div>
                        <label class="block text-sm mb-1">Nivel</label>
                        <select name="nivel"
                                x-model="nivel"
                                required
                                class="w-full border rounded px-3 py-2 text-sm">
                            <option value="">-- Selecciona nivel --</option>

                            <optgroup label="Sótanos (sin departamento)">
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                                <option value="S4">S4</option>
                                <option value="S5">S5</option>
                            </optgroup>

                            <optgroup label="Niveles">
                                <option value="L1">L1</option>
                                <option value="L4">L4</option>
                                <option value="L5">L5</option>
                                <option value="L6">L6</option>
                                <option value="L7">L7</option>
                                <option value="L8">L8</option>
                                <option value="L9">L9</option>
                                <option value="L10">L10</option>
                                <option value="L11">L11</option>
                                <option value="L12">L12</option>
                                <option value="L13">L13</option>
                            </optgroup>
                        </select>
                    </div>

                    {{-- ✅ Departamento --}}
                    <div>
                        <label class="block text-sm mb-1">Departamento</label>

                        <select name="departamento"
                                x-model="departamento"
                                :disabled="esSotano"
                                :required="!esSotano"
                                class="w-full border rounded px-3 py-2 text-sm">
                            <option value="">
                                -- <span x-text="esSotano ? 'No aplica en sótano' : 'Selecciona departamento'"></span> --
                            </option>
                            <option value="D1">D1</option>
                            <option value="D2">D2</option>
                            <option value="D3">D3</option>
                            <option value="D4">D4</option>
                            <option value="D5">D5</option>
                            <option value="D6">D6</option>
                            <option value="D7">D7</option>
                            <option value="D8">D8</option>
                        </select>

                        <div class="text-xs text-gray-500 mt-1" x-show="esSotano">
                            Para S1–S5 no aplica departamento.
                        </div>
                    </div>

                    {{-- ✅ OBSERVACIONES --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Observaciones</label>
                        <textarea name="observaciones"
                                  rows="2"
                                  maxlength="500"
                                  class="w-full border rounded px-3 py-2 text-sm"
                                  placeholder="Ej: nombre de chalán / incompleto / cuidado con golpes"></textarea>
                    </div>
                </div>

                {{-- ===================== --}}
                {{-- BUSCADOR PRODUCTOS --}}
                {{-- ===================== --}}
                <div class="border rounded p-3">
                    <div class="font-semibold text-sm mb-2">Agregar productos</div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
                        <div class="md:col-span-2 relative">
                            <label class="block text-xs mb-1">Buscar por ID o descripción</label>

                            <input type="text"
                                   class="w-full border rounded px-3 py-2 text-sm"
                                   placeholder="Ej: 120 ó cemento"
                                   x-model="q"
                                   @input.debounce.300ms="buscar()">

                            {{-- resultados --}}
                            <div x-show="resultados.length"
                                 class="absolute mt-1 w-full bg-white border rounded shadow max-h-56 overflow-auto z-50">
                                <template x-for="p in resultados" :key="p.id">
                                    <button type="button"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 border-b"
                                            @click="seleccionar(p)">
                                        <div class="font-semibold">
                                            #<span x-text="p.id"></span> —
                                            <span x-text="p.descripcion"></span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Unidad: <span x-text="p.unidad"></span> |
                                            Existencia: <span x-text="p.cantidad"></span>
                                            <span x-show="p.devolvible"> | Retornable</span>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            <div class="text-xs text-gray-500 mt-1" x-show="buscando">
                                Buscando...
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs mb-1">Cantidad</label>
                            <input type="number" step="0.01"
                                   class="w-full border rounded px-3 py-2"
                                   x-model="qty"
                                   :max="selected ? selected.cantidad : null"
                                   placeholder="Ej. 5">
                        </div>
                    </div>

                    {{-- devolvible --}}
                    <div class="mt-2 flex items-center gap-2">
                        <input type="checkbox" x-model="devolvible" class="rounded">
                        <span class="text-sm">Producto retornable (préstamo)</span>
                    </div>

                    <div class="mt-3 flex justify-end">
                        <button type="button"
                                class="px-3 py-2 text-sm border rounded bg-gray-800 text-white hover:bg-gray-900"
                                @click="agregarItem()">
                            Agregar
                        </button>
                    </div>

                    {{-- items agregados --}}
                    <template x-if="$store.salidas.items.length">
                        <table class="min-w-full text-sm border mt-3">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="p-2 text-left">ID</th>
                                <th class="p-2 text-left">Descripción</th>
                                <th class="p-2 text-left">Cantidad</th>
                                <th class="p-2 text-left">Unidad</th>
                                <th class="p-2 text-left">Retornable</th>
                                <th class="p-2 text-left">Acción</th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="(it, idx) in $store.salidas.items" :key="idx">
                                <tr class="border-b">
                                    <td class="p-2" x-text="it.inventario_id"></td>
                                    <td class="p-2" x-text="it.descripcion"></td>
                                    <td class="p-2" x-text="it.cantidad"></td>
                                    <td class="p-2" x-text="it.unidad"></td>
                                    <td class="p-2" x-text="it.devolvible ? 'Sí' : 'No'"></td>
                                    <td class="p-2">
                                        <button type="button"
                                                class="px-2 py-1 text-xs border rounded"
                                                @click="$store.salidas.removeItem(idx)">
                                            Quitar
                                        </button>

                                        {{-- hidden --}}
                                        <input type="hidden" :name="`items[${idx}][inventario_id]`" :value="it.inventario_id">
                                        <input type="hidden" :name="`items[${idx}][cantidad]`" :value="it.cantidad">
                                        <input type="hidden" :name="`items[${idx}][unidad]`" :value="it.unidad">
                                        <input type="hidden" :name="`items[${idx}][devolvible]`" :value="it.devolvible ? 1 : 0">

                                        {{-- ✅ ubicación por item --}}
                                        <input type="hidden" :name="`items[${idx}][nivel]`" :value="it.nivel">
                                        <input type="hidden" :name="`items[${idx}][departamento]`" :value="it.departamento ?? ''">
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </template>
                </div>

                {{-- ===================== --}}
                {{-- ✅ FIRMA DIGITAL (colapsable) --}}
                {{-- ===================== --}}
                <div class="border rounded p-3">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm">Firma digital</div>

                        <button type="button"
                                class="px-3 py-1 text-xs border rounded bg-gray-100 hover:bg-gray-200"
                                @click="showFirma = !showFirma">
                            <span x-text="showFirma ? 'Ocultar' : 'Mostrar'"></span>
                        </button>
                    </div>

                    <div x-show="showFirma" class="mt-3">
                        <div class="border rounded p-2 bg-white inline-block">
                            <canvas x-ref="firmaCanvas" class="border rounded w-full"
                                    width="520" height="160"></canvas>
                        </div>

                        <div class="mt-2 flex gap-2">
                            <button type="button"
                                    class="px-3 py-2 text-sm border rounded bg-gray-100 hover:bg-gray-200"
                                    @click="limpiarFirma()">
                                Limpiar firma
                            </button>

                            <button type="button"
                                    class="px-3 py-2 text-sm border rounded bg-gray-800 text-white hover:bg-gray-900"
                                    @click="usarFirma()">
                                Usar firma
                            </button>
                        </div>

                        <div class="text-xs text-gray-500 mt-1" x-text="firmaMsg"></div>
                    </div>

                    <div x-show="!showFirma" class="text-xs text-gray-500 mt-2">
                        (Firma oculta para ahorrar espacio)
                        <span x-show="$refs.firmaBase64?.value" class="ml-1 text-green-700 font-semibold">
                            ✅ Firma lista
                        </span>
                    </div>
                </div>
            </form>
        </div>

        {{-- footer (✅ SIEMPRE VISIBLE) --}}
        <div class="mt-4 flex justify-end gap-2 border-t pt-4 shrink-0">
            <button type="button"
                    class="px-4 py-2 text-sm border rounded"
                    @click="$store.salidas.close()">
                Cancelar
            </button>

            <button type="button"
                    class="px-4 py-2 rounded bg-black text-white text-sm"
                    :disabled="$store.salidas.items.length === 0"
                    @click="guardarSalida($refs.form)">
                Guardar salida
            </button>
        </div>
    </div>

    <style>[x-cloak]{display:none!important}</style>

    {{-- ✅ SignaturePad --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <script>
        function salidasUI() {
            return {
                destinos: [],
                destinoId: '',

                // buscador
                q: '',
                resultados: [],
                selected: null,
                qty: '',
                devolvible: false,
                buscando: false,

                // ✅ nivel/departamento
                nivel: '',
                departamento: '',

                get esSotano() {
                    return /^S[1-5]$/.test(this.nivel || '');
                },

                // ✅ firma
                signaturePad: null,
                firmaMsg: 'Firma pendiente.',
                showFirma: false,

                init() {
                    this.$watch('nivel', (v) => {
                        if ((v || '').startsWith('S')) {
                            this.departamento = '';
                        }
                    });

                    this.$watch('$store.salidas.show', async (v) => {
                        if (v) {
                            // reset buscador
                            this.q = '';
                            this.resultados = [];
                            this.selected = null;
                            this.qty = '';
                            this.devolvible = false;

                            // reset ubicación
                            this.nivel = '';
                            this.departamento = '';

                            // reset firma
                            this.firmaMsg = 'Firma pendiente.';
                            this.showFirma = false;
                            if (this.$refs.firmaBase64) this.$refs.firmaBase64.value = '';

                            // init SignaturePad cuando el modal ya es visible
                            this.$nextTick(() => {
                                const canvas = this.$refs.firmaCanvas;
                                if (!canvas) return;

                                this.signaturePad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2 });
                            });
                        } else {
                            this.signaturePad = null;
                        }
                    });
                },

                limpiarFirma() {
                    if (this.signaturePad) this.signaturePad.clear();
                    if (this.$refs.firmaBase64) this.$refs.firmaBase64.value = '';
                    this.firmaMsg = 'Firma limpia. Firma de nuevo y pulsa “Usar firma”.';
                },

                usarFirma() {
                    if (!this.signaturePad || this.signaturePad.isEmpty()) {
                        this.firmaMsg = 'Primero firma en el recuadro.';
                        alert('Primero firma en el recuadro.');
                        return;
                    }
                    const dataUrl = this.signaturePad.toDataURL('image/png');
                    this.$refs.firmaBase64.value = dataUrl;
                    this.firmaMsg = 'Firma lista ✅ (se enviará al guardar).';
                    // opcional: cerrar firma para ahorrar espacio
                    this.showFirma = false;
                },

                async buscar() {
                    const term = (this.q || '').trim();
                    if (!term) {
                        this.resultados = [];
                        this.selected = null;
                        return;
                    }

                    const clean = term.startsWith('#') ? term.slice(1).trim() : term;

                    this.buscando = true;
                    try {
                        const url = "{{ route('salidas.buscar') }}" + "?q=" + encodeURIComponent(clean) + "&_=" + Date.now();
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store'
                        });
                        this.resultados = await res.json();
                    } catch (e) {
                        console.error(e);
                        this.resultados = [];
                    } finally {
                        this.buscando = false;
                    }
                },

                seleccionar(p) {
                    this.selected = p;
                    this.q = `#${p.id} - ${p.descripcion}`;
                    this.resultados = [];
                    this.devolvible = Number(p.devolvible) === 1;
                    if (!this.qty) this.qty = 1;
                },

                agregarItem() {
                    if (!this.selected) return alert('Selecciona un producto de la lista.');
                    if (!this.nivel) return alert('Selecciona un nivel.');

                    const deptoFinal = this.esSotano ? null : (this.departamento ? this.departamento : null);

                    const qty = parseFloat(this.qty);
                    if (!qty || qty <= 0) return alert('Pon una cantidad válida.');

                    const existencia = parseFloat(this.selected.cantidad);

                    if (qty > existencia) {
                        return alert(`Solo hay ${this.selected.cantidad} en existencia.`);
                    }

                    const idx = this.$store.salidas.items.findIndex(x => x.inventario_id === this.selected.id);

                    if (idx >= 0) {
                        const actual = parseFloat(this.$store.salidas.items[idx].cantidad);
                        const nueva = actual + qty;

                        if (nueva > existencia) {
                            return alert(`Con esa suma te excedes. Solo hay ${this.selected.cantidad}.`);
                        }

                        this.$store.salidas.items[idx].cantidad = nueva;

                        this.$store.salidas.items[idx].devolvible =
                            this.$store.salidas.items[idx].devolvible || !!this.devolvible;

                        this.$store.salidas.items[idx].nivel = this.nivel;
                        this.$store.salidas.items[idx].departamento = deptoFinal;

                    } else {
                        this.$store.salidas.items.push({
                            inventario_id: this.selected.id,
                            descripcion: this.selected.descripcion,
                            unidad: this.selected.unidad,
                            cantidad: qty,
                            devolvible: !!this.devolvible,
                            nivel: this.nivel,
                            departamento: deptoFinal,
                        });
                    }

                    this.selected = null;
                    this.q = '';
                    this.resultados = [];
                    this.qty = '';
                    this.devolvible = false;
                },

                async guardarSalida(form) {
                    // 1) evitar submit vacío
                    if (!this.$store.salidas.items.length) {
                        alert('Agrega al menos un producto.');
                        return;
                    }

                    // ✅ firma obligatoria
                    if (!this.$refs.firmaBase64?.value) {
                        alert('Falta la firma. Firma y presiona “Usar firma”.');
                        this.showFirma = true;
                        return;
                    }

                    // 2) arma formdata
                    const fd = new FormData(form);

                    // 3) POST por AJAX esperando JSON
                    let res;
                    try {
                        res = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: fd,
                        });
                    } catch (err) {
                        console.error(err);
                        alert('Error de red.');
                        return;
                    }

                    // 4) manejar errores
                    let data = null;
                    try { data = await res.json(); } catch (_) {}

                    if (!res.ok) {
                        if (res.status === 422 && data?.errors) {
                            const first = Object.values(data.errors)[0]?.[0] || 'Revisa los campos.';
                            alert(first);
                            return;
                        }
                        if (data?.message) {
                            alert(data.message);
                            return;
                        }
                        if (res.status === 419) {
                            alert('Tu sesión expiró (CSRF 419). Recarga la página e intenta de nuevo.');
                            return;
                        }
                        alert('Error al guardar salida.');
                        return;
                    }

                    // 5) validar respuesta esperada
                    if (!data?.ok || !data?.pdf_url) {
                        console.error('Respuesta inesperada:', data);
                        alert(data?.message || 'No se recibió pdf_url.');
                        return;
                    }

                    // ✅ A) cerrar modal
                    this.$store.salidas.close();

                    // ✅ B) refrescar tabla
                    await this.refrescarTablaInventario();

                    // ✅ C) descargar PDF
                    await this.descargarPdf(data.pdf_url);
                },

                async refrescarTablaInventario() {
                    const res = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const html = await res.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    const nuevoTbody = doc.querySelector('table tbody');
                    const tbodyActual = document.querySelector('table tbody');

                    if (nuevoTbody && tbodyActual) {
                        tbodyActual.innerHTML = nuevoTbody.innerHTML;
                    }
                },

                async descargarPdf(url) {
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const blob = await res.blob();
                    const blobUrl = URL.createObjectURL(blob);

                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = 'salida.pdf';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    URL.revokeObjectURL(blobUrl);
                },
            }
        }
    </script>
</div>


</x-app-layout>
