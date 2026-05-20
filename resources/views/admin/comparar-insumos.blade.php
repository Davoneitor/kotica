<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Comparar Insumos: Almacén vs ERP
        </h2>
    </x-slot>

    <style>
        .cmp-table { border-collapse: collapse; table-layout: fixed; min-width: 1000px; width: 100%; }
        .cmp-table th, .cmp-table td { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .cmp-cell-ok   { background: #f0fdf4; color: #166534; }
        .cmp-cell-diff { background: #fef9c3; color: #713f12; }
        .cmp-cell-new  { background: #eff6ff; color: #1d4ed8; font-weight: 600; }
        .cmp-cell-null { background: #f9fafb; color: #9ca3af; }
    </style>

    <div class="py-6" x-data="compararInsumos()" x-init="cargarData()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- ── INFO ─────────────────────────────────────────────────────── --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-800 flex items-start gap-3">
                <span class="mt-0.5">ℹ️</span>
                <div>
                    <span class="font-semibold">Comparación campo a campo.</span>
                    Cada fila es un registro del inventario.
                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 ml-1">Amarillo</span> = valor actual difiere del ERP.
                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 ml-1">Azul</span> = valor nuevo del ERP.
                    Solo se actualiza <code class="text-xs">inventarios</code>.
                </div>
            </div>

            {{-- ── LOADING ──────────────────────────────────────────────────── --}}
            <div x-show="cargando" class="text-center py-20 text-gray-400">
                <svg class="animate-spin inline w-7 h-7 mb-2 text-indigo-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <p class="text-sm">Consultando inventario y ERP…</p>
            </div>

            <div x-show="!cargando" class="space-y-4">

                {{-- ── STATS ────────────────────────────────────────────────── --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-white rounded-lg border px-4 py-3 text-center shadow-sm">
                        <div class="text-xl font-bold text-gray-700" x-text="fmt(stats.total_registros)"></div>
                        <div class="text-xs text-gray-500">Registros totales</div>
                    </div>
                    <div class="bg-white rounded-lg border px-4 py-3 text-center shadow-sm">
                        <div class="text-xl font-bold text-indigo-600" x-text="fmt(stats.total_insumos)"></div>
                        <div class="text-xs text-gray-500">Insumos distintos</div>
                    </div>
                    <div class="bg-white rounded-lg border border-yellow-200 px-4 py-3 text-center shadow-sm">
                        <div class="text-xl font-bold text-yellow-700" x-text="fmt(stats.con_diferencias)"></div>
                        <div class="text-xs text-gray-500">Con diferencias</div>
                    </div>
                    <div class="bg-white rounded-lg border border-indigo-200 px-4 py-3 text-center shadow-sm">
                        <div class="text-xl font-bold text-indigo-600" x-text="fmt(selCount())"></div>
                        <div class="text-xs text-gray-500">Seleccionados</div>
                    </div>
                </div>

                {{-- ── BARRA DE CONTROLES ────────────────────────────────────── --}}
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 shadow-sm flex flex-wrap gap-3 items-center">

                    {{-- Filtros --}}
                    <div class="flex gap-1.5">
                        <button @click="filtro='todos'"
                            :class="filtro==='todos' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-colors">Todos</button>
                        <button @click="filtro='diffs'"
                            :class="filtro==='diffs' ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-colors">Con difs.</button>
                        <button @click="filtro='sin_erp'"
                            :class="filtro==='sin_erp' ? 'bg-gray-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 text-xs font-medium rounded transition-colors">Sin ERP</button>
                    </div>

                    {{-- Búsqueda --}}
                    <input x-model="busq" type="text" placeholder="Buscar insumo, obra o descripción…"
                           class="text-xs border border-gray-300 rounded px-2.5 py-1.5 w-48 focus:outline-none focus:border-indigo-400">

                    {{-- Campos --}}
                    <div class="flex items-center gap-3 border-l border-gray-200 pl-3">
                        <span class="text-xs text-gray-400 font-medium">Actualizar:</span>
                        <template x-for="[c, lbl] in campos" :key="c">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="checkbox" :checked="act[c]" @change="act[c]=$event.target.checked"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400 w-3 h-3">
                                <span class="text-xs text-gray-600" x-text="lbl"></span>
                            </label>
                        </template>
                    </div>

                    {{-- Acciones --}}
                    <div class="flex gap-2 ml-auto">
                        <button @click="selDiffs()"
                                class="px-2.5 py-1.5 text-xs bg-yellow-50 text-yellow-700 border border-yellow-200 rounded hover:bg-yellow-100 font-medium">
                            Sel. con difs.
                        </button>
                        <button @click="selNone()"
                                class="px-2.5 py-1.5 text-xs bg-gray-50 text-gray-600 border border-gray-200 rounded hover:bg-gray-100 font-medium">
                            Limpiar
                        </button>
                        <button @click="modalConfirm=true"
                                :disabled="selCount()===0"
                                :class="selCount()>0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-3 py-1.5 text-xs font-semibold rounded transition-colors">
                            Actualizar <span x-show="selCount()>0" x-text="'('+selCount()+')'"></span>
                        </button>
                    </div>
                </div>

                {{-- ── RESULTADO ─────────────────────────────────────────────── --}}
                <div x-show="resultado"
                     :class="resultado&&resultado.ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                     class="border rounded-lg px-4 py-2.5 text-sm flex items-center gap-3">
                    <span x-text="resultado&&resultado.ok ? '✅' : '❌'"></span>
                    <span x-show="resultado&&resultado.ok">
                        <strong x-text="resultado&&resultado.registros"></strong> registros actualizados
                        en <strong x-text="resultado&&resultado.tiempo_ms+'ms'"></strong>.
                    </span>
                    <span x-show="resultado&&!resultado.ok" x-text="resultado&&resultado.error"></span>
                    <button @click="resultado=null" class="ml-auto text-gray-400 hover:text-gray-600">✕</button>
                </div>

                {{-- ── TABLA ─────────────────────────────────────────────────── --}}
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="cmp-table text-xs">
                            <colgroup>
                                <col style="width:32px">    {{-- ☐ --}}
                                <col style="width:88px">    {{-- Insumo --}}
                                <col style="width:100px">   {{-- Obra --}}
                                <col style="width:130px">   {{-- Desc Sist --}}
                                <col style="width:130px">   {{-- Desc ERP --}}
                                <col style="width:58px">    {{-- Unid Sist --}}
                                <col style="width:58px">    {{-- Unid ERP --}}
                                <col style="width:90px">    {{-- Fam Sist --}}
                                <col style="width:90px">    {{-- Fam ERP --}}
                                <col style="width:90px">    {{-- Sub Sist --}}
                                <col style="width:90px">    {{-- Sub ERP --}}
                                <col style="width:72px">    {{-- PU Sist --}}
                                <col style="width:72px">    {{-- PU ERP --}}
                            </colgroup>
                            <thead>
                                {{-- Fila 1: grupos --}}
                                <tr class="bg-gray-100 border-b border-gray-300 text-gray-600 text-[10px] uppercase tracking-wide">
                                    <th rowspan="2" class="px-2 py-2 text-center border-r border-gray-200">
                                        <input type="checkbox"
                                               :checked="todosVisibles()"
                                               @change="toggleTodos($event.target.checked)"
                                               class="rounded border-gray-400 text-indigo-600 focus:ring-indigo-400">
                                    </th>
                                    <th rowspan="2" class="px-2 py-2 text-left border-r border-gray-200">Insumo</th>
                                    <th rowspan="2" class="px-2 py-2 text-left border-r border-gray-200">Obra</th>
                                    <th colspan="2" class="px-2 py-1.5 text-center border-r border-gray-200 font-bold">Descripción</th>
                                    <th colspan="2" class="px-2 py-1.5 text-center border-r border-gray-200 font-bold">Unidad</th>
                                    <th colspan="2" class="px-2 py-1.5 text-center border-r border-gray-200 font-bold">Familia</th>
                                    <th colspan="2" class="px-2 py-1.5 text-center border-r border-gray-200 font-bold">Subfamilia</th>
                                    <th colspan="2" class="px-2 py-1.5 text-center font-bold">PU / Costo</th>
                                </tr>
                                {{-- Fila 2: Sistema / ERP --}}
                                <tr class="bg-gray-50 border-b border-gray-300 text-[10px]">
                                    <th class="px-2 py-1.5 text-center text-gray-500 border-r border-gray-100 font-medium">Sistema</th>
                                    <th class="px-2 py-1.5 text-center text-blue-600 border-r border-gray-200 font-semibold">ERP</th>
                                    <th class="px-2 py-1.5 text-center text-gray-500 border-r border-gray-100 font-medium">Sistema</th>
                                    <th class="px-2 py-1.5 text-center text-blue-600 border-r border-gray-200 font-semibold">ERP</th>
                                    <th class="px-2 py-1.5 text-center text-gray-500 border-r border-gray-100 font-medium">Sistema</th>
                                    <th class="px-2 py-1.5 text-center text-blue-600 border-r border-gray-200 font-semibold">ERP</th>
                                    <th class="px-2 py-1.5 text-center text-gray-500 border-r border-gray-100 font-medium">Sistema</th>
                                    <th class="px-2 py-1.5 text-center text-blue-600 border-r border-gray-200 font-semibold">ERP</th>
                                    <th class="px-2 py-1.5 text-center text-gray-500 border-r border-gray-100 font-medium">Sistema</th>
                                    <th class="px-2 py-1.5 text-center text-blue-600 font-semibold">ERP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr x-show="filteredItems().length===0">
                                    <td colspan="13" class="text-center py-10 text-gray-400">
                                        No hay registros con el filtro actual.
                                    </td>
                                </tr>
                                <template x-for="(item, idx) in filteredItems()" :key="item.id">
                                    <tr :class="[
                                            sel[item.id] ? 'bg-indigo-50' : (idx%2===0 ? 'bg-white' : 'bg-gray-50/50'),
                                            item.diffs.length>0 ? 'border-l-2 border-l-yellow-400' : ''
                                        ]"
                                        class="border-b border-gray-100 hover:bg-blue-50/40 transition-colors">

                                        {{-- ☐ --}}
                                        <td class="px-1.5 py-1.5 text-center border-r border-gray-100">
                                            <input type="checkbox"
                                                   :checked="sel[item.id]"
                                                   :disabled="!item.en_erp"
                                                   @change="toggleSel(item.id, $event.target.checked)"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400 disabled:opacity-30">
                                        </td>

                                        {{-- Insumo --}}
                                        <td class="px-2 py-1.5 font-mono font-bold text-gray-800 border-r border-gray-100"
                                            :title="item.insumo_id" x-text="item.insumo_id"></td>

                                        {{-- Obra --}}
                                        <td class="px-2 py-1.5 text-gray-600 border-r border-gray-200"
                                            :title="item.obra" x-text="item.obra"></td>

                                        {{-- Descripción Sistema --}}
                                        <td class="px-2 py-1.5 border-r border-gray-100"
                                            :class="item.en_erp && item.diffs.includes('descripcion') ? 'cmp-cell-diff' : (item.en_erp ? 'cmp-cell-ok' : 'cmp-cell-null')"
                                            :title="item.local.descripcion" x-text="item.local.descripcion||'—'"></td>

                                        {{-- Descripción ERP --}}
                                        <td class="px-2 py-1.5 border-r border-gray-200"
                                            :class="!item.en_erp ? 'cmp-cell-null' : (item.diffs.includes('descripcion') ? 'cmp-cell-new' : 'cmp-cell-ok')"
                                            :title="item.erp ? item.erp.descripcion : ''"
                                            x-text="item.erp ? (item.erp.descripcion||'—') : '—'"></td>

                                        {{-- Unidad Sistema --}}
                                        <td class="px-2 py-1.5 text-center border-r border-gray-100"
                                            :class="item.en_erp && item.diffs.includes('unidad') ? 'cmp-cell-diff' : (item.en_erp ? 'cmp-cell-ok' : 'cmp-cell-null')"
                                            x-text="item.local.unidad||'—'"></td>

                                        {{-- Unidad ERP --}}
                                        <td class="px-2 py-1.5 text-center border-r border-gray-200"
                                            :class="!item.en_erp ? 'cmp-cell-null' : (item.diffs.includes('unidad') ? 'cmp-cell-new' : 'cmp-cell-ok')"
                                            x-text="item.erp ? (item.erp.unidad||'—') : '—'"></td>

                                        {{-- Familia Sistema --}}
                                        <td class="px-2 py-1.5 border-r border-gray-100"
                                            :class="item.en_erp && item.diffs.includes('familia') ? 'cmp-cell-diff' : (item.en_erp ? 'cmp-cell-ok' : 'cmp-cell-null')"
                                            :title="item.local.familia" x-text="item.local.familia||'—'"></td>

                                        {{-- Familia ERP --}}
                                        <td class="px-2 py-1.5 border-r border-gray-200"
                                            :class="!item.en_erp ? 'cmp-cell-null' : (item.diffs.includes('familia') ? 'cmp-cell-new' : 'cmp-cell-ok')"
                                            :title="item.erp ? item.erp.familia : ''"
                                            x-text="item.erp ? (item.erp.familia||'—') : '—'"></td>

                                        {{-- Subfamilia Sistema --}}
                                        <td class="px-2 py-1.5 border-r border-gray-100"
                                            :class="item.en_erp && item.diffs.includes('subfamilia') ? 'cmp-cell-diff' : (item.en_erp ? 'cmp-cell-ok' : 'cmp-cell-null')"
                                            :title="item.local.subfamilia" x-text="item.local.subfamilia||'—'"></td>

                                        {{-- Subfamilia ERP --}}
                                        <td class="px-2 py-1.5 border-r border-gray-200"
                                            :class="!item.en_erp ? 'cmp-cell-null' : (item.diffs.includes('subfamilia') ? 'cmp-cell-new' : 'cmp-cell-ok')"
                                            :title="item.erp ? item.erp.subfamilia : ''"
                                            x-text="item.erp ? (item.erp.subfamilia||'—') : '—'"></td>

                                        {{-- PU Sistema --}}
                                        <td class="px-2 py-1.5 text-right border-r border-gray-100 font-mono"
                                            :class="item.en_erp && item.diffs.includes('costo_promedio') ? 'cmp-cell-diff' : (item.en_erp ? 'cmp-cell-ok' : 'cmp-cell-null')"
                                            x-text="fmtN(item.local.costo_promedio)"></td>

                                        {{-- PU ERP --}}
                                        <td class="px-2 py-1.5 text-right font-mono"
                                            :class="!item.en_erp ? 'cmp-cell-null' : (item.diffs.includes('costo_promedio') ? 'cmp-cell-new' : 'cmp-cell-ok')"
                                            x-text="item.erp ? fmtN(item.erp.costo_promedio) : '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-2 bg-gray-50 border-t text-xs text-gray-500 flex justify-between">
                        <span>Mostrando <strong x-text="filteredItems().length"></strong> de <strong x-text="stats.total_registros"></strong> registros.</span>
                        <span class="text-gray-400">
                            <span class="inline-block w-3 h-3 rounded-sm bg-yellow-100 border border-yellow-300 mr-1 align-middle"></span>Sistema difiere del ERP
                            <span class="inline-block w-3 h-3 rounded-sm bg-blue-100 border border-blue-300 mr-1 ml-3 align-middle"></span>Valor ERP (nuevo)
                        </span>
                    </div>
                </div>

            </div>{{-- /!cargando --}}

        </div>

        {{-- ── MODAL ──────────────────────────────────────────────────────────── --}}
        <template x-teleport="body">
            <div x-show="modalConfirm" x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 style="display:none">
                <div @click.stop class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 space-y-4">
                    <h3 class="text-base font-bold text-gray-800">Confirmar actualización</h3>
                    <p class="text-sm text-gray-600">
                        Se actualizarán <strong class="text-indigo-700" x-text="selCount()+' registros'"></strong>
                        con los valores del ERP.
                    </p>
                    <div class="text-xs bg-gray-50 rounded-lg p-3 border space-y-1">
                        <p class="font-semibold text-gray-600">Campos:</p>
                        <template x-for="[c,lbl] in campos" :key="c">
                            <div x-show="act[c]" class="flex items-center gap-1.5 text-gray-600">
                                <span class="text-green-500">✓</span><span x-text="lbl"></span>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                        ⚠️ Solo modifica <code>inventarios</code>. No afecta salidas ni movimientos.
                    </p>
                    <div class="flex gap-3">
                        <button @click="modalConfirm=false"
                                class="flex-1 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg font-medium text-gray-700">Cancelar</button>
                        <button @click="aplicar()"
                                :disabled="aplicando"
                                class="flex-1 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium disabled:opacity-60">
                            <span x-show="!aplicando">Confirmar</span>
                            <span x-show="aplicando">Actualizando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

    </div>{{-- /x-data --}}

    <script>
    function compararInsumos() {
        return {
            cargando:    true,
            items:       [],
            stats:       { total_registros: 0, total_insumos: 0, con_diferencias: 0, sin_erp: 0 },
            filtro:      'todos',
            busq:        '',
            sel:         {},
            act: { descripcion: true, unidad: true, familia: true, subfamilia: true, costo_promedio: true },
            campos: [
                ['descripcion',    'Descripción'],
                ['unidad',         'Unidad'],
                ['familia',        'Familia'],
                ['subfamilia',     'Subfamilia'],
                ['costo_promedio', 'Costo/PU'],
            ],
            modalConfirm: false,
            aplicando:    false,
            resultado:    null,

            async cargarData() {
                this.cargando  = true;
                this.resultado = null;
                try {
                    const r = await fetch('{{ route('admin.comparar-insumos.data') }}');
                    const j = await r.json();
                    this.items = j.items || [];
                    this.stats = {
                        total_registros: j.total_registros || 0,
                        total_insumos:   j.total_insumos   || 0,
                        con_diferencias: j.con_diferencias || 0,
                        sin_erp:         j.sin_erp         || 0,
                    };
                    this.selDiffs();
                } catch(e) { console.error(e); }
                finally { this.cargando = false; }
            },

            filteredItems() {
                let list = this.items;
                if (this.filtro === 'diffs')   list = list.filter(i => i.diffs.length > 0);
                if (this.filtro === 'sin_erp') list = list.filter(i => !i.en_erp);
                if (this.busq.trim()) {
                    const q = this.busq.trim().toLowerCase();
                    list = list.filter(i =>
                        i.insumo_id.toLowerCase().includes(q) ||
                        (i.obra || '').toLowerCase().includes(q) ||
                        (i.local.descripcion || '').toLowerCase().includes(q)
                    );
                }
                return list;
            },

            selCount() { return Object.values(this.sel).filter(Boolean).length; },

            toggleSel(id, v) { this.sel = { ...this.sel, [id]: v }; },

            todosVisibles() {
                const vis = this.filteredItems().filter(i => i.en_erp);
                return vis.length > 0 && vis.every(i => this.sel[i.id]);
            },

            toggleTodos(v) {
                const ns = { ...this.sel };
                for (const i of this.filteredItems()) if (i.en_erp) ns[i.id] = v;
                this.sel = ns;
            },

            selDiffs() {
                const ns = {};
                for (const i of this.items) ns[i.id] = i.en_erp && i.diffs.length > 0;
                this.sel = ns;
            },

            selNone() { this.sel = {}; },

            async aplicar() {
                const ids    = Object.keys(this.sel).filter(k => this.sel[k]).map(Number);
                const campos = Object.keys(this.act).filter(k => this.act[k]);
                if (!ids.length || !campos.length) return;

                this.aplicando = true;
                try {
                    const r = await fetch('{{ route('admin.comparar-insumos.aplicar') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ ids, campos }),
                    });
                    const j = await r.json();
                    this.resultado = j;
                    if (j.ok) { this.modalConfirm = false; await this.cargarData(); }
                } catch(e) {
                    this.resultado = { ok: false, error: 'Error de red: ' + e.message };
                    this.modalConfirm = false;
                } finally { this.aplicando = false; }
            },

            fmt(n) { return n == null ? '—' : Number(n).toLocaleString('es-MX'); },

            fmtN(n) {
                if (!n && n !== 0) return '—';
                return new Intl.NumberFormat('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
            },
        };
    }
    </script>
</x-app-layout>
