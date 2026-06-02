<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Actualizar Reportes</h2>
    </x-slot>

    <style>
        .ar-card        { background:#fff; border:1px solid #d1d5db; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; }
        .ar-card-header { background:#f3f4f6; border-bottom:1px solid #d1d5db; padding:12px 20px; display:flex; align-items:center; justify-content:space-between; }
        .ar-card-title  { font-weight:700; font-size:14px; color:#111827; }
        .ar-label       { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin-bottom:8px; display:block; }
        .ar-sublabel    { font-size:11px; font-weight:600; color:#9ca3af; margin:12px 0 4px; display:block; }
        .ar-checkbox    { display:flex; align-items:center; gap:8px; padding:4px 0; cursor:pointer; }
        .ar-checkbox input[type=checkbox] { width:15px; height:15px; accent-color:#4f46e5; cursor:pointer; }
        .ar-checkbox span { font-size:13px; color:#374151; }
        .ar-input       { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:12px; color:#374151; outline:none; box-sizing:border-box; }
        .ar-input:focus { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.2); }
        .ar-btn         { display:inline-flex; align-items:center; gap:6px; padding:9px 20px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; border:none; transition:opacity .15s; }
        .ar-btn:disabled{ opacity:.4; cursor:not-allowed; }
        .ar-btn-primary { background:#4f46e5; color:#fff; }
        .ar-btn-primary:hover:not(:disabled) { background:#4338ca; }
        .ar-btn-warning { background:#d97706; color:#fff; }
        .ar-btn-warning:hover:not(:disabled) { background:#b45309; }
        .ar-btn-danger  { background:#dc2626; color:#fff; }
        .ar-btn-danger:hover:not(:disabled)  { background:#b91c1c; }
        .ar-btn-ghost   { background:#f3f4f6; color:#374151; border:1px solid #d1d5db; }
        .ar-btn-ghost:hover { background:#e5e7eb; }
        .ar-btn-sm      { padding:5px 12px; font-size:12px; }
        .ar-badge       { display:inline-block; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
        .ar-stat        { background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:6px 14px; font-size:12px; color:#374151; font-weight:500; }
        .ar-filter-grp  { display:flex; border:1px solid #d1d5db; border-radius:6px; overflow:hidden; }
        .ar-filter-btn  { padding:5px 14px; font-size:12px; font-weight:500; cursor:pointer; border:none; background:#fff; color:#6b7280; }
        .ar-filter-btn.active { background:#1f2937; color:#fff; }
        .ar-tbl th      { font-size:11px; font-weight:700; padding:8px 8px; white-space:nowrap; }
        .ar-tbl td      { font-size:12px; padding:6px 8px; border-top:1px solid #f3f4f6; }
        .ar-tbl tr:hover td { background:#f9fafb; }
        .ar-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:50; display:flex; align-items:center; justify-content:center; }
        .ar-modal       { background:#fff; border-radius:10px; box-shadow:0 20px 60px rgba(0,0,0,.3); width:100%; max-width:440px; margin:16px; overflow:hidden; }
        .ar-modal-hdr   { background:#f3f4f6; border-bottom:1px solid #d1d5db; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .ar-modal-title { font-weight:700; font-size:15px; color:#111827; }
        .ar-warn-box    { background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; font-size:12px; color:#991b1b; font-weight:500; }
        [x-cloak]       { display:none !important; }
    </style>

    <div class="py-8" x-data="actualizarReportes()" x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            {{-- Banner info --}}
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 18px;font-size:13px;color:#1e40af">
                Sincroniza <strong>descripción, unidad, familia, subfamilia y PU</strong> desde el ERP (fuente correcta) hacia las tablas de reportes.
                El sistema muestra las discrepancias primero — <strong>nunca actualiza sin tu confirmación</strong>.
            </div>

            {{-- ── PASO 1: CONFIGURACIÓN ── --}}
            <div class="ar-card">
                <div class="ar-card-header">
                    <span class="ar-card-title">① Configuración</span>
                    <button x-show="comparacion !== null"
                            @click="comparacion = null; resultado = null; sel = {}"
                            style="font-size:12px;color:#6366f1;background:none;border:none;cursor:pointer;font-weight:600">
                        ← Cambiar selección
                    </button>
                </div>
                <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px">

                    {{-- Tablas --}}
                    <div>
                        <span class="ar-label">Tablas</span>
                        <span class="ar-sublabel">Salidas</span>
                        @foreach($tablasConfig->where('grupo','salidas') as $t)
                        <label class="ar-checkbox">
                            <input type="checkbox" value="{{ $t['key'] }}" x-model="tablasSeleccionadas">
                            <span>{{ $t['label'] }}</span>
                        </label>
                        @endforeach
                        <span class="ar-sublabel">Entradas</span>
                        @foreach($tablasConfig->where('grupo','entradas') as $t)
                        <label class="ar-checkbox">
                            <input type="checkbox" value="{{ $t['key'] }}" x-model="tablasSeleccionadas">
                            <span>{{ $t['label'] }}</span>
                        </label>
                        @endforeach
                    </div>

                    {{-- Obras --}}
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <span class="ar-label" style="margin-bottom:0">Obras</span>
                            <div style="display:flex;gap:8px;font-size:11px">
                                <button @click="obrasSeleccionadas = obras.map(o=>o.id)"
                                        style="color:#4f46e5;background:none;border:none;cursor:pointer;font-weight:600">Todas</button>
                                <span style="color:#d1d5db">|</span>
                                <button @click="obrasSeleccionadas = []"
                                        style="color:#6b7280;background:none;border:none;cursor:pointer">Ninguna</button>
                            </div>
                        </div>
                        <input x-model="buscarObra" type="text" placeholder="Buscar obra…" class="ar-input" style="margin-bottom:8px">
                        <div x-show="obrasLoading" style="font-size:12px;color:#9ca3af;padding:8px 0">Cargando…</div>
                        <div x-show="!obrasLoading" style="max-height:180px;overflow-y:auto">
                            <template x-for="obra in obrasFiltradas" :key="obra.id">
                                <label class="ar-checkbox">
                                    <input type="checkbox" :value="obra.id" x-model="obrasSeleccionadas">
                                    <span style="font-size:12px" x-text="obra.nombre"></span>
                                </label>
                            </template>
                        </div>
                        <p x-show="obrasSeleccionadas.length" style="font-size:11px;color:#4f46e5;margin-top:6px;font-weight:600"
                           x-text="obrasSeleccionadas.length + ' seleccionada(s)'"></p>
                    </div>

                    {{-- Campos --}}
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <span class="ar-label" style="margin-bottom:0">Campos</span>
                            <div style="display:flex;gap:8px;font-size:11px">
                                <button @click="camposSeleccionados = camposDisponibles.map(c=>c.key)"
                                        style="color:#4f46e5;background:none;border:none;cursor:pointer;font-weight:600">Todos</button>
                                <span style="color:#d1d5db">|</span>
                                <button @click="camposSeleccionados = []"
                                        style="color:#6b7280;background:none;border:none;cursor:pointer">Ninguno</button>
                            </div>
                        </div>
                        <template x-for="campo in camposDisponibles" :key="campo.key">
                            <label class="ar-checkbox">
                                <input type="checkbox" :value="campo.key" x-model="camposSeleccionados">
                                <span x-text="campo.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <div style="padding:0 20px 20px;display:flex;align-items:center;gap:12px">
                    <button @click="comparar()"
                            :disabled="comparando || !tablasSeleccionadas.length || !obrasSeleccionadas.length || !camposSeleccionados.length"
                            class="ar-btn ar-btn-primary">
                        <svg x-show="comparando" style="width:16px;height:16px;animation:spin 1s linear infinite" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="comparando ? 'Comparando con ERP…' : '🔍 Comparar con ERP'"></span>
                    </button>
                    <span x-show="comparando" style="font-size:12px;color:#6b7280">Consultando ERP — puede tardar unos segundos…</span>
                </div>
            </div>

            {{-- ── PASO 2: RESULTADOS ── --}}
            <div x-show="comparacion !== null" x-cloak class="ar-card">

                {{-- Stats --}}
                <div class="ar-card-header">
                    <span class="ar-card-title">② Discrepancias encontradas</span>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <span class="ar-stat" x-text="(comparacion?.total ?? 0) + ' insumos comparados'"></span>
                        <span class="ar-stat" style="background:#fefce8;border-color:#fde68a;color:#92400e"
                              x-text="(comparacion?.con_diffs ?? 0) + ' con diferencias'"></span>
                        <span class="ar-stat" style="color:#9ca3af"
                              x-text="(comparacion?.sin_erp ?? 0) + ' sin ERP'"></span>
                    </div>
                </div>

                {{-- Controles --}}
                <div style="padding:10px 20px;border-bottom:1px solid #f3f4f6;display:flex;flex-wrap:wrap;align-items:center;gap:8px">
                    <div class="ar-filter-grp">
                        <button @click="filtro='todos'" :class="filtro==='todos'?'active':''" class="ar-filter-btn">Todos</button>
                        <button @click="filtro='diffs'" :class="filtro==='diffs'?'active':''" class="ar-filter-btn" style="border-left:1px solid #d1d5db">Solo diferencias</button>
                    </div>
                    <button @click="selDiffs()" class="ar-btn ar-btn-ghost ar-btn-sm">☑ Seleccionar diferencias</button>
                    <button @click="selTodo(true)"  class="ar-btn ar-btn-ghost ar-btn-sm">Todo</button>
                    <button @click="selTodo(false)" class="ar-btn ar-btn-ghost ar-btn-sm">Ninguno</button>
                    <span style="margin-left:auto;font-size:12px;color:#6b7280;font-weight:600"
                          x-text="selCount + ' seleccionados'"></span>
                </div>

                {{-- Tabla --}}
                <div style="overflow-x:auto">
                    <div style="overflow-y:auto;max-height:540px">
                        <table class="ar-tbl" style="width:100%;border-collapse:collapse;min-width:860px">
                            <thead style="position:sticky;top:0;z-index:10">
                                <tr style="background:#1f2937;color:#fff;text-align:center">
                                    <th style="width:36px;padding:10px 6px"></th>
                                    <th style="text-align:left;padding:10px 8px;min-width:120px">Insumo</th>
                                    <th style="min-width:80px">Tablas</th>
                                    <th style="width:40px">N</th>
                                    <template x-if="camposSeleccionados.includes('descripcion')">
                                        <th colspan="2">Descripción</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('unidad')">
                                        <th colspan="2">Unidad</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('familia')">
                                        <th colspan="2">Familia</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('subfamilia')">
                                        <th colspan="2">Subfamilia</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('precio_unitario')">
                                        <th colspan="2">PU</th>
                                    </template>
                                </tr>
                                <tr style="background:#374151;color:#d1d5db;text-align:center;font-size:11px">
                                    <th></th><th></th><th></th><th></th>
                                    <template x-if="camposSeleccionados.includes('descripcion')">
                                        <th style="padding:6px 8px">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('descripcion')">
                                        <th style="padding:6px 8px;color:#93c5fd;font-weight:700">ERP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('unidad')">
                                        <th style="padding:6px 8px">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('unidad')">
                                        <th style="padding:6px 8px;color:#93c5fd;font-weight:700">ERP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('familia')">
                                        <th style="padding:6px 8px">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('familia')">
                                        <th style="padding:6px 8px;color:#93c5fd;font-weight:700">ERP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('subfamilia')">
                                        <th style="padding:6px 8px">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('subfamilia')">
                                        <th style="padding:6px 8px;color:#93c5fd;font-weight:700">ERP</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('precio_unitario')">
                                        <th style="padding:6px 8px">Sistema</th>
                                    </template>
                                    <template x-if="camposSeleccionados.includes('precio_unitario')">
                                        <th style="padding:6px 8px;color:#93c5fd;font-weight:700">ERP</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="item in itemsFiltrados" :key="item.insumo_id">
                                    <tr :style="item.diffs.length > 0 ? 'border-left:3px solid #f59e0b' : 'border-left:3px solid transparent'">
                                        <td style="text-align:center">
                                            <input type="checkbox"
                                                   :checked="!!sel[item.insumo_id]"
                                                   :disabled="!item.en_erp"
                                                   @change="e => { sel = {...sel, [item.insumo_id]: e.target.checked} }"
                                                   style="width:14px;height:14px;accent-color:#4f46e5;cursor:pointer">
                                        </td>
                                        <td>
                                            <div style="font-family:monospace;color:#111827;font-size:11px;font-weight:600" x-text="item.insumo_id"></div>
                                            <span x-show="!item.en_erp" style="font-size:10px;color:#9ca3af;font-style:italic">sin ERP</span>
                                        </td>
                                        <td style="text-align:center">
                                            <div style="display:flex;flex-wrap:wrap;gap:3px;justify-content:center">
                                                <template x-for="t in item.tablas" :key="t">
                                                    <span style="display:inline-block;padding:2px 5px;border-radius:4px;font-size:10px;font-weight:700"
                                                          :style="tablaColor(t)"
                                                          x-text="tablaLabel(t)"></span>
                                                </template>
                                            </div>
                                        </td>
                                        <td style="text-align:center;color:#6b7280;font-size:11px" x-text="item.n"></td>

                                        <template x-if="camposSeleccionados.includes('descripcion')">
                                            <td :style="cc(item,'descripcion','s')"
                                                style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                                :title="item.local.descripcion"
                                                x-text="cv(item,'descripcion',item.local.descripcion)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('descripcion')">
                                            <td :style="cc(item,'descripcion','e')"
                                                style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                                :title="item.erp?.descripcion||''"
                                                x-text="item.erp ? (item.erp.descripcion||'—') : '–'"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('unidad')">
                                            <td :style="cc(item,'unidad','s')" style="text-align:center"
                                                x-text="cv(item,'unidad',item.local.unidad)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('unidad')">
                                            <td :style="cc(item,'unidad','e')" style="text-align:center"
                                                x-text="item.erp ? (item.erp.unidad||'—') : '–'"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('familia')">
                                            <td :style="cc(item,'familia','s')" style="white-space:nowrap"
                                                x-text="cv(item,'familia',item.local.familia)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('familia')">
                                            <td :style="cc(item,'familia','e')" style="white-space:nowrap"
                                                x-text="item.erp ? (item.erp.familia||'—') : '–'"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('subfamilia')">
                                            <td :style="cc(item,'subfamilia','s')" style="white-space:nowrap"
                                                x-text="cv(item,'subfamilia',item.local.subfamilia)"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('subfamilia')">
                                            <td :style="cc(item,'subfamilia','e')" style="white-space:nowrap"
                                                x-text="item.erp ? (item.erp.subfamilia||'—') : '–'"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('precio_unitario')">
                                            <td :style="cc(item,'precio_unitario','s')" style="text-align:right"
                                                x-text="cv(item,'precio_unitario',item.local.precio_unitario, v => '$'+Number(v).toFixed(2))"></td>
                                        </template>
                                        <template x-if="camposSeleccionados.includes('precio_unitario')">
                                            <td :style="cc(item,'precio_unitario','e')" style="text-align:right"
                                                x-text="item.erp?.precio_unitario!=null ? '$'+Number(item.erp.precio_unitario).toFixed(2) : '–'"></td>
                                        </template>
                                    </tr>
                                </template>
                                <tr x-show="itemsFiltrados.length === 0">
                                    <td colspan="20" style="text-align:center;padding:48px;color:#9ca3af;font-size:13px">
                                        No hay registros con el filtro seleccionado.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Acciones --}}
                <div style="padding:16px 20px;border-top:1px solid #f3f4f6;display:flex;flex-wrap:wrap;align-items:center;gap:10px;background:#fafafa">
                    <button @click="modal = true; modalModo = 'seleccionados'"
                            :disabled="selCount === 0 || aplicando"
                            class="ar-btn ar-btn-warning">
                        ✔ Actualizar seleccionados (<span x-text="selCount"></span>)
                    </button>
                    <button @click="modal = true; modalModo = 'todos'"
                            :disabled="!comparacion?.con_diffs || aplicando"
                            class="ar-btn ar-btn-danger">
                        ⚡ Actualizar todo con diferencias (<span x-text="comparacion?.con_diffs ?? 0"></span>)
                    </button>
                    <div x-show="aplicando" style="display:flex;align-items:center;gap:8px;background:#fefce8;border:1px solid #fde68a;border-radius:6px;padding:6px 14px;font-size:12px;color:#92400e">
                        <svg style="width:14px;height:14px;animation:spin 1s linear infinite;color:#d97706" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        Actualizando en segundo plano…
                    </div>
                </div>
            </div>

            {{-- ── PASO 3: RESULTADO ── --}}
            <div x-show="resultado !== null" x-cloak class="ar-card" style="border-color:#86efac">
                <div class="ar-card-header" style="background:#f0fdf4;border-color:#86efac">
                    <span class="ar-card-title" style="color:#166534">✓ Actualización completada</span>
                    <span style="font-size:12px;color:#6b7280" x-text="resultado ? (resultado.tiempo_ms/1000).toFixed(1)+'s' : ''"></span>
                </div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:16px">
                    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 24px;text-align:center;display:inline-block">
                        <div style="font-size:36px;font-weight:800;color:#15803d" x-text="resultado ? resultado.total.toLocaleString('es-MX') : 0"></div>
                        <div style="font-size:12px;color:#6b7280;margin-top:4px">Registros actualizados</div>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px">
                        <template x-if="resultado">
                            <template x-for="[key, n] in Object.entries(resultado.totales ?? {})" :key="key">
                                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 14px;text-align:center">
                                    <div style="font-weight:700;font-size:16px;color:#374151" x-text="n"></div>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:2px" x-text="tablaLabel(key)"></div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>

        </div>

        {{-- ── MODAL CONFIRMACIÓN ── --}}
        <div x-show="modal" x-cloak class="ar-modal-overlay" @keydown.escape.window="modal = false">
            <div class="ar-modal" @click.outside="modal = false">
                <div class="ar-modal-hdr">
                    <span class="ar-modal-title">Confirmar actualización</span>
                    <button @click="modal = false" style="background:none;border:none;cursor:pointer;font-size:22px;color:#6b7280;line-height:1">×</button>
                </div>
                <div style="padding:20px;display:flex;flex-direction:column;gap:12px;font-size:13px;color:#374151">
                    <p x-show="modalModo === 'seleccionados'">
                        Se actualizarán <strong x-text="selCount"></strong> insumo(s) seleccionado(s)
                        en las tablas marcadas, usando los valores del <strong>ERP como fuente</strong>.
                    </p>
                    <p x-show="modalModo === 'todos'">
                        Se actualizarán <strong>todos</strong> los insumos con diferencias
                        (<strong x-text="comparacion?.con_diffs ?? 0"></strong>) en las tablas marcadas,
                        usando los valores del <strong>ERP como fuente</strong>.
                    </p>
                    <div class="ar-warn-box">
                        ⚠ Esta acción sobreescribirá los campos seleccionados. No afecta cantidades ni movimientos históricos.
                    </div>
                    <p style="font-size:11px;color:#9ca3af">
                        Campos: <span x-text="camposSeleccionados.join(', ')"></span>
                    </p>
                </div>
                <div style="padding:0 20px 20px;display:flex;gap:10px">
                    <button @click="modal = false" class="ar-btn ar-btn-ghost" style="flex:1;justify-content:center">
                        Cancelar
                    </button>
                    <button @click="aplicar()" class="ar-btn ar-btn-danger" style="flex:1;justify-content:center">
                        Sí, actualizar
                    </button>
                </div>
            </div>
        </div>

    </div>

    <style>
    @keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
    </style>

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
                salidas:             'background:#e0e7ff;color:#3730a3',
                transferencias_env:  'background:#ffedd5;color:#c2410c',
                ordenes_compra:      'background:#dcfce7;color:#15803d',
                transferencias_rec:  'background:#ccfbf1;color:#0f766e',
                finiquitadas:        'background:#f3f4f6;color:#4b5563',
            },

            comparando:  false,
            comparacion: null,
            filtro:      'diffs',
            sel:         {},
            aplicando:   false,
            modal:       false,
            modalModo:   null,
            resultado:   null,

            get obrasFiltradas() {
                const q = this.buscarObra.toLowerCase().trim();
                return q ? this.obras.filter(o => o.nombre.toLowerCase().includes(q)) : this.obras;
            },
            get itemsFiltrados() {
                if (!this.comparacion) return [];
                if (this.filtro === 'diffs') return this.comparacion.items.filter(i => i.diffs.length > 0);
                return this.comparacion.items;
            },
            get selCount() {
                return Object.values(this.sel).filter(Boolean).length;
            },

            tablaLabel(k) { return this._tablaLabels[k] ?? k; },
            tablaColor(k) { return this._tablaColors[k] ?? 'background:#f3f4f6;color:#4b5563'; },

            cv(item, campo, val, fmt) {
                if (!(item.campos_ok ?? []).includes(campo)) return 'N/A';
                if (val === null || val === undefined || val === '') return '—';
                return fmt ? fmt(val) : String(val);
            },

            cc(item, campo, lado) {
                if (!item.en_erp) return 'background:#f9fafb;color:#9ca3af;font-style:italic';
                if (!(item.campos_ok ?? []).includes(campo)) return 'background:#f3f4f6;color:#9ca3af;font-style:italic';
                const diff = item.diffs.includes(campo);
                if (lado === 's') return diff ? 'background:#fef2f2;color:#991b1b;font-weight:600' : 'background:#f0fdf4;color:#166534';
                else              return diff ? 'background:#eff6ff;color:#1e40af;font-weight:700' : 'background:#f0fdf4;color:#166534';
            },

            async init() {
                try {
                    const r = await fetch('/admin/actualizar-reportes/obras',
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    this.obras = await r.json();
                } catch(e) { console.error(e); }
                finally    { this.obrasLoading = false; }
            },

            selDiffs() {
                if (!this.comparacion) return;
                const s = {};
                this.comparacion.items.forEach(i => { if (i.diffs.length > 0 && i.en_erp) s[i.insumo_id] = true; });
                this.sel = { ...s };
            },
            selTodo(v) {
                if (!this.comparacion) return;
                const s = {};
                if (v) this.comparacion.items.forEach(i => { if (i.en_erp) s[i.insumo_id] = true; });
                this.sel = { ...s };
            },

            async comparar() {
                if (this.comparando) return;
                this.comparando = true; this.comparacion = null; this.resultado = null; this.sel = {};
                const p = new URLSearchParams();
                this.tablasSeleccionadas.forEach(t => p.append('tablas[]', t));
                this.obrasSeleccionadas .forEach(o => p.append('obras[]',  o));
                this.camposSeleccionados.forEach(c => p.append('campos[]', c));
                try {
                    const r    = await fetch('/admin/actualizar-reportes/comparar?' + p,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await r.json();
                    if (data.error) { alert(data.error); return; }
                    this.comparacion = data; this.filtro = 'diffs'; this.selDiffs();
                } catch(e) { alert('Error al comparar: ' + e.message); }
                finally    { this.comparando = false; }
            },

            async aplicar() {
                this.modal = false; this.aplicando = true; this.resultado = null;
                const insumos = this.modalModo === 'todos'
                    ? ['todos']
                    : Object.entries(this.sel).filter(([,v]) => v).map(([k]) => k);
                if (!insumos.length) { this.aplicando = false; return; }
                try {
                    const r = await fetch('/admin/actualizar-reportes/aplicar', {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'X-Requested-With':'XMLHttpRequest' },
                        body: JSON.stringify({ tablas: this.tablasSeleccionadas, obras: this.obrasSeleccionadas, campos: this.camposSeleccionados, insumos }),
                    });
                    const dispatch = await r.json();
                    if (dispatch.error) { alert(dispatch.error); this.aplicando = false; return; }
                    await this._pollEstado(dispatch.token);
                } catch(e) { alert('Error: ' + e.message); this.aplicando = false; }
            },

            async _pollEstado(token) {
                const delay = ms => new Promise(r => setTimeout(r, ms));
                for (let i = 0; i < 120; i++) {
                    await delay(3000);
                    try {
                        const r    = await fetch('/admin/actualizar-reportes/estado/' + token,
                            { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                        const data = await r.json();
                        if (data.status === 'completado') {
                            this.resultado = { total: data.actualizados, totales: data.totales ?? {}, tiempo_ms: data.tiempo_ms };
                            this.aplicando = false; return;
                        }
                        if (data.status === 'error') { alert('Error: ' + (data.error ?? 'desconocido')); this.aplicando = false; return; }
                    } catch(e) {}
                }
                alert('El proceso tardó demasiado. Verifica en el log si se completó.');
                this.aplicando = false;
            },
        };
    }
    </script>

</x-app-layout>
