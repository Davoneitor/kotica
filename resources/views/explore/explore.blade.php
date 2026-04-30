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
                Vista de exploracion (solo lectura): salidas, inventario, ordenes (ERP) y graficas.
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="exploreUI()" x-init="init()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Tabs --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-3">
                <div class="flex gap-2 overflow-auto">

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='ent' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='ent'; cargarEntradas()">
                        Entradas
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='mov' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='mov'; cargarMovimientos()">
                        Salidas
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='escom' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='escom'; cargarEscombro()">
                        Control Salida Camiones
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='trans' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='trans'; cargarTransferencias()">
                        Transferencias
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='inv' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='inv'; cargarInventario()">
                        Inventario
                    </button>

                    <button class="px-4 py-2 rounded border text-sm whitespace-nowrap"
                            :class="tab==='oc' ? 'bg-gray-900 text-white' : 'bg-white'"
                            @click="tab='oc'; cargarOC()">
                        Ordenes compra (ERP)
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
                                   @input.debounce.400ms="mov.vista === 'tabla' ? cargarSalidasTabla() : cargarMovimientos()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Desde</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="mov.desde"
                                   @change="mov.vista === 'tabla' ? cargarSalidasTabla() : cargarMovimientos()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="mov.hasta"
                                   @change="mov.vista === 'tabla' ? cargarSalidasTabla() : cargarMovimientos()">
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-gray-500 font-medium">Vista:</span>
                        <button @click="mov.vista='tarjetas'; cargarMovimientos()"
                                :class="mov.vista==='tarjetas' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">Tarjetas</button>
                        <button @click="mov.vista='tabla'; cargarSalidasTabla()"
                                :class="mov.vista==='tabla' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">Tabla</button>
                        <button @click="mov.vista='ajustes'; cargarHistorialAjustes()"
                                :class="mov.vista==='ajustes' ? 'bg-amber-700 text-white' : 'bg-white hover:bg-amber-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">Historial ajustes</button>

                        <div class="ml-auto">
                            <button @click="exportarSalidas()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-sm font-medium transition-colors whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                </svg>
                                Exportar Excel
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="mov.vista==='tarjetas'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
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

                                <div class="flex flex-col items-end gap-1.5 shrink-0">
                                    <div class="flex gap-2">
                                        <a class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100"
                                           :href="'/movimientos/'+m.id+'/pdf'"
                                           target="_blank">
                                            PDF
                                        </a>
                                        <button class="px-3 py-2 text-sm rounded border bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-300 font-medium"
                                                @click="abrirAjuste(m)">
                                            Ajustar
                                        </button>
                                    </div>
                                    <template x-if="m.tiene_ajustes">
                                        <button @click="abrirAjuste(m)"
                                                class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 border border-amber-300 text-amber-800 text-xs font-semibold">
                                            <span>↩</span>
                                            <span x-text="m.num_ajustes + ' ajuste' + (m.num_ajustes > 1 ? 's' : '')"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                        <div class="mt-2 text-sm">
                        <div class="text-gray-500 text-xs">Destino</div>
                        <div class="font-medium">
                            <span x-text="m.destino_nombre || m.destino"></span>
                            <span class="text-gray-500" x-show="m.nivel || m.departamento">
                            � Nivel: <span x-text="m.nivel"></span>
                            � Depto: <span x-text="m.departamento"></span>
                            </span>
                        </div>
                        </div>

                        <div class="mt-2 text-sm">
                            <div class="text-gray-500 text-xs">Obra</div>
                            <div class="font-medium" x-text="m.obra ?? m.obra_id"></div>
                        </div>

                        <div class="mt-2 text-sm">
                            <div class="text-gray-500 text-xs">Fecha</div>
                            <div x-text="formatFecha(m.fecha)"></div>
                        </div>

                        <template x-if="m.observaciones">
                            <div class="mt-2 text-sm">
                                <div class="text-gray-500 text-xs">Observaciones</div>
                                <div class="text-gray-700 italic" x-text="m.observaciones"></div>
                            </div>
                        </template>

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
                                        <div class="text-xs text-gray-600 flex flex-wrap items-center gap-x-2 gap-y-1 mt-0.5">
                                            <span>Cant: <span x-text="d.cantidad"></span> <span x-text="d.unidad"></span></span>
                                            <template x-if="Number(d.devolvible)===1">
                                                <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-semibold">RETORNABLE</span>
                                            </template>
                                        </div>

                                        {{-- Destinos display / editor --}}
                                        <div class="mt-1">
                                            {{-- View mode --}}
                                            <template x-if="editNivel.detalleId !== d.id">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <template x-if="d.destinos && d.destinos.length > 1">
                                                        <div class="text-xs text-blue-700">
                                                            <template x-for="(dest, di) in d.destinos" :key="di">
                                                                <span>
                                                                    <span x-text="dest.nivel"></span><span x-show="dest.departamento">/<span x-text="dest.departamento"></span></span>
                                                                    (<span x-text="dest.cantidad"></span>)
                                                                    <span x-show="di < d.destinos.length - 1"> · </span>
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </template>
                                                    <template x-if="!d.destinos || d.destinos.length <= 1">
                                                        <span class="text-xs text-gray-600">
                                                            Nivel: <span x-text="d.clasificacion || '—'"></span>
                                                            <span x-show="d.clasificacion_d"> · Depto: <span x-text="d.clasificacion_d"></span></span>
                                                        </span>
                                                    </template>
                                                    <button
                                                        type="button"
                                                        @click="abrirEditNivel(d)"
                                                        style="font-size:11px;padding:2px 8px;border:1px solid #d1d5db;border-radius:6px;background:white;cursor:pointer;color:#374151;"
                                                    >Editar nivel</button>
                                                </div>
                                            </template>

                                            {{-- Edit mode --}}
                                            <template x-if="editNivel.detalleId === d.id">
                                                <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-top:4px;">
                                                    <div style="font-size:12px;font-weight:600;margin-bottom:8px;color:#374151;">Editar destinos — <span x-text="d.descripcion"></span></div>

                                                    <table style="width:100%;font-size:12px;border-collapse:collapse;margin-bottom:8px;">
                                                        <thead>
                                                            <tr style="background:#e5e7eb;">
                                                                <th style="padding:5px 8px;border:1px solid #d1d5db;text-align:left;width:30%;">Nivel</th>
                                                                <th style="padding:5px 8px;border:1px solid #d1d5db;text-align:left;width:30%;">Departamento</th>
                                                                <th style="padding:5px 8px;border:1px solid #d1d5db;text-align:left;width:28%;">Cantidad</th>
                                                                <th style="padding:5px 8px;border:1px solid #d1d5db;width:12%;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <template x-for="(dest, di) in editNivel.destinos" :key="di">
                                                                <tr>
                                                                    <td style="border:1px solid #d1d5db;padding:3px 6px;">
                                                                        <select x-model="dest.nivel" @change="if(editNivelSinDepto(dest.nivel)) dest.departamento=''" style="width:100%;border:none;background:transparent;font-size:12px;">
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
                                                                    <td style="border:1px solid #d1d5db;padding:3px 6px;">
                                                                        <select x-model="dest.departamento" :disabled="editNivelSinDepto(dest.nivel)" style="width:100%;border:none;background:transparent;font-size:12px;" :style="editNivelSinDepto(dest.nivel)?'color:#9ca3af;':''">
                                                                            <option value="">-- Depto --</option>
                                                                            @for($i = 1; $i <= 8; $i++)
                                                                                <option value="D{{ $i }}">D{{ $i }}</option>
                                                                            @endfor
                                                                        </select>
                                                                    </td>
                                                                    <td style="border:1px solid #d1d5db;padding:3px 6px;">
                                                                        <input type="number" step="0.01" min="0.01" x-model="dest.cantidad" style="width:100%;border:none;background:transparent;font-size:12px;">
                                                                    </td>
                                                                    <td style="border:1px solid #d1d5db;padding:3px 6px;text-align:center;">
                                                                        <button type="button" x-show="editNivel.destinos.length > 1" @click="quitarDestinoEdit(di)" style="color:#ef4444;font-weight:bold;font-size:14px;cursor:pointer;background:none;border:none;">×</button>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </table>

                                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                                                        <button type="button" @click="agregarDestinoEdit()" style="font-size:11px;padding:3px 10px;border:1px dashed #6b7280;border-radius:6px;background:white;cursor:pointer;">+ Agregar nivel</button>
                                                        <span style="font-size:11px;"
                                                            :style="Math.abs(editNivel.destinos.reduce((s,d)=>s+(+d.cantidad||0),0) - (editNivel.totalCantidad||0)) > 0.01 ? 'color:#ef4444;font-weight:600;' : 'color:#16a34a;font-weight:600;'"
                                                        >
                                                            <span x-text="editNivel.destinos.reduce((s,d)=>s+(+d.cantidad||0),0).toFixed(2)"></span>
                                                            / <span x-text="(editNivel.totalCantidad||0).toFixed(2)"></span>
                                                            <span x-show="Math.abs(editNivel.destinos.reduce((s,d)=>s+(+d.cantidad||0),0) - (editNivel.totalCantidad||0)) > 0.01"> ⚠ No cuadra</span>
                                                            <span x-show="Math.abs(editNivel.destinos.reduce((s,d)=>s+(+d.cantidad||0),0) - (editNivel.totalCantidad||0)) <= 0.01"> ✓</span>
                                                        </span>
                                                    </div>

                                                    <div x-show="editNivel.error" style="color:#ef4444;font-size:11px;margin-bottom:6px;" x-text="editNivel.error"></div>

                                                    <div style="display:flex;gap:8px;">
                                                        <button type="button" @click="guardarEditNivel(d.id)" :disabled="editNivel.guardando"
                                                            style="font-size:12px;padding:4px 14px;border-radius:6px;background:#1f2937;color:white;border:none;cursor:pointer;opacity:1;"
                                                            :style="editNivel.guardando ? 'opacity:0.5;cursor:not-allowed;' : ''"
                                                        ><span x-text="editNivel.guardando ? 'Guardando...' : 'Guardar'"></span></button>
                                                        <button type="button" @click="cerrarEditNivel()" style="font-size:12px;padding:4px 14px;border-radius:6px;background:white;border:1px solid #d1d5db;cursor:pointer;">Cancelar</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ========================= --}}
                {{-- TABLA DE SALIDAS --}}
                {{-- ========================= --}}
                <div x-show="mov.vista==='tabla'" class="space-y-3">

                    {{-- Total general --}}
                    <div class="bg-gray-900 text-white rounded-lg px-4 py-3 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="text-sm font-medium">Costo total del almacén (salidas filtradas)</div>
                            <button @click="toggleExpandirTodoSalidas()"
                                    class="px-2 py-1 rounded text-xs border border-white/30 hover:bg-white/10 transition-colors whitespace-nowrap"
                                    x-text="todosSalidasExpandidos() ? 'Colapsar todo' : 'Expandir todo'"></button>
                        </div>
                        <div class="text-2xl font-extrabold" x-text="'$' + formatMoney(totalSalidas())"></div>
                    </div>

                    {{-- Sección 1: Salidas --}}
                    <div x-show="salidasTablaData.length > 0" style="border:2px solid #4f46e5;border-radius:0.5rem;overflow:hidden">
                        {{-- Header colapsable --}}
                        <div @click="seccionSalidasAbierta.salidas = !seccionSalidasAbierta.salidas"
                             class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                             style="background:#4f46e5;color:#fff">
                            <div class="flex items-center gap-2">
                                <svg :class="seccionSalidasAbierta.salidas ? 'rotate-90' : ''"
                                     class="w-4 h-4 transition-transform duration-150"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="font-bold text-sm">🔴 Salidas</span>
                                <span class="text-xs opacity-75" x-text="'(' + salidasTablaData.length + ' registros)'"></span>
                            </div>
                            <div class="font-bold tabular-nums" x-text="'$' + formatMoney(totalSalidas())"></div>
                        </div>
                        {{-- Tabla --}}
                        <div x-show="seccionSalidasAbierta.salidas" class="overflow-x-auto">
                            <div x-show="loading" class="p-4 text-sm text-gray-500">Cargando tabla...</div>
                            <table x-show="!loading" class="w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase" style="background:#ede9fe">
                                    <tr>
                                        <th class="w-8 px-2 py-2"></th>
                                        <th class="px-3 py-2 text-left">Fecha</th>
                                        <th class="px-3 py-2 text-left">Código</th>
                                        <th class="px-3 py-2 text-left">Descripción</th>
                                        <th class="px-3 py-2 text-left">Unidad</th>
                                        <th class="px-3 py-2 text-left">Destino</th>
                                        <th class="px-3 py-2 text-right">Cantidad</th>
                                        <th class="px-3 py-2 text-right">P.U.</th>
                                        <th class="px-3 py-2 text-right">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in salidasGruposFlat()" :key="row._key">
                                        <tr :style="row._tipo === 'familia'
                                                    ? 'border-top:2px solid #c7d2fe;background:#e0e7ff;cursor:pointer'
                                                    : 'border-top:1px solid #e0e7ff'"
                                            @click="row._tipo === 'familia' && toggleSalidaGrupo(row.familia)">
                                            <td class="px-2 py-2 text-center w-8">
                                                <svg x-show="row._tipo === 'familia'"
                                                     :style="salidasTablaExpandidos[row.familia] ? 'transform:rotate(90deg)' : ''"
                                                     style="display:inline;width:1rem;height:1rem;color:#4f46e5;transition:transform 0.15s"
                                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </td>
                                            <td class="px-3 py-2 text-xs tabular-nums whitespace-nowrap"
                                                :style="row._tipo === 'familia' ? 'color:#818cf8;font-weight:500' : 'color:#6b7280;padding-left:1.5rem'"
                                                x-text="row._tipo === 'familia' ? row.count + ' registros' : formatFechaCorta(row.fecha)">
                                            </td>
                                            <td class="px-3 py-2 font-mono text-xs"
                                                :style="row._tipo === 'familia' ? 'color:#d1d5db' : 'color:#4b5563;padding-left:1.5rem'"
                                                x-text="row._tipo !== 'familia' ? row.insumo_id : ''">
                                            </td>
                                            <td class="px-3 py-2"
                                                :style="row._tipo === 'familia' ? 'color:#3730a3;font-weight:700;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.025em' : 'font-size:0.75rem;color:#374151;padding-left:1.5rem'"
                                                x-text="row._tipo === 'familia' ? row.familia : row.descripcion">
                                            </td>
                                            <td class="px-3 py-2 text-xs"
                                                :style="row._tipo !== 'familia' ? 'color:#6b7280' : ''"
                                                x-text="row._tipo !== 'familia' ? row.unidad : ''">
                                            </td>
                                            <td class="px-3 py-2 text-xs"
                                                :style="row._tipo !== 'familia' ? 'color:#374151' : ''"
                                                x-text="row._tipo !== 'familia' ? ((row.nivel || '') + (row.departamento ? ' / ' + row.departamento : '') || '—') : ''">
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums"
                                                :style="row._tipo === 'familia' ? 'color:#4338ca;font-weight:700' : 'font-size:0.75rem;font-weight:500;color:#374151'"
                                                x-text="formatNum(row._tipo === 'familia' ? row.cantidad_total : row.cantidad)">
                                            </td>
                                            <td class="px-3 py-2 text-right text-xs tabular-nums"
                                                :style="row._tipo === 'familia' ? 'color:#d1d5db' : 'color:#6b7280'"
                                                x-text="row._tipo !== 'familia' && row.precio_unitario !== null ? '$' + formatMoney(row.precio_unitario) : '—'">
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums"
                                                :style="row._tipo === 'familia' ? 'color:#4338ca;font-weight:700' : 'font-size:0.75rem;color:#374151'"
                                                x-text="row._tipo === 'familia'
                                                    ? (row.importe_total > 0 ? '$' + formatMoney(row.importe_total) : '—')
                                                    : (row.importe !== null ? '$' + formatMoney(row.importe) : '—')">
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Sección 2: Transferencias Enviadas --}}
                    <div x-show="transSalidasData.length > 0" style="border:2px solid #f97316;border-radius:0.5rem;overflow:hidden">
                        {{-- Header colapsable --}}
                        <div @click="seccionSalidasAbierta.transferencias = !seccionSalidasAbierta.transferencias"
                             class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                             style="background:#f97316;color:#fff">
                            <div class="flex items-center gap-2">
                                <svg :class="seccionSalidasAbierta.transferencias ? 'rotate-90' : ''"
                                     class="w-4 h-4 transition-transform duration-150"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="font-bold text-sm">🟠 Transferencias Enviadas</span>
                                <span class="text-xs opacity-75" x-text="'(' + transSalidasData.length + ' registros)'"></span>
                            </div>
                            <div class="font-bold tabular-nums text-sm" x-text="transSalidasData.reduce((s,r)=>s+parseFloat(r.cantidad||0),0).toFixed(2) + ' uds'"></div>
                        </div>
                        {{-- Tabla --}}
                        <div x-show="seccionSalidasAbierta.transferencias" class="overflow-x-auto">
                            <div x-show="loading" class="p-4 text-sm text-gray-500">Cargando tabla...</div>
                            <table x-show="!loading" class="w-full text-sm">
                                <thead class="text-xs text-gray-500 uppercase" style="background:#fff7ed">
                                    <tr>
                                        <th class="w-8 px-2 py-2"></th>
                                        <th class="px-3 py-2 text-left">Fecha</th>
                                        <th class="px-3 py-2 text-left">Código</th>
                                        <th class="px-3 py-2 text-left">Descripción</th>
                                        <th class="px-3 py-2 text-left">Unidad</th>
                                        <th class="px-3 py-2 text-left">Destino</th>
                                        <th class="px-3 py-2 text-right">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in transSalidasGruposFlat()" :key="row._key">
                                        <tr :style="row._fila === 'familia'
                                                    ? 'border-top:2px solid #fed7aa;background:#ffedd5;cursor:pointer'
                                                    : 'border-top:1px solid #fed7aa'"
                                            @click="row._fila === 'familia' && toggleTransSalidaGrupo(row.familia)">
                                            <td class="px-2 py-2 text-center w-8">
                                                <svg x-show="row._fila === 'familia'"
                                                     :style="transSalidasExpandidos[row.familia] ? 'transform:rotate(90deg)' : ''"
                                                     style="display:inline;width:1rem;height:1rem;color:#f97316;transition:transform 0.15s"
                                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </td>
                                            <td class="px-3 py-2 text-xs tabular-nums whitespace-nowrap"
                                                :style="row._fila === 'familia' ? 'color:#fb923c;font-weight:500' : 'color:#6b7280;padding-left:1.5rem'"
                                                x-text="row._fila === 'familia' ? row.count + ' registros' : formatFechaCorta(row.fecha)">
                                            </td>
                                            <td class="px-3 py-2 font-mono text-xs"
                                                :style="row._fila === 'familia' ? 'color:#d1d5db' : 'color:#4b5563;padding-left:1.5rem'"
                                                x-text="row._fila !== 'familia' ? row.insumo_id : ''">
                                            </td>
                                            <td class="px-3 py-2"
                                                :style="row._fila === 'familia' ? 'color:#9a3412;font-weight:700;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.025em' : 'font-size:0.75rem;color:#374151;padding-left:1.5rem'"
                                                x-text="row._fila === 'familia' ? row.familia : row.descripcion">
                                            </td>
                                            <td class="px-3 py-2 text-xs"
                                                :style="row._fila !== 'familia' ? 'color:#6b7280' : ''"
                                                x-text="row._fila !== 'familia' ? row.unidad : ''">
                                            </td>
                                            <td class="px-3 py-2 text-xs"
                                                :style="row._fila !== 'familia' ? 'color:#374151' : ''"
                                                x-text="row._fila !== 'familia' ? row.obra_destino : ''">
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums"
                                                :style="row._fila === 'familia' ? 'color:#c2410c;font-weight:700' : 'font-size:0.75rem;font-weight:500;color:#374151'"
                                                x-text="formatNum(row._fila === 'familia' ? row.cantidad_total : row.cantidad)">
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Empty state --}}
                    <div x-show="!loading && salidasTablaData.length === 0 && transSalidasData.length === 0"
                         class="p-8 text-center text-gray-500 text-sm bg-white rounded-lg border">
                        Sin movimientos en el período seleccionado.
                    </div>
                </div>

                {{-- ========================= --}}
                {{-- HISTORIAL DE AJUSTES --}}
                {{-- ========================= --}}
                <div x-show="mov.vista==='ajustes'" class="space-y-3">
                    <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
                        <div class="px-4 py-3 border-b bg-amber-50 flex items-center justify-between">
                            <div class="font-semibold text-amber-900">Historial de ajustes / devoluciones</div>
                            <div class="text-xs text-amber-700" x-text="ajustesHistorial.length + ' registros'"></div>
                        </div>
                        <div x-show="loading" class="p-4 text-sm text-gray-500">Cargando...</div>
                        <div x-show="!loading && ajustesHistorial.length === 0"
                             class="p-8 text-center text-gray-400 text-sm">
                            Sin ajustes registrados en el período seleccionado.
                        </div>
                        <table x-show="ajustesHistorial.length > 0" class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="px-4 py-2 text-left">Fecha</th>
                                    <th class="px-4 py-2 text-left">Salida #</th>
                                    <th class="px-4 py-2 text-left">Producto</th>
                                    <th class="px-4 py-2 text-right">Cant. devuelta</th>
                                    <th class="px-4 py-2 text-left">Usuario</th>
                                    <th class="px-4 py-2 text-left">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="a in ajustesHistorial" :key="a.id">
                                    <tr class="hover:bg-amber-50/40">
                                        <td class="px-4 py-2 text-gray-600 whitespace-nowrap"
                                            x-text="formatFecha(a.created_at)"></td>
                                        <td class="px-4 py-2 font-medium" x-text="'#' + a.movimiento_id"></td>
                                        <td class="px-4 py-2" x-text="a.descripcion"></td>
                                        <td class="px-4 py-2 text-right font-semibold text-amber-700">
                                            <span x-text="a.cantidad_devuelta"></span>
                                            <span class="text-xs text-gray-400 ml-1" x-text="a.unidad"></span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600" x-text="a.usuario"></td>
                                        <td class="px-4 py-2 text-gray-500 text-xs" x-text="a.observaciones || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>


 {{-- ========================= --}}
{{-- ENTRADAS (RECEPCIONES OC) --}}
{{-- ========================= --}}
<div x-show="tab==='ent'" class="mt-4 space-y-3">

    <div class="bg-white shadow-sm sm:rounded-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Buscar insumo / descripción / OC</label>
                <input class="w-full border rounded px-3 py-2"
                       placeholder="Ej: 303-ARF / varilla / 12345"
                       x-model="ent.q"
                       @input.debounce.400ms="cargarEntradas()">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo de entrada</label>
                <select class="w-full border rounded px-3 py-2 bg-white"
                        x-model="ent.tipo"
                        @change="cargarEntradas()">
                    <option value="">Todos</option>
                    <option value="oc">🟢 Órdenes de Compra</option>
                    <option value="manual">🔵 Entradas Manuales</option>
                    <option value="transferencia">🟠 Transferencias</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Desde</label>
                <input type="date" class="w-full border rounded px-3 py-2"
                       x-model="ent.desde"
                       @change="cargarEntradas()">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                <input type="date" class="w-full border rounded px-3 py-2"
                       x-model="ent.hasta"
                       @change="cargarEntradas()">
            </div>
        </div>

        <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-xs text-gray-500 font-medium">Vista:</span>
            <button @click="ent.vista='tarjetas'"
                    :class="ent.vista==='tarjetas' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                    class="px-3 py-1 rounded border text-sm transition-colors">Tarjetas</button>
            <button @click="ent.vista='tabla'"
                    :class="ent.vista==='tabla' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                    class="px-3 py-1 rounded border text-sm transition-colors">Tabla</button>

            <div class="ml-auto">
                <button @click="exportarEntradas()"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-sm font-medium transition-colors whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    Exportar Excel
                </button>
            </div>
        </div>
    </div>

    {{-- ✅ Tablet friendly: 1 columna / 2 columnas --}}
    <div x-show="ent.vista==='tarjetas'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <template x-for="e in entradas" :key="e.id">
            <div class="bg-white shadow-sm rounded-lg border p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold text-lg">
                            Entrada #<span x-text="e.id"></span>
                        </div>
                        <div class="text-sm text-gray-600">
                            OC #<span x-text="e.id_pedido"></span> · Det #<span x-text="e.pedido_det_id"></span>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-gray-500">Llegó</div>
                        <div class="font-bold text-xl">
                            <span x-text="num(e.cantidad_llego)"></span>
                        </div>
                        <div class="text-xs text-gray-600" x-text="e.unidad"></div>
                    </div>
                </div>

                <div class="mt-2 text-sm text-gray-700">
                    <div class="font-semibold" x-text="e.insumo"></div>
                    <div class="text-gray-600" x-text="e.descripcion"></div>
                </div>

                <div class="mt-2 text-xs text-gray-600">
                    <div>Fecha OC: <span class="font-medium" x-text="e.fecha_oc"></span></div>
                    <div>Recibido: <span class="font-medium" x-text="e.fecha_recibido"></span></div>
                    <div>
                        Foto:
                        <span class="font-medium" x-text="e.tiene_foto ? 'Sí' : 'No'"></span>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="w-full px-4 py-2 rounded bg-gray-900 text-white"
                            @click="verEntradaDetalles(e.id)">
                        Ver detalles
                    </button>
                </div>

                {{-- Desglose + imagen --}}
                <div x-show="detallesEntradaId===e.id" class="mt-3 border-t pt-3">
                    <template x-if="entradaDetalle==null">
                        <div class="text-sm text-gray-500">Cargando detalle...</div>
                    </template>

                    <template x-if="entradaDetalle!=null">
                        <div class="space-y-3">
                            <div class="text-sm">
                                <div class="text-gray-500 text-xs">Insumo</div>
                                <div class="font-semibold" x-text="entradaDetalle.insumo"></div>
                            </div>

                            <template x-if="entradaDetalle.foto_url">
                                <div>
                                    <div class="text-gray-500 text-xs mb-1">Foto de recepción</div>

                                    {{-- ✅ imagen responsive tablet + tap to zoom --}}
                                    <button type="button" class="w-full" @click="imgModal.url=entradaDetalle.foto_url; imgModal.show=true">
                                        <img :src="entradaDetalle.foto_url"
                                             class="w-full max-h-72 object-contain rounded border bg-gray-50"
                                             alt="foto recepción">
                                    </button>

                                    <div class="text-xs text-gray-500 mt-1">
                                        Toca la imagen para verla en grande.
                                    </div>
                                </div>
                            </template>

                            <template x-if="!entradaDetalle.foto_url">
                                <div class="text-sm text-gray-500">No hay foto en esta recepción.</div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- ========================= --}}
    {{-- TABLA DE ENTRADAS — 3 secciones --}}
    {{-- ========================= --}}
    <div x-show="ent.vista==='tabla'" class="space-y-3">

        {{-- Total general --}}
        <div x-show="!loading && entradas.length > 0"
             class="bg-gray-900 text-white rounded-lg px-4 py-3 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="text-sm font-medium">Total entradas filtradas</div>
                <button @click="toggleExpandirTodoEntradas()"
                        class="px-2 py-1 rounded text-xs border border-white/30 hover:bg-white/10 transition-colors whitespace-nowrap"
                        x-text="todosEntradasExpandidos() ? 'Colapsar todo' : 'Expandir todo'"></button>
            </div>
            <div class="text-2xl font-extrabold" x-text="'$' + formatMoney(totalEntradas())"></div>
        </div>

        <div x-show="loading" class="bg-white shadow-sm rounded-lg p-6 text-center text-sm text-gray-500">Cargando...</div>
        <div x-show="!loading && entradas.length === 0" class="bg-white shadow-sm rounded-lg p-8 text-center text-sm text-gray-500">
            Sin entradas en el período seleccionado.
        </div>

        {{-- ── 1. ÓRDENES DE COMPRA ── --}}
        <div x-show="entradasPorTipo('oc').length > 0" class="rounded-lg overflow-hidden" style="border:2px solid #059669">
            {{-- Header --}}
            <div @click="ent.seccionAbierta.oc = !ent.seccionAbierta.oc"
                 class="flex items-center justify-between px-4 py-3 cursor-pointer select-none"
                 style="background:#059669;color:#fff">
                <div class="flex items-center gap-2">
                    <svg :class="ent.seccionAbierta.oc ? 'rotate-90':''"
                         class="w-4 h-4 transition-transform shrink-0"
                         fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                    <span class="font-bold text-sm">🟢 Órdenes de Compra</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:rgba(255,255,255,0.25)"
                          x-text="entradasPorTipo('oc').length + ' registros'"></span>
                </div>
                <span class="font-bold text-base"
                      x-text="'$' + formatMoney(entradasPorTipo('oc').reduce((s,e)=>s+(e.importe??0),0))"></span>
            </div>
            {{-- Tabla --}}
            <div x-show="ent.seccionAbierta.oc" class="overflow-x-auto bg-white">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase border-b" style="background:#ecfdf5">
                        <tr>
                            <th class="w-8 px-2 py-2"></th>
                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Descripción</th>
                            <th class="px-3 py-2 text-left">Unidad</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">P.U.</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in entradasOcGruposFlat()" :key="row._key">
                            <tr :class="row._fila==='familia' ? 'cursor-pointer select-none' : ''"
                                :style="row._fila==='familia'
                                    ? 'border-top:2px solid #6ee7b7;background:#d1fae5'
                                    : 'border-top:1px solid #d1fae5'"
                                @click="row._fila==='familia' && toggleOcGrupo(row.familia)">
                                <td class="px-2 py-2 text-center w-8">
                                    <svg x-show="row._fila==='familia'"
                                         :class="ocTablaExpandidos[row.familia] ? 'rotate-90' : ''"
                                         class="inline w-4 h-4 transition-transform duration-150" style="color:#059669"
                                         fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </td>
                                <td class="px-3 py-2 text-xs whitespace-nowrap tabular-nums"
                                    :style="row._fila==='familia' ? 'color:#059669;font-weight:500' : 'color:#6b7280;padding-left:1.5rem'"
                                    x-text="row._fila==='familia' ? row.count+' registros' : formatFechaCorta(row.fecha_recibido)"></td>
                                <td class="px-3 py-2 font-mono text-xs"
                                    :style="row._fila==='familia' ? 'color:#d1d5db' : 'color:#4b5563;padding-left:1.5rem'"
                                    x-text="row._fila!=='familia' ? row.insumo : ''"></td>
                                <td class="px-3 py-2"
                                    :style="row._fila==='familia' ? 'color:#065f46;font-weight:700;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em' : 'font-size:0.75rem;color:#374151;padding-left:1.5rem'"
                                    x-text="row._fila==='familia' ? row.familia : row.descripcion"></td>
                                <td class="px-3 py-2 text-xs" style="color:#6b7280"
                                    x-text="row._fila!=='familia' ? row.unidad : ''"></td>
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :style="row._fila==='familia' ? 'color:#065f46;font-weight:700' : 'font-size:0.75rem;font-weight:500;color:#374151'"
                                    x-text="formatNum(row._fila==='familia' ? row.cantidad_total : row.cantidad_llego)"></td>
                                <td class="px-3 py-2 text-right text-xs tabular-nums" style="color:#6b7280"
                                    x-text="row._fila!=='familia' && row.precio_unitario!=null ? '$'+formatMoney(row.precio_unitario) : '—'"></td>
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :style="row._fila==='familia' ? 'color:#065f46;font-weight:700' : 'font-size:0.75rem;color:#374151'"
                                    x-text="row._fila==='familia'
                                        ? (row.importe_total>0 ? '$'+formatMoney(row.importe_total) : '—')
                                        : (row.importe!=null ? '$'+formatMoney(row.importe) : '—')"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── 2. ENTRADAS MANUALES ── --}}
        <div x-show="entradasPorTipo('manual').length > 0" class="rounded-lg overflow-hidden" style="border:2px solid #2563eb">
            <div @click="ent.seccionAbierta.manual = !ent.seccionAbierta.manual"
                 class="flex items-center gap-2 px-4 py-3 cursor-pointer select-none"
                 style="background:#2563eb;color:#fff">
                <svg :class="ent.seccionAbierta.manual ? 'rotate-90':''"
                     class="w-4 h-4 transition-transform shrink-0"
                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-sm">🔵 Entradas Manuales</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:rgba(255,255,255,0.25)"
                      x-text="entradasPorTipo('manual').length + ' registros'"></span>
            </div>
            <div x-show="ent.seccionAbierta.manual" class="overflow-x-auto bg-white">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase border-b" style="background:#eff6ff">
                        <tr>
                            <th class="w-8 px-2 py-2"></th>
                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Descripción</th>
                            <th class="px-3 py-2 text-left">Unidad</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">P.U.</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                            <th class="px-3 py-2 text-left">Registrado por</th>
                            <th class="px-3 py-2 text-left">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in entradasManualGruposFlat()" :key="row._key">
                            <tr :class="row._fila === 'familia' ? 'cursor-pointer select-none' : ''"
                                :style="row._fila === 'familia'
                                    ? 'border-top:2px solid #bfdbfe;background:#dbeafe'
                                    : 'border-top:1px solid #bfdbfe;' + (row.revertida ? 'opacity:0.5' : '')"
                                @click="row._fila === 'familia' && toggleManualGrupo(row.familia)">
                                {{-- Icono toggle --}}
                                <td class="px-2 py-2 text-center w-8">
                                    <svg x-show="row._fila === 'familia'"
                                         :class="manualTablaExpandidos[row.familia] ? 'rotate-90' : ''"
                                         class="inline w-4 h-4 transition-transform duration-150"
                                         style="color:#2563eb"
                                         fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </td>
                                {{-- Fecha / contador --}}
                                <td class="px-3 py-2 text-xs whitespace-nowrap tabular-nums"
                                    :style="row._fila === 'familia' ? 'color:#3b82f6;font-weight:500' : 'color:#6b7280;padding-left:1.5rem'"
                                    x-text="row._fila === 'familia' ? row.count + ' registros' : formatFechaCorta(row.fecha_recibido)"></td>
                                {{-- Código --}}
                                <td class="px-3 py-2 font-mono text-xs"
                                    :style="row._fila === 'familia' ? 'color:#d1d5db' : 'color:#4b5563;padding-left:1.5rem'"
                                    x-text="row._fila !== 'familia' ? row.insumo : ''"></td>
                                {{-- Descripción / Familia --}}
                                <td class="px-3 py-2"
                                    :style="row._fila === 'familia' ? 'color:#1e3a8a;font-weight:700;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em' : 'font-size:0.75rem;color:#374151;padding-left:1.5rem'"
                                    x-text="row._fila === 'familia' ? row.familia : row.descripcion"></td>
                                {{-- Unidad --}}
                                <td class="px-3 py-2 text-xs" style="color:#6b7280"
                                    x-text="row._fila !== 'familia' ? row.unidad : ''"></td>
                                {{-- Cantidad --}}
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :style="row._fila === 'familia' ? 'color:#1d4ed8;font-weight:700' : 'font-size:0.75rem;font-weight:500;color:#374151'"
                                    x-text="formatNum(row._fila === 'familia' ? row.cantidad_total : row.cantidad_llego)"></td>
                                {{-- P.U. --}}
                                <td class="px-3 py-2 text-right text-xs tabular-nums" style="color:#6b7280"
                                    x-text="row._fila !== 'familia' && row.precio_unitario != null ? '$'+formatMoney(row.precio_unitario) : '—'"></td>
                                {{-- Importe --}}
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :style="row._fila === 'familia' ? 'color:#1d4ed8;font-weight:700' : 'font-size:0.75rem;color:#374151'"
                                    x-text="row._fila === 'familia'
                                        ? (row.importe_total > 0 ? '$'+formatMoney(row.importe_total) : '—')
                                        : (row.importe != null ? '$'+formatMoney(row.importe) : '—')"></td>
                                {{-- Registrado por --}}
                                <td class="px-3 py-2 text-xs" style="color:#6b7280"
                                    x-text="row._fila !== 'familia' ? (row.usuario||'—') : ''"></td>
                                {{-- Estado --}}
                                <td class="px-3 py-2">
                                    <template x-if="row._fila !== 'familia' && row.revertida">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold" style="background:#fee2e2;color:#b91c1c">✗ Revertida</span>
                                    </template>
                                    <template x-if="row._fila !== 'familia' && !row.revertida">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold" style="background:#dcfce7;color:#15803d">✓ Activa</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── 3. TRANSFERENCIAS RECIBIDAS ── --}}
        <div x-show="entradasPorTipo('transferencia').length > 0" class="rounded-lg overflow-hidden" style="border:2px solid #f97316">
            <div @click="ent.seccionAbierta.transferencia = !ent.seccionAbierta.transferencia"
                 class="flex items-center gap-2 px-4 py-3 cursor-pointer select-none"
                 style="background:#f97316;color:#fff">
                <svg :class="ent.seccionAbierta.transferencia ? 'rotate-90':''"
                     class="w-4 h-4 transition-transform shrink-0"
                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="font-bold text-sm">🟠 Transferencias Recibidas</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:rgba(255,255,255,0.25)"
                      x-text="entradasPorTipo('transferencia').length + ' registros'"></span>
            </div>
            <div x-show="ent.seccionAbierta.transferencia" class="overflow-x-auto bg-white">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase border-b" style="background:#fff7ed">
                        <tr>
                            <th class="w-8 px-2 py-2"></th>
                            <th class="px-3 py-2 text-left">Fecha</th>
                            <th class="px-3 py-2 text-left">Origen</th>
                            <th class="px-3 py-2 text-left">Código</th>
                            <th class="px-3 py-2 text-left">Descripción</th>
                            <th class="px-3 py-2 text-left">Unidad</th>
                            <th class="px-3 py-2 text-right">Cantidad</th>
                            <th class="px-3 py-2 text-right">P.U.</th>
                            <th class="px-3 py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in entradasTransGruposFlat()" :key="row._key">
                            <tr :class="row._fila==='familia' ? 'cursor-pointer select-none' : ''"
                                :style="row._fila==='familia'
                                    ? 'border-top:2px solid #fdba74;background:#fed7aa'
                                    : 'border-top:1px solid #fed7aa'"
                                @click="row._fila==='familia' && toggleTransGrupo(row.familia)">
                                <td class="px-2 py-2 text-center w-8">
                                    <svg x-show="row._fila==='familia'"
                                         :class="transTablaExpandidos[row.familia] ? 'rotate-90' : ''"
                                         class="inline w-4 h-4 transition-transform duration-150" style="color:#f97316"
                                         fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </td>
                                <td class="px-3 py-2 text-xs whitespace-nowrap tabular-nums"
                                    :style="row._fila==='familia' ? 'color:#c2410c;font-weight:500' : 'color:#6b7280;padding-left:1.5rem'"
                                    x-text="row._fila==='familia' ? row.count+' registros' : formatFechaCorta(row.fecha_recibido)"></td>
                                <td class="px-3 py-2 text-xs" style="color:#6b7280"
                                    x-text="row._fila!=='familia' ? (row.obra_origen||'—') : ''"></td>
                                <td class="px-3 py-2 font-mono text-xs"
                                    :style="row._fila==='familia' ? 'color:#d1d5db' : 'color:#4b5563;padding-left:1.5rem'"
                                    x-text="row._fila!=='familia' ? row.insumo : ''"></td>
                                <td class="px-3 py-2"
                                    :style="row._fila==='familia' ? 'color:#7c2d12;font-weight:700;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em' : 'font-size:0.75rem;color:#374151;padding-left:1.5rem'"
                                    x-text="row._fila==='familia' ? row.familia : row.descripcion"></td>
                                <td class="px-3 py-2 text-xs" style="color:#6b7280"
                                    x-text="row._fila!=='familia' ? row.unidad : ''"></td>
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :style="row._fila==='familia' ? 'color:#7c2d12;font-weight:700' : 'font-size:0.75rem;font-weight:500;color:#374151'"
                                    x-text="formatNum(row._fila==='familia' ? row.cantidad_total : row.cantidad_llego)"></td>
                                <td class="px-3 py-2 text-right text-xs tabular-nums" style="color:#6b7280"
                                    x-text="row._fila!=='familia' && row.precio_unitario!=null ? '$'+formatMoney(row.precio_unitario) : '—'"></td>
                                <td class="px-3 py-2 text-right tabular-nums"
                                    :style="row._fila==='familia' ? 'color:#7c2d12;font-weight:700' : 'font-size:0.75rem;color:#374151'"
                                    x-text="row._fila==='familia'
                                        ? (row.importe_total>0 ? '$'+formatMoney(row.importe_total) : '—')
                                        : (row.importe!=null ? '$'+formatMoney(row.importe) : '—')"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- ✅ Modal Ajuste de Salida --}}
