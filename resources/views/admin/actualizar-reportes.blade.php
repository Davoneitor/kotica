<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Actualizar Reportes</h2>
    </x-slot>

    <div class="py-8" x-data="actualizarReportes()" x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Descripción --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-5 py-3 text-sm text-blue-800">
                Sincroniza <strong>descripción, unidad, familia, subfamilia y PU</strong> desde RP (fuente correcta) hacia las tablas de reportes.
                El sistema primero muestra las discrepancias — <strong>nunca actualiza sin confirmación</strong>.
            </div>

            {{-- ━━━ PASO 1: CONFIGURACIÓN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <span class="font-semibold text-gray-700 text-sm">① Configuración</span>
                    <button x-show="comparacion !== null"
                            @click="comparacion = null; resultado = null; sel = {}"
                            class="text-xs text-indigo-500 hover:text-indigo-700">
                        ← Cambiar selección
                    </button>
                </div>
                <div class="px-5 py-5 grid grid-cols-1 md:grid-cols-3 gap-6">

                    {{-- Tablas --}}
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Tablas</p>
                        <p class="text-xs font-medium text-gray-400 mb-1">Salidas</p>
                        @foreach($tablasConfig->where('grupo','salidas') as $t)
                        <label class="flex items-center gap-2 py-1 cursor-pointer select-none">
                            <input type="checkbox" value="{{ $t['key'] }}" x-model="tablasSeleccionadas"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400">
                            <span class="text-sm text-gray-700">{{ $t['label'] }}</span>
                        </label>
                        @endforeach
                        <p class="text-xs font-medium text-gray-400 mt-3 mb-1">Entradas</p>
                        @foreach($tablasConfig->where('grupo','entradas') as $t)
                        <label class="flex items-center gap-2 py-1 cursor-pointer select-none">
                            <input type="checkbox" value="{{ $t['key'] }}" x-model="tablasSeleccionadas"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400">
                            <span class="text-sm text-gray-700">{{ $t['label'] }}</span>
                        </label>
                        @endforeach
                    </div>

                    {{-- Obras --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Obras</p>
                            <div class="flex gap-2 text-xs">
                                <button @click="obrasSeleccionadas = obras.map(o=>o.id)" class="text-indigo-500 hover:text-indigo-700">Todas</button>
                                <span class="text-gray-300">|</span>
                                <button @click="obrasSeleccionadas = []" class="text-gray-400 hover:text-gray-600">Ninguna</button>
                            </div>
                        </div>
                        <input x-model="buscarObra" type="text" placeholder="Buscar obra…"
                               class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 mb-2 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        <div x-show="obrasLoading" class="text-xs text-gray-400 py-2">Cargando…</div>
                        <div x-show="!obrasLoading" class="max-h-44 overflow-y-auto space-y-0.5 pr-1">
                            <template x-for="obra in obrasFiltradas" :key="obra.id">
                                <label class="flex items-center gap-2 py-0.5 cursor-pointer select-none">
                                    <input type="checkbox" :value="obra.id" x-model="obrasSeleccionadas"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400">
                                    <span class="text-xs text-gray-700" x-text="obra.nombre"></span>
                                </label>
                            </template>
                        </div>
                        <p class="text-xs text-indigo-600 mt-1.5" x-show="obrasSeleccionadas.length"
                           x-text="obrasSeleccionadas.length + ' seleccionada(s)'"></p>
                    </div>

                    {{-- Campos --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Campos</p>
                            <div class="flex gap-2 text-xs">
                                <button @click="camposSeleccionados = camposDisponibles.map(c=>c.key)" class="text-indigo-500 hover:text-indigo-700">Todos</button>
                                <span class="text-gray-300">|</span>
                                <button @click="camposSeleccionados = []" class="text-gray-400 hover:text-gray-600">Ninguno</button>
                            </div>
                        </div>
                        <template x-for="campo in camposDisponibles" :key="campo.key">
                            <label class="flex items-center gap-2 py-1 cursor-pointer select-none">
                                <input type="checkbox" :value="campo.key" x-model="camposSeleccionados"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400">
                                <span class="text-sm text-gray-700" x-text="campo.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div class="px-5 pb-5 flex items-center gap-4">
                    <button @click="comparar()"
                            :disabled="comparando || !tablasSeleccionadas.length || !obrasSeleccionadas.length || !camposSeleccionados.length"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-indigo-700 disabled:opacity-40 transition">
                        <svg x-show="comparando" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="comparando ? 'Comparando con RP…' : '🔍 Comparar con RP'"></span>
                    </button>
                    <p x-show="comparando" class="text-xs text-gray-400">Consultando RP — puede tardar unos segundos…</p>
                </div>
            </div>

            {{-- ━━━ PASO 2: TABLA COMPARATIVA ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div x-show="comparacion !== null" x-cloak
                 class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">

                {{-- Stats --}}
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center gap-4">
                    <span class="font-semibold text-gray-700 text-sm">② Discrepancias encontradas</span>
                    <div class="flex gap-2 ml-auto flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200"
                              x-text="(comparacion?.total ?? 0) + ' insumos comparados'"></span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-300"
                              x-text="(comparacion?.con_diffs ?? 0) + ' con diferencias'"></span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200"
                              x-text="(comparacion?.sin_erp ?? 0) + ' sin RP'"></span>
                    </div>
                </div>

                {{-- Controles --}}
                <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2">
                    <div class="flex rounded-lg overflow-hidden border border-gray-200 text-xs">
                        <button @click="filtro='todos'"
                                :class="filtro==='todos' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                class="px-3 py-1.5 font-medium transition">Todos</button>
                        <button @click="filtro='diffs'"
                                :class="filtro==='diffs' ? 'bg-yellow-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                class="px-3 py-1.5 font-medium transition border-l border-gray-200">Solo diferencias</button>
                    </div>
                    <button @click="selDiffs()"
                            class="text-xs px-3 py-1.5 rounded border border-yellow-400 text-yellow-700 hover:bg-yellow-50 transition">
                        ☑ Seleccionar diferencias
                    </button>
                    <button @click="selTodo(true)"
                            class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-600 hover:bg-gray-50 transition">
                        Todo
                    </button>
                    <button @click="selTodo(false)"
                            class="text-xs px-3 py-1.5 rounded border border-gray-300 text-gray-500 hover:bg-gray-50 transition">
                        Ninguno
                    </button>
                    <span class="ml-auto text-xs text-gray-500" x-text="selCount + ' seleccionados'"></span>
                </div>

                {{-- Tabla --}}
                <div class="overflow-x-auto">
                    <div class="overflow-y-auto" style="max-height:540px">
                        <table class="w-full text-xs border-collapse" style="min-width:860px">
                            <thead class="sticky top-0 z-10">
                                <tr class="bg-gray-800 text-white text-center">
                                    <th class="border border-gray-600 py-2 w-8"></th>
                                    <th class="border border-gray-600 py-2 px-2 text-left w-32">Insumo</th>
                                    <th class="border border-gray-600 py-2 w-16">Tablas</th>
                                    <th class="border border-gray-600 py-2 w-10">N</th>
                                    <template x-if="camposSeleccionados.includes('descripcion')">
                                        <th colspan="2" class="border border-gray-600 py-2">Descripción</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('unidad')">
                                        <th colspan="2" class="border border-gray-600 py-2">Unidad</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('familia')">
                                        <th colspan="2" class="border border-gray-600 py-2">Familia</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('subfamilia')">
                                        <th colspan="2" class="border border-gray-600 py-2">Subfamilia</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('precio_unitario')">
                                        <th colspan="2" class="border border-gray-600 py-2">PU</th>
                                    </template>
                                </tr>
                                <tr class="bg-gray-700 text-center text-gray-300">
                                    <th class="border border-gray-600 py-1.5"></th>
                                    <th class="border border-gray-600 py-1.5"></th>
                                    <th class="border border-gray-600 py-1.5"></th>
                                    <th class="border border-gray-600 py-1.5"></th>
                                    <template x-if="camposSeleccionados.includes('descripcion')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-normal">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('descripcion')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-semibold text-blue-300">RP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('unidad')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-normal">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('unidad')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-semibold text-blue-300">RP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('familia')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-normal">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('familia')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-semibold text-blue-300">RP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('subfamilia')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-normal">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('subfamilia')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-semibold text-blue-300">RP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('precio_unitario')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-normal">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('precio_unitario')">
                                        <th class="border border-gray-600 py-1.5 text-xs font-semibold text-blue-300">RP</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in itemsFiltrados" :key="item.insumo_id">
                                    <tr :class="item.diffs.length > 0 ? 'border-l-[3px] border-yellow-400' : 'border-l-[3px] border-transparent'"
                                        class="hover:bg-gray-50 transition-colors">

                                        <td class="border border-gray-100 text-center w-8">
                                            <input type="checkbox"
                                                   :checked="!!sel[item.insumo_id]"
                                                   :disabled="!item.en_erp"
                                                   @change="e => { sel = {...sel, [item.insumo_id]: e.target.checked} }"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-400 disabled:opacity-30 cursor-pointer">
                                        </td>

                                        <td class="border border-gray-100 px-2 py-1.5">
                                            <div class="font-mono text-gray-800 text-xs truncate leading-tight" x-text="item.insumo_id"></div>
                                            <span x-show="!item.en_erp" class="text-xs text-gray-400 italic">sin RP</span>
                                        </td>

                                        <td class="border border-gray-100 px-1 py-1.5 text-center">
                                            <div class="flex flex-wrap gap-0.5 justify-center">
                                                <template x-for="t in item.tablas" :key="t">
                                                    <span class="inline-block px-1 py-0.5 rounded text-xs font-medium leading-none"
                                                          :class="tablaColor(t)"
                                                          x-text="tablaLabel(t)"></span>
                                                </template>
                                            </div>
                                        </td>

                                        <td class="border border-gray-100 text-center text-gray-500 py-1.5" x-text="item.n"></td>

                                        {{-- Descripción --}}
                                        <template x-if="camposSeleccionados.includes('descripcion')">
                                            <td :class="cc(item,'descripcion','s')"
                                                class="border border-gray-100 px-1.5 py-1.5 max-w-xs truncate"
                                                :title="item.local.descripcion"
                                                x-text="cv(item,'descripcion',item.local.descripcion)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('descripcion')">
                                            <td :class="cc(item,'descripcion','e')"
                                                class="border border-gray-100 px-1.5 py-1.5 max-w-xs truncate"
                                                :title="item.erp?.descripcion||''"
                                                x-text="item.erp ? (item.erp.descripcion||'—') : '–'"></td>
                                        </template>

                                        {{-- Unidad --}}
                                        <template x-if="camposSeleccionados.includes('unidad')">
                                            <td :class="cc(item,'unidad','s')"
                                                class="border border-gray-100 px-1.5 py-1.5 text-center"
                                                x-text="cv(item,'unidad',item.local.unidad)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('unidad')">
                                            <td :class="cc(item,'unidad','e')"
                                                class="border border-gray-100 px-1.5 py-1.5 text-center"
                                                x-text="item.erp ? (item.erp.unidad||'—') : '–'"></td>
                                        </template>

                                        {{-- Familia --}}
                                        <template x-if="camposSeleccionados.includes('familia')">
                                            <td :class="cc(item,'familia','s')"
                                                class="border border-gray-100 px-1.5 py-1.5 truncate"
                                                x-text="cv(item,'familia',item.local.familia)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('familia')">
                                            <td :class="cc(item,'familia','e')"
                                                class="border border-gray-100 px-1.5 py-1.5 truncate"
                                                x-text="item.erp ? (item.erp.familia||'—') : '–'"></td>
                                        </template>

                                        {{-- Subfamilia --}}
                                        <template x-if="camposSeleccionados.includes('subfamilia')">
                                            <td :class="cc(item,'subfamilia','s')"
                                                class="border border-gray-100 px-1.5 py-1.5 truncate"
                                                x-text="cv(item,'subfamilia',item.local.subfamilia)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('subfamilia')">
                                            <td :class="cc(item,'subfamilia','e')"
                                                class="border border-gray-100 px-1.5 py-1.5 truncate"
                                                x-text="item.erp ? (item.erp.subfamilia||'—') : '–'"></td>
                                        </template>

                                        {{-- PU --}}
                                        <template x-if="camposSeleccionados.includes('precio_unitario')">
                                            <td :class="cc(item,'precio_unitario','s')"
                                                class="border border-gray-100 px-1.5 py-1.5 text-right"
                                                x-text="cv(item,'precio_unitario',item.local.precio_unitario, v => '$'+Number(v).toFixed(2))"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('precio_unitario')">
                                            <td :class="cc(item,'precio_unitario','e')"
                                                class="border border-gray-100 px-1.5 py-1.5 text-right"
                                                x-text="item.erp?.precio_unitario!=null ? '$'+Number(item.erp.precio_unitario).toFixed(2) : '–'"></td>
                                        </template>
                                    </tr>
                                </template>

                                <tr x-show="itemsFiltrados.length === 0">
                                    <td colspan="20" class="text-center py-12 text-gray-400">
                                        No hay registros con el filtro seleccionado.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap items-center gap-3">
                    <button @click="modal = true; modalModo = 'seleccionados'"
                            :disabled="selCount === 0 || aplicando"
                            class="px-5 py-2 bg-yellow-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-yellow-700 disabled:opacity-40 transition">
                        ✔ Actualizar seleccionados
                        (<span x-text="selCount"></span>)
                    </button>
                    <button @click="modal = true; modalModo = 'todos'"
                            :disabled="!comparacion?.con_diffs || aplicando"
                            class="px-5 py-2 bg-red-700 text-white text-sm font-semibold rounded-lg shadow hover:bg-red-800 disabled:opacity-40 transition">
                        ⚡ Actualizar todo con diferencias
                        (<span x-text="comparacion?.con_diffs ?? 0"></span>)
                    </button>
                    <div x-show="aplicando" class="flex items-center gap-2 text-xs text-gray-500 bg-yellow-50 border border-yellow-200 rounded px-3 py-1.5">
                        <svg class="animate-spin h-3.5 w-3.5 text-yellow-600 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        Actualizando en segundo plano… puede tardar unos minutos.
                    </div>
                </div>
            </div>

            {{-- ━━━ PASO 3: RESULTADO ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div x-show="resultado !== null" x-cloak
                 class="bg-white rounded-lg border border-green-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-green-50 border-b border-green-200 flex items-center justify-between">
                    <span class="font-semibold text-green-800 text-sm">✓ Actualización completada</span>
                    <span class="text-xs text-gray-400"
                          x-text="resultado ? (resultado.tiempo_ms/1000).toFixed(1)+'s' : ''"></span>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div class="flex items-center gap-4">
                        <div class="text-center px-6 py-4 bg-green-50 rounded-lg border border-green-200">
                            <div class="text-3xl font-bold text-green-700"
                                 x-text="resultado ? resultado.total.toLocaleString('es-MX') : 0"></div>
                            <div class="text-xs text-gray-500 mt-1">Registros actualizados</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
                        <template x-if="resultado">
                            <template x-for="[key, n] in Object.entries(resultado.totales ?? {})" :key="key">
                                <div class="text-center px-3 py-2 bg-gray-50 rounded border border-gray-100">
                                    <div class="font-semibold text-gray-700" x-text="n"></div>
                                    <div class="text-xs text-gray-400 mt-0.5" x-text="tablaLabel(key)"></div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>

        </div>

        {{-- ━━━ MODAL CONFIRMACIÓN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
        <div x-show="modal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             @keydown.escape.window="modal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden"
                 @click.outside="modal = false">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Confirmar actualización</h3>
                    <button @click="modal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                </div>
                <div class="px-6 py-5 space-y-3 text-sm text-gray-700">
                    <p x-show="modalModo === 'seleccionados'">
                        Se actualizarán <strong x-text="selCount"></strong> insumo(s) seleccionado(s)
                        en las tablas marcadas, usando los valores de <strong>RP como fuente</strong>.
                    </p>
                    <p x-show="modalModo === 'todos'">
                        Se actualizarán <strong>todos</strong> los insumos con diferencias
                        (<strong x-text="comparacion?.con_diffs ?? 0"></strong>) en las tablas marcadas,
                        usando los valores de <strong>RP como fuente</strong>.
                    </p>
                    <p class="text-red-600 font-medium text-xs bg-red-50 border border-red-100 rounded px-3 py-2">
                        ⚠ Esta acción sobreescribirá los campos seleccionados. No afecta cantidades ni movimientos históricos.
                    </p>
                    <p class="text-xs text-gray-500">
                        Campos: <span x-text="camposSeleccionados.join(', ')"></span>
                    </p>
                </div>
                <div class="px-6 pb-5 flex gap-3">
                    <button @click="modal = false"
                            class="flex-1 py-2 rounded border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button @click="aplicar()"
                            class="flex-1 py-2 rounded bg-red-700 text-white text-sm font-semibold hover:bg-red-800">
                        Sí, actualizar
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
    function actualizarReportes() {
        return {
            tablasSeleccionadas:  [],
            obrasSeleccionadas:   [],
            camposSeleccionados:  ['descripcion','unidad','familia','subfamilia','precio_unitario'],
            buscarObra:           '',
            obras:                [],
            obrasLoading:         true,

            camposDisponibles: [
                { key: 'descripcion',     label: 'Descripción'  },
                { key: 'unidad',          label: 'Unidad'        },
                { key: 'familia',         label: 'Familia'       },
                { key: 'subfamilia',      label: 'Subfamilia'    },
                { key: 'precio_unitario', label: 'PU / Costo'   },
            ],

            _tablaLabels: {
                salidas:             'Salidas',
                transferencias_env:  'T.Env.',
                ordenes_compra:      'OC',
                transferencias_rec:  'T.Rec.',
                finiquitadas:        'Finiq.',
            },
            _tablaColors: {
                salidas:             'bg-indigo-100 text-indigo-700',
                transferencias_env:  'bg-orange-100 text-orange-700',
                ordenes_compra:      'bg-green-100 text-green-700',
                transferencias_rec:  'bg-teal-100 text-teal-700',
                finiquitadas:        'bg-gray-100 text-gray-600',
            },

            comparando:  false,
            comparacion: null,
            filtro:      'diffs',
            sel:         {},

            aplicando:  false,
            modal:      false,
            modalModo:  null,
            resultado:  null,

            // ── Computed ─────────────────────────────────────────────────────

            get obrasFiltradas() {
                const q = this.buscarObra.toLowerCase().trim();
                return q ? this.obras.filter(o => o.nombre.toLowerCase().includes(q)) : this.obras;
            },

            get itemsFiltrados() {
                if (! this.comparacion) return [];
                if (this.filtro === 'diffs') return this.comparacion.items.filter(i => i.diffs.length > 0);
                return this.comparacion.items;
            },

            get selCount() {
                return Object.values(this.sel).filter(Boolean).length;
            },

            // ── Helpers ───────────────────────────────────────────────────────

            tablaLabel(k) { return this._tablaLabels[k] ?? k; },
            tablaColor(k) { return this._tablaColors[k] ?? 'bg-gray-100 text-gray-600'; },

            // Texto de celda local — muestra N/A si la tabla no tiene esa columna
            cv(item, campo, val, fmt) {
                if (! (item.campos_ok ?? []).includes(campo)) return 'N/A';
                if (val === null || val === undefined || val === '') return '—';
                return fmt ? fmt(val) : String(val);
            },

            // Celda coloring: s = sistema, e = erp
            cc(item, campo, lado) {
                if (! item.en_erp) return 'bg-gray-50 text-gray-400 italic';
                if (! (item.campos_ok ?? []).includes(campo)) return 'bg-gray-100 text-gray-400 italic';  // tabla no tiene esta columna
                const diff = item.diffs.includes(campo);
                if (lado === 's') return diff ? 'bg-red-50 text-red-800 font-medium'     : 'bg-green-50 text-green-800';
                else              return diff ? 'bg-blue-50 text-blue-900 font-semibold'  : 'bg-green-50 text-green-800';
            },

            // ── Métodos ───────────────────────────────────────────────────────

            async init() {
                try {
                    const r = await fetch('/admin/actualizar-reportes/obras',
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    this.obras = await r.json();
                } catch(e) { console.error('obras:', e); }
                finally   { this.obrasLoading = false; }
            },

            selDiffs() {
                if (! this.comparacion) return;
                const s = {};
                this.comparacion.items.forEach(i => {
                    if (i.diffs.length > 0 && i.en_erp) s[i.insumo_id] = true;
                });
                this.sel = { ...s };
            },

            selTodo(v) {
                if (! this.comparacion) return;
                const s = {};
                if (v) this.comparacion.items.forEach(i => { if (i.en_erp) s[i.insumo_id] = true; });
                this.sel = { ...s };
            },

            async comparar() {
                if (this.comparando) return;
                this.comparando  = true;
                this.comparacion = null;
                this.resultado   = null;
                this.sel         = {};

                const p = new URLSearchParams();
                this.tablasSeleccionadas.forEach(t => p.append('tablas[]', t));
                this.obrasSeleccionadas .forEach(o => p.append('obras[]',  o));
                this.camposSeleccionados.forEach(c => p.append('campos[]', c));

                try {
                    const r    = await fetch('/admin/actualizar-reportes/comparar?' + p,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await r.json();
                    if (data.error) { alert(data.error); return; }
                    this.comparacion = data;
                    this.filtro      = 'diffs';
                    this.selDiffs();
                } catch(e) {
                    alert('Error al comparar: ' + e.message);
                } finally {
                    this.comparando = false;
                }
            },

            async aplicar() {
                this.modal     = false;
                this.aplicando = true;
                this.resultado = null;

                const insumos = this.modalModo === 'todos'
                    ? ['todos']
                    : Object.entries(this.sel).filter(([,v]) => v).map(([k]) => k);

                if (! insumos.length) { this.aplicando = false; return; }

                try {
                    // 1) Despachar job (respuesta inmediata, no bloquea FPM)
                    const r = await fetch('/admin/actualizar-reportes/aplicar', {
                        method:  'POST',
                        headers: {
                            'Content-Type':     'application/json',
                            'X-CSRF-TOKEN':     '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            tablas:  this.tablasSeleccionadas,
                            obras:   this.obrasSeleccionadas,
                            campos:  this.camposSeleccionados,
                            insumos: insumos,
                        }),
                    });
                    const dispatch = await r.json();
                    if (dispatch.error) { alert(dispatch.error); this.aplicando = false; return; }

                    // 2) Polling hasta completado o error
                    const token = dispatch.token;
                    await this._pollEstado(token);

                } catch(e) {
                    alert('Error al aplicar: ' + e.message);
                    this.aplicando = false;
                }
            },

            async _pollEstado(token) {
                const delay = ms => new Promise(r => setTimeout(r, ms));
                for (let i = 0; i < 120; i++) {   // máx 6 min
                    await delay(3000);
                    try {
                        const r    = await fetch('/admin/actualizar-reportes/estado/' + token,
                            { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await r.json();

                        if (data.status === 'completado') {
                            this.resultado = {
                                total:     data.actualizados,
                                totales:   data.totales ?? {},
                                tiempo_ms: data.tiempo_ms,
                            };
                            this.aplicando = false;
                            return;
                        }
                        if (data.status === 'error') {
                            alert('Error en el proceso: ' + (data.error ?? 'desconocido'));
                            this.aplicando = false;
                            return;
                        }
                        // pendiente / procesando — seguir esperando
                    } catch(e) {
                        // red error temporal — seguir intentando
                    }
                }
                // timeout
                alert('El proceso tardó demasiado. Verifica en el log si se completó.');
                this.aplicando = false;
            },
        };
    }
    </script>

</x-app-layout>
