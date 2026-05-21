<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Importar Inventario
    </h2>
</x-slot>

<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto" x-data="importarInventario()" x-init="init()">

    {{-- ── Barra de progreso ── --}}
    <div class="mb-8">
        <div class="flex items-center gap-0">
            @php
            $pasos = [
                1 => 'Cargar archivo',
                2 => 'Mapear columnas',
                3 => 'Validar datos',
                4 => 'Confirmar',
                5 => 'Resultado',
            ];
            @endphp
            @foreach($pasos as $num => $label)
            <div class="flex items-center {{ $num < count($pasos) ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all"
                         :class="paso > {{ $num }}
                            ? 'bg-indigo-600 text-white'
                            : paso === {{ $num }}
                                ? 'bg-indigo-600 text-white ring-4 ring-indigo-200'
                                : 'bg-gray-200 text-gray-500'">
                        <template x-if="paso > {{ $num }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="paso <= {{ $num }}">
                            <span>{{ $num }}</span>
                        </template>
                    </div>
                    <span class="text-xs mt-1 font-medium whitespace-nowrap hidden sm:block"
                          :class="paso === {{ $num }} ? 'text-indigo-600' : 'text-gray-400'">{{ $label }}</span>
                </div>
                @if($num < count($pasos))
                <div class="flex-1 h-0.5 mx-2 transition-all"
                     :class="paso > {{ $num }} ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Mensaje de error global ── --}}
    <div x-show="errorMsg" x-transition
         class="mb-4 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-5 h-5 shrink-0 mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
        <span x-text="errorMsg"></span>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- PASO 1 — Instrucciones + Subir archivo                           --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="paso === 1" x-transition>

        {{-- Info cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">¿Qué puedes hacer?</h3>
                </div>
                <ul class="space-y-1.5 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-400 rounded-full shrink-0"></span>Cargar inventario inicial masivamente</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-400 rounded-full shrink-0"></span>Actualizar cantidades existentes desde Excel</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-400 rounded-full shrink-0"></span>Cargar precios unitarios de forma masiva</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-indigo-400 rounded-full shrink-0"></span>Importar desde cualquier Excel (mapeo flexible)</li>
                </ul>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Validaciones automáticas</h3>
                </div>
                <ul class="space-y-1.5 text-sm text-gray-600">
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full shrink-0"></span>Existencia del código en ERP</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full shrink-0"></span>Coincidencia de unidad con ERP</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full shrink-0"></span>Verificación de descripción y familia</li>
                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 bg-emerald-400 rounded-full shrink-0"></span>Datos numéricos y campos obligatorios</li>
                </ul>
            </div>
        </div>

        {{-- Columnas requeridas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm mb-6">
            <h3 class="font-semibold text-gray-800 text-sm mb-3">Columnas del Excel</h3>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>codigo_insumo <span class="text-red-500 ml-0.5">*</span>
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>cantidad <span class="text-red-500 ml-0.5">*</span>
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>unidad
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>descripcion
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>pu
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>familia
                </span>
                <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>subfamilia
                </span>
                <span class="text-xs text-gray-400 self-center ml-1"><span class="text-red-500">*</span> obligatorio</span>
            </div>
        </div>

        {{-- Template download + upload --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h3 class="font-semibold text-gray-800">Cargar archivo Excel</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Formatos aceptados: .xlsx, .xls, .csv · Máx. 20 MB</p>
                </div>
                <a href="{{ route('admin.importar-inventario.plantilla') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-sm font-medium hover:bg-indigo-100 transition-colors shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    Descargar Plantilla
                </a>
            </div>

            {{-- Drop zone --}}
            <label for="fileInput"
                   class="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed rounded-xl cursor-pointer transition-colors"
                   :class="archivo ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:border-indigo-400 hover:bg-indigo-50'">
                <template x-if="!archivo">
                    <div class="flex flex-col items-center gap-2 text-gray-400">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <span class="text-sm font-medium">Arrastra tu archivo aquí o <span class="text-indigo-600">selecciona</span></span>
                        <span class="text-xs">XLSX, XLS, CSV</span>
                    </div>
                </template>
                <template x-if="archivo">
                    <div class="flex flex-col items-center gap-2 text-indigo-700">
                        <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                        <span class="text-sm font-semibold" x-text="archivo.name"></span>
                        <span class="text-xs text-indigo-500" x-text="(archivo.size/1024/1024).toFixed(2) + ' MB'"></span>
                        <span class="text-xs text-indigo-400">Haz clic para cambiar</span>
                    </div>
                </template>
                <input id="fileInput" x-ref="fileInput" type="file" class="hidden"
                       accept=".xlsx,.xls,.csv"
                       @change="onFileChange($event)">
            </label>

            <div class="mt-5 flex justify-end">
                <button @click="analizar()"
                        :disabled="!archivo || analizando"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <svg x-show="analizando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <span x-text="analizando ? 'Analizando...' : 'Analizar archivo'"></span>
                    <svg x-show="!analizando" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- PASO 2 — Mapeo de columnas                                        --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="paso === 2" x-transition>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-5">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800">Mapear columnas</h3>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Archivo: <span class="font-medium text-gray-700" x-text="archivo?.name"></span>
                        · <span class="font-medium text-indigo-600" x-text="totalFilas + ' filas'"></span> detectadas
                    </p>
                </div>
                <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full font-medium" x-text="columnas.length + ' columnas en Excel'"></span>
            </div>

            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @php
                    $campos = [
                        ['key' => 'codigo_insumo', 'label' => 'Código Insumo',  'required' => true,  'hint' => 'Clave ERP del insumo'],
                        ['key' => 'cantidad',       'label' => 'Cantidad',        'required' => true,  'hint' => 'Stock a registrar'],
                        ['key' => 'unidad',         'label' => 'Unidad',          'required' => false, 'hint' => 'Se validará vs ERP'],
                        ['key' => 'descripcion',    'label' => 'Descripción',     'required' => false, 'hint' => 'Se comparará vs ERP'],
                        ['key' => 'pu',             'label' => 'Precio Unitario', 'required' => false, 'hint' => 'Costo promedio'],
                        ['key' => 'familia',        'label' => 'Familia',         'required' => false, 'hint' => 'Se validará vs ERP'],
                        ['key' => 'subfamilia',     'label' => 'Subfamilia',      'required' => false, 'hint' => 'Se validará vs ERP'],
                    ];
                    @endphp
                    @foreach($campos as $campo)
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">
                            {{ $campo['label'] }}
                            @if($campo['required']) <span class="text-red-500">*</span> @endif
                            <span class="font-normal text-gray-400 ml-1">{{ $campo['hint'] }}</span>
                        </label>
                        <select x-model="mapeo.{{ $campo['key'] }}"
                                class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
                                :class="mapeo.{{ $campo['key'] }} !== '' ? 'border-indigo-400 bg-indigo-50' : ''">
                            <option value="">— {{ $campo['required'] ? 'Seleccionar (obligatorio)' : 'No mapear' }} —</option>
                            <template x-for="(col, idx) in columnas" :key="idx">
                                <option :value="String(idx)" x-text="col || ('Columna ' + (idx+1))"></option>
                            </template>
                        </select>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-5">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-700 text-sm">Vista previa (primeras filas)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase">
                        <tr>
                            <template x-for="(col, idx) in columnas" :key="idx">
                                <th class="px-3 py-2 text-left font-medium whitespace-nowrap"
                                    :class="Object.values(mapeo).includes(String(idx)) ? 'bg-indigo-50 text-indigo-700' : ''"
                                    x-text="col || ('Col ' + (idx+1))"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(fila, ri) in previewFilas" :key="ri">
                            <tr :style="ri%2===0?'background:#f9fafb':''">
                                <template x-for="(cel, ci) in fila" :key="ci">
                                    <td class="px-3 py-1.5 text-gray-700 max-w-xs truncate"
                                        :class="Object.values(mapeo).includes(String(ci)) ? 'bg-indigo-50 font-medium text-indigo-800' : ''"
                                        x-text="cel ?? ''"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button @click="paso = 1; errorMsg = ''"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Atrás
            </button>
            <button @click="validar()"
                    :disabled="!mapeoValido() || validando"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <svg x-show="validando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span x-text="validando ? 'Validando contra ERP...' : 'Validar datos'"></span>
                <svg x-show="!validando" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- PASO 3 — Resultados de validación                                 --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="paso === 3" x-transition>

        {{-- Resumen de conteos --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center shadow-sm">
                <div class="text-2xl font-bold text-gray-800" x-text="validacion.counts?.total ?? 0"></div>
                <div class="text-xs text-gray-500 mt-0.5">Total filas</div>
            </div>
            <div class="bg-white rounded-xl border border-emerald-200 p-4 text-center shadow-sm">
                <div class="text-2xl font-bold text-emerald-600" x-text="validacion.counts?.ok ?? 0"></div>
                <div class="text-xs text-emerald-600 mt-0.5">Correctas</div>
            </div>
            <div class="bg-white rounded-xl border border-amber-200 p-4 text-center shadow-sm">
                <div class="text-2xl font-bold text-amber-600" x-text="validacion.counts?.advertencia ?? 0"></div>
                <div class="text-xs text-amber-600 mt-0.5">Advertencias</div>
            </div>
            <div class="bg-white rounded-xl border border-red-200 p-4 text-center shadow-sm">
                <div class="text-2xl font-bold text-red-600" x-text="validacion.counts?.error ?? 0"></div>
                <div class="text-xs text-red-600 mt-0.5">Errores</div>
            </div>
        </div>

        {{-- Bloqueo si hay errores --}}
        <div x-show="(validacion.counts?.error ?? 0) > 0"
             class="mb-5 flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 shrink-0 mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            <div>
                <p class="font-semibold">Existen errores que deben corregirse antes de importar.</p>
                <p class="mt-0.5 text-red-700">Corrige los registros marcados en rojo en tu archivo Excel y vuelve a cargar.</p>
            </div>
        </div>

        {{-- Advertencias info --}}
        <div x-show="(validacion.counts?.error ?? 0) === 0 && (validacion.counts?.advertencia ?? 0) > 0"
             class="mb-5 flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-5 py-4 text-sm">
            <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
            <div>
                <p class="font-semibold">Hay advertencias, pero puedes continuar con la importación.</p>
                <p class="mt-0.5 text-amber-700">Los datos de ERP (descripción, familia) se usarán para los registros con advertencias. Revisa antes de confirmar.</p>
            </div>
        </div>

        {{-- Filtro de tabla --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                <h3 class="font-semibold text-gray-700 text-sm">Tabla de validación</h3>
                <div class="flex gap-1.5">
                    <button @click="filtroVal = 'todos'"
                            :class="filtroVal==='todos' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-full text-xs font-medium transition-colors">Todos</button>
                    <button @click="filtroVal = 'error'"
                            :class="filtroVal==='error' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100'"
                            class="px-3 py-1 rounded-full text-xs font-medium transition-colors">Solo errores</button>
                    <button @click="filtroVal = 'advertencia'"
                            :class="filtroVal==='advertencia' ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'"
                            class="px-3 py-1 rounded-full text-xs font-medium transition-colors">Advertencias</button>
                </div>
            </div>
            <div class="overflow-x-auto max-h-[50vh] overflow-y-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase sticky top-0 z-10">
                        <tr>
                            <th class="px-3 py-2 text-left w-12">Fila</th>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Descripción ERP</th>
                            <th class="px-3 py-2 text-left">Unidad ERP</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">P.U.</th>
                            <th class="px-3 py-2 text-center w-20">Estado</th>
                            <th class="px-3 py-2 text-left">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(r, i) in resultadosFiltrados()" :key="i">
                            <tr :class="r.estado==='error' ? 'bg-red-50' : r.estado==='advertencia' ? 'bg-amber-50' : (i%2===0 ? 'bg-white' : 'bg-gray-50')"
                                style="border-top:1px solid #f3f4f6">
                                <td class="px-3 py-2 text-gray-400" x-text="r.fila"></td>
                                <td class="px-3 py-2 font-mono font-semibold" x-text="r.codigo"
                                    :class="r.erp_existe ? 'text-gray-800' : 'text-red-700'"></td>
                                <td class="px-3 py-2 text-gray-600 max-w-xs truncate" x-text="r.descripcion_erp || '—'"></td>
                                <td class="px-3 py-2 text-gray-600" x-text="r.unidad_erp || '—'"></td>
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :class="r.cantidad_ok ? 'text-gray-800' : 'text-red-600 font-semibold'"
                                    x-text="r.cantidad !== null ? r.cantidad : '✗ inválida'"></td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-600"
                                    x-text="r.pu !== null ? '$'+r.pu.toLocaleString('es-MX', {minimumFractionDigits:2}) : '—'"></td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full font-semibold text-xs"
                                          :class="r.estado==='ok' ? 'bg-emerald-100 text-emerald-700' : r.estado==='advertencia' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'"
                                          x-text="r.estado==='ok' ? '✓ OK' : r.estado==='advertencia' ? '⚠ Aviso' : '✗ Error'"></span>
                                </td>
                                <td class="px-3 py-2 text-gray-500 italic" x-text="r.errores?.join(' · ') || ''"></td>
                            </tr>
                        </template>
                        <tr x-show="resultadosFiltrados().length === 0">
                            <td colspan="8" class="px-5 py-6 text-center text-gray-400">Sin registros para el filtro seleccionado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 flex items-center justify-between">
            <button @click="paso = 2; errorMsg = ''"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Ajustar mapeo
            </button>
            <button @click="paso = 4; errorMsg = ''"
                    :disabled="!puedeImportar()"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                Continuar a confirmación
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- PASO 4 — Confirmación                                             --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="paso === 4" x-transition>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 text-lg">Confirmar importación</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Revisa el resumen y confirma para iniciar la importación al inventario de la obra activa.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 text-center border border-gray-200">
                    <div class="text-3xl font-bold text-gray-800" x-text="validacion.counts?.total ?? 0"></div>
                    <div class="text-sm text-gray-500 mt-1">Registros a procesar</div>
                </div>
                <div class="bg-emerald-50 rounded-lg p-4 text-center border border-emerald-200">
                    <div class="text-3xl font-bold text-emerald-600" x-text="(validacion.counts?.ok ?? 0) + (validacion.counts?.advertencia ?? 0)"></div>
                    <div class="text-sm text-emerald-600 mt-1">Se importarán</div>
                </div>
                <div class="bg-amber-50 rounded-lg p-4 text-center border border-amber-200">
                    <div class="text-3xl font-bold text-amber-600" x-text="validacion.counts?.advertencia ?? 0"></div>
                    <div class="text-sm text-amber-600 mt-1">Con advertencias</div>
                </div>
            </div>

            <div class="mt-5 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 flex items-start gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                <span>Los registros existentes en la obra activa <strong>se sobreescribirán</strong> con las cantidades del archivo. Esta acción no se puede deshacer automáticamente.</span>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button @click="paso = 3; errorMsg = ''"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Revisar validación
            </button>
            <button @click="importar()"
                    :disabled="importando"
                    class="inline-flex items-center gap-2 px-7 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <svg x-show="importando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <svg x-show="!importando" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                <span x-text="importando ? 'Importando...' : 'Importar ahora'"></span>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- PASO 5 — Resultado final                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div x-show="paso === 5" x-transition>
        <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-8 text-center">
            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-1">¡Importación completada!</h3>
            <p class="text-gray-500 text-sm mb-6">El inventario se ha actualizado correctamente.</p>

            <div class="grid grid-cols-3 gap-4 max-w-md mx-auto mb-8">
                <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-200">
                    <div class="text-2xl font-bold text-indigo-700" x-text="importResult?.insertados ?? 0"></div>
                    <div class="text-xs text-indigo-500 mt-1">Nuevos registros</div>
                </div>
                <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                    <div class="text-2xl font-bold text-emerald-700" x-text="importResult?.actualizados ?? 0"></div>
                    <div class="text-xs text-emerald-500 mt-1">Actualizados</div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <div class="text-2xl font-bold text-gray-600" x-text="importResult?.omitidos ?? 0"></div>
                    <div class="text-xs text-gray-400 mt-1">Omitidos</div>
                </div>
            </div>

            <div class="flex items-center justify-center gap-3">
                <button @click="reiniciar()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Nueva importación
                </button>
                <a href="{{ route('inventario.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Ver Inventario
                </a>
            </div>
        </div>
    </div>

</div>

<script>
function importarInventario() {
    return {
        paso: 1,
        archivo: null,
        analizando: false,
        validando: false,
        importando: false,
        columnas: [],
        previewFilas: [],
        totalFilas: 0,
        mapeo: { codigo_insumo: '', cantidad: '', descripcion: '', unidad: '', pu: '', familia: '', subfamilia: '' },
        validacion: { resultados: [], counts: { ok: 0, advertencia: 0, error: 0, total: 0 } },
        filtroVal: 'todos',
        importResult: null,
        errorMsg: '',

        init() {},

        onFileChange(e) {
            this.archivo = e.target.files[0] || null;
            this.errorMsg = '';
        },

        async analizar() {
            if (!this.archivo) return;
            this.analizando = true;
            this.errorMsg = '';
            try {
                const fd = this._fd();
                const res = await fetch('{{ route("admin.importar-inventario.analizar") }}', { method: 'POST', body: fd });
                const data = await res.json();
                if (!res.ok) { this.errorMsg = data.message || data.error || 'Error al analizar el archivo.'; return; }
                this.columnas = data.columnas;
                this.previewFilas = data.preview;
                this.totalFilas = data.total_filas;
                this._autoMapear();
                this.paso = 2;
            } catch(e) {
                this.errorMsg = 'Error de red al analizar el archivo.';
            } finally {
                this.analizando = false;
            }
        },

        _autoMapear() {
            const norm = s => s.toLowerCase().replace(/[\s_\-\.]+/g, '');
            const aliases = {
                codigo_insumo: ['codigoinsumo','codigo','insumo','clave','id','sku'],
                cantidad:      ['cantidad','qty','stock','existencia','inventario'],
                descripcion:   ['descripcion','description','desc','nombre'],
                unidad:        ['unidad','unit','um','ume','umedida'],
                pu:            ['pu','precio','costo','costopromedio','preciounitario','pu'],
                familia:       ['familia','family','cat','categoria'],
                subfamilia:    ['subfamilia','subcategory','subcat'],
            };
            this.columnas.forEach((col, idx) => {
                const n = norm(col);
                for (const [field, als] of Object.entries(aliases)) {
                    if (this.mapeo[field] === '' && als.some(a => n.includes(a))) {
                        this.mapeo[field] = String(idx);
                    }
                }
            });
        },

        mapeoValido() {
            return this.mapeo.codigo_insumo !== '' && this.mapeo.cantidad !== '';
        },

        async validar() {
            if (!this.mapeoValido()) return;
            this.validando = true;
            this.errorMsg = '';
            try {
                const fd = this._fd();
                for (const [k, v] of Object.entries(this.mapeo)) {
                    if (v !== '') fd.append(`mapeo[${k}]`, v);
                }
                const res = await fetch('{{ route("admin.importar-inventario.validar") }}', { method: 'POST', body: fd });
                const data = await res.json();
                if (!res.ok) { this.errorMsg = data.message || data.error || 'Error al validar.'; return; }
                this.validacion = data;
                this.filtroVal = 'todos';
                this.paso = 3;
            } catch(e) {
                this.errorMsg = 'Error de red al validar.';
            } finally {
                this.validando = false;
            }
        },

        resultadosFiltrados() {
            const rs = this.validacion.resultados || [];
            if (this.filtroVal === 'todos') return rs;
            return rs.filter(r => r.estado === this.filtroVal);
        },

        puedeImportar() {
            return (this.validacion.counts?.error ?? 1) === 0 && (this.validacion.counts?.total ?? 0) > 0;
        },

        async importar() {
            if (!this.puedeImportar()) return;
            this.importando = true;
            this.errorMsg = '';
            try {
                const fd = this._fd();
                for (const [k, v] of Object.entries(this.mapeo)) {
                    if (v !== '') fd.append(`mapeo[${k}]`, v);
                }
                const res = await fetch('{{ route("admin.importar-inventario.importar") }}', { method: 'POST', body: fd });
                const data = await res.json();
                if (!res.ok) { this.errorMsg = data.message || data.error || 'Error al importar.'; return; }
                this.importResult = data;
                this.paso = 5;
            } catch(e) {
                this.errorMsg = 'Error de red al importar.';
            } finally {
                this.importando = false;
            }
        },

        reiniciar() {
            this.paso = 1; this.archivo = null;
            this.columnas = []; this.previewFilas = []; this.totalFilas = 0;
            this.mapeo = { codigo_insumo: '', cantidad: '', descripcion: '', unidad: '', pu: '', familia: '', subfamilia: '' };
            this.validacion = { resultados: [], counts: { ok: 0, advertencia: 0, error: 0, total: 0 } };
            this.importResult = null; this.errorMsg = '';
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        _fd() {
            const fd = new FormData();
            fd.append('archivo', this.archivo);
            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
            return fd;
        },
    };
}
</script>
</x-app-layout>