<div x-show="ajuste.show" x-cloak
     class="fixed inset-0 z-50 bg-black/60 flex items-start justify-center p-3 pt-6 overflow-y-auto"
     @keydown.escape.window="ajuste.show = false">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b bg-amber-50 rounded-t-xl">
            <div>
                <div class="font-bold text-amber-900 text-base">
                    Ajustar salida #<span x-text="ajuste.movimiento?.id"></span>
                </div>
                <div class="text-xs text-amber-700 mt-0.5">
                    Quien recibió: <span x-text="ajuste.movimiento?.nombre_cabo"></span>
                </div>
            </div>
            <button @click="ajuste.show = false"
                    class="text-gray-400 hover:text-gray-700 text-2xl leading-none p-1">✕</button>
        </div>

        {{-- Productos --}}
        <div class="px-5 py-4 space-y-3 max-h-[55vh] overflow-y-auto">
            <div x-show="ajuste.cargando" class="text-sm text-gray-400 py-4 text-center">Cargando productos...</div>
            <template x-if="!ajuste.cargando">
                <div class="space-y-2">
                    <template x-for="item in ajuste.items" :key="item.id">
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-sm truncate" x-text="item.descripcion"></div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        Salida: <span class="font-semibold" x-text="item.cantidad"></span>
                                        <span x-text="item.unidad"></span>
                                        <template x-if="item.ya_devuelto > 0">
                                            <span class="ml-2 text-amber-600">
                                                · Ya devuelto: <span x-text="item.ya_devuelto"></span>
                                            </span>
                                        </template>
                                        · Disponible:
                                        <span class="font-semibold text-emerald-700" x-text="item.disponible"></span>
                                    </div>
                                </div>
                                <div class="shrink-0 w-28">
                                    <input type="number"
                                           x-model.number="item.cantidad_ajuste"
                                           :max="item.disponible"
                                           min="0" step="0.01"
                                           :disabled="item.disponible <= 0"
                                           class="w-full border rounded px-2 py-1.5 text-sm text-right disabled:bg-gray-100 disabled:text-gray-400"
                                           placeholder="0">
                                    <div class="text-xs text-red-500 mt-1"
                                         x-show="item.cantidad_ajuste > item.disponible">
                                        Máximo: <span x-text="item.disponible"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Observaciones --}}
        <div class="px-5 pb-3">
            <label class="text-xs text-gray-500 font-medium">Observaciones (opcional)</label>
            <textarea x-model="ajuste.observaciones" rows="2"
                      class="w-full border rounded px-3 py-2 text-sm mt-1"
                      placeholder="Motivo del ajuste o devolución..."></textarea>
        </div>

        {{-- Errores --}}
        <div x-show="ajuste.error" class="mx-5 mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm text-red-700"
             x-text="ajuste.error"></div>
        <div x-show="ajuste.exito" class="mx-5 mb-3 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded text-sm text-emerald-700"
             x-text="ajuste.exito"></div>

        {{-- Footer --}}
        <div class="px-5 py-4 border-t flex gap-3 justify-end bg-gray-50 rounded-b-xl">
            <button @click="ajuste.show = false"
                    :disabled="ajuste.guardando"
                    class="px-4 py-2 text-sm rounded border bg-white hover:bg-gray-50">
                Cancelar
            </button>
            <button @click="confirmarAjuste()"
                    :disabled="ajuste.guardando || !ajusteTieneItems()"
                    :class="ajusteTieneItems() ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                    class="px-5 py-2 text-sm rounded font-semibold transition-colors">
                <span x-show="!ajuste.guardando">Guardar ajuste</span>
                <span x-show="ajuste.guardando">Guardando...</span>
            </button>
        </div>
    </div>
