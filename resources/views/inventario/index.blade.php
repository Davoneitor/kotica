


{{-- resources/views/inventario/index.blade.php --}}
<x-app-layout>

    <x-slot name="header">
        @php
    $isAdmin = auth()->check() && auth()->user()->is_admin == 1;

@endphp

        {{-- ✅ x-data="{}" (NO vacío) para que Alpine habilite $store en el header --}}
        <div x-data="{}" class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Inventario
                </h2>

                <div class="text-sm text-gray-600 mt-1">
                    Obra actual:
                    <strong>
                        {{ $obraActual?->nombre ?? 'Sin obra asignada' }}
                    </strong>
                </div>

                @if($isMultiobra)
                <form method="POST" action="{{ route('inventario.cambiarObra') }}" class="mt-2">
                    @csrf
                    <select name="obra_id"
                            onchange="this.form.submit()"
                            class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 bg-white text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="">— Selecciona una obra —</option>
                        @foreach($obras as $obra)
                            <option value="{{ $obra->id }}"
                                {{ $obraActual?->id == $obra->id ? 'selected' : '' }}>
                                {{ $obra->nombre }}
                            </option>
                        @endforeach
                    </select>
                </form>
                @endif
            </div>
            

            <div class="flex items-center gap-2">
                @if($isAdmin)
    <a href="{{ route('inventario.create') }}"
       class="px-3 py-2 border rounded bg-white hover:bg-gray-50">
         Nuevo producto
    </a>
