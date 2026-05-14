<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Actualización de Precio Unitario
        </h2>
    </x-slot>

    <div class="py-8" x-data="puAdmin()" x-init="cargarStats()">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- ── DESCRIPCIÓN ──────────────────────────────────────── --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg px-5 py-4 text-sm text-blue-800">
                <p class="font-semibold mb-1">¿Qué hace este módulo?</p>
                <p>Rellena el campo <strong>Precio Unitario (PU)</strong> en los registros de Entradas (OC y Manuales), Salidas, Transferencias Enviadas y Transferencias Recibidas usando el <strong>costo promedio</strong> registrado en el inventario por obra.</p>
                <p class="mt-1 opacity-75">Por defecto solo actualiza registros <em>sin PU</em> (NULL o 0). La opción <em>Forzar</em> sobreescribe también los que ya tienen PU.</p>
            </div>

            {{-- ── OPCIONES ─────────────────────────────────────────── --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-5 py-4 flex flex-wrap gap-6 items-center">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" x-model="forzado"
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm font-medium text-gray-700">Forzar actualización</span>
                    <span class="text-xs text-gray-400">(sobreescribe PU existentes)</span>
                </label>
            </div>

            {{-- ── TARJETAS DE STATS ────────────────────────────────── --}}
            <template x-if="statsLoading">
                <div class="text-center py-10 text-gray-400 text-sm">Cargando estadísticas…</div>
            </template>

            <template x-if="!statsLoading">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    {{-- Entradas OC + Manual --}}
                    <div class="bg-white rounded-lg border border-green-200 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-green-50 border-b border-green-200 flex items-center justify-between">
                            <span class="font-semibold text-green-800 text-sm">📋 Entradas</span>
                            <span class="text-xs text-green-500 font-mono" x-text="stats.entradas?.total + ' registros'"></span>
                        </div>
                        <div class="px-4 py-3 space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Con PU válido</span>
                                <span class="font-semibold text-green-700" x-text="fmt(stats.entradas?.conPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sin PU (NULL)</span>
                                <span class="font-semibold text-red-600" x-text="fmt(stats.entradas?.sinPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">PU = 0</span>
                                <span class="font-semibold text-orange-600" x-text="fmt(stats.entradas?.puCero)"></span>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 pt-1.5 mt-1">
                                <span class="text-green-700 font-medium">Actualizables ahora</span>
                                <span class="font-bold text-green-700" x-text="fmt(stats.entradas?.actualizables)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Sin coincidencia</span>
                                <span class="text-gray-500" x-text="fmt(stats.entradas?.sinCoincidencia)"></span>
                            </div>
                        </div>
                        <div class="px-4 pb-3">
                            <button @click="ejecutar('entradas')"
                                    :disabled="ejecutando"
                                    class="w-full py-1.5 px-3 text-xs font-medium rounded bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 transition">
                                Actualizar Entradas
                            </button>
                        </div>
                    </div>

                    {{-- Salidas --}}
                    <div class="bg-white rounded-lg border border-indigo-200 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-indigo-50 border-b border-indigo-200 flex items-center justify-between">
                            <span class="font-semibold text-indigo-800 text-sm">📤 Salidas</span>
                            <span class="text-xs text-indigo-500 font-mono" x-text="stats.salidas?.total + ' registros'"></span>
                        </div>
                        <div class="px-4 py-3 space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Con PU válido</span>
                                <span class="font-semibold text-green-700" x-text="fmt(stats.salidas?.conPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sin PU (NULL)</span>
                                <span class="font-semibold text-red-600" x-text="fmt(stats.salidas?.sinPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">PU = 0</span>
                                <span class="font-semibold text-orange-600" x-text="fmt(stats.salidas?.puCero)"></span>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 pt-1.5 mt-1">
                                <span class="text-indigo-700 font-medium">Actualizables ahora</span>
                                <span class="font-bold text-indigo-700" x-text="fmt(stats.salidas?.actualizables)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Sin coincidencia</span>
                                <span class="text-gray-500" x-text="fmt(stats.salidas?.sinCoincidencia)"></span>
                            </div>
                        </div>
                        <div class="px-4 pb-3">
                            <button @click="ejecutar('salidas')"
                                    :disabled="ejecutando"
                                    class="w-full py-1.5 px-3 text-xs font-medium rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition">
                                Actualizar Salidas
                            </button>
                        </div>
                    </div>

                    {{-- Transferencias Enviadas --}}
                    <div class="bg-white rounded-lg border border-orange-200 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-orange-50 border-b border-orange-200 flex items-center justify-between">
                            <span class="font-semibold text-orange-800 text-sm">📦 Trans. Enviadas</span>
                            <span class="text-xs text-orange-500 font-mono" x-text="stats.enviadas?.total + ' registros'"></span>
                        </div>
                        <div class="px-4 py-3 space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Con PU válido</span>
                                <span class="font-semibold text-green-700" x-text="fmt(stats.enviadas?.conPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sin PU (NULL)</span>
                                <span class="font-semibold text-red-600" x-text="fmt(stats.enviadas?.sinPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">PU = 0</span>
                                <span class="font-semibold text-orange-600" x-text="fmt(stats.enviadas?.puCero)"></span>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 pt-1.5 mt-1">
                                <span class="text-orange-700 font-medium">Actualizables ahora</span>
                                <span class="font-bold text-orange-700" x-text="fmt(stats.enviadas?.actualizables)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Sin coincidencia</span>
                                <span class="text-gray-500" x-text="fmt(stats.enviadas?.sinCoincidencia)"></span>
                            </div>
                        </div>
                        <div class="px-4 pb-3">
                            <button @click="ejecutar('enviadas')"
                                    :disabled="ejecutando"
                                    class="w-full py-1.5 px-3 text-xs font-medium rounded bg-orange-600 text-white hover:bg-orange-700 disabled:opacity-50 transition">
                                Actualizar Enviadas
                            </button>
                        </div>
                    </div>

                    {{-- Transferencias Recibidas --}}
                    <div class="bg-white rounded-lg border border-teal-200 shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-teal-50 border-b border-teal-200 flex items-center justify-between">
                            <span class="font-semibold text-teal-800 text-sm">📥 Trans. Recibidas</span>
                            <span class="text-xs text-teal-500 font-mono" x-text="stats.recibidas?.total + ' registros'"></span>
                        </div>
                        <div class="px-4 py-3 space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Con PU válido</span>
                                <span class="font-semibold text-green-700" x-text="fmt(stats.recibidas?.conPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Sin PU (NULL)</span>
                                <span class="font-semibold text-red-600" x-text="fmt(stats.recibidas?.sinPu)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">PU = 0</span>
                                <span class="font-semibold text-orange-600" x-text="fmt(stats.recibidas?.puCero)"></span>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 pt-1.5 mt-1">
                                <span class="text-teal-700 font-medium">Actualizables ahora</span>
                                <span class="font-bold text-teal-700" x-text="fmt(stats.recibidas?.actualizables)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Sin coincidencia</span>
                                <span class="text-gray-500" x-text="fmt(stats.recibidas?.sinCoincidencia)"></span>
                            </div>
                        </div>
                        <div class="px-4 pb-3">
                            <button @click="ejecutar('recibidas')"
                                    :disabled="ejecutando"
                                    class="w-full py-1.5 px-3 text-xs font-medium rounded bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50 transition">
                                Actualizar Recibidas
                            </button>
                        </div>
                    </div>

                </div>
            </template>

            {{-- ── BOTÓN PRINCIPAL ─────────────────────────────────── --}}
            <template x-if="!statsLoading">
                <div class="flex justify-center">
                    <button @click="confirmarTodo()"
                            :disabled="ejecutando"
                            class="px-8 py-3 bg-gray-900 text-white font-semibold rounded-lg shadow hover:bg-gray-700 disabled:opacity-50 transition text-sm">
                        <span x-show="!ejecutando">⚡ Actualizar todos los PU</span>
                        <span x-show="ejecutando">Procesando…</span>
                    </button>
                </div>
            </template>

            {{-- ── CONFIRMACIÓN ─────────────────────────────────────── --}}
            <template x-if="mostrarConfirmacion">
                <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
                        <h3 class="font-bold text-gray-900 text-lg mb-2">¿Confirmar actualización?</h3>
                        <p class="text-sm text-gray-600 mb-1">
                            Se actualizarán los PU de <strong x-text="confirmTipo === 'todos' ? 'todas las tablas' : confirmTipo"></strong>.
                        </p>
                        <p x-show="forzado" class="text-xs text-red-600 font-medium mb-3">
                            ⚠️ Modo forzado: se sobreescribirán PU existentes.
                        </p>
                        <p x-show="!forzado" class="text-xs text-gray-400 mb-3">
                            Solo se actualizarán registros sin PU o con PU = 0.
                        </p>
                        <div class="flex gap-3 mt-4">
                            <button @click="mostrarConfirmacion = false"
                                    class="flex-1 py-2 rounded border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button @click="ejecutar(confirmTipo); mostrarConfirmacion = false"
                                    class="flex-1 py-2 rounded bg-gray-900 text-white text-sm font-semibold hover:bg-gray-700">
                                Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ── RESULTADOS ───────────────────────────────────────── --}}
            <template x-if="resultados.length > 0">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-semibold text-gray-700 text-sm">Historial de ejecuciones</span>
                        <button @click="resultados = []" class="text-xs text-gray-400 hover:text-gray-600">Limpiar</button>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <template x-for="(r, i) in resultados" :key="i">
                            <div class="px-5 py-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-500 uppercase" x-text="r.label"></span>
                                    <span class="text-xs text-gray-400" x-text="r.hora + ' — ' + r.tiempo_ms + 'ms'"></span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <template x-if="r.entradas">
                                        <div class="bg-green-50 rounded px-3 py-2 text-xs">
                                            <div class="font-medium text-green-700 mb-1">Entradas</div>
                                            <div class="flex justify-between"><span class="text-gray-500">Actualizados</span><span class="font-bold text-green-700" x-text="r.entradas.actualizados"></span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Sin coincidencia</span><span x-text="r.entradas.sin_coincidencia"></span></div>
                                        </div>
                                    </template>
                                    <template x-if="r.salidas">
                                        <div class="bg-indigo-50 rounded px-3 py-2 text-xs">
                                            <div class="font-medium text-indigo-700 mb-1">Salidas</div>
                                            <div class="flex justify-between"><span class="text-gray-500">Actualizados</span><span class="font-bold text-indigo-700" x-text="r.salidas.actualizados"></span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Sin coincidencia</span><span x-text="r.salidas.sin_coincidencia"></span></div>
                                        </div>
                                    </template>
                                    <template x-if="r.enviadas">
                                        <div class="bg-orange-50 rounded px-3 py-2 text-xs">
                                            <div class="font-medium text-orange-700 mb-1">Enviadas</div>
                                            <div class="flex justify-between"><span class="text-gray-500">Actualizados</span><span class="font-bold text-orange-700" x-text="r.enviadas.actualizados"></span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Sin coincidencia</span><span x-text="r.enviadas.sin_coincidencia"></span></div>
                                        </div>
                                    </template>
                                    <template x-if="r.recibidas">
                                        <div class="bg-teal-50 rounded px-3 py-2 text-xs">
                                            <div class="font-medium text-teal-700 mb-1">Recibidas</div>
                                            <div class="flex justify-between"><span class="text-gray-500">Actualizados</span><span class="font-bold text-teal-700" x-text="r.recibidas.actualizados"></span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Sin coincidencia</span><span x-text="r.recibidas.sin_coincidencia"></span></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- ── TABLA DE CAMBIOS ──────────────────────────────────── --}}
            <template x-if="cambios.length > 0">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-semibold text-gray-700 text-sm">
                            Insumos actualizados
                            <span class="ml-2 text-xs font-normal text-gray-400" x-text="'(' + cambios.length + ' insumos)'"></span>
                        </span>
                        <button @click="cambios = []" class="text-xs text-gray-400 hover:text-gray-600">Limpiar</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-500 font-medium">Tabla</th>
                                    <th class="px-4 py-2 text-left text-gray-500 font-medium">Código</th>
                                    <th class="px-4 py-2 text-left text-gray-500 font-medium">Descripción</th>
                                    <th class="px-4 py-2 text-right text-gray-500 font-medium">PU anterior</th>
                                    <th class="px-4 py-2 text-right text-gray-500 font-medium">PU nuevo (ERP)</th>
                                    <th class="px-4 py-2 text-right text-gray-500 font-medium">Registros</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(c, i) in cambios" :key="i">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <span class="px-1.5 py-0.5 rounded text-white text-xs font-medium"
                                                  :class="{
                                                      'bg-green-600':  c.tabla === 'Entradas',
                                                      'bg-indigo-600': c.tabla === 'Salidas',
                                                      'bg-orange-500': c.tabla === 'Enviadas',
                                                      'bg-teal-600':   c.tabla === 'Recibidas',
                                                  }"
                                                  x-text="c.tabla"></span>
                                        </td>
                                        <td class="px-4 py-2 font-mono text-gray-700" x-text="c.insumo_id"></td>
                                        <td class="px-4 py-2 text-gray-600 max-w-xs truncate" x-text="c.descripcion"></td>
                                        <td class="px-4 py-2 text-right">
                                            <template x-if="c.pu_anterior.length === 0">
                                                <span class="text-gray-400">—</span>
                                            </template>
                                            <template x-if="c.pu_anterior.length > 0">
                                                <span>
                                                    <template x-for="(p, j) in c.pu_anterior" :key="j">
                                                        <span :class="p != null && p !== c.pu_nuevo ? 'text-red-600 line-through' : 'text-gray-500'"
                                                              x-text="p != null ? '$'+Number(p).toLocaleString('es-MX',{minimumFractionDigits:2}) : '—'"></span>
                                                        <span x-show="j < c.pu_anterior.length - 1" class="text-gray-300 mx-0.5">/</span>
                                                    </template>
                                                </span>
                                            </template>
                                        </td>
                                        <td class="px-4 py-2 text-right font-semibold text-green-700"
                                            x-text="fmtPeso(c.pu_nuevo)"></td>
                                        <td class="px-4 py-2 text-right text-gray-500" x-text="c.registros"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SECCIÓN: ACTUALIZACIÓN MASIVA DE INSUMOS
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="py-8" x-data="masivoAdmin()" x-init="cargarObras()">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Título --}}
            <div class="flex items-center gap-3">
                <div class="h-px flex-1 bg-gray-200"></div>
                <h3 class="text-base font-bold text-gray-700 whitespace-nowrap">Actualización Masiva de Insumos</h3>
                <div class="h-px flex-1 bg-gray-200"></div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg px-5 py-4 text-sm text-blue-800">
                <p class="font-semibold mb-1">¿Qué hace este módulo?</p>
                <p>Sincroniza campos de los insumos en las obras seleccionadas usando el <strong>catálogo ERP</strong> como fuente de verdad. Solo actualiza los campos seleccionados y solo en las obras elegidas. La relación se hace por <strong>insumo_id</strong>. No crea registros nuevos.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- ── Panel izquierdo: Campos ─────────────────────────── --}}
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-semibold text-gray-700 text-sm">Campos a actualizar</span>
                        <div class="flex gap-2">
                            <button @click="selectAllCampos(true)"  class="text-xs text-indigo-600 hover:underline">Todos</button>
                            <span class="text-gray-300">|</span>
                            <button @click="selectAllCampos(false)" class="text-xs text-gray-400 hover:underline">Ninguno</button>
                        </div>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        <template x-for="campo in camposDisponibles" :key="campo.key">
                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                <input type="checkbox" :value="campo.key" x-model="camposSeleccionados"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       style="width:16px;height:16px;appearance:auto;">
                                <div>
                                    <span class="text-sm font-medium text-gray-800" x-text="campo.label"></span>
                                    <span class="block text-xs text-gray-400" x-text="campo.hint"></span>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- ── Panel derecho: Obras ─────────────────────────────── --}}
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-semibold text-gray-700 text-sm">
                            Obras
                            <span class="text-xs text-gray-400 font-normal" x-text="'(' + obrasSeleccionadas.length + ' selec.)'"></span>
                        </span>
                        <div class="flex gap-2">
                            <button @click="selectAllObras(true)"  class="text-xs text-indigo-600 hover:underline">Todas</button>
                            <span class="text-gray-300">|</span>
                            <button @click="selectAllObras(false)" class="text-xs text-gray-400 hover:underline">Ninguna</button>
                        </div>
                    </div>
                    <div class="px-3 py-2 border-b border-gray-100">
                        <input type="text" x-model="buscarObra" placeholder="Buscar obra…"
                               class="w-full text-sm border border-gray-200 rounded px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    </div>
                    <div class="px-5 py-3 space-y-2 max-h-64 overflow-y-auto">
                        <template x-if="obrasLoading">
                            <p class="text-xs text-gray-400">Cargando obras…</p>
                        </template>
                        <template x-for="obra in obrasFiltradas" :key="obra.id">
                            <label class="flex items-center gap-3 cursor-pointer select-none">
                                <input type="checkbox" :value="obra.id" x-model="obrasSeleccionadas"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       style="width:16px;height:16px;appearance:auto;">
                                <span class="text-sm text-gray-700" x-text="obra.nombre"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ── Botón Analizar ───────────────────────────────────────── --}}
            <div class="flex justify-center">
                <button @click="analizar()"
                        :disabled="analizando || camposSeleccionados.length === 0 || obrasSeleccionadas.length === 0"
                        class="px-8 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow hover:bg-indigo-700 disabled:opacity-40 transition text-sm">
                    <span x-show="!analizando">🔍 Analizar discrepancias</span>
                    <span x-show="analizando">Analizando…</span>
                </button>
            </div>

            {{-- ── Tabla de análisis ────────────────────────────────────── --}}
            <template x-if="analisis !== null">
                <div class="space-y-4">

                    {{-- Resumen --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 text-center">
                            <div class="text-2xl font-bold text-gray-800" x-text="analisis.total.toLocaleString('es-MX')"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Registros en scope</div>
                        </div>
                        <div class="bg-white border border-red-200 rounded-lg px-4 py-3 text-center">
                            <div class="text-2xl font-bold text-red-600" x-text="analisis.discrepancias.toLocaleString('es-MX')"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Con discrepancias</div>
                        </div>
                        <div class="bg-white border border-green-200 rounded-lg px-4 py-3 text-center">
                            <div class="text-2xl font-bold text-green-600" x-text="(analisis.total - analisis.discrepancias).toLocaleString('es-MX')"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Ya sincronizados</div>
                        </div>
                    </div>

                    {{-- Filtro tabla --}}
                    <div class="flex items-center justify-between">
                        <div class="flex gap-2">
                            <button @click="filtroTabla = 'todos'"
                                    :class="filtroTabla==='todos' ? 'bg-gray-800 text-white' : 'bg-white text-gray-600 border border-gray-300'"
                                    class="px-3 py-1 text-xs rounded-full font-medium">
                                Todos (<span x-text="analisis.total"></span>)
                            </button>
                            <button @click="filtroTabla = 'discrepancias'"
                                    :class="filtroTabla==='discrepancias' ? 'bg-red-600 text-white' : 'bg-white text-gray-600 border border-gray-300'"
                                    class="px-3 py-1 text-xs rounded-full font-medium">
                                Solo discrepancias (<span x-text="analisis.discrepancias"></span>)
                            </button>
                        </div>
                        <span class="text-xs text-gray-400" x-show="rowsFiltradas.length < analisis.total"
                              x-text="'Mostrando ' + rowsFiltradas.length + ' de ' + analisis.total"></span>
                    </div>

                    {{-- Tabla --}}
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <div class="overflow-x-auto max-h-[480px] overflow-y-auto">
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-gray-500 font-medium">Obra</th>
                                        <th class="px-3 py-2 text-left text-gray-500 font-medium">Código</th>
                                        <template x-for="campo in camposDisponibles.filter(c => camposSeleccionados.includes(c.key))" :key="campo.key">
                                            <th class="px-3 py-2 text-left text-gray-500 font-medium" x-text="campo.label + ' actual'"></th>
                                            <th class="px-3 py-2 text-left text-gray-500 font-medium" x-text="campo.label + ' nuevo'"></th>
                                        </template>
                                        <th class="px-3 py-2 text-center text-gray-500 font-medium">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(row, i) in rowsFiltradas.slice(0, 300)" :key="row.inv_id">
                                        <tr :class="row.discrepancia ? 'bg-red-50' : ''">
                                            <td class="px-3 py-2 text-gray-700 max-w-[140px] truncate" x-text="row.obra_nombre"></td>
                                            <td class="px-3 py-2 font-mono text-gray-700" x-text="row.insumo_id"></td>
                                            <template x-for="campo in camposDisponibles.filter(c => camposSeleccionados.includes(c.key))" :key="campo.key">
                                                <td class="px-3 py-2 max-w-[160px] truncate"
                                                    :class="row.campos[campo.key]?.diferente ? 'text-red-700' : 'text-gray-500'"
                                                    :title="row.campos[campo.key]?.actual"
                                                    x-text="row.campos[campo.key]?.actual || '—'"></td>
                                                <td class="px-3 py-2 max-w-[160px] truncate"
                                                    :class="row.campos[campo.key]?.diferente ? 'text-green-700 font-medium' : 'text-gray-400'"
                                                    :title="row.campos[campo.key]?.nuevo"
                                                    x-text="row.campos[campo.key]?.nuevo || '—'"></td>
                                            </template>
                                            <td class="px-3 py-2 text-center">
                                                <span x-show="row.discrepancia"
                                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                                    ⚠ Discrepancia
                                                </span>
                                                <span x-show="!row.discrepancia"
                                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    ✓ OK
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div x-show="rowsFiltradas.length > 300"
                             class="px-4 py-2 text-xs text-center text-gray-400 border-t border-gray-100">
                            Se muestran los primeros 300 registros. Aplica el filtro "Solo discrepancias" para ver más.
                        </div>
                    </div>

                    {{-- Botón confirmar --}}
                    <div x-show="analisis.total > 0" class="flex justify-center">
                        <button @click="confirmarEjecucion()"
                                :disabled="ejecutando"
                                class="px-8 py-3 bg-gray-900 text-white font-semibold rounded-lg shadow hover:bg-gray-700 disabled:opacity-40 transition text-sm">
                            <span x-show="!ejecutando">
                                ⚡ Actualizar <span x-text="analisis.total.toLocaleString('es-MX')"></span> registros en
                                <span x-text="obrasSeleccionadas.length"></span> obras
                            </span>
                            <span x-show="ejecutando">Actualizando…</span>
                        </button>
                    </div>
                </div>
            </template>

            {{-- ── Modal confirmación ───────────────────────────────────── --}}
            <template x-if="modalConfirmar">
                <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
                        <h3 class="font-bold text-gray-900 text-lg mb-2">¿Confirmar actualización masiva?</h3>
                        <div class="text-sm text-gray-600 space-y-1 mb-4">
                            <p>Se actualizarán <strong x-text="analisis?.total?.toLocaleString('es-MX')"></strong> registros en <strong x-text="obrasSeleccionadas.length"></strong> obras.</p>
                            <p>Campos: <strong x-text="camposSeleccionados.join(', ')"></strong></p>
                            <p class="text-red-600 font-medium" x-show="analisis?.discrepancias > 0">
                                ⚠ <span x-text="analisis.discrepancias"></span> registros tienen discrepancias que se corregirán.
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button @click="modalConfirmar = false"
                                    class="flex-1 py-2 rounded border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button @click="ejecutar()"
                                    class="flex-1 py-2 rounded bg-gray-900 text-white text-sm font-semibold hover:bg-gray-700">
                                Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ── Resultado ────────────────────────────────────────────── --}}
            <template x-if="resultado !== null">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <span class="font-semibold text-gray-700 text-sm">Resultado de la actualización</span>
                    </div>
                    <div class="px-5 py-4 grid grid-cols-3 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-green-700" x-text="resultado.actualizados.toLocaleString('es-MX')"></div>
                            <div class="text-xs text-gray-500">Registros actualizados</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-500" x-text="resultado.omitidos.toLocaleString('es-MX')"></div>
                            <div class="text-xs text-gray-500">Omitidos (sin ERP)</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-400" x-text="resultado.tiempo_ms + 'ms'"></div>
                            <div class="text-xs text-gray-500">Tiempo</div>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════
         SECCIÓN: OVERRIDE MANUAL DE PU
    ══════════════════════════════════════════════════════════════════════════ --}}
    <div class="py-8" x-data="manualPu()" x-init="init()">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center gap-3">
                <div class="h-px flex-1 bg-gray-200"></div>
                <h3 class="text-base font-bold text-gray-700 whitespace-nowrap">Override Manual de PU</h3>
                <div class="h-px flex-1 bg-gray-200"></div>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-5 py-4 text-sm text-yellow-800">
                <p class="font-semibold mb-1">Sobreescritura directa de Precio Unitario</p>
                <p>Aplica los precios indicados a <strong>todos</strong> los registros de Entradas, Salidas, Transferencias Enviadas y Recibidas que coincidan con cada código de insumo. El JSON debe tener el formato <code class="bg-yellow-100 px-1 rounded">"CODIGO": precio</code>.</p>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <span class="font-semibold text-gray-700 text-sm">Precios a aplicar (JSON)</span>
                    <button @click="resetearPrecios()" class="text-xs text-indigo-500 hover:text-indigo-700">Restaurar predeterminados</button>
                </div>
                <div class="px-5 py-4">
                    <textarea x-model="preciosJson" rows="14"
                              class="w-full font-mono text-xs border border-gray-200 rounded p-3 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-y"
                              :class="jsonError ? 'border-red-400 bg-red-50' : ''"></textarea>
                    <p x-show="jsonError" class="text-xs text-red-600 mt-1" x-text="jsonError"></p>
                </div>
                <div class="px-5 pb-4 flex items-center gap-4">
                    <button @click="ejecutar()"
                            :disabled="ejecutando || !!jsonError"
                            class="px-6 py-2 bg-yellow-600 text-white text-sm font-semibold rounded-lg shadow hover:bg-yellow-700 disabled:opacity-50 transition">
                        <span x-show="!ejecutando">⚡ Aplicar override manual</span>
                        <span x-show="ejecutando">Aplicando…</span>
                    </button>
                    <span x-show="ejecutando" class="text-xs text-gray-400">Actualizando Entradas, Salidas y Transferencias…</span>
                </div>
            </div>

            {{-- Resultado --}}
            <template x-if="resultado !== null">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-semibold text-gray-700 text-sm">Resultado del override</span>
                        <span class="text-xs text-gray-400" x-text="resultado.tiempo_ms + 'ms'"></span>
                    </div>
                    <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div class="bg-green-50 rounded-lg px-3 py-3">
                            <div class="text-xl font-bold text-green-700" x-text="resultado.entradas?.actualizados ?? 0"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Entradas</div>
                        </div>
                        <div class="bg-indigo-50 rounded-lg px-3 py-3">
                            <div class="text-xl font-bold text-indigo-700" x-text="resultado.salidas?.actualizados ?? 0"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Salidas</div>
                        </div>
                        <div class="bg-orange-50 rounded-lg px-3 py-3">
                            <div class="text-xl font-bold text-orange-700" x-text="resultado.enviadas?.actualizados ?? 0"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Enviadas</div>
                        </div>
                        <div class="bg-teal-50 rounded-lg px-3 py-3">
                            <div class="text-xl font-bold text-teal-700" x-text="resultado.recibidas?.actualizados ?? 0"></div>
                            <div class="text-xs text-gray-500 mt-0.5">Recibidas</div>
                        </div>
                    </div>

                    <template x-if="cambios.length > 0">
                        <div class="border-t border-gray-100">
                            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-gray-500 font-medium">Tabla</th>
                                            <th class="px-4 py-2 text-left text-gray-500 font-medium">Código</th>
                                            <th class="px-4 py-2 text-left text-gray-500 font-medium">Descripción</th>
                                            <th class="px-4 py-2 text-right text-gray-500 font-medium">PU anterior</th>
                                            <th class="px-4 py-2 text-right text-gray-500 font-medium">PU nuevo</th>
                                            <th class="px-4 py-2 text-right text-gray-500 font-medium">Regs.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(c, i) in cambios" :key="i">
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2">
                                                    <span class="px-1.5 py-0.5 rounded text-white text-xs font-medium"
                                                          :class="{
                                                              'bg-green-600':  c.tabla === 'Entradas',
                                                              'bg-indigo-600': c.tabla === 'Salidas',
                                                              'bg-orange-500': c.tabla === 'Enviadas',
                                                              'bg-teal-600':   c.tabla === 'Recibidas',
                                                          }"
                                                          x-text="c.tabla"></span>
                                                </td>
                                                <td class="px-4 py-2 font-mono text-gray-700" x-text="c.insumo_id"></td>
                                                <td class="px-4 py-2 text-gray-600 max-w-xs truncate" x-text="c.descripcion"></td>
                                                <td class="px-4 py-2 text-right text-red-500 line-through font-mono"
                                                    x-text="c.pu_anterior.length ? '$'+Number(c.pu_anterior[0]).toLocaleString('es-MX',{minimumFractionDigits:2}) : '—'"></td>
                                                <td class="px-4 py-2 text-right font-semibold text-green-700 font-mono"
                                                    x-text="'$'+Number(c.pu_nuevo).toLocaleString('es-MX',{minimumFractionDigits:2})"></td>
                                                <td class="px-4 py-2 text-right text-gray-500" x-text="c.registros"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

        </div>
    </div>

    <script>
    function manualPu() {
        const PRECIOS_DEFAULT = {
            "13ON-EQM-0064":  4298.99,
            "13ON-HTA-00082": 4298.99,
            "60SH-EPP-0008":   441.52,
            "60SH-EPP-0029":   195.00,
            "06ON-MAD-0004":   120.00,
            "09ON-ADI-0002": 17724.80,
            "09ON-CEM-0002":  4799.02,
            "13ON-HTA-0005":   319.00,
            "16ON-FRR-0018":   127.00,
            "16ON-FRR-0028":  1830.13,
            "16ON-FRR-0052":  4350.00
        };

        return {
            preciosJson: '',
            jsonError:   '',
            ejecutando:  false,
            resultado:   null,
            cambios:     [],

            init() {
                this.resetearPrecios();
                this.$watch('preciosJson', () => this.validarJson());
            },

            resetearPrecios() {
                this.preciosJson = JSON.stringify(PRECIOS_DEFAULT, null, 4);
                this.jsonError   = '';
            },

            validarJson() {
                try {
                    const obj = JSON.parse(this.preciosJson);
                    if (typeof obj !== 'object' || Array.isArray(obj)) {
                        this.jsonError = 'Debe ser un objeto JSON { "CODIGO": precio, … }';
                    } else {
                        this.jsonError = '';
                    }
                } catch (e) {
                    this.jsonError = 'JSON inválido: ' + e.message;
                }
            },

            async ejecutar() {
                this.validarJson();
                if (this.jsonError) return;
                const precios = JSON.parse(this.preciosJson);
                this.ejecutando = true;
                this.resultado  = null;
                this.cambios    = [];
                try {
                    const r = await fetchConCsrf('{{ route('admin.actualizar-pu.manual') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type':  'application/json',
                            'X-CSRF-TOKEN':  '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ precios }),
                    });
                    const data = await r.json();
                    if (data.error) { alert(data.error); return; }
                    this.resultado = data;
                    this.cambios   = data.cambios ?? [];
                } catch(e) {
                    alert('Error: ' + e.message);
                } finally {
                    this.ejecutando = false;
                }
            },
        };
    }
    </script>

    <script>
    function puAdmin() {
        return {
            stats:              {},
            statsLoading:       true,
            forzado:            true,
            ejecutando:         false,
            mostrarConfirmacion:false,
            confirmTipo:        'todos',
            resultados:         [],
            cambios:            [],

            fmt(v) { return v !== undefined ? v.toLocaleString('es-MX') : '—'; },
            fmtPeso(v) { return v != null ? '$' + Number(v).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—'; },

            async cargarStats() {
                this.statsLoading = true;
                try {
                    const r = await fetchConCsrf('{{ route('admin.actualizar-pu.stats') }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.stats = await r.json();
                } catch(e) {
                    console.error(e);
                } finally {
                    this.statsLoading = false;
                }
            },

            confirmarTodo() {
                this.confirmTipo = 'todos';
                this.mostrarConfirmacion = true;
            },

            async ejecutar(tipo) {
                this.ejecutando = true;
                const label = { todos: 'Todos', entradas: 'Entradas', salidas: 'Salidas', enviadas: 'Enviadas', recibidas: 'Recibidas' }[tipo] || tipo;
                try {
                    const r = await fetchConCsrf('{{ route('admin.actualizar-pu.run') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ tipo, forzado: this.forzado }),
                    });
                    const data = await r.json();
                    this.resultados.unshift({
                        label,
                        hora:      new Date().toLocaleTimeString('es-MX'),
                        tiempo_ms: data.tiempo_ms ?? '—',
                        entradas:  data.entradas,
                        salidas:   data.salidas,
                        enviadas:  data.enviadas,
                        recibidas: data.recibidas,
                    });
                    if (Array.isArray(data.cambios) && data.cambios.length > 0) {
                        this.cambios = data.cambios.concat(this.cambios);
                    }
                    await this.cargarStats();
                } catch(e) {
                    alert('Error: ' + e.message);
                } finally {
                    this.ejecutando = false;
                }
            },
        };
    }

    function masivoAdmin() {
        return {
            // Catálogo de campos
            camposDisponibles: [
                { key: 'descripcion',    label: 'Descripción',   hint: 'Nombre largo del insumo' },
                { key: 'familia',        label: 'Familia',        hint: 'Familia principal ERP' },
                { key: 'subfamilia',     label: 'Subfamilia',     hint: 'Subfamilia ERP' },
                { key: 'unidad',         label: 'Unidad',         hint: 'Unidad de medida ERP' },
                { key: 'costo_promedio', label: 'PU / Costo',     hint: 'Precio unitario del catálogo ERP' },
            ],
            camposSeleccionados: [],

            // Obras
            obras:             [],
            obrasLoading:      true,
            obrasSeleccionadas:[],
            buscarObra:        '',

            // Estado
            analizando:    false,
            ejecutando:    false,
            analisis:      null,
            filtroTabla:   'todos',
            modalConfirmar:false,
            resultado:     null,

            get obrasFiltradas() {
                const q = this.buscarObra.toLowerCase().trim();
                return q ? this.obras.filter(o => o.nombre.toLowerCase().includes(q)) : this.obras;
            },

            get rowsFiltradas() {
                if (!this.analisis) return [];
                return this.filtroTabla === 'discrepancias'
                    ? this.analisis.rows.filter(r => r.discrepancia)
                    : this.analisis.rows;
            },

            selectAllCampos(v) {
                this.camposSeleccionados = v ? this.camposDisponibles.map(c => c.key) : [];
            },

            selectAllObras(v) {
                this.obrasSeleccionadas = v ? this.obras.map(o => o.id) : [];
            },

            async cargarObras() {
                try {
                    const r = await fetchConCsrf('{{ route('admin.masivo.obras') }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.obras = await r.json();
                } catch(e) {
                    console.error(e);
                } finally {
                    this.obrasLoading = false;
                }
            },

            async analizar() {
                if (!this.camposSeleccionados.length || !this.obrasSeleccionadas.length) return;
                this.analizando = true;
                this.analisis   = null;
                this.resultado  = null;
                try {
                    const params = new URLSearchParams();
                    this.camposSeleccionados.forEach(c => params.append('campos[]', c));
                    this.obrasSeleccionadas.forEach(o => params.append('obras[]', o));
                    const r = await fetchConCsrf('{{ route('admin.masivo.analizar') }}?' + params, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    this.analisis = await r.json();
                } catch(e) {
                    alert('Error al analizar: ' + e.message);
                } finally {
                    this.analizando = false;
                }
            },

            confirmarEjecucion() {
                this.modalConfirmar = true;
            },

            async ejecutar() {
                this.modalConfirmar = false;
                this.ejecutando     = true;
                this.resultado      = null;
                try {
                    const r = await fetchConCsrf('{{ route('admin.masivo.ejecutar') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            campos: this.camposSeleccionados,
                            obras:  this.obrasSeleccionadas,
                        }),
                    });
                    const data = await r.json();
                    if (data.error) { alert(data.error); return; }
                    this.resultado = data;
                    this.analisis  = null;
                } catch(e) {
                    alert('Error al ejecutar: ' + e.message);
                } finally {
                    this.ejecutando = false;
                }
            },
        };
    }
    </script>
</x-app-layout>