</div>

{{-- ✅ Modal imagen full (tablet friendly) --}}
<div x-show="imgModal.show" x-cloak
     class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-3"

     @keydown.escape.window="imgModal.show=false">
    <button class="absolute top-3 right-3 px-3 py-2 rounded bg-white/90"
            @click="imgModal.show=false">✕</button>

    <img :src="imgModal.url" class="max-w-full max-h-[85vh] object-contain rounded bg-white">
</div>


            {{-- ========================= --}}
            {{-- TRANSFERENCIAS           --}}
            {{-- ========================= --}}
            <div x-show="tab==='trans'" class="mt-4 space-y-3">

                {{-- Filtros --}}
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2">

                        {{-- Filtro por obra (solo obras con transferencias cargadas) --}}
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Filtrar por obra</label>
                            <select class="w-full border rounded px-3 py-2 text-sm"
                                    x-model="trans.obra_nombre">
                                <option value="">— Todas —</option>
                                <template x-for="nombre in obrasEnTransferencias()" :key="nombre">
                                    <option :value="nombre" x-text="nombre"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Buscar obra / usuario</label>
                            <input class="w-full border rounded px-3 py-2"
                                   placeholder="Ej: Oblatos, Juan García…"
                                   x-model="trans.q"
                                   @input.debounce.400ms="cargarTransferencias()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Desde</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="trans.desde"
                                   @change="cargarTransferencias()">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                            <input type="date" class="w-full border rounded px-3 py-2"
                                   x-model="trans.hasta"
                                   @change="cargarTransferencias()">
                        </div>
                    </div>

                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                    <div class="mt-3 flex flex-wrap items-center gap-2">

                        <span class="text-xs text-gray-500 font-medium">Mostrar:</span>
                        <button @click="trans.dir = 'todas'"
                                :class="trans.dir === 'todas' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">
                            Todas
                        </button>
                        <button @click="trans.dir = 'enviada'"
                                :class="trans.dir === 'enviada' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">
                            Enviadas
                        </button>
                        <button @click="trans.dir = 'recibida'"
                                :class="trans.dir === 'recibida' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">
                            Recibidas
                        </button>

                        <span class="text-xs text-gray-500 font-medium ml-2">Vista:</span>
                        <button @click="trans.vista = 'tarjetas'"
                                :class="trans.vista === 'tarjetas' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">
                            Tarjetas
                        </button>
                        <button @click="trans.vista = 'tabla'"
                                :class="trans.vista === 'tabla' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">
                            Tabla
                        </button>

                        <div class="ml-auto">
                            <button @click="exportarTransferenciasExcel()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-sm font-medium transition-colors whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                </svg>
                                Exportar Excel
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Sin resultados --}}
                <div x-show="!loading && transferenciasFiltered().length === 0"
                     class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500 text-sm">
                    <span x-text="transferencias.length === 0
                        ? 'No hay transferencias para esta obra en el periodo seleccionado.'
                        : 'No hay transferencias ' + (trans.dir === \'enviada\' ? \'enviadas\' : \'recibidas\') + \' en el periodo seleccionado.\'">
                    </span>
                </div>

                {{-- ═══════════════════════════ VISTA TABLA ═══════════════════════════ --}}
                <div x-show="trans.vista === 'tabla' && transferenciasFiltered().length > 0"
                     class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dirección</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Obra Origen</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Obra Destino</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Usuario</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Insumos</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Piezas</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Observaciones</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <template x-for="tr in transferenciasFiltered()" :key="tr.id">
                                <tbody class="border-b border-gray-100">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-mono text-gray-500 text-xs" x-text="'#' + tr.id"></td>
                                            <td class="px-4 py-3 whitespace-nowrap" x-text="tr.fecha"></td>
                                            <td class="px-4 py-3">
                                                <span :class="tr.direccion === 'enviada'
                                                              ? 'bg-blue-100 text-blue-800'
                                                              : 'bg-amber-100 text-amber-800'"
                                                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                        <path x-show="tr.direccion === 'enviada'" stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                                                        <path x-show="tr.direccion !== 'enviada'" stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3"/>
                                                    </svg>
                                                    <span x-text="tr.direccion === 'enviada' ? 'Enviada' : 'Recibida'"></span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-800" x-text="tr.obra_origen"></td>
                                            <td class="px-4 py-3 text-sm font-medium text-gray-800" x-text="tr.obra_destino"></td>
                                            <td class="px-4 py-3 text-sm text-gray-600" x-text="tr.usuario"></td>
                                            <td class="px-4 py-3 text-right font-semibold tabular-nums" x-text="tr.total_insumos"></td>
                                            <td class="px-4 py-3 text-right tabular-nums" x-text="parseFloat(tr.total_piezas || 0).toFixed(2)"></td>
                                            <td class="px-4 py-3 text-xs text-gray-500 italic max-w-xs truncate" x-text="tr.observaciones || '—'"></td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button @click="verTransDetalles(tr.id)"
                                                            :class="detallesTransId === tr.id ? 'bg-gray-900 text-white' : 'bg-gray-50 hover:bg-gray-100'"
                                                            class="px-3 py-2 text-sm rounded border transition-colors">
                                                        <span x-text="detallesTransId === tr.id ? 'Ocultar' : 'Detalles'"></span>
                                                    </button>
                                                    <a :href="'/transferencias/'+tr.id+'/pdf'"
                                                       target="_blank"
                                                       class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100">
                                                        PDF
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Fila de detalles expandibles --}}
                                        <tr x-show="detallesTransId === tr.id" class="bg-gray-50">
                                            <td colspan="10" class="px-6 py-3">
                                                <template x-if="!transDetalle || !transDetalle.detalles || transDetalle.detalles.length === 0">
                                                    <div class="text-sm text-gray-500 py-2">Sin detalles registrados.</div>
                                                </template>
                                                <template x-if="transDetalle && transDetalle.detalles && transDetalle.detalles.length > 0">
                                                    <div class="overflow-x-auto">
                                                        <table class="min-w-full text-xs border rounded">
                                                            <thead class="bg-white border-b">
                                                                <tr>
                                                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Cód.</th>
                                                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Descripción</th>
                                                                    <th class="px-3 py-2 text-right font-semibold text-gray-500">Cant.</th>
                                                                    <th class="px-3 py-2 text-right font-semibold text-indigo-400">Orig. antes</th>
                                                                    <th class="px-3 py-2 text-right font-semibold text-indigo-600">Orig. desp.</th>
                                                                    <th class="px-3 py-2 text-right font-semibold text-emerald-400">Dest. antes</th>
                                                                    <th class="px-3 py-2 text-right font-semibold text-emerald-600">Dest. desp.</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-100 bg-white">
                                                                <template x-for="d in transDetalle.detalles" :key="d.id">
                                                                    <tr class="hover:bg-gray-50">
                                                                        <td class="px-3 py-2 font-mono text-gray-400" x-text="d.insumo_id ?? '—'"></td>
                                                                        <td class="px-3 py-2 font-medium text-gray-800" x-text="d.descripcion"></td>
                                                                        <td class="px-3 py-2 text-right font-semibold tabular-nums">
                                                                            <span x-text="parseFloat(d.cantidad).toFixed(2)"></span>
                                                                            <span class="text-gray-400 ml-0.5" x-text="d.unidad ?? ''"></span>
                                                                        </td>
                                                                        <td class="px-3 py-2 text-right text-indigo-500 tabular-nums" x-text="parseFloat(d.origen_stock_antes).toFixed(2)"></td>
                                                                        <td class="px-3 py-2 text-right text-red-500 font-semibold tabular-nums" x-text="parseFloat(d.origen_stock_despues).toFixed(2)"></td>
                                                                        <td class="px-3 py-2 text-right text-emerald-500 tabular-nums" x-text="parseFloat(d.destino_stock_antes).toFixed(2)"></td>
                                                                        <td class="px-3 py-2 text-right text-emerald-700 font-semibold tabular-nums" x-text="parseFloat(d.destino_stock_despues).toFixed(2)"></td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </template>
                                            </td>
                                        </tr>
                                </tbody>
                            </template>
                        </table>
                    </div>
                </div>

                {{-- ═══════════════════════════ VISTA TARJETAS ═══════════════════════════ --}}
                <div x-show="trans.vista === 'tarjetas'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="tr in transferenciasFiltered()" :key="tr.id">
                        <div class="bg-white shadow-sm rounded-lg border p-4">

                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-lg">
                                        Transferencia #<span x-text="tr.id"></span>
                                    </div>
                                    <span :class="tr.direccion === 'enviada' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'"
                                          class="inline-flex items-center gap-1 px-2 py-0.5 mt-0.5 rounded-full text-xs font-semibold">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path x-show="tr.direccion === 'enviada'" stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/>
                                            <path x-show="tr.direccion !== 'enviada'" stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3"/>
                                        </svg>
                                        <span x-text="tr.direccion === 'enviada' ? 'ENVIADA' : 'RECIBIDA'"></span>
                                    </span>
                                </div>
                                <a class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100 shrink-0"
                                   :href="'/transferencias/'+tr.id+'/pdf'"
                                   target="_blank">PDF</a>
                            </div>

                            <div class="mt-3 flex items-center gap-2 flex-wrap text-sm">
                                <span :class="tr.direccion === 'enviada' ? 'bg-blue-50 border-blue-100 text-blue-800' : 'bg-indigo-50 border-indigo-100 text-indigo-700'"
                                      class="px-2.5 py-1 rounded-lg border font-semibold text-xs" x-text="tr.obra_origen"></span>
                                <span class="text-gray-400 font-light text-lg">→</span>
                                <span :class="tr.direccion === 'recibida' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-100 text-emerald-800'"
                                      class="px-2.5 py-1 rounded-lg border font-semibold text-xs" x-text="tr.obra_destino"></span>
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                <div>
                                    <div class="text-xs text-gray-400">Fecha</div>
                                    <div class="font-medium" x-text="tr.fecha"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Insumos</div>
                                    <div class="font-bold text-lg" x-text="tr.total_insumos"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Piezas</div>
                                    <div class="font-bold text-lg" x-text="parseFloat(tr.total_piezas || 0).toFixed(2)"></div>
                                </div>
                            </div>

                            <div class="mt-2 text-sm">
                                <div class="text-xs text-gray-400">Usuario</div>
                                <div class="font-medium" x-text="tr.usuario"></div>
                            </div>

                            <div x-show="tr.observaciones" class="mt-2 text-sm">
                                <div class="text-xs text-gray-400">Observaciones</div>
                                <div class="text-gray-600 italic" x-text="tr.observaciones"></div>
                            </div>

                            <div class="mt-3">
                                <button :class="detallesTransId === tr.id ? 'bg-gray-900 text-white' : 'bg-gray-50 hover:bg-gray-100'"
                                        class="w-full px-3 py-2 text-sm rounded border transition-colors"
                                        @click="verTransDetalles(tr.id)">
                                    <span x-text="detallesTransId === tr.id ? 'Ocultar detalles' : 'Ver detalles'"></span>
                                </button>
                            </div>

                            <div x-show="detallesTransId === tr.id" class="mt-3 border-t pt-3">
                                <template x-if="!transDetalle || !transDetalle.detalles || transDetalle.detalles.length === 0">
                                    <div class="text-sm text-gray-500">Sin detalles registrados.</div>
                                </template>
                                <template x-if="transDetalle && transDetalle.detalles && transDetalle.detalles.length > 0">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs">
                                            <thead class="bg-gray-50 border-b border-gray-200">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-semibold text-gray-500">Insumo</th>
                                                    <th class="px-3 py-2 text-right font-semibold text-gray-500">Cant.</th>
                                                    <th class="px-3 py-2 text-right font-semibold text-indigo-400">Orig. antes</th>
                                                    <th class="px-3 py-2 text-right font-semibold text-indigo-600">Orig. desp.</th>
                                                    <th class="px-3 py-2 text-right font-semibold text-emerald-400">Dest. antes</th>
                                                    <th class="px-3 py-2 text-right font-semibold text-emerald-600">Dest. desp.</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <template x-for="d in transDetalle.detalles" :key="d.id">
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-3 py-2">
                                                            <div class="font-medium text-gray-800" x-text="d.descripcion"></div>
                                                            <div class="font-mono text-gray-400" x-text="d.insumo_id ?? ''"></div>
                                                        </td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-800 tabular-nums">
                                                            <span x-text="parseFloat(d.cantidad).toFixed(2)"></span>
                                                            <span class="text-gray-400 ml-0.5" x-text="d.unidad ?? ''"></span>
                                                        </td>
                                                        <td class="px-3 py-2 text-right text-indigo-500 tabular-nums" x-text="parseFloat(d.origen_stock_antes).toFixed(2)"></td>
                                                        <td class="px-3 py-2 text-right text-red-500 font-semibold tabular-nums" x-text="parseFloat(d.origen_stock_despues).toFixed(2)"></td>
                                                        <td class="px-3 py-2 text-right text-emerald-500 tabular-nums" x-text="parseFloat(d.destino_stock_antes).toFixed(2)"></td>
                                                        <td class="px-3 py-2 text-right text-emerald-700 font-semibold tabular-nums" x-text="parseFloat(d.destino_stock_despues).toFixed(2)"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
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
                           placeholder="Ej: 303-ARF  varilla"
                           x-model="inv.q"
                           @input.debounce.400ms="cargarInventario()">
                    <div class="mt-3 text-xs text-gray-500" x-show="loading">Cargando...</div>
                    <div class="mt-3 flex items-center gap-2">
                        <span class="text-xs text-gray-500 font-medium">Vista:</span>
                        <button @click="inv.vista='tarjetas'"
                                :class="inv.vista==='tarjetas' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">Tarjetas</button>
                        <button @click="inv.vista='tabla'"
                                :class="inv.vista==='tabla' ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50'"
                                class="px-3 py-1 rounded border text-sm transition-colors">Tabla</button>
                        <div class="ml-auto">
                            <button @click="exportarInventario()"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 text-sm font-medium transition-colors whitespace-nowrap">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                </svg>
                                Exportar Excel
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Vista tarjetas --}}
                <div x-show="inv.vista==='tarjetas'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="p in inventario" :key="p.id">
                        <div class="bg-white shadow-sm rounded-lg border p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-lg" x-text="p.insumo_id || p.id"></div>
                                    <div class="text-sm text-gray-700" x-text="p.descripcion"></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Existencia</div>
                                    <div class="font-bold text-xl" x-text="p.cantidad"></div>
                                    <div class="text-xs text-gray-600" x-text="p.unidad"></div>
                                </div>
                            </div>

                            <div class="mt-2 text-xs text-gray-600">
                                <div>P.U.: <span class="font-medium" x-text="p.costo_promedio !== null ? '$' + formatMoney(p.costo_promedio) : '—'"></span></div>
                                <div>Proveedor: <span class="font-medium" x-text="p.proveedor"></span></div>
                                <div>Destino: <span class="font-medium" x-text="p.destino"></span></div>
                                <div>Actualizado: <span class="font-medium" x-text="p.updated_at"></span></div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Vista tabla --}}
                <div x-show="inv.vista==='tabla'" class="space-y-3">

                    {{-- Total general --}}
                    <div class="bg-gray-900 text-white rounded-lg px-4 py-3 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="text-sm font-medium">Costo total del inventario (existencias × P.U.)</div>
                            <button @click="toggleExpandirTodoInventario()"
                                    class="px-2 py-1 rounded text-xs border border-white/30 hover:bg-white/10 transition-colors whitespace-nowrap"
                                    x-text="todosInventarioExpandidos() ? 'Colapsar todo' : 'Expandir todo'"></button>
                        </div>
                        <div class="text-2xl font-extrabold" x-text="'$' + formatMoney(totalInventario())"></div>
                    </div>

                    {{-- Tabla --}}
                    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                        <div x-show="loading" class="p-4 text-sm text-gray-500">Cargando tabla...</div>
                        <div x-show="!loading && inventario.length === 0"
                             class="p-8 text-center text-gray-500 text-sm">
                            Sin productos en inventario.
                        </div>
                        <table x-show="inventario.length > 0" class="w-full text-sm">
                            <thead class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                                <tr>
                                    <th class="w-8 px-2 py-2"></th>
                                    <th class="px-3 py-2 text-left">Código</th>
                                    <th class="px-3 py-2 text-left">Descripción</th>
                                    <th class="px-3 py-2 text-left">Unidad</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">P.U.</th>
                                    <th class="px-3 py-2 text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in inventarioGruposFlat()" :key="row._key">
                                    <tr :class="row._tipo === 'familia'
                                                ? 'border-t-2 border-amber-200 bg-amber-50 cursor-pointer hover:bg-amber-100 select-none'
                                                : 'border-t hover:bg-gray-50'"
                                        @click="row._tipo === 'familia' && toggleInventarioFamilia(row.familia)">
                                        {{-- Toggle icon --}}
                                        <td class="px-2 py-2 text-center w-8">
                                            <svg x-show="row._tipo === 'familia'"
                                                 :class="inventarioFamiliaExpandidos[row.familia] ? 'rotate-90' : ''"
                                                 class="inline w-4 h-4 text-amber-500 transition-transform duration-150"
                                                 fill="none" stroke="currentColor" stroke-width="2.5"
                                                 viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </td>
                                        {{-- Código / contador --}}
                                        <td class="px-3 py-2 font-mono text-xs"
                                            :class="row._tipo === 'familia' ? 'text-amber-400 font-medium' : 'text-gray-700'"
                                            x-text="row._tipo === 'familia' ? row.count + ' insumos' : (row.insumo_id || row.id)">
                                        </td>
                                        {{-- Descripción / Nombre de familia --}}
                                        <td class="px-3 py-2"
                                            :class="row._tipo === 'familia' ? 'text-amber-800 font-bold text-sm uppercase tracking-wide' : 'text-sm text-gray-700'"
                                            x-text="row._tipo === 'familia' ? row.familia : row.descripcion">
                                        </td>
                                        {{-- Unidad --}}
                                        <td class="px-3 py-2 text-xs"
                                            :class="row._tipo === 'familia' ? '' : 'text-gray-600'"
                                            x-text="row._tipo !== 'familia' ? row.unidad : ''">
                                        </td>
                                        {{-- Cantidad --}}
                                        <td class="px-3 py-2 text-right tabular-nums"
                                            :class="row._tipo === 'familia' ? 'text-amber-700 font-bold' : 'font-medium text-gray-800'"
                                            x-text="formatNum(row._tipo === 'familia' ? row.cantidad_total : row.cantidad)">
                                        </td>
                                        {{-- P.U. --}}
                                        <td class="px-3 py-2 text-right text-xs tabular-nums"
                                            :class="row._tipo === 'familia' ? 'text-gray-300' : 'text-gray-500'"
                                            x-text="row._tipo !== 'familia' && row.costo_promedio !== null ? '$' + formatMoney(row.costo_promedio) : '—'">
                                        </td>
                                        {{-- Importe --}}
                                        <td class="px-3 py-2 text-right tabular-nums"
                                            :class="row._tipo === 'familia' ? 'text-amber-700 font-bold' : 'font-semibold text-gray-800'"
                                            x-text="row._tipo === 'familia'
                                                ? (row.importe_total > 0 ? '$' + formatMoney(row.importe_total) : '—')
                                                : (row.importe !== null ? '$' + formatMoney(row.importe) : '—')">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
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
                                        <span class="font-medium" x-text="r.FechaUltimaEntrada ?? r.fecha"></span>

                                    </div>
                                </template>

                                <template x-if="estadoOC(r)==='finalizada'">
                                    <div>
                                        Entregada (sistema):
                                        <span class="font-medium" x-text="r.FechaUltimaEntrada ?? r.fecha"></span>

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

        {{-- ═══════════════════════════════════════════ --}}
        {{-- CONTROL DE CAMIONES --}}
        {{-- ═══════════════════════════════════════════ --}}
        <div x-show="tab==='escom'" x-cloak class="mt-4 space-y-3">

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <div class="flex flex-wrap gap-2 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Desde</label>
                        <input type="date" x-model="escom.desde"
                               class="border rounded px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Hasta</label>
                        <input type="date" x-model="escom.hasta"
                               class="border rounded px-3 py-2 text-sm">
                    </div>
                    <button @click="cargarEscombro()"
                            class="px-4 py-2 bg-gray-900 text-white rounded text-sm">
                        Filtrar
                    </button>
                    <a :href="urlPdfEscombro()"
                       target="_blank"
                       class="px-4 py-2 border rounded text-sm bg-white hover:bg-gray-50">
                        PDF
                    </a>
                    <button @click="exportarEscombro()"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-sm font-medium transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        Exportar Excel
                    </button>
                </div>
            </div>

            <div x-show="loadingEscom" class="text-sm text-gray-500 px-1">Cargando...</div>

            {{-- Resumen total --}}
            <div x-show="escombros.length > 0 && !loadingEscom"
                 class="bg-gray-900 text-white rounded-lg px-4 py-3 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="text-sm font-medium">Total m³ en el período</div>
                    <button @click="toggleExpandirTodoEscombro()"
                            class="px-2 py-1 rounded text-xs border border-white/30 hover:bg-white/10 transition-colors whitespace-nowrap"
                            x-text="todosEscomExpandidos() ? 'Colapsar todo' : 'Expandir todo'"></button>
                </div>
                <div class="text-2xl font-extrabold"
                     x-text="escombros.reduce((s,r) => s + (parseFloat(r.metros_cubicos) || 0), 0).toFixed(1) + ' m³'">
                </div>
            </div>

            {{-- Bloques expandibles por día --}}
            <div x-show="escombros.length > 0 && !loadingEscom" class="space-y-2">
                <template x-for="grupo in escomGrupos()" :key="grupo.fecha">
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">

                        {{-- ── Encabezado del día (siempre visible) ── --}}
                        <button type="button"
                                @click="toggleEscomDia(grupo.fecha)"
                                class="w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors select-none group">
                            <div class="flex items-center gap-3">
                                <svg :class="escomExpandidos[grupo.fecha] ? 'rotate-90 text-gray-600' : 'text-gray-300'"
                                     class="w-4 h-4 transition-transform duration-200 flex-shrink-0"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="font-bold text-gray-900 text-sm tracking-tight"
                                      x-text="grupo.fecha"></span>
                                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full whitespace-nowrap"
                                      x-text="grupo.count + (grupo.count === 1 ? ' viaje' : ' viajes')"></span>
                            </div>
                            <span class="font-extrabold text-gray-900 text-base tabular-nums whitespace-nowrap"
                                  x-text="grupo.total.toFixed(1) + ' m³'"></span>
                        </button>

                        {{-- ── Tabla de registros (expandible) ── --}}
                        <div x-show="escomExpandidos[grupo.fecha]"
                             class="border-t border-gray-100">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                                        <tr>
                                            <th class="px-3 py-2 text-left">H. Entrada</th>
                                            <th class="px-3 py-2 text-left">H. Salida</th>
                                            <th class="px-3 py-2 text-left">Tipo material</th>
                                            <th class="px-3 py-2 text-left">Placas</th>
                                            <th class="px-3 py-2 text-right">m³</th>
                                            <th class="px-3 py-2 text-left">Cód. recibo</th>
                                            <th class="px-3 py-2 text-left">Usuario</th>
                                            <th class="px-3 py-2 text-center">Fotos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="r in grupo.filas" :key="r.id">
                                            <tr class="border-t border-gray-50 hover:bg-gray-50"
                                                :style="r.placa_repetida ? 'background:#fff7ed;' : ''">
                                                <td class="px-3 py-2 whitespace-nowrap text-gray-700"
                                                    x-text="formatHoraEscom(r.hora_entrada)"></td>
                                                <td class="px-3 py-2 whitespace-nowrap text-gray-700"
                                                    x-text="formatHoraEscom(r.hora_salida)"></td>
                                                <td class="px-3 py-2"
                                                    x-text="r.tipo_material || '—'"></td>
                                                <td class="px-3 py-2">
                                                    <span x-text="r.placas || '—'"></span>
                                                    <template x-if="r.placa_repetida">
                                                        <span title="Esta placa aparece más de una vez hoy — posible duplicado"
                                                              style="margin-left:4px;font-size:11px;background:#fed7aa;color:#c2410c;border-radius:4px;padding:1px 5px;font-weight:600;">
                                                            ⚠ repetida
                                                        </span>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 text-right font-bold tabular-nums whitespace-nowrap"
                                                    x-text="(parseFloat(r.metros_cubicos) || 0).toFixed(1) + ' m³'"></td>
                                                <td class="px-3 py-2 text-xs text-gray-500"
                                                    x-text="r.folio_recibo || '—'"></td>
                                                <td class="px-3 py-2 text-xs text-gray-600"
                                                    x-text="r.usuario || '—'"></td>
                                                <td class="px-3 py-2 text-center">
                                                    <div class="flex gap-1 justify-center">
                                                        <template x-if="r.foto_vale_url">
                                                            <button type="button"
                                                                    @click="escomImgModal.url = r.foto_vale_url; escomImgModal.label = 'Vale'; escomImgModal.show = true"
                                                                    class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 border"
                                                                    title="Ver foto del vale">
                                                                📷 Vale
                                                            </button>
                                                        </template>
                                                        <template x-if="r.foto_camion_url">
                                                            <button type="button"
                                                                    @click="escomImgModal.url = r.foto_camion_url; escomImgModal.label = 'Camión'; escomImgModal.show = true"
                                                                    class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 border"
                                                                    title="Ver foto del camión">
                                                                🚛 Camión
                                                            </button>
                                                        </template>
                                                        <template x-if="!r.foto_vale_url && !r.foto_camion_url">
                                                            <span class="text-gray-300 text-xs">—</span>
                                                        </template>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </template>
            </div>

            <div x-show="!loadingEscom && escombros.length === 0"
                 class="bg-white shadow-sm rounded-lg p-6 text-center text-gray-500 text-sm">
                Sin registros en el rango seleccionado.
            </div>

        </div>

        {{-- Modal imagen fotos camiones --}}
        <div x-show="escomImgModal.show" x-cloak
             class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
             @keydown.escape.window="escomImgModal.show = false"
             @click.self="escomImgModal.show = false">
            <div class="relative max-w-2xl w-full">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-white font-semibold text-sm" x-text="'Foto: ' + escomImgModal.label"></span>
                    <button class="text-white bg-white/20 hover:bg-white/30 rounded px-3 py-1 text-sm"
                            @click="escomImgModal.show = false">✕ Cerrar</button>
                </div>
                <img :src="escomImgModal.url"
                     class="w-full max-h-[80vh] object-contain rounded bg-black"
                     alt="foto">
            </div>
        </div>

        </div>

        <style>[x-cloak]{display:none!important}</style>

        <script>
            function formatFecha(fechaStr) {
                if (!fechaStr) return '';
                const d = new Date(fechaStr.replace(' ', 'T'));
                if (isNaN(d)) return fechaStr;
                const mes  = String(d.getMonth() + 1).padStart(2, '0');
                const dia  = String(d.getDate()).padStart(2, '0');
                const anio = d.getFullYear();
                let h = d.getHours();
                const min = String(d.getMinutes()).padStart(2, '0');
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${mes}/${dia}/${anio} ${String(h).padStart(2,'0')}:${min} ${ampm}`;
            }

            function exploreUI() {
                return {
                    ent: { q:'', desde:'', hasta:'', vista:'tarjetas', tipo:'', seccionAbierta:{ oc:true, manual:true, transferencia:true } },
                    entradas: [],
                    detallesEntradaId: null,
                    entradaDetalle: null,
                    imgModal: { show:false, url:'' },

                    trans: { q:'', desde:'', hasta:'', dir:'todas', obra_nombre:'', vista:'tabla' },
                    transferencias: [],
                    detallesTransId: null,
                    transDetalle: null,


                    tab: 'mov',
                    loading: false,

                    mov: { q:'', desde:'', hasta:'', vista:'tarjetas' },
                    salidasTablaData: [],
                    salidasTablaExpandidos: {},
                    transSalidasData: [],
                    transSalidasExpandidos: {},
                    seccionSalidasAbierta: { salidas: true, transferencias: true },
                    entradasTablaExpandidos: {},
                    manualTablaExpandidos: {},
                    ocTablaExpandidos: {},
                    transTablaExpandidos: {},
                    inventarioFamiliaExpandidos: {},
                    inv: { q:'', vista:'tarjetas' },
                    oc:  { q:'', estado:'todas' },
                    graf: { q:'', desde:'', hasta:'', soloObraActual:true },

                    pdf: { show:false, loading:false, ok:false, error:'' },

                    escom: { desde: '', hasta: '' },
                    escombros: [],
                    loadingEscom: false,
                    escomImgModal: { show: false, url: '', label: '' },
                    escomExpandidos: {},
                    movimientos: [],
                    detallesMovId: null,
                    detalles: [],
                    movimientoCabecera: null,

                    editNivel: { detalleId: null, destinos: [], guardando: false, error: '' },

                    ajustesHistorial: [],

                    ajuste: {
                        show: false,
                        cargando: false,
                        guardando: false,
                        movimiento: null,
                        items: [],
                        observaciones: '',
                        error: '',
                        exito: '',
                    },


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
                        this.movimientoCabecera = null;
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
        this.movimientoCabecera = null; // ? limpiamos cabecera
        return;
    }

    this.loading = true;

    try {
        const res = await fetch("/explore/movimientos/" + movId + "/detalles", {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        });

        const data = await res.json(); // ? ahora recibimos objeto

        // ? detalles del movimiento
        this.detalles = Array.isArray(data.detalles) ? data.detalles : [];

        // ? informaci�n general del movimiento (obra, destino, etc.)
        this.movimientoCabecera = data.movimiento ?? null;

        this.detallesMovId = movId;

    } catch (e) {
        console.error(e);
        this.detalles = [];
        this.movimientoCabecera = null;
        this.detallesMovId = movId;
    } finally {
        this.loading = false;
    }
},


                    // ── Editar nivel / destinos de un movimiento_detalle ──────────────
                    abrirEditNivel(detalle) {
                        // Clone destinos so edits don't affect display until saved
                        const destinos = (detalle.destinos && detalle.destinos.length > 0)
                            ? detalle.destinos.map(d => ({ ...d }))
                            : [{ nivel: detalle.clasificacion || '', departamento: detalle.clasificacion_d || '', cantidad: parseFloat(detalle.cantidad) || 0 }];
                        this.editNivel = { detalleId: detalle.id, destinos, guardando: false, error: '', totalCantidad: parseFloat(detalle.cantidad) || 0 };
                    },

                    cerrarEditNivel() {
                        this.editNivel = { detalleId: null, destinos: [], guardando: false, error: '' };
                    },

                    editNivelSinDepto(nivel) {
                        if (!nivel) return false;
                        if (/^S[1-5]$/.test(nivel)) return true;
                        return ['ROOFTOP','PASILLOS','CIMENTACION','PB','GYM','AREAS_COMUNES'].includes(nivel);
                    },

                    agregarDestinoEdit() {
                        const allocated = this.editNivel.destinos.reduce((s, d) => s + (parseFloat(d.cantidad) || 0), 0);
                        const remaining = Math.max(0, (this.editNivel.totalCantidad || 0) - allocated);
                        this.editNivel.destinos.push({ nivel: '', departamento: '', cantidad: parseFloat(remaining.toFixed(4)) || 1 });
                    },

                    quitarDestinoEdit(di) {
                        if (this.editNivel.destinos.length <= 1) return;
                        this.editNivel.destinos.splice(di, 1);
                    },

                    async guardarEditNivel(detalleId) {
                        this.editNivel.guardando = true;
                        this.editNivel.error = '';

                        const suma = this.editNivel.destinos.reduce((s, d) => s + (parseFloat(d.cantidad) || 0), 0);
                        if (Math.abs(suma - (this.editNivel.totalCantidad || 0)) > 0.01) {
                            this.editNivel.error = 'La suma (' + suma.toFixed(2) + ') no coincide con la cantidad total (' + (this.editNivel.totalCantidad || 0).toFixed(2) + ').';
                            this.editNivel.guardando = false;
                            return;
                        }

                        try {
                            const fd = new FormData();
                            fd.append('_method', 'PUT');
                            this.editNivel.destinos.forEach((d, i) => {
                                fd.append('destinos[' + i + '][nivel]',        d.nivel || '');
                                fd.append('destinos[' + i + '][departamento]', d.departamento || '');
                                fd.append('destinos[' + i + '][cantidad]',     d.cantidad);
                            });

                            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                            const res = await fetch('/salidas/detalles/' + detalleId + '/destinos', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: fd,
                            });

                            const data = await res.json().catch(() => ({}));

                            if (res.ok && data.ok) {
                                // Update local detalles array so display refreshes
                                const d = this.detalles.find(d => d.id === detalleId);
                                if (d) {
                                    d.destinos = this.editNivel.destinos.map(x => ({ ...x }));
                                    if (d.destinos.length > 0) {
                                        d.clasificacion   = d.destinos[0].nivel;
                                        d.clasificacion_d = d.destinos[0].departamento;
                                    }
                                }
                                this.cerrarEditNivel();
                            } else {
                                this.editNivel.error = data.message || 'Error al guardar.';
                            }
                        } catch (e) {
                            console.error(e);
                            this.editNivel.error = 'Error de red.';
                        } finally {
                            this.editNivel.guardando = false;
                        }
                    },

                    async cargarInventario() {
                        this.loading = true;
                        this.inventarioFamiliaExpandidos = {};
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
}, // ✅ ESTA COMA ES LA QUE TE FALTABA
                    async cargarEntradas() {
    this.loading = true;
    this.detallesEntradaId = null;
    this.entradaDetalle = null;
    this.entradasTablaExpandidos = {};

    try {
        const params = new URLSearchParams();
        if (this.ent.q)     params.set('q',     this.ent.q);
        if (this.ent.desde) params.set('desde', this.ent.desde);
        if (this.ent.hasta) params.set('hasta', this.ent.hasta);
        if (this.ent.tipo)  params.set('tipo',  this.ent.tipo);

        const res = await fetch("{{ route('explore.entradas') }}?" + params.toString(), {
            headers: {'Accept':'application/json'},
            cache: 'no-store'
        });

        this.entradas = await res.json();
    } catch (e) {
        console.error(e);
        this.entradas = [];
    } finally {
        this.loading = false;
    }
},

                    async verEntradaDetalles(id) {
    if (this.detallesEntradaId === id) {
        this.detallesEntradaId = null;
        this.entradaDetalle = null;
        return;
    }

    this.loading = true;
    this.entradaDetalle = null;

    try {
        const res = await fetch("{{ url('/explore/entradas') }}/" + id + "/detalles", {
            headers: {'Accept':'application/json'},
            cache: 'no-store'
        });

        this.entradaDetalle = await res.json();
        this.detallesEntradaId = id;
    } catch (e) {
        console.error(e);
        this.entradaDetalle = null;
        this.detallesEntradaId = null;
    } finally {
        this.loading = false;
    }
},

                    transferenciasFiltered() {
                        return this.transferencias.filter(tr => {
                            if (this.trans.dir !== 'todas' && tr.direccion !== this.trans.dir) return false;
                            if (this.trans.obra_nombre && tr.obra_origen !== this.trans.obra_nombre && tr.obra_destino !== this.trans.obra_nombre) return false;
                            return true;
                        });
                    },

                    obrasEnTransferencias() {
                        const set = new Set();
                        this.transferencias.forEach(tr => {
                            if (tr.obra_origen) set.add(tr.obra_origen);
                            if (tr.obra_destino) set.add(tr.obra_destino);
                        });
                        return Array.from(set).sort();
                    },

                    async cargarTransferencias() {
                        this.loading = true;
                        this.detallesTransId = null;
                        this.transDetalle = null;
                        try {
                            const params = new URLSearchParams();
                            if (this.trans.q)     params.set('q',     this.trans.q);
                            if (this.trans.desde) params.set('desde', this.trans.desde);
                            if (this.trans.hasta) params.set('hasta', this.trans.hasta);

                            const res = await fetch("{{ route('explore.transferencias') }}?" + params.toString(), {
                                headers: { 'Accept': 'application/json' },
                                cache: 'no-store'
                            });
                            this.transferencias = await res.json();
                        } catch (e) {
                            console.error(e);
                            this.transferencias = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    exportarTransferenciasExcel() {
                        const params = new URLSearchParams();
                        if (this.trans.q)          params.set('q',          this.trans.q);
                        if (this.trans.desde)      params.set('desde',      this.trans.desde);
                        if (this.trans.hasta)      params.set('hasta',      this.trans.hasta);
                        if (this.trans.obra_nombre) params.set('obra_nombre', this.trans.obra_nombre);
                        window.location.href = "{{ route('explore.exportar.transferencias') }}?" + params.toString();
                    },

                    formatHoraEscom(h24) {
                        if (!h24) return '—';
                        const parts = h24.split(':');
                        let h = parseInt(parts[0]);
                        const min = parts[1] || '00';
                        const ampm = h >= 12 ? 'PM' : 'AM';
                        h = (h % 12) || 12;
                        return String(h).padStart(2,'0') + ':' + min + ' ' + ampm;
                    },

                    escomFechaHoy() {
                        const d = new Date();
                        return d.getFullYear() + '-'
                            + String(d.getMonth() + 1).padStart(2, '0') + '-'
                            + String(d.getDate()).padStart(2, '0');
                    },

                    async cargarEscombro() {
                        this.loadingEscom = true;
                        this.escomExpandidos = {};
                        try {
                            const params = new URLSearchParams();
                            if (this.escom.desde) params.set('desde', this.escom.desde);
                            if (this.escom.hasta) params.set('hasta', this.escom.hasta);
                            const res = await fetch('/control-camiones/explore?' + params.toString(), {
                                headers: { 'Accept': 'application/json' },
                                cache: 'no-store'
                            });
                            this.escombros = await res.json();
                        } catch (e) {
                            console.error(e);
                            this.escombros = [];
                        } finally {
                            this.loadingEscom = false;
                        }
                    },

                    async verTransDetalles(id) {
                        if (this.detallesTransId === id) {
                            this.detallesTransId = null;
                            this.transDetalle = null;
                            return;
                        }
                        this.loading = true;
                        this.transDetalle = null;
                        try {
                            const res = await fetch("/explore/transferencias/" + id + "/detalles", {
                                headers: { 'Accept': 'application/json' },
                                cache: 'no-store'
                            });
                            this.transDetalle = await res.json();
                            this.detallesTransId = id;
                        } catch (e) {
                            console.error(e);
                            this.transDetalle = null;
                            this.detallesTransId = null;
                        } finally {
                            this.loading = false;
                        }
                    },

                    // ─── TABLA: cargar datos de salidas ───────────────────────
                    async cargarSalidasTabla() {
                        this.loading = true;
                        this.salidasTablaExpandidos = {};
                        this.transSalidasExpandidos = {};
                        try {
                            const params = new URLSearchParams();
                            if (this.mov.q)     params.set('q',     this.mov.q);
                            if (this.mov.desde) params.set('desde', this.mov.desde);
                            if (this.mov.hasta) params.set('hasta', this.mov.hasta);
                            const [resSal, resTrans] = await Promise.all([
                                fetch('/explore/salidas/tabla?' + params.toString(), { headers:{'Accept':'application/json'}, cache:'no-store' }),
                                fetch('/explore/transferencias/enviadas/tabla?' + params.toString(), { headers:{'Accept':'application/json'}, cache:'no-store' }),
                            ]);
                            this.salidasTablaData = await resSal.json();
                            this.transSalidasData = await resTrans.json();
                        } catch (e) {
                            console.error(e);
                            this.salidasTablaData = [];
                            this.transSalidasData = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    transSalidasGruposFlat() {
                        return this._gruposFlat(
                            this.transSalidasData.map(e => ({ ...e, cantidad_llego: e.cantidad })),
                            this.transSalidasExpandidos, 'ts_'
                        );
                    },
                    toggleTransSalidaGrupo(familia) {
                        this.transSalidasExpandidos = { ...this.transSalidasExpandidos, [familia]: !this.transSalidasExpandidos[familia] };
                    },

                    async cargarHistorialAjustes() {
                        this.loading = true;
                        try {
                            const params = new URLSearchParams();
                            if (this.mov.desde) params.set('desde', this.mov.desde);
                            if (this.mov.hasta) params.set('hasta', this.mov.hasta);
                            const res = await fetch('/explore/ajustes?' + params.toString(), {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });
                            this.ajustesHistorial = await res.json();
                        } catch (e) {
                            console.error(e);
                            this.ajustesHistorial = [];
                        } finally {
                            this.loading = false;
                        }
                    },

                    async abrirAjuste(movimiento) {
                        this.ajuste.show        = true;
                        this.ajuste.cargando    = true;
                        this.ajuste.movimiento  = movimiento;
                        this.ajuste.items       = [];
                        this.ajuste.observaciones = '';
                        this.ajuste.error       = '';
                        this.ajuste.exito       = '';
                        this.ajuste.guardando   = false;

                        try {
                            const res = await fetch('/explore/movimientos/' + movimiento.id + '/ajuste-detalles', {
                                headers: {'Accept':'application/json'},
                                cache: 'no-store'
                            });
                            const data = await res.json();
                            this.ajuste.items = data.map(d => ({
                                ...d,
                                cantidad_ajuste: 0,
                            }));
                        } catch(e) {
                            this.ajuste.error = 'Error al cargar productos.';
                        } finally {
                            this.ajuste.cargando = false;
                        }
                    },

                    ajusteTieneItems() {
                        return this.ajuste.items.some(i => Number(i.cantidad_ajuste) > 0);
                    },

                    async confirmarAjuste() {
                        const itemsValidos = this.ajuste.items.filter(i => Number(i.cantidad_ajuste) > 0);
                        if (!itemsValidos.length) return;

                        const invalido = itemsValidos.find(i => Number(i.cantidad_ajuste) > Number(i.disponible));
                        if (invalido) {
                            this.ajuste.error = invalido.descripcion + ': cantidad supera lo disponible (' + invalido.disponible + ').';
                            return;
                        }

                        this.ajuste.guardando = true;
                        this.ajuste.error     = '';
                        this.ajuste.exito     = '';

                        try {
                            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                            const res = await fetch('/explore/movimientos/' + this.ajuste.movimiento.id + '/ajustar', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    items: itemsValidos.map(i => ({
                                        detalle_id: i.id,
                                        cantidad: Number(i.cantidad_ajuste),
                                    })),
                                    observaciones: this.ajuste.observaciones,
                                }),
                            });
                            const data = await res.json();
                            if (!res.ok) {
                                this.ajuste.error = data.error || 'Error al guardar.';
                            } else {
                                const advertencias = data.errores?.length
                                    ? ' Advertencias: ' + data.errores.join(', ')
                                    : '';
                                this.ajuste.exito = data.ajustes + ' ajuste(s) registrado(s) correctamente.' + advertencias;
                                // Refrescar disponibles
                                setTimeout(() => this.abrirAjuste(this.ajuste.movimiento), 1200);
                            }
                        } catch(e) {
                            this.ajuste.error = 'Error de conexión.';
                        } finally {
                            this.ajuste.guardando = false;
                        }
                    },

                    // ─── TABLA: agrupar por familia ───────────────────────────
                    agruparPorFamilia(items, claveQty) {
                        const grupos = {};
                        for (const row of items) {
                            const familia = ((row.familia || '').trim()) || 'SIN FAMILIA';
                            if (!grupos[familia]) {
                                grupos[familia] = {
                                    familia,
                                    cantidad_total: 0,
                                    importe_total:  0,
                                    filas: [],
                                };
                            }
                            grupos[familia].cantidad_total += parseFloat(row[claveQty] || 0);
                            if (row.importe !== null && row.importe !== undefined) {
                                grupos[familia].importe_total += parseFloat(row.importe || 0);
                            }
                            grupos[familia].filas.push(row);
                        }
                        return Object.values(grupos).sort((a, b) => {
                            if (a.familia === 'SIN FAMILIA') return 1;
                            if (b.familia === 'SIN FAMILIA') return -1;
                            return a.familia.localeCompare(b.familia, 'es-MX');
                        });
                    },

                    // ─── TABLA: agrupar por insumo (legacy) ───────────────────
                    agruparPorInsumo(items, claveInsumo, claveQty) {
                        const grupos = {};
                        for (const row of items) {
                            const key = (row[claveInsumo] || '') + '|' + (row.descripcion || '');
                            if (!grupos[key]) {
                                grupos[key] = {
                                    insumo_id:      row[claveInsumo] || '',
                                    descripcion:    row.descripcion || '',
                                    unidad:         row.unidad || '',
                                    cantidad_total: 0,
                                    precio_unitario: row.precio_unitario ?? null,
                                    importe_total:  0,
                                    filas: [],
                                };
                            }
                            grupos[key].cantidad_total += parseFloat(row[claveQty] || 0);
                            if (row.importe !== null && row.importe !== undefined) {
                                grupos[key].importe_total += parseFloat(row.importe || 0);
                            }
                            grupos[key].filas.push(row);
                        }
                        return Object.values(grupos);
                    },

                    salidasGruposFlat() {
                        const result = [];
                        const grupos = this.agruparPorFamilia(this.salidasTablaData, 'cantidad');
                        for (const grupo of grupos) {
                            result.push({
                                _tipo: 'familia', _key: 'f_' + grupo.familia,
                                familia: grupo.familia,
                                cantidad_total: grupo.cantidad_total,
                                importe_total: grupo.importe_total,
                                count: grupo.filas.length,
                            });
                            if (this.salidasTablaExpandidos[grupo.familia]) {
                                for (const fila of grupo.filas) {
                                    result.push({ _tipo: 'detalle', _key: 'd_' + fila.id, ...fila });
                                }
                            }
                        }
                        return result;
                    },

                    entradasPorTipo(tipo) {
                        if (tipo === 'oc') return this.entradas.filter(e => !e.tipo || e.tipo === 'oc');
                        return this.entradas.filter(e => e.tipo === tipo);
                    },

                    _gruposFlat(items, expandidos, keyPrefix) {
                        const mapped = items.map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' }));
                        const result = [];
                        const grupos = this.agruparPorFamilia(mapped, 'cantidad_llego');
                        for (const grupo of grupos) {
                            result.push({
                                _fila: 'familia', _key: keyPrefix + 'f_' + grupo.familia,
                                familia: grupo.familia,
                                cantidad_total: grupo.cantidad_total,
                                importe_total:  grupo.importe_total,
                                count: grupo.filas.length,
                            });
                            if (expandidos[grupo.familia]) {
                                for (const fila of grupo.filas) {
                                    result.push({ _fila: 'detalle', _key: keyPrefix + 'd_' + fila.id, ...fila });
                                }
                            }
                        }
                        return result;
                    },

                    entradasOcGruposFlat() {
                        return this._gruposFlat(this.entradasPorTipo('oc'), this.ocTablaExpandidos, 'oc_');
                    },
                    toggleOcGrupo(familia) {
                        this.ocTablaExpandidos = { ...this.ocTablaExpandidos, [familia]: !this.ocTablaExpandidos[familia] };
                    },

                    entradasManualGruposFlat() {
                        const items = this.entradasPorTipo('manual').map(e => ({
                            ...e,
                            familia: e.familia || 'SIN FAMILIA',
                        }));
                        const result = [];
                        const grupos = this.agruparPorFamilia(items, 'cantidad_llego');
                        for (const grupo of grupos) {
                            result.push({
                                _fila: 'familia', _key: 'mf_' + grupo.familia,
                                familia: grupo.familia,
                                cantidad_total: grupo.cantidad_total,
                                importe_total:  grupo.importe_total,
                                count: grupo.filas.length,
                            });
                            if (this.manualTablaExpandidos[grupo.familia]) {
                                for (const fila of grupo.filas) {
                                    result.push({ _fila: 'detalle', _key: 'md_' + fila.id, ...fila });
                                }
                            }
                        }
                        return result;
                    },

                    toggleManualGrupo(familia) {
                        this.manualTablaExpandidos = { ...this.manualTablaExpandidos, [familia]: !this.manualTablaExpandidos[familia] };
                    },

                    entradasTransGruposFlat() {
                        return this._gruposFlat(this.entradasPorTipo('transferencia'), this.transTablaExpandidos, 'tr_');
                    },
                    toggleTransGrupo(familia) {
                        this.transTablaExpandidos = { ...this.transTablaExpandidos, [familia]: !this.transTablaExpandidos[familia] };
                    },

                    entradasGruposFlat() {
                        const mapped = this.entradas.map(e => ({
                            id:              e.id,
                            familia:         e.familia || 'SIN FAMILIA',
                            insumo_id:       e.insumo,
                            descripcion:     e.descripcion,
                            unidad:          e.unidad,
                            cantidad:        e.cantidad_llego,
                            cantidad_llego:  e.cantidad_llego,
                            fecha_recibido:  e.fecha_recibido,
                            precio_unitario: e.precio_unitario ?? null,
                            importe:         e.importe ?? null,
                        }));
                        const result = [];
                        const grupos = this.agruparPorFamilia(mapped, 'cantidad');
                        for (const grupo of grupos) {
                            result.push({
                                _tipo: 'familia', _key: 'f_' + grupo.familia,
                                familia: grupo.familia,
                                cantidad_total: grupo.cantidad_total,
                                importe_total: grupo.importe_total,
                                count: grupo.filas.length,
                            });
                            if (this.entradasTablaExpandidos[grupo.familia]) {
                                for (const fila of grupo.filas) {
                                    result.push({ _tipo: 'detalle', _key: 'd_' + fila.id, ...fila });
                                }
                            }
                        }
                        return result;
                    },

                    inventarioGruposFlat() {
                        const result = [];
                        const grupos = this.agruparPorFamilia(this.inventario, 'cantidad');
                        for (const grupo of grupos) {
                            result.push({
                                _tipo: 'familia', _key: 'f_' + grupo.familia,
                                familia: grupo.familia,
                                cantidad_total: grupo.cantidad_total,
                                importe_total: grupo.importe_total,
                                count: grupo.filas.length,
                            });
                            if (this.inventarioFamiliaExpandidos[grupo.familia]) {
                                for (const fila of grupo.filas) {
                                    result.push({ _tipo: 'detalle', _key: 'd_' + fila.id, ...fila });
                                }
                            }
                        }
                        return result;
                    },

                    totalSalidas() {
                        return this.salidasTablaData.reduce((s, r) => s + (r.importe ?? 0), 0);
                    },

                    totalEntradas() {
                        return this.entradas.reduce((s, e) => s + (e.importe ?? 0), 0);
                    },

                    totalInventario() {
                        return this.inventario.reduce((s, p) => s + (p.importe ?? 0), 0);
                    },

                    toggleSalidaGrupo(familia) {
                        this.salidasTablaExpandidos = {
                            ...this.salidasTablaExpandidos,
                            [familia]: !this.salidasTablaExpandidos[familia]
                        };
                    },

                    toggleEntradaGrupo(familia) {
                        this.entradasTablaExpandidos = {
                            ...this.entradasTablaExpandidos,
                            [familia]: !this.entradasTablaExpandidos[familia]
                        };
                    },

                    toggleInventarioFamilia(familia) {
                        this.inventarioFamiliaExpandidos = {
                            ...this.inventarioFamiliaExpandidos,
                            [familia]: !this.inventarioFamiliaExpandidos[familia]
                        };
                    },

                    todosSalidasExpandidos() {
                        const salGrupos   = this.agruparPorFamilia(this.salidasTablaData, 'cantidad');
                        const transGrupos = this.agruparPorFamilia(
                            this.transSalidasData.map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA', cantidad_llego: e.cantidad })),
                            'cantidad_llego'
                        );
                        const all = [...salGrupos, ...transGrupos];
                        if (all.length === 0) return false;
                        return salGrupos.every(g => this.salidasTablaExpandidos[g.familia])
                            && transGrupos.every(g => this.transSalidasExpandidos[g.familia]);
                    },

                    toggleExpandirTodoSalidas() {
                        const expandir = !this.todosSalidasExpandidos();
                        const salNuevo = {}, transNuevo = {};
                        for (const g of this.agruparPorFamilia(this.salidasTablaData, 'cantidad'))
                            salNuevo[g.familia] = expandir;
                        for (const g of this.agruparPorFamilia(
                            this.transSalidasData.map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA', cantidad_llego: e.cantidad })),
                            'cantidad_llego'
                        )) transNuevo[g.familia] = expandir;
                        this.salidasTablaExpandidos = salNuevo;
                        this.transSalidasExpandidos = transNuevo;
                        if (expandir) {
                            this.seccionSalidasAbierta = { salidas: true, transferencias: true };
                        }
                    },

                    todosEntradasExpandidos() {
                        const ocG  = this.agruparPorFamilia(this.entradasPorTipo('oc').map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' })), 'cantidad_llego');
                        const manG = this.agruparPorFamilia(this.entradasPorTipo('manual').map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' })), 'cantidad_llego');
                        const trG  = this.agruparPorFamilia(this.entradasPorTipo('transferencia').map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' })), 'cantidad_llego');
                        if (ocG.length + manG.length + trG.length === 0) return false;
                        return ocG.every(g => this.ocTablaExpandidos[g.familia])
                            && manG.every(g => this.manualTablaExpandidos[g.familia])
                            && trG.every(g => this.transTablaExpandidos[g.familia]);
                    },

                    toggleExpandirTodoEntradas() {
                        const expandir = !this.todosEntradasExpandidos();
                        const ocNuevo = {}, manNuevo = {}, transNuevo = {};
                        for (const g of this.agruparPorFamilia(this.entradasPorTipo('oc').map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' })), 'cantidad_llego'))
                            ocNuevo[g.familia] = expandir;
                        for (const g of this.agruparPorFamilia(this.entradasPorTipo('manual').map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' })), 'cantidad_llego'))
                            manNuevo[g.familia] = expandir;
                        for (const g of this.agruparPorFamilia(this.entradasPorTipo('transferencia').map(e => ({ ...e, familia: e.familia || 'SIN FAMILIA' })), 'cantidad_llego'))
                            transNuevo[g.familia] = expandir;
                        this.ocTablaExpandidos     = ocNuevo;
                        this.manualTablaExpandidos  = manNuevo;
                        this.transTablaExpandidos   = transNuevo;
                        if (expandir) {
                            this.ent.seccionAbierta = { oc: true, manual: true, transferencia: true };
                        }
                    },

                    todosInventarioExpandidos() {
                        const grupos = this.agruparPorFamilia(this.inventario, 'cantidad');
                        return grupos.length > 0 && grupos.every(g => this.inventarioFamiliaExpandidos[g.familia]);
                    },

                    toggleExpandirTodoInventario() {
                        const grupos = this.agruparPorFamilia(this.inventario, 'cantidad');
                        const expandir = !this.todosInventarioExpandidos();
                        const nuevo = {};
                        for (const g of grupos) nuevo[g.familia] = expandir;
                        this.inventarioFamiliaExpandidos = nuevo;
                    },

                    // ─── Formatters ───────────────────────────────────────────
                    formatNum(v) {
                        const n = parseFloat(v || 0);
                        if (isNaN(n)) return '0';
                        return n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    },

                    formatMoney(v) {
                        const n = parseFloat(v || 0);
                        if (isNaN(n)) return '0.00';
                        return n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    },

                    formatFechaCorta(fechaStr) {
                        if (!fechaStr) return '';
                        const d = new Date(fechaStr.replace(' ', 'T'));
                        if (isNaN(d)) return fechaStr;
                        return String(d.getDate()).padStart(2,'0') + '/' +
                               String(d.getMonth()+1).padStart(2,'0') + '/' +
                               d.getFullYear();
                    },

                    // ─── Exportar a Excel ─────────────────────────────────────
                    exportarEntradas() {
                        const params = new URLSearchParams();
                        if (this.ent.q)     params.set('q',     this.ent.q);
                        if (this.ent.desde) params.set('desde', this.ent.desde);
                        if (this.ent.hasta) params.set('hasta', this.ent.hasta);
                        window.open('/explore/exportar/entradas?' + params.toString(), '_blank');
                    },

                    exportarSalidas() {
                        const params = new URLSearchParams();
                        if (this.mov.q)     params.set('q',     this.mov.q);
                        if (this.mov.desde) params.set('desde', this.mov.desde);
                        if (this.mov.hasta) params.set('hasta', this.mov.hasta);
                        window.open('/explore/exportar/salidas?' + params.toString(), '_blank');
                    },

                    exportarInventario() {
                        const params = new URLSearchParams();
                        if (this.inv.q) params.set('q', this.inv.q);
                        window.open('/explore/exportar/inventario?' + params.toString(), '_blank');
                    },

                    exportarEscombro() {
                        const params = new URLSearchParams();
                        if (this.escom.desde) params.set('desde', this.escom.desde);
                        if (this.escom.hasta) params.set('hasta', this.escom.hasta);
                        window.open('/control-camiones/exportar?' + params.toString(), '_blank');
                    },

                    urlPdfEscombro() {
                        const params = new URLSearchParams();
                        if (this.escom.desde) params.set('desde', this.escom.desde);
                        if (this.escom.hasta) params.set('hasta', this.escom.hasta);
                        const qs = params.toString();
                        return '/control-camiones/pdf' + (qs ? '?' + qs : '');
                    },

                    // ─── Agrupación por día ───────────────────────────────────
                    // Devuelve array de grupos: { fecha, total, count, filas[] }
                    // Preserva el orden DESC que viene del servidor usando Map
                    escomGrupos() {
                        const gruposMap = new Map();
                        for (const r of this.escombros) {
                            if (!gruposMap.has(r.fecha)) {
                                gruposMap.set(r.fecha, { fecha: r.fecha, total: 0, count: 0, filas: [] });
                            }
                            const g = gruposMap.get(r.fecha);
                            g.total += parseFloat(r.metros_cubicos || 0);
                            g.count++;
                            g.filas.push(r);
                        }
                        return [...gruposMap.values()];
                    },

                    toggleEscomDia(fecha) {
                        this.escomExpandidos = { ...this.escomExpandidos, [fecha]: !this.escomExpandidos[fecha] };
                    },

                    todosEscomExpandidos() {
                        const dias = [...new Set(this.escombros.map(r => r.fecha))];
                        return dias.length > 0 && dias.every(f => this.escomExpandidos[f]);
                    },

                    toggleExpandirTodoEscombro() {
                        const dias = [...new Set(this.escombros.map(r => r.fecha))];
                        const expandir = !this.todosEscomExpandidos();
                        const nuevo = {};
                        for (const d of dias) nuevo[d] = expandir;
                        this.escomExpandidos = nuevo;
                    },

                }
            }
        </script>
    </div>
</x-app-layout>