@endif


                <button type="button"
                        class="px-3 py-2 border rounded bg-gray-800 text-white hover:bg-gray-900"
                        @click="$store.salidas.open()">
                     Salidas
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-4">

                {{-- 🔍 BUSCADOR INVENTARIO (amigable para tablet) --}}
                <form method="GET"
                      action="{{ route('inventario.index') }}"
                      class="mb-4 p-3 md:p-4 border rounded-lg bg-white">

                    <div class="flex flex-col md:flex-row md:items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs md:text-sm text-gray-600 mb-1">
                                Buscar por ID (INSUMO) o descripción
                            </label>

                            <input type="text"
                                   name="q"
                                   value="{{ request('q') }}"
                                   class="w-full border rounded-lg px-4 py-3 text-base md:text-sm"
                                   placeholder="Ej: 303-ARF-0201 ó varilla"
                                   inputmode="search">
                        </div>

                        <div class="flex gap-2">
                            <button type="submit"
                                    class="w-full md:w-auto px-5 py-3 rounded-lg bg-gray-800 text-white text-base md:text-sm hover:bg-gray-900">
                                Buscar
                            </button>

                            @if(request('q'))
                                <a href="{{ route('inventario.index', $obsoleto ? ['obsoleto' => 1] : []) }}"
                                   class="w-full md:w-auto px-5 py-3 rounded-lg border bg-gray-100 text-gray-800 text-base md:text-sm hover:bg-gray-200 text-center">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Chips / ayuda + toggle obsoleto --}}
                    <div class="mt-3 flex flex-wrap gap-2 text-sm items-center">
                        <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-700">
                            Tip: puedes escribir <b>#303-ARF-0201</b>
                        </span>

                        @if(request('q'))
                            <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-900">
                                Filtro: <b>{{ request('q') }}</b>
                            </span>
                        @endif

                        <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-600">
                            Obra: <b>{{ $obraActual?->nombre ?? 'Sin obra' }}</b>
                        </span>

                        {{-- Botón toggle Inventario obsoleto --}}
                        @if($obsoleto)
                            <a href="{{ route('inventario.index', array_filter(['q' => request('q')])) }}"
                               class="px-3 py-1 rounded-full bg-yellow-400 text-yellow-900 font-semibold border border-yellow-500 hover:bg-yellow-500 transition-colors">
                                Inventario obsoleto ✕
                            </a>
                        @else
                            <a href="{{ route('inventario.index', array_filter(['q' => request('q'), 'obsoleto' => 1])) }}"
                               class="px-3 py-1 rounded-full border border-yellow-400 text-yellow-700 bg-yellow-50 hover:bg-yellow-100 font-medium transition-colors">
                                Inventario obsoleto
                            </a>
                        @endif
                    </div>

                    {{-- Banner cuando se está viendo solo obsoletos --}}
                    @if($obsoleto)
                        <div class="mt-3 flex items-center gap-2 px-4 py-2 rounded-lg bg-yellow-50 border border-yellow-300 text-yellow-800 text-sm">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                            Mostrando <b class="mx-1">solo inventario obsoleto</b> — estos insumos ya no están en uso activo pero conservan su historial de movimientos.
                        </div>
                    @endif
                </form>


                <div class="overflow-auto">
                    <div class="text-xs text-gray-600 mb-3">
                        Registros en esta página: {{ $inventarios->count() }}
                    </div>

                    @if(session('success'))
                        <div class="mb-3 p-3 bg-green-100 text-green-800 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-3 p-3 bg-red-100 text-red-800 rounded">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="mb-3 p-3 bg-red-100 text-red-800 rounded">
                            <div class="font-semibold mb-1">Revisa los errores:</div>
                            <ul class="list-disc ml-5 text-sm">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <table class="min-w-full text-sm">
                        <thead class="text-left border-b">
                        <tr>
                            <th class="py-2 pr-3">Insumo</th>
                            <th class="py-2 pr-3">Familia</th>
                            <th class="py-2 pr-3">Subfamilia</th>
                            <th class="py-2 pr-3">Descripción</th>
                            <th class="py-2 pr-3">Desc. auxiliar</th>
                            <th class="py-2 pr-3">Unidad</th>
                            <th class="py-2 pr-3">Obra</th>
                            <th class="py-2 pr-3">Proveedor</th>
                            <th class="py-2 pr-3">Cantidad</th>
                            <th class="py-2 pr-3">P.U</th>
                            <th class="py-2 pr-3">Acciones</th>
                        </tr>
                        </thead>

                        @php
                            $highlightId   = (string) session('highlight_id');
                            $highlightType = session('highlight_type');
                        @endphp

                        <tbody>
                        @foreach($inventarios as $inv)
                            @php
                                $style = '';

                                if ($highlightId !== '' && $highlightId === (string) $inv->id) {
                                    $style = 'background-color:#dcfce7;color:#166534;';
                                }

                                // ✅ SOLO ADMIN (por correo)
                               $isAdmin = auth()->check() && auth()->user()->is_admin == 1;

                            @endphp

                            @php
                                // Prioridad visual: verde (editado recientemente) > amarillo (obsoleto) > blanco
                                $rowStyle = $style ?: ($inv->obsoleto ? 'background-color:#fefce8;' : '');
                            @endphp
                            <tr class="border-b" id="inv-{{ $inv->id }}" style="{{ $rowStyle }}"
                                x-data="{
                                    editing: false,
                                    val: {{ json_encode($inv->descripcionauxiliar ?? '') }},
                                    saving: false,
                                    saved: false,
                                    error: '',
                                    async guardar() {
                                        this.saving = true;
                                        this.error  = '';
                                        try {
                                            const r = await fetch('{{ route('inventario.desc_auxiliar', $inv) }}', {
                                                method: 'PATCH',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                    'Accept': 'application/json',
                                                },
                                                body: JSON.stringify({ descripcionauxiliar: this.val }),
                                            });
                                            const data = await r.json();
                                            if (!r.ok) throw new Error(data.error ?? 'Error al guardar.');
                                            this.val     = data.descripcionauxiliar;
                                            this.editing = false;
                                            this.saved   = true;
                                            this.$el.style.backgroundColor = '#dcfce7';
                                        } catch(e) {
                                            this.error = e.message;
                                        } finally {
                                            this.saving = false;
                                        }
                                    },
                                    cancelar() {
                                        this.val     = {{ json_encode($inv->descripcionauxiliar ?? '') }};
                                        this.editing = false;
                                        this.error   = '';
                                    }
                                }">
                                <td class="py-2 pr-3">{{ $inv->insumo_id }}</td>
                                <td class="py-2 pr-3">{{ $inv->familia }}</td>
                                <td class="py-2 pr-3">{{ $inv->subfamilia }}</td>
                                <td class="py-2 pr-3">
                                    {{ $inv->descripcion }}
                                    @if($inv->obsoleto)
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-yellow-200 text-yellow-800 border border-yellow-300">OBSOLETO</span>
                                    @endif
                                </td>

                                {{-- Columna: Descripción auxiliar --}}
                                <td class="py-2 pr-3 min-w-[160px]">
                                    @if($puedeEditarAuxiliar && !$inv->obsoleto)
                                        {{-- Modo lectura: clic para editar --}}
                                        <div x-show="!editing" class="flex items-center gap-1 group cursor-pointer" @click="editing = true">
                                            <span x-text="val || '—'" class="text-gray-700"></span>
                                            <svg class="w-3 h-3 text-gray-300 group-hover:text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z"/>
                                            </svg>
                                        </div>
                                        {{-- Modo edición --}}
                                        <div x-show="editing" class="flex flex-col gap-1" @keydown.escape.window="cancelar()">
                                            <input x-model="val"
                                                   x-ref="inputAux"
                                                   x-init="$watch('editing', v => v && $nextTick(() => $refs.inputAux.focus()))"
                                                   type="text"
                                                   maxlength="450"
                                                   class="border rounded px-2 py-1 text-sm w-full"
                                                   @keydown.enter.prevent="guardar()"
                                                   @keydown.escape.prevent="cancelar()">
                                            <div class="flex gap-1">
                                                <button @click="guardar()"
                                                        :disabled="saving"
                                                        class="px-2 py-0.5 rounded bg-gray-800 text-white text-xs hover:bg-gray-900 disabled:opacity-50">
                                                    <span x-text="saving ? 'Guardando...' : 'Guardar'"></span>
                                                </button>
                                                <button @click="cancelar()"
                                                        class="px-2 py-0.5 rounded border text-xs hover:bg-gray-100">
                                                    Cancelar
                                                </button>
                                            </div>
                                            <span x-show="error" x-text="error" class="text-red-600 text-xs"></span>
                                        </div>
                                    @else
                                        {{-- Solo lectura --}}
                                        <span class="text-gray-600">{{ $inv->descripcionauxiliar ?: '—' }}</span>
                                    @endif
                                </td>

                                <td class="py-2 pr-3">{{ $inv->unidad }}</td>
                                <td class="py-2 pr-3">{{ optional($inv->obra)->nombre }}</td>
                                <td class="py-2 pr-3">{{ $inv->proveedor }}</td>
                                <td class="py-2 pr-3">{{ $inv->cantidad }}</td>
                                <td class="py-2 pr-3">{{ $inv->costo_promedio }}</td>

                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        {{-- Historial (todos los usuarios) --}}
                                        <button type="button"
                                                onclick="abrirHistorial({{ $inv->id }}, '{{ addslashes($inv->descripcion) }}')"
                                                class="px-3 py-2 text-sm rounded border bg-blue-50 hover:bg-blue-100 text-blue-700">
                                            Historial
                                        </button>

                                        @if($isAdmin && !$inv->obsoleto)
                                            <a href="{{ route('inventario.edit', array_filter(['inventario' => $inv->id, 'page' => request('page', 1), 'obsoleto' => request('obsoleto')])) }}"
                                               class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100">
                                                Editar
                                            </a>

                                            <form method="POST"
                                                  action="{{ route('inventario.destroy', $inv) }}"
                                                  onsubmit="return confirm('¿Seguro que quieres eliminar este registro (ID {{ $inv->id }})?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="px-3 py-2 text-sm rounded border bg-gray-50 hover:bg-gray-100">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach

                        @if($inventarios->count() === 0)
                            <tr>
                                <td colspan="11" class="py-6 text-center text-gray-500">
                                    No hay productos en inventario todavía.
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $inventarios->links() }}
                </div>

            </div>
        </div>
    </div>

    {{-- ===================== --}}
    {{-- MODAL HISTORIAL --}}
    {{-- ===================== --}}
    <div id="modalHistorial"
         style="display:none; position:fixed; inset:0; z-index:60; background:rgba(0,0,0,0.5);"
         onclick="if(event.target===this) cerrarHistorial()">

        <div style="background:#fff; width:100%; max-width:680px; max-height:90vh;
                    margin:5vh auto; border-radius:10px; display:flex; flex-direction:column; overflow:hidden;">

            {{-- Header --}}
            <div style="padding:16px 20px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div style="font-weight:700; font-size:16px;">Historial de trazabilidad</div>
                    <div id="historialSubtitulo" style="font-size:13px; color:#6b7280; margin-top:2px;"></div>
                </div>
                <button onclick="cerrarHistorial()"
                        style="padding:4px 10px; border:1px solid #d1d5db; border-radius:6px; background:#f9fafb; cursor:pointer;">
                    ✕
                </button>
            </div>

            {{-- Stats --}}
            <div id="historialStats" style="padding:10px 20px; background:#f9fafb; border-bottom:1px solid #e5e7eb; font-size:12px; color:#374151; display:none;">
            </div>

            {{-- Cuerpo scrollable --}}
            <div style="overflow-y:auto; padding:20px; flex:1;">

                <div id="historialLoading" style="text-align:center; color:#9ca3af; padding:30px;">
                    Cargando...
                </div>

                <div id="historialTimeline" style="display:none;">
                    {{-- generado por JS --}}
                </div>

                <div id="historialVacio" style="display:none; text-align:center; color:#9ca3af; padding:30px;">
                    Sin eventos registrados.
                </div>
            </div>
        </div>
    </div>

    <script>
    const _historialUrls = {};
    @foreach($inventarios as $inv)
        _historialUrls[{{ $inv->id }}] = "{{ route('inventario.historial', $inv) }}";
    @endforeach

    function abrirHistorial(id, descripcion) {
        document.getElementById('modalHistorial').style.display = 'block';
        document.getElementById('historialSubtitulo').textContent = descripcion;
        document.getElementById('historialLoading').style.display = 'block';
        document.getElementById('historialTimeline').style.display = 'none';
        document.getElementById('historialVacio').style.display = 'none';
        document.getElementById('historialStats').style.display = 'none';

        fetch(_historialUrls[id], { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => renderHistorial(data))
            .catch(() => {
                document.getElementById('historialLoading').textContent = 'Error al cargar.';
            });
    }

    function cerrarHistorial() {
        document.getElementById('modalHistorial').style.display = 'none';
    }

    function renderHistorial(data) {
        document.getElementById('historialLoading').style.display = 'none';

        const eventos   = data.eventos || [];
        const unidad    = data.unidad  || '';
        const actual    = parseFloat(data.cantidad) || 0;

        // Totales por tipo
        const sumaEntradas = eventos.filter(e => e.tipo === 'entrada').reduce((s, e) => s + (parseFloat(e.cantidad) || 0), 0);
        const sumaSalidas  = eventos.filter(e => e.tipo === 'salida' ).reduce((s, e) => s + (parseFloat(e.cantidad) || 0), 0);
        const sumaAjustes  = eventos.filter(e => e.tipo === 'ajuste' ).reduce((s, e) => s + (parseFloat(e.cantidad) || 0), 0);
        const netoMovs     = sumaEntradas - sumaSalidas + sumaAjustes;
        const diferencia   = parseFloat((actual - netoMovs).toFixed(4));

        // Indicador de cuadre
        let cuadreHtml;
        if (diferencia === 0) {
            cuadreHtml = `<span style="background:#dcfce7; color:#166534; border:1px solid #16a34a;
                                       border-radius:6px; padding:3px 10px; font-weight:700; font-size:12px;">
                            ✅ CUADRA
                          </span>`;
        } else if (diferencia > 0) {
            cuadreHtml = `<span style="background:#fef9c3; color:#713f12; border:1px solid #ca8a04;
                                       border-radius:6px; padding:3px 10px; font-weight:700; font-size:12px;">
                            ⚠️ NO CUADRA — stock inicial no registrado en OC: +${diferencia} ${unidad}
                          </span>`;
        } else {
            cuadreHtml = `<span style="background:#fee2e2; color:#991b1b; border:1px solid #dc2626;
                                       border-radius:6px; padding:3px 10px; font-weight:700; font-size:12px;">
                            ❌ NO CUADRA — faltante: ${diferencia} ${unidad}
                          </span>`;
        }

        // Barra de stats
        const statsEl = document.getElementById('historialStats');
        statsEl.style.display = 'block';
        statsEl.innerHTML = `
            <div style="display:flex; gap:20px; flex-wrap:wrap; align-items:center; margin-bottom:8px;">
                <span>📦 Entradas: <b>+${sumaEntradas} ${unidad}</b></span>
                <span>🔴 Salidas: <b>-${sumaSalidas} ${unidad}</b></span>
                <span>↩️ Devoluciones: <b>+${sumaAjustes} ${unidad}</b></span>
                <span style="margin-left:auto;">Existencia actual: <b>${actual} ${unidad}</b></span>
            </div>
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <span style="font-size:12px; color:#6b7280;">
                    Neto por movimientos: <b>${netoMovs} ${unidad}</b>
                </span>
                ${cuadreHtml}
            </div>`;

        if (!eventos.length) {
            document.getElementById('historialVacio').style.display = 'block';
            return;
        }

        const colores = {
            creacion : { bg:'#dcfce7', border:'#16a34a', text:'#166534' },
            entrada  : { bg:'#dbeafe', border:'#2563eb', text:'#1e40af' },
            salida   : { bg:'#fee2e2', border:'#dc2626', text:'#991b1b' },
            ajuste   : { bg:'#fef9c3', border:'#ca8a04', text:'#713f12' },
        };

        const tl = document.getElementById('historialTimeline');
        tl.innerHTML = '';

        // Saldo acumulado (recorremos en orden cronológico)
        let saldo = 0;
        eventos.forEach((ev, idx) => {
            const c = colores[ev.tipo] || colores.creacion;
            const fecha   = ev.fecha ? ev.fecha.substring(0, 16).replace('T', ' ') : '';
            const usuario = ev.usuario ? `<span style="color:#6b7280; font-size:11px;"> · ${ev.usuario}</span>` : '';

            // Actualizar saldo acumulado
            if (ev.tipo === 'entrada') saldo += parseFloat(ev.cantidad) || 0;
            if (ev.tipo === 'salida')  saldo -= parseFloat(ev.cantidad) || 0;
            if (ev.tipo === 'ajuste')  saldo += parseFloat(ev.cantidad) || 0;

            const saldoTag = ev.tipo !== 'creacion'
                ? `<span style="display:inline-block; background:#f3f4f6; border:1px solid #d1d5db;
                                border-radius:4px; padding:1px 7px; font-size:11px; color:#374151;
                                margin-top:3px;">
                       Saldo: <b>${parseFloat(saldo.toFixed(4))} ${unidad}</b>
                   </span>`
                : '';

            const item = document.createElement('div');
            item.style.cssText = 'display:flex; gap:12px; margin-bottom:16px;';
            item.innerHTML = `
                <div style="display:flex; flex-direction:column; align-items:center;">
                    <div style="width:36px; height:36px; border-radius:50%; background:${c.bg};
                                border:2px solid ${c.border}; display:flex; align-items:center;
                                justify-content:center; font-size:16px; flex-shrink:0;">
                        ${ev.icono}
                    </div>
                    ${idx < eventos.length - 1
                        ? `<div style="width:2px; flex:1; background:#e5e7eb; margin-top:4px;"></div>`
                        : ''}
                </div>
                <div style="flex:1; padding-bottom:8px;">
                    <div style="font-weight:600; font-size:13px; color:${c.text};">${ev.titulo}</div>
                    <div style="font-size:12px; color:#374151; margin-top:2px;">${ev.detalle}</div>
                    <div style="font-size:11px; color:#9ca3af; margin-top:2px;">${fecha}${usuario}</div>
                    ${saldoTag}
                </div>`;
            tl.appendChild(item);
        });

        tl.style.display = 'block';
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') cerrarHistorial();
    });
    </script>

    {{-- ===================== --}}
    {{-- ✅ MODAL SALIDAS --}}
    {{-- ===================== --}}
   <div
    x-data="salidasUI()"
    x-init="init()"
    x-show="$store.salidas.show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
