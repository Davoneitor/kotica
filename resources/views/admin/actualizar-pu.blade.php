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

        </div>
    </div>

    <script>
    function puAdmin() {
        return {
            stats:              {},
            statsLoading:       true,
            forzado:            false,
            ejecutando:         false,
            mostrarConfirmacion:false,
            confirmTipo:        'todos',
            resultados:         [],

            fmt(v) { return v !== undefined ? v.toLocaleString('es-MX') : '—'; },

            async cargarStats() {
                this.statsLoading = true;
                try {
                    const r = await fetch('{{ route('admin.actualizar-pu.stats') }}', {
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
                    const r = await fetch('{{ route('admin.actualizar-pu.run') }}', {
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
                        salidas:   data.salidas,
                        enviadas:  data.enviadas,
                        recibidas: data.recibidas,
                    });
                    await this.cargarStats();
                } catch(e) {
                    alert('Error: ' + e.message);
                } finally {
                    this.ejecutando = false;
                }
            },
        };
    }
    </script>
</x-app-layout>
