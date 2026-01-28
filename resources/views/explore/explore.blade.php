<x-app-layout>
    <x-slot name="header">
        <div x-data="{}" class="flex flex-col gap-1">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Explore</h2>

                <div class="text-sm text-gray-600">
                    Obra actual:
                    <strong>{{ $obraActual?->nombre ?? 'Sin obra asignada' }}</strong>
                </div>
            </div>

            <div class="text-xs text-gray-500">
                Vista de exploración (solo lectura): salidas, inventario, órdenes (ERP) y gráficas.
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="exploreUI()" x-init="init()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Tabs --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-3">
                <div class="flex gap-2 overflow-auto">

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='mov' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='mov'; cargarMovimientos()">
                        Salidas
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='inv' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='inv'; cargarInventario()">
                        Inventario
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='oc' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='oc'; cargarOC()">
                        Órdenes compra (ERP)
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='graf' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='graf'; cargarGraficas()">
                        Gráficas
                    </button>
                </div>
            </div>

            <!-- ✅ Modal PDF -->
            <div x-show="pdf.show" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                 @keydown.escape.window="cerrarModalPdf()">

                <div class="bg-white w-full max-w-md rounded-lg shadow-lg border p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-semibold text-lg">Generando reporte PDF…</div>
                            <div class="text-sm text-gray-600 mt-1">
                                Pendientes + Parciales
                            </div>
                        </div>

                        <button class="text-gray-500 hover:text-gray-800"
                                @click="cerrarModalPdf()"
                                :disabled="pdf.loading">
                            ✕
                        </button>
                    </div>

                    <div class="mt-4">
                        <template x-if="pdf.loading">
                            <div class="text-sm text-gray-700">
                                Espera un momento, estamos preparando el archivo.
                            </div>
                        </template>

                        <template x-if="!pdf.loading && pdf.ok">
                            <div class="text-sm text-emerald-700 font-semibold">
                                ✅ Listo. La descarga debió iniciar.
                            </div>
                        </template>

                        <template x-if="!pdf.loading && pdf.error">
                            <div class="text-sm text-red-700">
                                ❌ <span x-text="pdf.error"></span>
                            </div>
                        </template>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button class="px-3 py-2 rounded border"
                                @click="cerrarModalPdf()"
                                :disabled="pdf.loading">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>

            {{-- ========================= --}}
            {{-- SALIDAS (MOVIMIENTOS) --}}
            {{-- ========================= --}}
            <div x-show="tab==='mov'" class="mt-4 space-y-3">

                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Buscar cabo o destino</label>
                            <input class="w-full border rounded px-3 py-2"
                                   placeholder="Ej: Juan Pérez / Centro Histórico"
                                   x-model="mov.q"
                                   @input.debounce.400ms="cargarMovimientos()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Desde</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="mov.desde"
                                   @change="cargarMovimientos()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="mov.hasta"
                                   @change="cargarMovimientos()">
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="m in movimientos" :key="m.id">
                        <div class="bg-white shadow-sm rounded-lg border p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-lg">
                                        Salida #<span x-text="m.id"></span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <span x-text="m.nombre_cabo"></span>
                                    </div>
                                </div>

                                <a class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100"
                                   :href="'/movimientos/'+m.id+'/pdf'"
                                   target="_blank">
                                    PDF
                                </a>
                            </div>

                            <div class="mt-2 text-sm">
                                <div class="text-gray-500 text-xs">Destino</div>
                                <div class="font-medium" x-text="m.destino"></div>
                            </div>

                            <div class="mt-2 text-sm">
                                <div class="text-gray-500 text-xs">Fecha</div>
                                <div x-text="m.fecha"></div>
                            </div>

                            <div class="mt-3">
                                <button class="w-full px-4 py-2 rounded bg-gray-900 text-white"
                                        @click="verDetalles(m.id)">
                                    Ver detalles
                                </button>
                            </div>

                            <div x-show="detallesMovId===m.id" class="mt-3 border-t pt-3">
                                <template x-if="detalles.length===0">
                                    <div class="text-sm text-gray-500">Sin detalles</div>
                                </template>

                                <template x-for="d in detalles" :key="d.id">
                                    <div class="py-2 border-b text-sm">
                                        <div class="font-semibold">
                                            <span x-text="d.inventario_id"></span> —
                                            <span x-text="d.descripcion"></span>
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            Cant: <span x-text="d.cantidad"></span> <span x-text="d.unidad"></span>
                                            · Nivel: <span x-text="d.clasificacion"></span>
                                            · Depto: <span x-text="d.clasificacion_d"></span>
                                            <template x-if="Number(d.devolvible)===1">
                                                <span class="ml-2 px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-semibold">
                                                    RETORNABLE
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ========================= --}}
            {{-- INVENTARIO --}}
            {{-- ========================= --}}
            <div x-show="tab==='inv'" class="mt-4 space-y-3">
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <label class="block text-xs text-gray-500 mb-1">Buscar por ID (insumo) o descripción</label>
                    <input class="w-full border rounded px-3 py-2"
                           placeholder="Ej: 303-ARF ó varilla"
                           x-model="inv.q"
                           @input.debounce.400ms="cargarInventario()">
                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="p in inventario" :key="p.id">
                        <div class="bg-white shadow-sm rounded-lg border p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-lg" x-text="p.id"></div>
                                    <div class="text-sm text-gray-700" x-text="p.descripcion"></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Existencia</div>
                                    <div class="font-bold text-xl" x-text="p.cantidad"></div>
                                    <div class="text-xs text-gray-600" x-text="p.unidad"></div>
                                </div>
                            </div>

                            <div class="mt-2 text-xs text-gray-600">
                                <div>Proveedor: <span class="font-medium" x-text="p.proveedor"></span></div>
                                <div>Destino: <span class="font-medium" x-text="p.destino"></span></div>
                                <div>Actualizado: <span class="font-medium" x-text="p.updated_at"></span></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ========================= --}}
            {{-- ORDENES COMPRA (ERP) --}}
            {{-- ========================= --}}
            <div x-show="tab==='oc'" class="mt-4 space-y-3">

                <div class="bg-white shadow-sm sm:rounded-lg p-4">

                    <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
                        <div class="md:col-span-3">
                            <label class="block text-xs text-gray-500 mb-1">Buscar: insumo / descripción / razón social</label>
                            <input class="w-full border rounded px-3 py-2"
                                   placeholder="Ej: 303-ARF / varilla / proveedor"
                                   x-model="oc.q"
                                   @input.debounce.400ms="cargarOC()">
                        </div>

                        <div class="md:col-span-3 flex gap-2">
                            <button class="w-1/3 px-3 py-2 rounded bg-gray-900 text-white"
                                    @click="cargarOC()">
                                Refrescar
                            </button>

                            <button class="w-1/3 px-3 py-2 rounded border"
                                    @click="oc.estado='todas'; cargarOC()">
                                Ver todo
                            </button>

                            <button class="w-1/3 px-3 py-2 rounded border"
                                    @click="mostrarModalPdf(); generarPdfPendParc()">
                                PDF Pend+Parc
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <button class="px-3 py-2 rounded border text-sm"
                                :class="oc.estado==='pendiente' ? 'bg-slate-900 text-white' : 'bg-white'"
                                @click="oc.estado='pendiente'; cargarOC()">
                            Pendientes
                        </button>

                        <button class="px-3 py-2 rounded border text-sm"
                                :class="oc.estado==='parcial' ? 'bg-yellow-100 border-yellow-300' : 'bg-white'"
                                @click="oc.estado='parcial'; cargarOC()">
                            Parciales
                        </button>

                        <button class="px-3 py-2 rounded border text-sm"
                                :class="oc.estado==='finalizada' ? 'bg-emerald-100 border-emerald-300' : 'bg-white'"
                                @click="oc.estado='finalizada'; cargarOC()">
                            Finalizadas
                        </button>
                    </div>

                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                    <div class="mt-2 text-xs text-gray-500">
                        * Pendiente: ParcialPralmacen = 0 · Parcial: 0 &lt; ParcialPralmacen &lt; Cantidad · Finalizada: ParcialPralmacen = Cantidad
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        * “Última llegada/Entregada (sistema)” = fecha de última actualización del registro (PD si existe, si no P, si no Fecha OC).
                    </div>
                </div>

                <div class="space-y-3">
                    <template x-for="r in ordenesCompraFiltradas()" :key="r.idPedidoDet">
                        <div class="bg-white shadow-sm rounded-lg border p-4"
                             :style="estadoOC(r)==='parcial' ? 'background-color:#ffedd5;border-color:#fdba74;' : (estadoOC(r)==='finalizada' ? 'background-color:#ecfdf5;border-color:#6ee7b7;' : '')">

                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-base">
                                        OC #<span x-text="r.idPedido"></span> · Det #<span x-text="r.idPedidoDet"></span>
                                    </div>

                                    <div class="text-sm text-gray-700">
                                        <span class="font-semibold" x-text="r.insumo"></span> —
                                        <span x-text="r.descripcion"></span>
                                    </div>

                                    <div class="text-xs text-gray-600 mt-1">
                                        Proveedor: <span class="font-medium" x-text="r.razon"></span>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Recibido / Pedido</div>
                                    <div class="font-bold text-lg">
                                        <span x-text="num(r.recibida)"></span> / <span x-text="num(r.pedida)"></span>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        Falta: <span x-text="num(r.faltante)"></span> <span x-text="r.unidad"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 text-xs text-gray-600 flex items-center gap-2 flex-wrap">
                                <div>
                                    Fecha OC: <span class="font-medium" x-text="r.fecha"></span>
                                </div>

                                <template x-if="estadoOC(r)==='parcial'">
                                    <div>
                                        Última llegada (sistema):
                                        <span class="font-medium" x-text="r.fecha_evento ?? r.fecha"></span>
                                    </div>
                                </template>

                                <template x-if="estadoOC(r)==='finalizada'">
                                    <div>
                                        Entregada (sistema):
                                        <span class="font-medium" x-text="r.fecha_evento ?? r.fecha"></span>
                                    </div>
                                </template>

                                <template x-if="estadoOC(r)==='pendiente'">
                                    <span class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold">PENDIENTE</span>
                                </template>

                                <template x-if="estadoOC(r)==='parcial'">
                                    <span class="px-2 py-1 rounded bg-orange-100 text-orange-800 font-semibold">PARCIAL</span>
                                </template>

                                <template x-if="estadoOC(r)==='finalizada'">
                                    <span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-semibold">FINALIZADA</span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

            </div>

            {{-- ========================= --}}
            {{-- GRAFICAS --}}
            {{-- ========================= --}}
            <div x-show="tab==='graf'" class="mt-4 space-y-3">
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Buscar (familia / insumo / descripción)</label>
                            <input class="w-full border rounded px-3 py-2"
                                   placeholder="Ej: acero / 303-ARF / varilla"
                                   x-model="graf.q"
                                   @input.debounce.400ms="cargarGraficas()">
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Desde</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="graf.desde"
                                   @change="cargarGraficas()">
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="graf.hasta"
                                   @change="cargarGraficas()">
                        </div>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button class="px-3 py-2 rounded border text-sm"
                                :class="graf.soloObraActual ? 'bg-emerald-50 border-emerald-200' : 'bg-white'"
                                @click="graf.soloObraActual = !graf.soloObraActual; cargarGraficas()">
                            Solo obra actual
                        </button>

                        <button class="px-3 py-2 rounded bg-gray-900 text-white text-sm"
                                @click="cargarGraficas()">
                            Refrescar
                        </button>
                    </div>

                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

                    <div class="bg-white shadow-sm rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-base">Consumo por familia</div>
                            <div class="text-xs text-gray-500">Top 10</div>
                        </div>

                        <template x-if="familias.length===0">
                            <div class="mt-3 text-sm text-gray-500">Sin datos</div>
                        </template>

                        <div class="mt-3 space-y-2">
                            <template x-for="f in familias" :key="f.familia">
                                <div class="flex items-center justify-between text-sm border-b pb-2">
                                    <div class="font-medium" x-text="f.familia"></div>
                                    <div class="font-bold" x-text="f.total"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-base">Top insumos gastados</div>
                            <div class="text-xs text-gray-500">Top 10</div>
                        </div>

                        <template x-if="insumos.length===0">
                            <div class="mt-3 text-sm text-gray-500">Sin datos</div>
                        </template>

                        <div class="mt-3 space-y-2">
                            <template x-for="i in insumos" :key="i.inventario_id">
                                <div class="border-b pb-2">
                                    <div class="text-sm font-semibold">
                                        <span x-text="i.inventario_id"></span>
                                        <span class="text-gray-500 font-normal">—</span>
                                        <span x-text="i.descripcion"></span>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        Total: <span class="font-bold" x-text="i.total"></span>
                                        <span x-text="i.unidad"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>

                <div class="text-xs text-gray-500">
                    * Esta sección es para “qué se está gastando” en movimientos/movimiento_detalles (solo lectura).
                </div>
            </div>

        </div>

        <style>[x-cloak]{display:none!important}</style>

        <script>
            function exploreUI() {
                return {
                    tab: 'mov',
                    loading: false,

                    mov: { q:'', desde:'', hasta:'' },
                    inv: { q:'' },
                    oc:  { q:'', estado:'todas' },
                    graf: { q:'', desde:'', hasta:'', soloObraActual:true },

                    pdf: { show:false, loading:false, ok:false, error:'' },

                    movimientos: [],
                    detallesMovId: null,
                    detalles: [],

                    inventario: [],
                    ordenesCompra: [],

                    familias: [],
                    insumos: [],

                    init() {
                        this.cargarMovimientos();
                    },

                    mostrarModalPdf() {
                        this.pdf.show = true;
                        this.pdf.loading = true;
                        this.pdf.ok = false;
                        this.pdf.error = '';
                    },
                    cerrarModalPdf() {
                        if (this.pdf.loading) return;
                        this.pdf.show = false;
                    },

                    async generarPdfPendParc() {
                        try {
                            const params = new URLSearchParams();
                            if (this.oc.q) params.set('q', this.oc.q);

                            const url = "{{ route('explore.ordenes_compra_reporte_pdf') }}?" + params.toString();

                            const res = await fetch(url, {
                                headers: { 'Accept': 'application/pdf' },
                                cache: 'no-store'
                            });

                            if (!res.ok) {
                                throw new Error("No se pudo generar el PDF (HTTP " + res.status + ").");
                            }

                            const blob = await res.blob();

                            let filename = "OC_pendientes_parciales.pdf";
                            const dispo = res.headers.get('content-disposition') || '';
                            const match = dispo.match(/filename="?([^"]+)"?/i);
                            if (match && match[1]) filename = match[1];

                            const blobUrl = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = blobUrl;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            window.URL.revokeObjectURL(blobUrl);

                            this.pdf.ok = true;
                        } catch (e) {
                            console.error(e);
                            this.pdf.error = e?.message ?? 'Error desconocido.';
                        } finally {
                            this.pdf.loading = false;
                        }
                    },

                    num(v) {
                        const n = Number(v ?? 0);
                        if (Number.isNaN(n)) return '0';
                        return (Math.round(n * 100) / 100).toString();
                    },

                    estadoOC(r) {
                        if (r.estado) return r.estado;
                        const ped = Number(r.pedida ?? 0);
                        const rec = Number(r.recibida ?? 0);
                        if (rec <= 0) return 'pendiente';
                        if (rec >= ped) return 'finalizada';
                        return 'parcial';
                    },

                    ordenesCompraFiltradas() {
                        if (this.oc.estado === 'todas') return this.ordenesCompra;
                        return this.ordenesCompra.filter(r => this.estadoOC(r) === this.oc.estado);
                    },

                    async cargarMovimientos() {
                        this.loading = true;
                        this.detallesMovId = null;
                        this.detalles = [];
                        try {
                            const params = new URLSearchParams();
                            if (this.mov.q) params.set('q', this.mov.q);
                            if (this.mov.desde) params.set('desde', this.mov.desde);
                            if (this.mov.hasta) params.set('hasta', this.mov.hasta);

                            const res = await fetch("{{ route('explore.movimientos') }}?" + params.toString(), {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });
                            this.movimientos = await res.json();
                        } catch (e) {
                            console.error(e);
                            this.movimientos = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    async verDetalles(movId) {
                        if (this.detallesMovId === movId) {
                            this.detallesMovId = null;
                            this.detalles = [];
                            return;
                        }

                        this.loading = true;
                        try {
                            const res = await fetch("/explore/movimientos/" + movId + "/detalles", {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });
                            this.detalles = await res.json();
                            this.detallesMovId = movId;
                        } catch (e) {
                            console.error(e);
                            this.detalles = [];
                            this.detallesMovId = movId;
                        } finally {
                            this.loading = false;
                        }
                    },

                    async cargarInventario() {
                        this.loading = true;
                        try {
                            const params = new URLSearchParams();
                            if (this.inv.q) params.set('q', this.inv.q);

                            const res = await fetch("{{ route('explore.inventario') }}?" + params.toString(), {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });
                            this.inventario = await res.json();
                        } catch (e) {
                            console.error(e);
                            this.inventario = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    async cargarOC() {
                        this.loading = true;
                        try {
                            const params = new URLSearchParams();
                            if (this.oc.q) params.set('q', this.oc.q);

                            const res = await fetch("{{ route('explore.ordenes_compra') }}?" + params.toString(), {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });
                            this.ordenesCompra = await res.json();
                        } catch (e) {
                            console.error(e);
                            this.ordenesCompra = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    async cargarGraficas() {
                        this.loading = true;
                        try {
                            const params = new URLSearchParams();
                            if (this.graf.q) params.set('q', this.graf.q);
                            if (this.graf.desde) params.set('desde', this.graf.desde);
                            if (this.graf.hasta) params.set('hasta', this.graf.hasta);
                            params.set('solo_obra_actual', this.graf.soloObraActual ? '1' : '0');

                            const res = await fetch("{{ route('explore.graficas') }}?" + params.toString(), {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });

                            const data = await res.json();
                            this.familias = Array.isArray(data.familias) ? data.familias : [];
                            this.insumos  = Array.isArray(data.insumos) ? data.insumos : [];
                        } catch (e) {
                            console.error(e);
                            this.familias = [];
                            this.insumos = [];
                        } finally {
                            this.loading = false;
                        }
                    }
                }
            }
        </script>
    </div>
</x-app-layout>