>
    {{-- fondo (sin @click para evitar cierre accidental) --}}
    <div class="absolute inset-0 bg-black/50"></div>

    {{-- caja (✅ NO CRECE INFINITO: alto fijo + layout flex) --}}
    <div class="relative bg-white w-full max-w-3xl mx-4 rounded-lg shadow-lg p-5
                max-h-[90vh] overflow-hidden flex flex-col">

        {{-- header --}}
        <div class="flex items-center justify-between border-b pb-3 shrink-0">
            <h3 class="text-lg font-semibold">Registrar salida</h3>

            <button type="button"
                    class="px-2 py-1 text-sm border rounded bg-gray-100 hover:bg-gray-200"
                    @click="$store.salidas.close()">
                Cerrar
            </button>
        </div>

        {{-- ✅ CONTENIDO SCROLL (solo esta parte hace scroll) --}}
        <div class="mt-4 overflow-y-auto pr-1 grow">
            {{-- x-ref="form" para leer el FormData en guardarSalida --}}
            <form x-ref="form"
                  method="POST"
                  action="{{ route('salidas.store') }}"
                  class="space-y-4"
                  @submit.prevent>

                @csrf

                {{-- ✅ NUEVO: aquí viaja la firma al backend --}}
                <input type="hidden" name="firma_base64" x-ref="firmaBase64">

                {{-- datos generales --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- ✅ QUIÉN RECIBE (COMBO) --}}
                    <div>
                        <label class="block text-sm mb-1">Quién recibe</label>

                        <select name="nombre_cabo" class="w-full border rounded px-3 py-2 text-sm" required>
                            <option value="">-- Selecciona responsable --</option>
                            @foreach($responsables as $r)
                                <option value="{{ $r->Nombre }}">{{ $r->Nombre }}</option>
                            @endforeach
                        </select>

                        @if(empty($responsables) || count($responsables) === 0)
                            <div class="text-xs text-amber-700 mt-1">
                                No se pudieron cargar responsables (BD almacén).
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm mb-1">Destino</label>
                        <select name="destino_proyecto_id"
                                class="w-full border rounded px-3 py-2 text-sm"
                                required>
                            <option value="">-- Selecciona destino --</option>
                            @foreach($destinos as $d)
                                <option value="{{ $d->IdProyecto }}">{{ $d->Proyecto }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ✅ Nivel --}}
                    <div>
                        <label class="block text-sm mb-1">Nivel</label>
                        <select name="nivel"
                                x-model="nivel"
                                required
                                class="w-full border rounded px-3 py-2 text-sm">
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
                                <option value="L1">L1</option>
                                <option value="L2">L2</option>
                                <option value="L3">L3</option>
                                <option value="L4">L4</option>
                                <option value="L5">L5</option>
                                <option value="L6">L6</option>
                                <option value="L7">L7</option>
                                <option value="L8">L8</option>
                                <option value="L9">L9</option>
                                <option value="L10">L10</option>
                                <option value="L11">L11</option>
                                <option value="L12">L12</option>
                                <option value="L13">L13</option>
                            </optgroup>
                        </select>
                    </div>

                    {{-- ✅ Departamento --}}
                    <div>
                        <label class="block text-sm mb-1">Departamento</label>

                        <select name="departamento"
                                x-model="departamento"
                                :disabled="sinDepartamento"
                                :required="!sinDepartamento"
                                class="w-full border rounded px-3 py-2 text-sm">
                            <option value="">
                                -- <span x-text="sinDepartamento ? 'No aplica' : 'Selecciona departamento'"></span> --
                            </option>
                            <option value="D1">D1</option>
                            <option value="D2">D2</option>
                            <option value="D3">D3</option>
                            <option value="D4">D4</option>
                            <option value="D5">D5</option>
                            <option value="D6">D6</option>
                            <option value="D7">D7</option>
                            <option value="D8">D8</option>
                        </select>

                        <div class="text-xs text-gray-500 mt-1" x-show="sinDepartamento">
                            Este nivel no requiere departamento.
                        </div>
                    </div>

                    {{-- ✅ OBSERVACIONES --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm mb-1">Observaciones</label>
                        <textarea name="observaciones"
                                  rows="2"
                                  maxlength="500"
                                  class="w-full border rounded px-3 py-2 text-sm"
                                  placeholder="Ej: nombre de chalán / incompleto / cuidado con golpes"></textarea>
                    </div>
                </div>

                {{-- ===================== --}}
                {{-- BUSCADOR PRODUCTOS --}}
                {{-- ===================== --}}
                <div class="border border-gray-200 rounded-xl p-4 md:p-5">
                    <h3 class="font-semibold text-gray-800 mb-1">Agregar productos</h3>
                    <p class="text-sm text-gray-500 mb-4">Busca por código o descripción y agrega la cantidad deseada.</p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div class="md:col-span-2 relative">
                            <label class="block text-xs text-gray-600 mb-1">Buscar por código o descripción</label>

                            <input type="text"
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                   placeholder="Ej: 40IE-VAR-0007 ó varilla"
                                   x-model="q"
                                   autocomplete="off"
                                   @input.debounce.300ms="buscar()">

                            {{-- resultados --}}
                            <div x-show="resultados.length"
                                 class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-64 overflow-auto z-50">
                                <template x-for="p in resultados" :key="p.id">
                                    <button type="button"
                                            class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 border-b border-gray-100 last:border-b-0 first:rounded-t-xl last:rounded-b-xl"
                                            @click="seleccionar(p)">
                                        <div class="font-semibold text-gray-800" x-text="p.descripcion"></div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <template x-if="p.insumo_id">
                                                <span>Cód: <strong x-text="p.insumo_id"></strong> &nbsp;|&nbsp;</span>
                                            </template>
                                            Unidad: <span x-text="p.unidad"></span> &nbsp;|&nbsp;
                                            Exist: <span x-text="p.cantidad"></span>
                                            <span x-show="p.devolvible" class="text-blue-600"> | Retornable</span>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            <div class="text-xs text-gray-400 mt-1" x-show="buscando">
                                Buscando...
                            </div>

                            {{-- Mensaje sin resultados --}}
                            <div class="text-sm text-gray-500 mt-1 px-1"
                                 x-show="q.trim() && !buscando && !selected && resultados.length === 0">
                                No se encontraron resultados.
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Cantidad</label>
                            <input type="number" step="0.01" min="0.01"
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-base focus:ring-2 focus:ring-gray-400 focus:border-transparent"
                                   x-model="qty"
                                   :max="selected ? selected.cantidad : null"
                                   placeholder="Ej: 5">
                        </div>
                    </div>

                    {{-- devolvible --}}
                    <div class="mt-4 flex items-center gap-3">
                        <input type="checkbox" x-model="devolvible"
                               class="w-5 h-5 rounded border-gray-300 text-gray-900">
                        <span class="text-sm text-gray-700 cursor-pointer">Producto retornable (préstamo)</span>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button"
                                class="px-5 py-3 text-sm font-medium border rounded-xl bg-gray-800 text-white hover:bg-gray-900 active:bg-black"
                                @click="agregarItem()">
                            Agregar producto
                        </button>
                    </div>

                    {{-- items agregados --}}
                    <template x-if="$store.salidas.items.length">
                        <div class="mt-5 overflow-x-auto rounded-xl border border-gray-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Código</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Descripción</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Cant.</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Unidad</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Retornable</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(it, idx) in $store.salidas.items" :key="idx">
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-700" x-text="it.inventario_id"></td>
                                            <td class="px-4 py-3 text-gray-800 font-medium" x-text="it.descripcion"></td>
                                            <td class="px-4 py-3 text-gray-700" x-text="it.cantidad"></td>
                                            <td class="px-4 py-3 text-gray-700" x-text="it.unidad"></td>
                                            <td class="px-4 py-3">
                                                <span x-text="it.devolvible ? 'Sí' : 'No'"
                                                      :class="it.devolvible ? 'text-blue-700 font-medium' : 'text-gray-500'"></span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <button type="button"
                                                        class="px-3 py-1.5 text-xs border rounded-lg hover:bg-red-50 hover:border-red-300 hover:text-red-700 transition-colors"
                                                        @click="$store.salidas.removeItem(idx)">
                                                    Quitar
                                                </button>
                                                <input type="hidden" :name="`items[${idx}][inventario_id]`" :value="it.inventario_id">
                                                <input type="hidden" :name="`items[${idx}][cantidad]`" :value="it.cantidad">
                                                <input type="hidden" :name="`items[${idx}][unidad]`" :value="it.unidad">
                                                <input type="hidden" :name="`items[${idx}][devolvible]`" :value="it.devolvible ? 1 : 0">
                                                <input type="hidden" :name="`items[${idx}][nivel]`" :value="it.nivel">
                                                <input type="hidden" :name="`items[${idx}][departamento]`" :value="it.departamento ?? ''">
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                {{-- ===================== --}}
                {{-- ✅ FIRMA DIGITAL (colapsable) --}}
                {{-- ===================== --}}
                <div class="border rounded p-3">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-sm">Firma digital</div>

                        <button type="button"
                                class="px-3 py-1 text-xs border rounded bg-gray-100 hover:bg-gray-200"
                                @click="showFirma = !showFirma">
                            <span x-text="showFirma ? 'Ocultar' : 'Mostrar'"></span>
                        </button>
                    </div>

                    <div x-show="showFirma" class="mt-3">
                        <div class="rounded-xl p-2 w-full" style="border: 2px solid #3b82f6;">
                            <canvas x-ref="firmaCanvas" class="rounded-lg w-full bg-white touch-none block"
                                    width="700" height="300"></canvas>
                        </div>

                        <div class="mt-2 flex gap-2">
                            <button type="button"
                                    class="px-3 py-2 text-sm border rounded bg-gray-100 hover:bg-gray-200"
                                    @click="limpiarFirma()">
                                Limpiar firma
                            </button>

                            <button type="button"
                                    class="px-3 py-2 text-sm border rounded bg-gray-800 text-white hover:bg-gray-900"
                                    @click="usarFirma()">
                                Usar firma
                            </button>
                        </div>

                        <div class="text-xs text-gray-500 mt-1" x-text="firmaMsg"></div>
                    </div>

                    <div x-show="!showFirma" class="text-xs text-gray-500 mt-2">
                        (Firma oculta para ahorrar espacio)
                        <span x-show="$refs.firmaBase64?.value" class="ml-1 text-green-700 font-semibold">
                            ✅ Firma lista
                        </span>
                    </div>
                </div>
            </form>
        </div>

        {{-- footer (✅ SIEMPRE VISIBLE) --}}
        <div class="mt-4 flex justify-end gap-2 border-t pt-4 shrink-0">
            <button type="button"
                    class="px-4 py-2 text-sm border rounded"
                    @click="$store.salidas.close()">
                Cancelar
            </button>

            <button type="button"
                    class="px-4 py-2 rounded bg-black text-white text-sm"
                    :disabled="$store.salidas.items.length === 0"
                    @click="guardarSalida($refs.form)">
                Guardar salida
            </button>
        </div>
    </div>

    <style>[x-cloak]{display:none!important}</style>

    {{-- ✅ SignaturePad --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <script>
        function salidasUI() {
            return {
                destinos: [],
                destinoId: '',

                // buscador
                q: '',
                resultados: [],
                selected: null,
                qty: '',
                devolvible: false,
                buscando: false,

                // ✅ nivel/departamento
                nivel: '',
                departamento: '',

                get sinDepartamento() {
                    const nivel = this.nivel || '';
                    if (/^S[1-5]$/.test(nivel)) return true;
                    return ['ROOFTOP', 'PASILLOS', 'CIMENTACION', 'PB', 'GYM', 'AREAS_COMUNES'].includes(nivel);
                },

                // ✅ firma
                signaturePad: null,
                firmaMsg: 'Firma pendiente.',
                showFirma: false,

                init() {
                    this.$watch('nivel', (v) => {
                        if (this.sinDepartamento) {
                            this.departamento = '';
                        }
                    });

                    this.$watch('$store.salidas.show', async (v) => {
                        if (v) {
                            // reset buscador
                            this.q = '';
                            this.resultados = [];
                            this.selected = null;
                            this.qty = '';
                            this.devolvible = false;

                            // reset ubicación
                            this.nivel = '';
                            this.departamento = '';

                            // reset firma
                            this.firmaMsg = 'Firma pendiente.';
                            this.showFirma = false;
                            if (this.$refs.firmaBase64) this.$refs.firmaBase64.value = '';

                            // init SignaturePad cuando el modal ya es visible
                            this.$nextTick(() => {
                                const canvas = this.$refs.firmaCanvas;
                                if (!canvas) return;

                                this.signaturePad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2 });
                            });
                        } else {
                            this.signaturePad = null;
                        }
                    });
                },

                limpiarFirma() {
                    if (this.signaturePad) this.signaturePad.clear();
                    if (this.$refs.firmaBase64) this.$refs.firmaBase64.value = '';
                    this.firmaMsg = 'Firma limpia. Firma de nuevo y pulsa “Usar firma”.';
                },

                usarFirma() {
                    if (!this.signaturePad || this.signaturePad.isEmpty()) {
                        this.firmaMsg = 'Primero firma en el recuadro.';
                        alert('Primero firma en el recuadro.');
                        return;
                    }
                    const dataUrl = this.signaturePad.toDataURL('image/png');
                    this.$refs.firmaBase64.value = dataUrl;
                    this.firmaMsg = 'Firma lista ✅ (se enviará al guardar).';
                    // opcional: cerrar firma para ahorrar espacio
                    this.showFirma = false;
                },

                async buscar() {
                    const term = (this.q || '').trim();
                    if (!term) {
                        this.resultados = [];
                        this.selected = null;
                        return;
                    }

                    const clean = term.startsWith('#') ? term.slice(1).trim() : term;

                    this.buscando = true;
                    try {
                        const url = "{{ route('salidas.buscar') }}" + "?q=" + encodeURIComponent(clean) + "&_=" + Date.now();
                        const res = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            cache: 'no-store'
                        });
                        this.resultados = await res.json();
                    } catch (e) {
                        console.error(e);
                        this.resultados = [];
                    } finally {
                        this.buscando = false;
                    }
                },

                seleccionar(p) {
                    this.selected = p;
                    this.q = `#${p.id} - ${p.descripcion}`;
                    this.resultados = [];
                    this.devolvible = Number(p.devolvible) === 1;
                    if (!this.qty) this.qty = 1;
                },

                agregarItem() {
                    if (!this.selected) return alert('Selecciona un producto de la lista.');
                    if (!this.nivel) return alert('Selecciona un nivel.');

                    const deptoFinal = this.sinDepartamento ? null : (this.departamento ? this.departamento : null);

                    const qty = parseFloat(this.qty);
                    if (!qty || qty <= 0) return alert('Pon una cantidad válida.');

                    const existencia = parseFloat(this.selected.cantidad);

                    if (qty > existencia) {
                        return alert(`Solo hay ${this.selected.cantidad} en existencia.`);
                    }

                    const idx = this.$store.salidas.items.findIndex(x => x.inventario_id === this.selected.id);

                    if (idx >= 0) {
                        const actual = parseFloat(this.$store.salidas.items[idx].cantidad);
                        const nueva = actual + qty;

                        if (nueva > existencia) {
                            return alert(`Con esa suma te excedes. Solo hay ${this.selected.cantidad}.`);
                        }

                        this.$store.salidas.items[idx].cantidad = nueva;

                        this.$store.salidas.items[idx].devolvible =
                            this.$store.salidas.items[idx].devolvible || !!this.devolvible;

                        this.$store.salidas.items[idx].nivel = this.nivel;
                        this.$store.salidas.items[idx].departamento = deptoFinal;

                    } else {
                        this.$store.salidas.items.push({
                            inventario_id: this.selected.id,
                            descripcion: this.selected.descripcion,
                            unidad: this.selected.unidad,
                            cantidad: qty,
                            devolvible: !!this.devolvible,
                            nivel: this.nivel,
                            departamento: deptoFinal,
                        });
                    }

                    this.selected = null;
                    this.q = '';
                    this.resultados = [];
                    this.qty = '';
                    this.devolvible = false;
                },

                async guardarSalida(form) {
                    if (this._guardando) return;
                    this._guardando = true;

                    // 1) evitar submit vacío
                    if (!this.$store.salidas.items.length) {
                        this._guardando = false;
                        alert('Agrega al menos un producto.');
                        return;
                    }

                    // ✅ firma obligatoria
                    if (!this.$refs.firmaBase64?.value) {
                        this._guardando = false;
                        alert('Falta la firma. Firma y presiona “Usar firma”.');
                        this.showFirma = true;
                        return;
                    }

                    // 2) arma formdata
                    const fd = new FormData(form);

                    // 3) POST por AJAX esperando JSON
                    let res;
                    try {
                        res = await fetchConCsrf(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: fd,
                        });
                    } catch (err) {
                        console.error(err);
                        this._guardando = false;
                        alert('Error de red.');
                        return;
                    }

                    // 4) manejar errores
                    let data = null;
                    try { data = await res.json(); } catch (_) {}

                    if (!res.ok) {
                        this._guardando = false;
                        if (res.status === 422 && data?.errors) {
                            const first = Object.values(data.errors)[0]?.[0] || 'Revisa los campos.';
                            alert(first);
                            return;
                        }
                        if (data?.message) {
                            alert(data.message);
                            return;
                        }
                        if (res.status === 419) {
                            if (confirm('Tu sesión expiró. ¿Recargar la página ahora?\n(No perderás los datos del formulario si cancelas primero)')) {
                                location.reload();
                            }
                            return;
                        }
                        alert('Error al guardar salida.');
                        return;
                    }

                    // 5) validar respuesta esperada
                    if (!data?.ok || !data?.pdf_url) {
                        this._guardando = false;
                        console.error('Respuesta inesperada:', data);
                        alert(data?.message || 'No se recibió pdf_url.');
                        return;
                    }

                    // ✅ A) cerrar modal
                    this.$store.salidas.close();

                    // ✅ B) refrescar tabla
                    await this.refrescarTablaInventario();

                    // ✅ C) descargar PDF
                    await this.descargarPdf(data.pdf_url);

                    this._guardando = false;
                },

                async refrescarTablaInventario() {
                    const res = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const html = await res.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    const nuevoTbody = doc.querySelector('table tbody');
                    const tbodyActual = document.querySelector('table tbody');

                    if (nuevoTbody && tbodyActual) {
                        tbodyActual.innerHTML = nuevoTbody.innerHTML;
                    }
                },

                async descargarPdf(url) {
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });

                    const blob = await res.blob();
                    const blobUrl = URL.createObjectURL(blob);

                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = 'salida.pdf';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();

                    URL.revokeObjectURL(blobUrl);
                },
            }
        }
    </script>
</div>


@php $highlightId = session('highlight_id'); @endphp
@if($highlightId)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('inv-{{ $highlightId }}');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endif

</x-app-layout>
