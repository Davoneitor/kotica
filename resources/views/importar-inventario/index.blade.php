<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Importar Inventario
        </h2>
    </x-slot>

    <div class="py-8 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8"
         x-data="importarInventario()"
         x-init="init()">

        {{-- ── Stepper ──────────────────────────────────────────────── --}}
        <div class="mb-8">
            <div class="flex items-center gap-0">
                @php
                $steps = [
                    1 => 'Obra',
                    2 => 'Archivo',
                    3 => 'Columnas',
                    4 => 'Validar',
                    5 => 'Confirmar',
                    6 => 'Resultado',
                ];
                @endphp
                @foreach($steps as $n => $label)
                <div class="flex items-center {{ $loop->last ? '' : 'flex-1' }}">
                    <div class="flex flex-col items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors"
                             :class="{
                                 'bg-indigo-600 text-white':   paso === {{ $n }},
                                 'bg-indigo-100 text-indigo-600': paso > {{ $n }},
                                 'bg-gray-100 text-gray-400':  paso < {{ $n }},
                             }">
                            <template x-if="paso > {{ $n }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </template>
                            <template x-if="paso <= {{ $n }}">
                                <span>{{ $n }}</span>
                            </template>
                        </div>
                        <span class="mt-1 text-[10px] font-medium hidden sm:block"
                              :class="{
                                  'text-indigo-600': paso >= {{ $n }},
                                  'text-gray-400':   paso < {{ $n }},
                              }">{{ $label }}</span>
                    </div>
                    @if(!$loop->last)
                    <div class="flex-1 h-px mx-2 transition-colors"
                         :class="paso > {{ $n }} ? 'bg-indigo-300' : 'bg-gray-200'">
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- ── PASO 1: Seleccionar Obra ─────────────────────────────── --}}
        <div x-show="paso === 1" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-1">Selecciona la obra de destino</h3>
                <p class="text-sm text-gray-500 mb-5">El inventario importado se asignará a la obra que selecciones.</p>

                <div class="mb-4">
                    <input type="text"
                           x-model="busquedaObra"
                           placeholder="Buscar obra..."
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                </div>

                <div class="max-h-72 overflow-y-auto border border-gray-100 rounded-xl divide-y divide-gray-50">
                    <template x-for="obra in obrasFiltradas" :key="obra.id">
                        <button type="button"
                                @click="seleccionarObra(obra)"
                                class="w-full text-left px-4 py-3 flex items-center gap-3 hover:bg-indigo-50 transition-colors"
                                :class="obraSeleccionada?.id === obra.id ? 'bg-indigo-50' : 'bg-white'">
                            <span class="w-4 h-4 flex items-center justify-center shrink-0">
                                <svg x-show="obraSeleccionada?.id === obra.id"
                                     class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </span>
                            <span x-text="obra.nombre"
                                  class="text-sm"
                                  :class="obraSeleccionada?.id === obra.id ? 'font-semibold text-indigo-700' : 'text-gray-700'"></span>
                        </button>
                    </template>
                    <template x-if="obrasFiltradas.length === 0">
                        <div class="px-4 py-6 text-center text-sm text-gray-400">No se encontraron obras</div>
                    </template>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button"
                            @click="paso = 2"
                            :disabled="!obraSeleccionada"
                            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors"
                            :class="obraSeleccionada
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        Continuar
                    </button>
                </div>
            </div>
        </div>

        {{-- ── PASO 2: Subir archivo ────────────────────────────────── --}}
        <div x-show="paso === 2" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">

                <div class="flex items-center gap-2 mb-5">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Obra:</span>
                    <span class="text-sm font-medium text-gray-800" x-text="obraSeleccionada?.nombre"></span>
                </div>

                <h3 class="text-base font-semibold text-gray-800 mb-1">Sube el archivo Excel</h3>
                <p class="text-sm text-gray-500 mb-5">
                    Formatos aceptados: <strong>.xlsx</strong>, <strong>.xls</strong>, <strong>.csv</strong> — máx. 20 MB.
                    La primera fila debe contener los nombres de las columnas.
                </p>

                <div class="mb-4">
                    <a href="{{ route('admin.importar-inventario.plantilla') }}"
                       class="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 underline">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        Descargar plantilla de ejemplo
                    </a>
                </div>

                <label class="block w-full cursor-pointer">
                    <div class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center hover:border-indigo-300 transition-colors"
                         :class="archivo ? 'border-indigo-300 bg-indigo-50' : ''">
                        <svg class="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                        </svg>
                        <template x-if="!archivo">
                            <div>
                                <p class="text-sm text-gray-500">Arrastra tu archivo aquí o <span class="text-indigo-600 font-medium">haz clic para seleccionar</span></p>
                            </div>
                        </template>
                        <template x-if="archivo">
                            <div>
                                <p class="text-sm font-medium text-indigo-700" x-text="archivo.name"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="(archivo.size / 1024).toFixed(1) + ' KB'"></p>
                            </div>
                        </template>
                    </div>
                    <input type="file"
                           accept=".xlsx,.xls,.csv"
                           class="hidden"
                           @change="onFileChange($event)">
                </label>

                <div x-show="errorArchivo" class="mt-3 text-sm text-red-600" x-text="errorArchivo"></div>

                <div class="mt-6 flex justify-between">
                    <button type="button" @click="paso = 1" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                        ← Atrás
                    </button>
                    <button type="button"
                            @click="analizarArchivo()"
                            :disabled="!archivo || cargando"
                            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors"
                            :class="archivo && !cargando
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        <span x-show="!cargando">Analizar archivo</span>
                        <span x-show="cargando">Analizando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── PASO 3: Mapear columnas ──────────────────────────────── --}}
        <div x-show="paso === 3" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">

                <div class="flex items-center gap-2 mb-5">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Obra:</span>
                    <span class="text-sm font-medium text-gray-800" x-text="obraSeleccionada?.nombre"></span>
                    <span class="text-gray-300">·</span>
                    <span class="text-xs text-gray-500" x-text="totalFilas + ' filas detectadas'"></span>
                </div>

                <h3 class="text-base font-semibold text-gray-800 mb-1">Mapea las columnas</h3>
                <p class="text-sm text-gray-500 mb-5">Indica qué columna de tu archivo corresponde a cada campo del sistema.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <template x-for="campo in camposMapeo" :key="campo.key">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">
                                <span x-text="campo.label"></span>
                                <span x-show="campo.required" class="text-red-500 ml-0.5">*</span>
                            </label>
                            <select x-model="mapeo[campo.key]"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                                <template x-if="!campo.required">
                                    <option value="">— No incluido —</option>
                                </template>
                                <template x-for="(col, idx) in columnas" :key="idx">
                                    <option :value="idx" x-text="col"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>

                {{-- Preview --}}
                <div x-show="preview.length > 0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">Vista previa (primeras 6 filas)</p>
                    <div class="overflow-x-auto rounded-xl border border-gray-100">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <template x-for="(col, i) in columnas" :key="i">
                                        <th class="px-3 py-2 text-left font-semibold text-gray-500 whitespace-nowrap" x-text="col"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="(row, ri) in preview" :key="ri">
                                    <tr :class="ri % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">
                                        <template x-for="(cell, ci) in row" :key="ci">
                                            <td class="px-3 py-1.5 text-gray-600 whitespace-nowrap max-w-[140px] truncate" x-text="cell ?? ''"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 flex justify-between">
                    <button type="button" @click="paso = 2" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                        ← Atrás
                    </button>
                    <button type="button"
                            @click="validarArchivo()"
                            :disabled="!mapeoValido() || cargando"
                            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors"
                            :class="mapeoValido() && !cargando
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        <span x-show="!cargando">Validar →</span>
                        <span x-show="cargando">Validando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── PASO 4: Validación + Conflictos ─────────────────────── --}}
        <div x-show="paso === 4" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">

                <div class="flex flex-wrap items-center gap-2 mb-5">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Obra:</span>
                    <span class="text-sm font-medium text-gray-800" x-text="obraSeleccionada?.nombre"></span>
                </div>

                <h3 class="text-base font-semibold text-gray-800 mb-4">Revisión de filas</h3>

                {{-- Resumen contadores --}}
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-5">
                    <div class="text-center px-2 py-2 rounded-xl bg-gray-50 border border-gray-100">
                        <div class="text-lg font-bold text-gray-700" x-text="counts.total ?? 0"></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Total</div>
                    </div>
                    <div class="text-center px-2 py-2 rounded-xl bg-green-50 border border-green-100">
                        <div class="text-lg font-bold text-green-700" x-text="counts.ok ?? 0"></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-green-500">Correctas</div>
                    </div>
                    <div class="text-center px-2 py-2 rounded-xl bg-orange-50 border border-orange-100">
                        <div class="text-lg font-bold text-orange-600" x-text="counts.conflicto ?? 0"></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-orange-400">Conflictos</div>
                    </div>
                    <div class="text-center px-2 py-2 rounded-xl bg-yellow-50 border border-yellow-100">
                        <div class="text-lg font-bold text-yellow-700" x-text="counts.advertencia ?? 0"></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-yellow-500">Advertencias</div>
                    </div>
                    <div class="text-center px-2 py-2 rounded-xl bg-red-50 border border-red-100">
                        <div class="text-lg font-bold text-red-700" x-text="counts.error ?? 0"></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-red-400">Errores</div>
                    </div>
                    <div class="text-center px-2 py-2 rounded-xl bg-purple-50 border border-purple-100">
                        <div class="text-lg font-bold text-purple-700" x-text="counts.duplicado ?? 0"></div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-purple-400">Duplicados</div>
                    </div>
                </div>

                {{-- Resolución global para conflictos --}}
                <div x-show="(counts.conflicto ?? 0) > 0" class="mb-5 p-4 rounded-xl bg-orange-50 border border-orange-200">
                    <p class="text-sm font-semibold text-orange-800 mb-3">
                        <svg class="inline w-4 h-4 mr-1 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                        </svg>
                        Hay insumos que ya existen en el inventario de esta obra. ¿Qué hacer con ellos?
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <template x-for="opt in conflictOpts" :key="opt.value">
                            <button type="button"
                                    @click="conflictResolution = opt.value"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                                    :class="conflictResolution === opt.value
                                        ? 'bg-orange-600 text-white border-orange-600'
                                        : 'bg-white text-orange-700 border-orange-300 hover:bg-orange-100'">
                                <span x-text="opt.label"></span>
                            </button>
                        </template>
                    </div>
                    <p class="text-xs text-orange-600" x-text="conflictOptDesc[conflictResolution] ?? ''"></p>
                </div>

                {{-- Filtro de filas --}}
                <div class="flex flex-wrap gap-2 mb-3">
                    <template x-for="f in filtrosVal" :key="f.value">
                        <button type="button"
                                @click="filtroVal = f.value"
                                class="px-3 py-1 rounded-full text-xs font-medium border transition-colors"
                                :class="filtroVal === f.value
                                    ? 'bg-gray-800 text-white border-gray-800'
                                    : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
                            <span x-text="f.label"></span>
                        </button>
                    </template>
                </div>

                {{-- Tabla de resultados --}}
                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Fila</th>
                                <th class="px-3 py-2 text-left font-semibold">Código</th>
                                <th class="px-3 py-2 text-left font-semibold">Descripción ERP</th>
                                <th class="px-3 py-2 text-right font-semibold">Cant. Excel</th>
                                <th class="px-3 py-2 text-right font-semibold">Cant. Actual</th>
                                <th class="px-3 py-2 text-left font-semibold">Estado</th>
                                <th class="px-3 py-2 text-left font-semibold">Errores / Notas</th>
                                <th class="px-3 py-2 text-left font-semibold">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="(r, i) in resultadosFiltrados" :key="r.fila">
                                <tr :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">
                                    <td class="px-3 py-2 text-gray-400" x-text="r.fila"></td>
                                    <td class="px-3 py-2 font-mono text-gray-700" x-text="r.codigo"></td>
                                    <td class="px-3 py-2 text-gray-600 max-w-[180px] truncate" x-text="r.descripcion_erp ?? '—'"></td>
                                    <td class="px-3 py-2 text-right text-gray-700" x-text="r.cantidad ?? '—'"></td>
                                    <td class="px-3 py-2 text-right"
                                        :class="r.conflicto ? 'text-orange-600 font-semibold' : 'text-gray-400'"
                                        x-text="r.conflicto ? r.cantidad_actual : '—'"></td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                                              :class="{
                                                  'bg-green-100 text-green-700':   r.estado === 'ok' && !r.conflicto && !r.duplicado_excel,
                                                  'bg-orange-100 text-orange-700': r.conflicto && r.estado !== 'error',
                                                  'bg-yellow-100 text-yellow-700': r.estado === 'advertencia' && !r.conflicto,
                                                  'bg-red-100 text-red-700':       r.estado === 'error',
                                                  'bg-purple-100 text-purple-700': r.duplicado_excel && r.estado !== 'error' && !r.conflicto,
                                              }"
                                              x-text="r.estado === 'error' ? 'Error' : (r.conflicto ? 'Conflicto' : (r.duplicado_excel ? 'Duplicado' : (r.estado === 'advertencia' ? 'Advertencia' : 'OK')))">
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500 text-[11px]">
                                        <template x-if="r.errores && r.errores.length > 0">
                                            <ul class="space-y-0.5">
                                                <template x-for="(e, ei) in r.errores" :key="ei">
                                                    <li x-text="e"></li>
                                                </template>
                                            </ul>
                                        </template>
                                        <template x-if="!r.errores || r.errores.length === 0">
                                            <span class="text-gray-300">—</span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-2">
                                        <template x-if="r.conflicto && r.estado !== 'error'">
                                            <select x-model="overrides[r.codigo]"
                                                    class="border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                                <option value="">Global</option>
                                                <option value="sumar">Sumar</option>
                                                <option value="sobrescribir">Sobrescribir</option>
                                                <option value="ignorar">Ignorar</option>
                                            </select>
                                        </template>
                                        <template x-if="!r.conflicto || r.estado === 'error'">
                                            <span class="text-gray-300 text-xs">—</span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="resultadosFiltrados.length === 0">
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-400">Sin filas para este filtro</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div x-show="(counts.error ?? 0) > 0" class="mt-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                    <strong x-text="counts.error"></strong> fila(s) con errores no se importarán. Puedes continuar e importar solo las filas válidas.
                </div>

                <div class="mt-6 flex justify-between">
                    <button type="button" @click="paso = 3" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                        ← Atrás
                    </button>
                    <button type="button"
                            @click="paso = 5"
                            :disabled="!puedeImportar()"
                            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors"
                            :class="puedeImportar()
                                ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                        Continuar →
                    </button>
                </div>
            </div>
        </div>

        {{-- ── PASO 5: Confirmar ────────────────────────────────────── --}}
        <div x-show="paso === 5" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-5">Confirma la importación</h3>

                <div class="space-y-0 mb-6 rounded-xl border border-gray-100 overflow-hidden">
                    <div class="flex justify-between text-sm px-4 py-3 bg-white border-b border-gray-50">
                        <span class="text-gray-500">Obra destino</span>
                        <span class="font-semibold text-gray-800" x-text="obraSeleccionada?.nombre"></span>
                    </div>
                    <div class="flex justify-between text-sm px-4 py-3 bg-gray-50/50 border-b border-gray-50">
                        <span class="text-gray-500">Archivo</span>
                        <span class="font-medium text-gray-700" x-text="archivo?.name"></span>
                    </div>
                    <div class="flex justify-between text-sm px-4 py-3 bg-white border-b border-gray-50">
                        <span class="text-gray-500">Total filas en archivo</span>
                        <span class="font-medium text-gray-700" x-text="counts.total"></span>
                    </div>
                    <div class="flex justify-between text-sm px-4 py-3 bg-gray-50/50 border-b border-gray-50">
                        <span class="text-gray-500">Filas a importar (sin errores)</span>
                        <span class="font-semibold text-green-700" x-text="(counts.total ?? 0) - (counts.error ?? 0)"></span>
                    </div>
                    <template x-if="(counts.conflicto ?? 0) > 0">
                        <div>
                            <div class="flex justify-between text-sm px-4 py-3 bg-white border-b border-gray-50">
                                <span class="text-gray-500">Insumos con conflicto</span>
                                <span class="font-semibold text-orange-600" x-text="counts.conflicto"></span>
                            </div>
                            <div class="flex justify-between text-sm px-4 py-3 bg-gray-50/50 border-b border-gray-50">
                                <span class="text-gray-500">Resolución de conflictos</span>
                                <span class="font-semibold text-orange-700 capitalize" x-text="conflictResolution"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="(counts.error ?? 0) > 0">
                        <div class="flex justify-between text-sm px-4 py-3 bg-white">
                            <span class="text-gray-500">Filas con errores (se omitirán)</span>
                            <span class="font-semibold text-red-600" x-text="counts.error"></span>
                        </div>
                    </template>
                </div>

                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800 mb-6">
                    <strong>Atención:</strong> Esta acción modificará el inventario de la obra <strong x-text="obraSeleccionada?.nombre"></strong>. Esta operación no se puede deshacer automáticamente.
                </div>

                <div class="flex justify-between">
                    <button type="button" @click="paso = 4" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                        ← Atrás
                    </button>
                    <button type="button"
                            @click="ejecutarImportacion()"
                            :disabled="cargando"
                            class="px-6 py-2 rounded-lg text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                        <span x-show="!cargando">Importar ahora</span>
                        <span x-show="cargando">Importando...</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── PASO 6: Resultado ────────────────────────────────────── --}}
        <div x-show="paso === 6" x-transition>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">

                <template x-if="resultado?.ok">
                    <div>
                        <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-1">Importación completada</h3>
                        <p class="text-sm text-gray-500 mb-6">
                            Obra: <strong x-text="resultado.obra_nombre"></strong>
                        </p>

                        <div class="grid grid-cols-3 gap-4 max-w-sm mx-auto mb-8">
                            <div class="text-center p-3 rounded-xl bg-green-50 border border-green-100">
                                <div class="text-2xl font-bold text-green-700" x-text="resultado.insertados"></div>
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-green-500 mt-0.5">Nuevos</div>
                            </div>
                            <div class="text-center p-3 rounded-xl bg-blue-50 border border-blue-100">
                                <div class="text-2xl font-bold text-blue-700" x-text="resultado.actualizados"></div>
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-blue-500 mt-0.5">Actualizados</div>
                            </div>
                            <div class="text-center p-3 rounded-xl bg-gray-50 border border-gray-100">
                                <div class="text-2xl font-bold text-gray-500" x-text="resultado.omitidos"></div>
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mt-0.5">Omitidos</div>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="resultado && !resultado.ok">
                    <div>
                        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Error en la importación</h3>
                        <p class="text-sm text-red-600 mb-6" x-text="resultado.error"></p>
                    </div>
                </template>

                <div class="flex justify-center gap-3">
                    <button type="button"
                            @click="reiniciar()"
                            class="px-5 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                        Nueva importación
                    </button>
                    <a href="{{ route('inventario.index') }}"
                       class="px-5 py-2 rounded-lg text-sm font-medium bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors">
                        Ir a Inventario
                    </a>
                </div>
            </div>
        </div>

    </div>

    <script>
    function importarInventario() {
        return {
            paso: 1,
            cargando: false,

            // Paso 1
            obras: @json($obras->values()),
            obraSeleccionada: null,
            busquedaObra: '',

            // Paso 2
            archivo: null,
            errorArchivo: '',

            // Paso 3
            columnas: [],
            preview: [],
            totalFilas: 0,
            mapeo: {},
            camposMapeo: [
                { key: 'codigo_insumo', label: 'Código Insumo',  required: true  },
                { key: 'cantidad',      label: 'Cantidad',        required: true  },
                { key: 'pu',            label: 'Precio Unitario', required: false },
                { key: 'descripcion',   label: 'Descripción',     required: false },
                { key: 'unidad',        label: 'Unidad',          required: false },
                { key: 'familia',       label: 'Familia',         required: false },
                { key: 'subfamilia',    label: 'Subfamilia',      required: false },
            ],

            // Paso 4
            resultados: [],
            counts: {},
            filtroVal: 'todos',
            filtrosVal: [
                { value: 'todos',       label: 'Todos' },
                { value: 'conflicto',   label: 'Conflictos' },
                { value: 'error',       label: 'Errores' },
                { value: 'advertencia', label: 'Advertencias' },
                { value: 'ok',          label: 'Correctas' },
            ],
            conflictResolution: 'sobrescribir',
            conflictOpts: [
                { value: 'sumar',        label: 'Sumar cantidades' },
                { value: 'sobrescribir', label: 'Sobrescribir' },
                { value: 'ignorar',      label: 'Ignorar todos' },
                { value: 'manual',       label: 'Definir uno a uno' },
            ],
            conflictOptDesc: {
                sumar:        'La cantidad del archivo se sumará a la cantidad actual en inventario.',
                sobrescribir: 'La cantidad actual será reemplazada por la del archivo.',
                ignorar:      'Los insumos que ya existen en inventario no serán modificados.',
                manual:       'Puedes definir la acción para cada insumo en conflicto individualmente en la tabla.',
            },
            overrides: {},

            // Paso 6
            resultado: null,

            init() {},

            get obrasFiltradas() {
                if (!this.busquedaObra) return this.obras;
                const q = this.busquedaObra.toLowerCase();
                return this.obras.filter(o => o.nombre.toLowerCase().includes(q));
            },

            get resultadosFiltrados() {
                if (this.filtroVal === 'todos')      return this.resultados;
                if (this.filtroVal === 'conflicto')  return this.resultados.filter(r => r.conflicto);
                if (this.filtroVal === 'advertencia') return this.resultados.filter(r => r.estado === 'advertencia' && !r.conflicto);
                return this.resultados.filter(r => r.estado === this.filtroVal);
            },

            seleccionarObra(obra) {
                this.obraSeleccionada = obra;
            },

            onFileChange(e) {
                const f = e.target.files[0];
                if (!f) return;
                this.archivo     = f;
                this.errorArchivo = '';
            },

            async analizarArchivo() {
                if (!this.archivo) return;
                this.cargando     = true;
                this.errorArchivo = '';
                try {
                    const fd = new FormData();
                    fd.append('archivo', this.archivo);
                    fd.append('_token',  '{{ csrf_token() }}');

                    const res  = await fetch('{{ route('admin.importar-inventario.analizar') }}', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (!res.ok) {
                        this.errorArchivo = data.error ?? 'Error al analizar el archivo.';
                        return;
                    }

                    this.columnas   = data.columnas;
                    this.preview    = data.preview;
                    this.totalFilas = data.total_filas;
                    this.autoMapear();
                    this.paso = 3;
                } catch (err) {
                    this.errorArchivo = 'Error de red. Intenta de nuevo.';
                } finally {
                    this.cargando = false;
                }
            },

            autoMapear() {
                const normalizar = s => s.toLowerCase()
                    .normalize('NFD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');

                const alias = {
                    codigo_insumo: ['codigo_insumo', 'codigo', 'insumo', 'clave', 'id_insumo', 'insumo_id'],
                    cantidad:      ['cantidad', 'qty', 'cant', 'stock'],
                    pu:            ['pu', 'precio_unitario', 'precio', 'p_u', 'costo', 'costo_promedio'],
                    descripcion:   ['descripcion', 'desc', 'nombre', 'descripcion_larga'],
                    unidad:        ['unidad', 'u_m', 'um', 'unidad_medida'],
                    familia:       ['familia', 'familia_principal'],
                    subfamilia:    ['subfamilia', 'sub_familia', 'sub'],
                };

                const mapeo = {};
                this.columnas.forEach((col, idx) => {
                    const norm = normalizar(col);
                    for (const [campo, aliasList] of Object.entries(alias)) {
                        if (!(campo in mapeo) && aliasList.some(a => norm === a || norm.includes(a))) {
                            mapeo[campo] = idx;
                        }
                    }
                });
                this.mapeo = mapeo;
            },

            mapeoValido() {
                return this.mapeo.codigo_insumo !== undefined && this.mapeo.cantidad !== undefined;
            },

            async validarArchivo() {
                if (!this.mapeoValido()) return;
                this.cargando = true;
                try {
                    const fd = new FormData();
                    fd.append('archivo',              this.archivo);
                    fd.append('obra_id',              this.obraSeleccionada.id);
                    fd.append('mapeo[codigo_insumo]', this.mapeo.codigo_insumo);
                    fd.append('mapeo[cantidad]',       this.mapeo.cantidad);
                    for (const k of ['pu', 'descripcion', 'unidad', 'familia', 'subfamilia']) {
                        if (this.mapeo[k] !== undefined && this.mapeo[k] !== '') {
                            fd.append('mapeo[' + k + ']', this.mapeo[k]);
                        }
                    }
                    fd.append('_token', '{{ csrf_token() }}');

                    const res  = await fetch('{{ route('admin.importar-inventario.validar') }}', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (!res.ok) {
                        alert(data.error ?? 'Error al validar.');
                        return;
                    }

                    this.resultados = data.resultados;
                    this.counts     = data.counts;
                    this.overrides  = {};
                    this.filtroVal  = 'todos';
                    this.paso = 4;
                } catch (err) {
                    alert('Error de red. Intenta de nuevo.');
                } finally {
                    this.cargando = false;
                }
            },

            puedeImportar() {
                return (this.counts.total ?? 0) > 0
                    && (this.counts.error ?? 0) < (this.counts.total ?? 1);
            },

            async ejecutarImportacion() {
                this.cargando = true;
                try {
                    const fd = new FormData();
                    fd.append('archivo',              this.archivo);
                    fd.append('obra_id',              this.obraSeleccionada.id);
                    fd.append('mapeo[codigo_insumo]', this.mapeo.codigo_insumo);
                    fd.append('mapeo[cantidad]',       this.mapeo.cantidad);
                    for (const k of ['pu', 'descripcion', 'unidad', 'familia', 'subfamilia']) {
                        if (this.mapeo[k] !== undefined && this.mapeo[k] !== '') {
                            fd.append('mapeo[' + k + ']', this.mapeo[k]);
                        }
                    }
                    fd.append('conflict_resolution', this.conflictResolution);
                    fd.append('overrides',           JSON.stringify(this.overrides));
                    fd.append('_token',              '{{ csrf_token() }}');

                    const res  = await fetch('{{ route('admin.importar-inventario.importar') }}', { method: 'POST', body: fd });
                    const data = await res.json();

                    this.resultado = data;
                    this.paso = 6;
                } catch (err) {
                    this.resultado = { ok: false, error: 'Error de red. Intenta de nuevo.' };
                    this.paso = 6;
                } finally {
                    this.cargando = false;
                }
            },

            reiniciar() {
                this.paso               = 1;
                this.archivo            = null;
                this.errorArchivo       = '';
                this.columnas           = [];
                this.preview            = [];
                this.totalFilas         = 0;
                this.mapeo              = {};
                this.resultados         = [];
                this.counts             = {};
                this.overrides          = {};
                this.conflictResolution = 'sobrescribir';
                this.resultado          = null;
                this.obraSeleccionada   = null;
                this.busquedaObra       = '';
            },
        };
    }
    </script>
</x-app-layout>
