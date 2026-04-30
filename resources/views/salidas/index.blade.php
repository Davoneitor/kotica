{{-- resources/views/salidas/index.blade.php --}}
<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Salidas
            </h2>
            <div class="text-sm text-gray-500">
                Registra una o varias salidas usando las pestañas
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div
                class="bg-white shadow-sm sm:rounded-lg p-4 md:p-6"
                x-data="salidasPageUI()"
            >

                {{-- ══════════════════════════════════════════════
                     PESTAÑAS HEADER
                ══════════════════════════════════════════════ --}}
                <div class="flex items-center border-b mb-6 overflow-x-auto" style="min-height:48px;">

                    <template x-for="(tab, idx) in tabs" :key="tab.id">
                        <button
                            type="button"
                            @click="activarTab(idx)"
                            class="relative flex items-center gap-2 px-5 py-3 text-sm whitespace-nowrap border-b-2 transition-colors focus:outline-none"
                            :class="activeIdx === idx
                                ? 'border-gray-900 text-gray-900 font-semibold bg-white'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        >
                            {{-- Número de pestaña --}}
                            <span x-text="'Salida ' + (idx + 1)"></span>

                            {{-- Badge error --}}
                            <span
                                x-show="tieneErrores(idx)"
                                class="inline-block w-2 h-2 rounded-full bg-red-500"
                                title="Esta pestaña tiene errores"
                            ></span>

                            {{-- Badge guardada --}}
                            <span
                                x-show="tab.saved"
                                class="inline-block text-xs text-green-600 font-bold"
                            >✓</span>

                            {{-- Botón cerrar pestaña --}}
                            <span
                                x-show="tabs.length > 1"
                                @click.stop="cerrarTab(idx)"
                                class="ml-1 w-5 h-5 flex items-center justify-center rounded-full text-gray-400 hover:bg-red-100 hover:text-red-600 font-bold text-base leading-none cursor-pointer"
                                title="Cerrar esta pestaña"
                            >×</span>
                        </button>
                    </template>

                    {{-- Botón agregar pestaña --}}
                    <button
                        type="button"
                        @click="agregarTab()"
                        class="ml-3 px-4 py-2 text-sm border rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-700 whitespace-nowrap flex items-center gap-1 shrink-0"
                    >
                        <span class="text-base font-bold leading-none">+</span>
                        <span>Agregar nueva salida</span>
                    </button>

                    {{-- Botón transferencia --}}
                    <a href="{{ route('transferencias.index') }}"
                       class="ml-2 px-4 py-2 text-sm rounded-lg bg-gray-900 text-white hover:bg-gray-700 whitespace-nowrap flex items-center gap-1 shrink-0">
                        <span class="text-base leading-none">⇄</span>
                        <span>Transferir a otra obra</span>
                    </a>
                </div>

                {{-- ══════════════════════════════════════════════
                     AVISO CARGANDO CATÁLOGOS
                ══════════════════════════════════════════════ --}}
                <div x-show="cargandoCatalogos" class="mb-4 p-3 bg-blue-50 text-blue-700 rounded-lg text-sm">
                    Cargando catálogos...
                </div>

                {{-- ══════════════════════════════════════════════
                     CONTENIDO DE LA PESTAÑA ACTIVA
                ══════════════════════════════════════════════ --}}
                <div x-show="tabs.length > 0">

                    {{-- Error general de la pestaña --}}
                    <div
                        x-show="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.general"
                        class="mb-4 p-4 bg-red-100 text-red-800 rounded-lg text-sm font-medium"
                        x-text="tabs[activeIdx] ? tabs[activeIdx].errors.general : ''"
                    ></div>

                    {{-- Estado: Guardada correctamente --}}
                    <div
                        x-show="tabs[activeIdx] && tabs[activeIdx].saved"
                        class="mb-4 p-5 bg-green-50 border border-green-200 text-green-900 rounded-xl"
                    >
                        <div class="font-semibold text-base mb-3">✅ Salida registrada correctamente</div>
                        <div class="flex flex-wrap gap-3">
                            <a
                                :href="tabs[activeIdx] ? tabs[activeIdx].pdfUrl : '#'"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-5 py-3 bg-white border border-gray-300 text-gray-800 text-sm font-medium rounded-xl hover:bg-gray-50 active:bg-gray-100"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Ver PDF
                            </a>
                            <a
                                :href="tabs[activeIdx] ? tabs[activeIdx].pdfUrl : '#'"
                                :download="'salida-' + (tabs[activeIdx] ? tabs[activeIdx].id : '') + '.pdf'"
                                class="inline-flex items-center gap-2 px-5 py-3 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-black active:bg-gray-800"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Descargar PDF
                            </a>
                        </div>
                    </div>

                    {{-- Formulario (visible cuando NO está guardada) --}}
                    <div x-show="tabs[activeIdx] && !tabs[activeIdx].saved">

                        {{-- ─── DATOS GENERALES ─────────────────── --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6">

                            {{-- Quién recibe --}}
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Quién recibe <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="w-full border rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                    :class="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.nombre_cabo
                                        ? 'border-red-400 bg-red-50'
                                        : 'border-gray-300'"
                                    x-model="tabs[activeIdx].nombre_cabo"
                                    placeholder="Escribe o selecciona un nombre"
                                    autocomplete="off"
                                    @focus="tabs[activeIdx].showResponsables = true"
                                    @blur="setTimeout(() => { if(tabs[activeIdx]) tabs[activeIdx].showResponsables = false }, 200)"
                                    @input="tabs[activeIdx].showResponsables = true"
                                >

                                {{-- Dropdown personalizado --}}
                                <div
                                    x-show="tabs[activeIdx] && tabs[activeIdx].showResponsables && responsablesFiltrados().length > 0"
                                    class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto z-50"
                                >
                                    <template x-for="nombre in responsablesFiltrados()" :key="nombre">
                                        <button
                                            type="button"
                                            class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                                            @mousedown.prevent="tabs[activeIdx].nombre_cabo = nombre; tabs[activeIdx].showResponsables = false"
                                        >
                                            <span x-text="nombre"></span>
                                        </button>
                                    </template>
                                </div>

                                <p
                                    x-show="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.nombre_cabo"
                                    class="text-red-600 text-xs mt-1"
                                    x-text="tabs[activeIdx] ? (tabs[activeIdx].errors.nombre_cabo || '') : ''"
                                ></p>
                            </div>

                            {{-- Destino --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Destino <span class="text-red-500">*</span>
                                </label>
                                <select
                                    class="w-full border rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                    :class="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.destino
                                        ? 'border-red-400 bg-red-50'
                                        : 'border-gray-300'"
                                    x-model="tabs[activeIdx].destino_proyecto_id"
                                >
                                    <option value="">-- Selecciona destino --</option>
                                    <template x-for="d in destinos" :key="d.IdProyecto">
                                        <option :value="d.IdProyecto" x-text="'[' + d.Tipo + '] ' + d.Proyecto"></option>
                                    </template>
                                </select>
                                <p
                                    x-show="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.destino"
                                    class="text-red-600 text-xs mt-1"
                                    x-text="tabs[activeIdx] ? (tabs[activeIdx].errors.destino || '') : ''"
                                ></p>
                            </div>

                            {{-- Nivel --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nivel <span class="text-red-500">*</span>
                                </label>
                                <select
                                    class="w-full border rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                    :class="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.nivel
                                        ? 'border-red-400 bg-red-50'
                                        : 'border-gray-300'"
                                    x-model="tabs[activeIdx].nivel"
                                    @change="onNivelChange()"
                                >
                                    <option value="">-- Selecciona nivel --</option>
                                    <optgroup label="Sótanos (sin departamento)">
                                        <option value="S1">S1</option>
                                        <option value="S2">S2</option>
                                        <option value="S3">S3</option>
                                        <option value="S4">S4</option>
                                        <option value="S5">S5</option>
                                    </optgroup>
                                    <optgroup label="Áreas comunes (sin departamento)">
                                        <option value="ROOFTOP">ROOFTOP GARDEN</option>
                                        <option value="PASILLOS">PASILLOS</option>
                                        <option value="CIMENTACION">CIMENTACIÓN</option>
                                        <option value="PB">PB</option>
                                        <option value="GYM">GYM</option>
                                        <option value="AREAS_COMUNES">ÁREAS COMUNES</option>
                                    </optgroup>
                                    <optgroup label="Niveles">
                                        @for($i = 1; $i <= 13; $i++)
                                            <option value="L{{ $i }}">L{{ $i }}</option>
                                        @endfor
                                    </optgroup>
                                </select>
                                <p
                                    x-show="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.nivel"
                                    class="text-red-600 text-xs mt-1"
                                    x-text="tabs[activeIdx] ? (tabs[activeIdx].errors.nivel || '') : ''"
                                ></p>
                            </div>

                            {{-- Departamento --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Departamento
                                    <span x-show="!sinDepartamento" class="text-red-500">*</span>
                                </label>
                                <select
                                    class="w-full border rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed"
                                    :class="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.departamento
                                        ? 'border-red-400 bg-red-50'
                                        : 'border-gray-300'"
                                    x-model="tabs[activeIdx].departamento"
                                    :disabled="sinDepartamento"
                                >
                                    <option value="">
                                        -- <template x-if="sinDepartamento"><span>No aplica</span></template><template x-if="!sinDepartamento"><span>Selecciona</span></template> --
                                    </option>
                                    @for($i = 1; $i <= 8; $i++)
                                        <option value="D{{ $i }}">D{{ $i }}</option>
                                    @endfor
                                </select>
                                <p class="text-xs text-gray-500 mt-1" x-show="sinDepartamento">
                                    Este nivel no requiere departamento.
                                </p>
                            </div>

                            {{-- Observaciones --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                                <textarea
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                    rows="2"
                                    maxlength="500"
                                    x-model="tabs[activeIdx].observaciones"
                                    placeholder="Ej: nombre de chalán / material incompleto / cuidado con golpes"
                                ></textarea>
                            </div>
                        </div>

                        {{-- ─── BUSCADOR DE PRODUCTOS ────────────── --}}
                        <div class="border border-gray-200 rounded-xl p-4 md:p-5 mb-6">
                            <h3 class="font-semibold text-gray-800 mb-1">Agregar productos</h3>
                            <p class="text-sm text-gray-500 mb-4">Busca por ID o descripción y agrega la cantidad deseada.</p>

                            <p
                                x-show="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.items"
                                class="mb-3 p-3 bg-red-100 text-red-700 rounded-lg text-sm font-medium"
                                x-text="tabs[activeIdx] ? (tabs[activeIdx].errors.items || '') : ''"
                            ></p>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">

                                {{-- Campo de búsqueda --}}
                                <div class="md:col-span-2 relative">
                                    <label class="block text-xs text-gray-600 mb-1">Buscar por ID o descripción</label>
                                    <input
                                        type="text"
                                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                        placeholder="Ej: 120 ó cemento"
                                        x-model="tabs[activeIdx].q"
                                        @input.debounce.300ms="buscar()"
                                        autocomplete="off"
                                    >

                                    {{-- Dropdown resultados --}}
                                    <div
                                        x-show="tabs[activeIdx] && tabs[activeIdx].resultados && tabs[activeIdx].resultados.length > 0"
                                        class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-auto z-50"
                                    >
                                        <template x-for="p in (tabs[activeIdx] ? tabs[activeIdx].resultados : [])" :key="p.id">
                                            <button
                                                type="button"
                                                class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 border-b border-gray-100 last:border-b-0 first:rounded-t-xl last:rounded-b-xl"
                                                @click="seleccionar(p)"
                                            >
                                                <div class="font-semibold text-gray-800" x-text="p.descripcion"></div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    <template x-if="p.insumo_id">
                                                        <span>Cód: <strong x-text="p.insumo_id"></strong> &nbsp;|&nbsp;</span>
                                                    </template>
                                                    Unidad: <span x-text="p.unidad"></span> &nbsp;|&nbsp;
                                                    Exist: <span x-text="p.cantidad"></span>
                                                    <span x-show="p.devolvible == 1" class="text-blue-600"> | Retornable</span>
                                                </div>
                                            </button>
                                        </template>
                                    </div>

                                    <div
                                        class="text-xs text-gray-400 mt-1"
                                        x-show="tabs[activeIdx] && tabs[activeIdx].buscando"
                                    >Buscando...</div>

                                    {{-- Mensaje sin resultados --}}
                                    <div
                                        class="text-sm text-gray-500 mt-1 px-1"
                                        x-show="tabs[activeIdx]
                                            && (tabs[activeIdx].q || '').trim() !== ''
                                            && !tabs[activeIdx].buscando
                                            && !tabs[activeIdx].selected
                                            && (!tabs[activeIdx].resultados || tabs[activeIdx].resultados.length === 0)"
                                    >No se encontraron resultados.</div>
                                </div>

                                {{-- Cantidad --}}
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Cantidad</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                        x-model="tabs[activeIdx].qty"
                                        :max="tabs[activeIdx] && tabs[activeIdx].selected ? tabs[activeIdx].selected.cantidad : null"
                                        placeholder="Ej: 5"
                                    >
                                </div>
                            </div>

                            {{-- Checkbox retornable --}}
                            <div class="mt-4 flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    x-model="tabs[activeIdx].devolvible"
                                    class="w-5 h-5 rounded border-gray-300 text-gray-900"
                                    id="devolvible-check"
                                >
                                <label for="devolvible-check" class="text-sm text-gray-700 cursor-pointer">
                                    Producto retornable (préstamo)
                                </label>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    class="px-5 py-3 text-sm font-medium border rounded-xl bg-gray-800 text-white hover:bg-gray-900 active:bg-black"
                                    @click="agregarItem()"
                                >
                                    Agregar producto
                                </button>
                            </div>

                            {{-- Tabla de items --}}
                            <template x-if="tabs[activeIdx] && tabs[activeIdx].items && tabs[activeIdx].items.length > 0">
                                <div class="mt-5 overflow-x-auto rounded-xl border border-gray-200">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50 border-b border-gray-200">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">ID</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Descripción</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Cant.</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Unidad</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Retornable</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Destinos</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Quitar</th>
                                            </tr>
                                        </thead>
                                        {{-- x-for en <tbody> permite múltiples <tr> por ítem --}}
                                        <template x-for="(it, itIdx) in tabs[activeIdx].items" :key="itIdx">
                                            <tbody>
                                                {{-- fila principal --}}
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 text-gray-700" x-text="it.inventario_id"></td>
                                                    <td class="px-4 py-3 text-gray-800 font-medium" x-text="it.descripcion"></td>
                                                    <td class="px-4 py-3 text-gray-700" x-text="it.cantidad"></td>
                                                    <td class="px-4 py-3 text-gray-700" x-text="it.unidad"></td>
                                                    <td class="px-4 py-3">
                                                        <span
                                                            x-text="it.devolvible ? 'Sí' : 'No'"
                                                            :class="it.devolvible ? 'text-blue-700 font-medium' : 'text-gray-500'"
                                                        ></span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                                            {{-- Texto con el/los nivel(es) actual(es) --}}
                                                            <span style="font-size:13px;color:#374151;">
                                                                <template x-if="it.destinos.length === 1">
                                                                    <span>
                                                                        <span x-text="it.destinos[0].nivel || '—'"></span><span x-show="it.destinos[0].departamento">/<span x-text="it.destinos[0].departamento"></span></span>
                                                                    </span>
                                                                </template>
                                                                <template x-if="it.destinos.length > 1">
                                                                    <span style="color:#1d4ed8;font-weight:600;" x-text="it.destinos.length + ' niveles'"></span>
                                                                </template>
                                                            </span>

                                                            {{-- Botón cambiar / ocultar --}}
                                                            <button
                                                                type="button"
                                                                @click="it.mostrarDestinos = !it.mostrarDestinos"
                                                                style="font-size:11px;padding:2px 9px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;color:#374151;white-space:nowrap;"
                                                            >
                                                                <span x-text="it.mostrarDestinos ? 'Ocultar' : 'Cambiar nivel'"></span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <button
                                                            type="button"
                                                            class="px-3 py-1.5 text-xs border rounded-lg hover:bg-red-50 hover:border-red-300 hover:text-red-700 transition-colors"
                                                            @click="quitarItem(itIdx)"
                                                        >Quitar</button>
                                                    </td>
                                                </tr>

                                                {{-- fila de distribución de destinos --}}
                                                <tr x-show="it.mostrarDestinos" style="background:#f8fafc;">
                                                    <td colspan="7" style="padding:12px 16px;">
                                                        <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">
                                                            Distribución por nivel / departamento
                                                        </div>
                                                        <table style="width:100%;font-size:12px;border-collapse:collapse;margin-bottom:10px;">
                                                            <thead>
                                                                <tr style="background:#e5e7eb;">
                                                                    <th style="padding:6px 10px;text-align:left;border:1px solid #d1d5db;width:28%;">Nivel</th>
                                                                    <th style="padding:6px 10px;text-align:left;border:1px solid #d1d5db;width:28%;">Departamento</th>
                                                                    <th style="padding:6px 10px;text-align:left;border:1px solid #d1d5db;width:28%;">Cantidad</th>
                                                                    <th style="padding:6px 10px;text-align:center;border:1px solid #d1d5db;width:16%;"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <template x-for="(dest, di) in it.destinos" :key="di">
                                                                    <tr>
                                                                        <td style="border:1px solid #d1d5db;padding:4px 6px;">
                                                                            <select
                                                                                x-model="dest.nivel"
                                                                                @change="if(destinoSinDepto(dest.nivel)) dest.departamento=''"
                                                                                style="width:100%;border:none;background:transparent;font-size:12px;padding:2px;"
                                                                            >
                                                                                <option value="">-- Nivel --</option>
                                                                                <optgroup label="Sótanos">
                                                                                    <option>S1</option><option>S2</option><option>S3</option><option>S4</option><option>S5</option>
                                                                                </optgroup>
                                                                                <optgroup label="Áreas comunes">
                                                                                    <option value="ROOFTOP">ROOFTOP</option>
                                                                                    <option value="PASILLOS">PASILLOS</option>
                                                                                    <option value="CIMENTACION">CIMENTACIÓN</option>
                                                                                    <option value="PB">PB</option>
                                                                                    <option value="GYM">GYM</option>
                                                                                    <option value="AREAS_COMUNES">ÁREAS COMUNES</option>
                                                                                </optgroup>
                                                                                <optgroup label="Niveles">
                                                                                    @for($i = 1; $i <= 13; $i++)
                                                                                        <option value="L{{ $i }}">L{{ $i }}</option>
                                                                                    @endfor
                                                                                </optgroup>
                                                                            </select>
                                                                        </td>
                                                                        <td style="border:1px solid #d1d5db;padding:4px 6px;">
                                                                            <select
                                                                                x-model="dest.departamento"
                                                                                :disabled="destinoSinDepto(dest.nivel)"
                                                                                style="width:100%;border:none;background:transparent;font-size:12px;padding:2px;"
                                                                                :style="destinoSinDepto(dest.nivel) ? 'color:#9ca3af;' : ''"
                                                                            >
                                                                                <option value="">-- Depto --</option>
                                                                                @for($i = 1; $i <= 8; $i++)
                                                                                    <option value="D{{ $i }}">D{{ $i }}</option>
                                                                                @endfor
                                                                            </select>
                                                                        </td>
                                                                        <td style="border:1px solid #d1d5db;padding:4px 6px;">
                                                                            <input
                                                                                type="number"
                                                                                step="0.01"
                                                                                min="0.01"
                                                                                x-model="dest.cantidad"
                                                                                style="width:100%;border:none;background:transparent;font-size:12px;padding:2px;"
                                                                            >
                                                                        </td>
                                                                        <td style="border:1px solid #d1d5db;padding:4px 6px;text-align:center;">
                                                                            <button
                                                                                type="button"
                                                                                x-show="it.destinos.length > 1"
                                                                                @click="quitarDestino(itIdx, di)"
                                                                                style="color:#ef4444;font-weight:bold;font-size:14px;cursor:pointer;background:none;border:none;padding:0 4px;"
                                                                                title="Quitar destino"
                                                                            >×</button>
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                                                            <button
                                                                type="button"
                                                                @click="agregarDestino(itIdx)"
                                                                style="font-size:12px;padding:5px 12px;border:1px dashed #6b7280;border-radius:8px;background:white;cursor:pointer;color:#374151;"
                                                            >+ Agregar nivel</button>
                                                            <span style="font-size:12px;"
                                                                :style="Math.abs(it.destinos.reduce((s,d)=>s+(+d.cantidad||0),0) - it.cantidad) > 0.01
                                                                    ? 'color:#ef4444;font-weight:600;'
                                                                    : 'color:#16a34a;font-weight:600;'"
                                                            >
                                                                Distribuido:
                                                                <span x-text="it.destinos.reduce((s,d)=>s+(+d.cantidad||0),0).toFixed(2)"></span>
                                                                / <span x-text="parseFloat(it.cantidad).toFixed(2)"></span>
                                                                <span x-text="it.unidad"></span>
                                                                <span x-show="Math.abs(it.destinos.reduce((s,d)=>s+(+d.cantidad||0),0) - it.cantidad) > 0.01"> ⚠ No cuadra</span>
                                                                <span x-show="Math.abs(it.destinos.reduce((s,d)=>s+(+d.cantidad||0),0) - it.cantidad) <= 0.01"> ✓</span>
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </template>
                                    </table>
                                </div>
                            </template>
                        </div>

                        {{-- ─── FIRMA DIGITAL ────────────────────── --}}
                        <div class="border border-gray-200 rounded-xl p-4 md:p-5 mb-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="font-semibold text-gray-800">
                                    Firma digital <span class="text-red-500">*</span>
                                    <span
                                        x-show="tabs[activeIdx] && tabs[activeIdx].firma_base64"
                                        class="ml-2 text-sm text-green-600 font-normal"
                                    >✅ Lista</span>
                                </div>
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm border rounded-lg bg-gray-100 hover:bg-gray-200 active:bg-gray-300"
                                    @click="toggleFirma()"
                                >
                                    <span x-text="tabs[activeIdx] && tabs[activeIdx].showFirma ? 'Ocultar' : 'Mostrar'"></span>
                                </button>
                            </div>

                            <p
                                x-show="tabs[activeIdx] && tabs[activeIdx].errors && tabs[activeIdx].errors.firma"
                                class="text-red-600 text-sm mb-3"
                                x-text="tabs[activeIdx] ? (tabs[activeIdx].errors.firma || '') : ''"
                            ></p>

                            {{-- Canvas area (visible cuando showFirma = true) --}}
                            <div x-show="tabs[activeIdx] && tabs[activeIdx].showFirma">
                                <div class="rounded-xl p-2 inline-block w-full" style="border: 2px solid #3b82f6;">
                                    <canvas
                                        :id="'firma-canvas-' + (tabs[activeIdx] ? tabs[activeIdx].id : 'none')"
                                        class="rounded-lg w-full bg-white touch-none block"
                                        width="700"
                                        height="300"
                                    ></canvas>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Firma con el dedo o el mouse en el recuadro blanco.</p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="px-4 py-2 text-sm border rounded-lg bg-white hover:bg-gray-50 active:bg-gray-100"
                                        @click="limpiarFirma()"
                                    >Limpiar</button>
                                    <button
                                        type="button"
                                        class="px-4 py-2 text-sm border rounded-lg bg-gray-800 text-white hover:bg-gray-900 active:bg-black"
                                        @click="usarFirma()"
                                    >Usar firma ✓</button>
                                </div>

                                <p
                                    class="text-xs mt-2"
                                    :class="tabs[activeIdx] && tabs[activeIdx].firma_base64 ? 'text-green-700' : 'text-gray-500'"
                                    x-text="tabs[activeIdx] ? tabs[activeIdx].firmaMsg : ''"
                                ></p>
                            </div>

                            {{-- Resumen cuando está oculta --}}
                            <div
                                x-show="tabs[activeIdx] && !tabs[activeIdx].showFirma"
                                class="text-sm text-gray-500"
                            >
                                <span x-show="!tabs[activeIdx] || !tabs[activeIdx].firma_base64">
                                    Presiona "Mostrar" para firmar.
                                </span>
                                <span
                                    x-show="tabs[activeIdx] && tabs[activeIdx].firma_base64"
                                    class="text-green-700 font-medium"
                                >
                                    ✅ Firma capturada. Lista para guardar.
                                </span>
                            </div>
                        </div>

                    </div>{{-- /!saved --}}
                </div>{{-- /tabs content --}}

                {{-- ══════════════════════════════════════════════
                     FOOTER: ACCIONES
                ══════════════════════════════════════════════ --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-t pt-5 mt-2">

                    <div class="text-sm text-gray-600">
                        <span x-text="tabs.length"></span> salida(s) en proceso
                        <span x-show="savedCount > 0" class="ml-2 text-green-700 font-semibold">
                            — <span x-text="savedCount"></span> guardada(s)
                        </span>
                        <span x-show="tabsConError > 0" class="ml-2 text-red-600 font-semibold">
                            — <span x-text="tabsConError"></span> con error(es)
                        </span>
                    </div>

                    <button
                        type="button"
                        class="px-6 py-3 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-black active:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                        :disabled="guardando || allSaved"
                        @click="guardarTodos()"
                    >
                        <span x-show="!guardando">
                            <span x-show="tabs.length === 1">Guardar salida</span>
                            <span x-show="tabs.length > 1">
                                Guardar todas (<span x-text="tabs.filter(t => !t.saved).length"></span>)
                            </span>
                        </span>
                        <span x-show="guardando">Guardando...</span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <style>[x-cloak] { display: none !important; }</style>

    {{-- SignaturePad library --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <script>
    function salidasPageUI() {
        return {

            // ── Catálogos ────────────────────────────────────
            destinos: [],
            responsables: [],
            cargandoCatalogos: true,

            // ── Pestañas ─────────────────────────────────────
            tabs: [],
            activeIdx: 0,
            guardando: false,

            // ── Signature pads (por tab.id) ───────────────────
            signaturePads: {},

            // ── Computed ─────────────────────────────────────
            get sinDepartamento() {
                const nivel = this.tabs[this.activeIdx]?.nivel || '';
                if (/^S[1-5]$/.test(nivel)) return true;
                return ['ROOFTOP','PASILLOS','CIMENTACION','PB','GYM','AREAS_COMUNES'].includes(nivel);
            },

            get savedCount() {
                return this.tabs.filter(t => t.saved).length;
            },

            get allSaved() {
                return this.tabs.length > 0 && this.tabs.every(t => t.saved);
            },

            get tabsConError() {
                return this.tabs.filter(t => t.errors && Object.keys(t.errors).length > 0).length;
            },

            // ── Inicialización ────────────────────────────────
            init() {
                this.agregarTab();
                this.cargarCatalogos();
            },

            responsablesFiltrados() {
                const q = (this.tabs[this.activeIdx]?.nombre_cabo || '').toLowerCase().trim();
                if (!q) return this.responsables.slice(0, 30);
                return this.responsables
                    .filter(n => n.toLowerCase().includes(q))
                    .slice(0, 20);
            },

            async cargarCatalogos() {
                this.cargandoCatalogos = true;
                try {
                    const [resD, resR] = await Promise.all([
                        fetch("{{ route('salidas.destinos') }}", { headers: { 'Accept': 'application/json' } }),
                        fetch("{{ route('salidas.responsables') }}", { headers: { 'Accept': 'application/json' } }),
                    ]);
                    if (resD.ok) this.destinos = await resD.json();
                    if (resR.ok) this.responsables = await resR.json();
                } catch (e) {
                    console.error('Error cargando catálogos:', e);
                } finally {
                    this.cargandoCatalogos = false;
                }
            },

            // ── Gestión de pestañas ───────────────────────────
            crearTab() {
                return {
                    id: Date.now() + '_' + Math.floor(Math.random() * 99999),
                    nombre_cabo: '',
                    showResponsables: false,
                    destino_proyecto_id: '',
                    nivel: '',
                    departamento: '',
                    observaciones: '',
                    items: [],
                    firma_base64: '',
                    firmaMsg: 'Firma pendiente.',
                    showFirma: false,
                    // buscador
                    q: '',
                    resultados: [],
                    selected: null,
                    qty: '',
                    devolvible: false,
                    buscando: false,
                    // estado
                    errors: {},
                    saved: false,
                    pdfUrl: null,
                };
            },

            agregarTab() {
                this.tabs.push(this.crearTab());
                this.activeIdx = this.tabs.length - 1;
            },

            activarTab(idx) {
                this.activeIdx = idx;
                // Si la firma ya está visible en esta pestaña, reinit el pad
                this.$nextTick(() => {
                    const tab = this.tabs[idx];
                    if (tab?.showFirma) {
                        this.initSignaturePad(tab.id);
                    }
                });
            },

            cerrarTab(idx) {
                if (this.tabs.length <= 1) return;
                const tabId = this.tabs[idx].id;
                delete this.signaturePads[tabId];
                this.tabs.splice(idx, 1);
                if (this.activeIdx >= this.tabs.length) {
                    this.activeIdx = this.tabs.length - 1;
                }
            },

            tieneErrores(idx) {
                const e = this.tabs[idx]?.errors;
                return e && Object.keys(e).length > 0;
            },

            // ── Nivel / Departamento ──────────────────────────
            onNivelChange() {
                if (this.sinDepartamento) {
                    this.tabs[this.activeIdx].departamento = '';
                }
            },

            // ── Firma digital ─────────────────────────────────
            toggleFirma() {
                const tab = this.tabs[this.activeIdx];
                if (!tab) return;
                tab.showFirma = !tab.showFirma;
                if (tab.showFirma) {
                    this.$nextTick(() => this.initSignaturePad(tab.id));
                }
            },

            initSignaturePad(tabId) {
                if (this.signaturePads[tabId]) return;
                const canvas = document.getElementById('firma-canvas-' + tabId);
                if (!canvas) return;
                this.signaturePads[tabId] = new SignaturePad(canvas, {
                    minWidth: 1,
                    maxWidth: 2.5,
                    penColor: '#111827',
                });
            },

            limpiarFirma() {
                const tab = this.tabs[this.activeIdx];
                if (!tab) return;
                const pad = this.signaturePads[tab.id];
                if (pad) pad.clear();
                tab.firma_base64 = '';
                tab.firmaMsg = 'Firma limpia. Firma de nuevo y pulsa "Usar firma".';
            },

            usarFirma() {
                const tab = this.tabs[this.activeIdx];
                if (!tab) return;
                const pad = this.signaturePads[tab.id];
                if (!pad || pad.isEmpty()) {
                    tab.firmaMsg = 'Primero firma en el recuadro blanco.';
                    alert('Primero firma en el recuadro.');
                    return;
                }
                tab.firma_base64 = pad.toDataURL('image/png');
                tab.firmaMsg = '✅ Firma lista (se enviará al guardar).';
                tab.showFirma = false;
                if (tab.errors.firma) delete tab.errors.firma;
            },

            // ── Buscador de productos ─────────────────────────
            async buscar() {
                const tab = this.tabs[this.activeIdx];
                if (!tab) return;

                const term = (tab.q || '').trim();
                if (!term) {
                    tab.resultados = [];
                    tab.selected = null;
                    return;
                }

                const clean = term.startsWith('#') ? term.slice(1).trim() : term;
                tab.buscando = true;

                try {
                    const url = "{{ route('salidas.buscar') }}"
                        + '?q=' + encodeURIComponent(clean)
                        + '&_=' + Date.now();
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });
                    tab.resultados = await res.json();
                } catch (e) {
                    console.error(e);
                    tab.resultados = [];
                } finally {
                    tab.buscando = false;
                }
            },

            seleccionar(p) {
                const tab = this.tabs[this.activeIdx];
                if (!tab) return;
                tab.selected = p;
                tab.q = '#' + p.id + ' - ' + p.descripcion;
                tab.resultados = [];
                tab.devolvible = Number(p.devolvible) === 1;
                if (!tab.qty) tab.qty = 1;
            },

            agregarItem() {
                const tab = this.tabs[this.activeIdx];
                if (!tab) return;

                if (!tab.selected) {
                    alert('Selecciona un producto de la lista primero.');
                    return;
                }
                if (!tab.nivel) {
                    alert('Selecciona un nivel antes de agregar productos.');
                    return;
                }

                const deptoFinal = this.sinDepartamento ? null : (tab.departamento || null);
                const qty = parseFloat(tab.qty);

                if (!qty || qty <= 0) {
                    alert('Ingresa una cantidad válida mayor a 0.');
                    return;
                }

                const existencia = parseFloat(tab.selected.cantidad);
                if (qty > existencia) {
                    alert('Solo hay ' + tab.selected.cantidad + ' en existencia.');
                    return;
                }

                const existingIdx = tab.items.findIndex(x => x.inventario_id === tab.selected.id);
                if (existingIdx >= 0) {
                    const nueva = parseFloat(tab.items[existingIdx].cantidad) + qty;
                    if (nueva > existencia) {
                        alert('Con esa suma te excedes. Solo hay ' + tab.selected.cantidad + '.');
                        return;
                    }
                    tab.items[existingIdx].cantidad = nueva;
                    tab.items[existingIdx].destinos.push({ nivel: tab.nivel, departamento: deptoFinal, cantidad: qty });
                } else {
                    tab.items.push({
                        inventario_id:   tab.selected.id,
                        descripcion:     tab.selected.descripcion,
                        unidad:          tab.selected.unidad,
                        cantidad:        qty,
                        devolvible:      !!tab.devolvible,
                        destinos:        [{ nivel: tab.nivel, departamento: deptoFinal, cantidad: qty }],
                        mostrarDestinos: false,
                    });
                }

                // Limpiar buscador
                tab.selected = null;
                tab.q = '';
                tab.resultados = [];
                tab.qty = '';
                tab.devolvible = false;

                // Limpiar error de items si existía
                if (tab.errors && tab.errors.items) delete tab.errors.items;
            },

            quitarItem(idx) {
                const tab = this.tabs[this.activeIdx];
                if (tab) tab.items.splice(idx, 1);
            },

            // ── Distribución de destinos por ítem ─────────────
            destinoSinDepto(nivel) {
                if (!nivel) return false;
                if (/^S[1-5]$/.test(nivel)) return true;
                return ['ROOFTOP','PASILLOS','CIMENTACION','PB','GYM','AREAS_COMUNES'].includes(nivel);
            },

            agregarDestino(itIdx) {
                const tab = this.tabs[this.activeIdx];
                const item = tab?.items[itIdx];
                if (!item) return;

                // Split the last destino in half so totals stay balanced
                const last = item.destinos[item.destinos.length - 1];
                const lastQty = parseFloat(last.cantidad) || 0;
                const half = parseFloat((lastQty / 2).toFixed(4));
                last.cantidad = half;

                item.destinos.push({
                    nivel:        last.nivel,
                    departamento: last.departamento,
                    cantidad:     parseFloat((lastQty - half).toFixed(4)),
                });

                item.mostrarDestinos = true; // open sub-form immediately
            },

            quitarDestino(itIdx, di) {
                const item = this.tabs[this.activeIdx]?.items[itIdx];
                if (!item || item.destinos.length <= 1) return;
                item.destinos.splice(di, 1);
            },

            // ── Validación de pestaña ─────────────────────────
            validarTab(tab) {
                const errors = {};
                if (!tab.nombre_cabo)         errors.nombre_cabo = 'Selecciona quién recibe.';
                if (!tab.destino_proyecto_id)  errors.destino     = 'Selecciona un destino.';
                if (!tab.nivel)                errors.nivel       = 'Selecciona un nivel.';
                if (!tab.items.length)         errors.items       = 'Agrega al menos un producto.';
                if (!tab.firma_base64)         errors.firma       = 'Falta la firma digital.';
                return errors;
            },

            // ── Guardar todas las pestañas ────────────────────
            async guardarTodos() {
                if (this.guardando) return;
                this.guardando = true;

                let primerError = -1;

                for (let i = 0; i < this.tabs.length; i++) {
                    const tab = this.tabs[i];
                    if (tab.saved) continue;

                    // Validar frontend
                    tab.errors = this.validarTab(tab);
                    if (Object.keys(tab.errors).length) {
                        if (primerError === -1) primerError = i;
                        continue;
                    }

                    // Construir FormData
                    const fd = new FormData();
                    fd.append('nombre_cabo',          tab.nombre_cabo);
                    fd.append('destino_proyecto_id',  tab.destino_proyecto_id);
                    fd.append('observaciones',        tab.observaciones || '');
                    fd.append('firma_base64',         tab.firma_base64);
                    tab.items.forEach((it, idx) => {
                        fd.append('items[' + idx + '][inventario_id]', it.inventario_id);
                        fd.append('items[' + idx + '][cantidad]',       it.cantidad);
                        fd.append('items[' + idx + '][unidad]',         it.unidad);
                        fd.append('items[' + idx + '][devolvible]',     it.devolvible ? 1 : 0);
                        (it.destinos || []).forEach((dest, di) => {
                            fd.append('items[' + idx + '][destinos][' + di + '][nivel]',        dest.nivel || '');
                            fd.append('items[' + idx + '][destinos][' + di + '][departamento]', dest.departamento || '');
                            fd.append('items[' + idx + '][destinos][' + di + '][cantidad]',     dest.cantidad);
                        });
                    });

                    // POST al backend
                    try {
                        const res = await fetchConCsrf("{{ route('salidas.store') }}", {
                            method: 'POST',
                            headers: {
                                'Accept':           'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: fd,
                        });

                        let data = null;
                        try { data = await res.json(); } catch (_) {}

                        if (!res.ok) {
                            if (res.status === 422 && data?.errors) {
                                const first = Object.values(data.errors)[0]?.[0] || 'Revisa los campos.';
                                tab.errors.general = first;
                            } else if (res.status === 419) {
                                tab.errors.general = 'Sesión expirada (419). Recarga la página.';
                            } else {
                                tab.errors.general = data?.message || 'Error al guardar.';
                            }
                            if (primerError === -1) primerError = i;
                            continue;
                        }

                        if (data?.ok) {
                            tab.saved  = true;
                            tab.pdfUrl = data.pdf_url;
                            tab.errors = {};
                        } else {
                            tab.errors.general = data?.message || 'Respuesta inesperada del servidor.';
                            if (primerError === -1) primerError = i;
                        }

                    } catch (err) {
                        console.error('Error en salida ' + (i + 1) + ':', err);
                        tab.errors.general = 'Error de red. Verifica tu conexión.';
                        if (primerError === -1) primerError = i;
                    }
                }

                this.guardando = false;

                // Ir a la primera pestaña con error
                if (primerError >= 0) {
                    this.activeIdx = primerError;
                }
            },
        };
    }
    </script>

</x-app-layout>
