<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transferencia entre obras</h2>
        <p class="text-sm text-gray-500 mt-1">Mueve insumos de tu obra actual hacia otra obra.</p>
    </x-slot>

    {{-- SignaturePad CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

    <div class="py-6" x-data="transferencias({{ $obras->toJson() }})" x-init="init()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Flash success --}}
            @if(session('success'))
                <div class="p-3 rounded-lg border bg-green-50 border-green-200 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Errores --}}
            @if($errors->any())
                <div class="p-3 rounded-lg border bg-red-50 border-red-200 text-red-800 text-sm">
                    <ul class="list-disc ml-4 space-y-0.5">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 1. ENCABEZADO ORIGEN ⇄ DESTINO
                 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <div class="flex flex-col md:flex-row items-stretch md:items-center gap-4">

                    {{-- Obra origen --}}
                    <div class="flex-1 bg-indigo-50 border border-indigo-200 rounded-xl p-4">
                        <div class="text-xs font-bold text-indigo-400 uppercase tracking-widest mb-1">Obra origen</div>
                        <div class="text-xl font-bold text-indigo-800">{{ $obraOrigen->nombre }}</div>
                        <div class="text-xs text-indigo-400 mt-1">Solo lectura · tu obra actual</div>
                    </div>

                    {{-- Símbolo --}}
                    <div class="flex items-center justify-center text-4xl text-gray-300 font-light select-none">⇄</div>

                    {{-- Obra destino --}}
                    <div class="flex-1 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                        <div class="text-xs font-bold text-emerald-500 uppercase tracking-widest mb-1">Obra destino</div>
                        <select
                            x-model="obraDestinoId"
                            @change="onObraDestinoChange()"
                            class="w-full border-0 bg-transparent text-xl font-bold text-emerald-800 focus:ring-0 p-0 cursor-pointer">
                            <option value="">— Seleccionar obra —</option>
                            @foreach($obras as $o)
                                <option value="{{ $o->id }}">{{ $o->nombre }}</option>
                            @endforeach
                        </select>
                        <div class="text-xs text-emerald-400 mt-1">Selecciona la obra destino</div>
                    </div>
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━
                 2. BUSCADOR
                 ━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Agregar insumo</label>
                <div class="relative">
                    <input
                        type="text"
                        x-model="busqueda"
                        @input.debounce.400ms="buscarInsumos()"
                        @keydown.escape="resultados = []"
                        @keydown.tab="resultados = []"
                        placeholder="Buscar por nombre o código (mín. 2 caracteres)…"
                        autocomplete="off"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 outline-none">

                    <div x-show="buscando" class="absolute right-3 top-3.5 text-xs text-gray-400">Buscando…</div>

                    {{-- Resultados --}}
                    <div
                        x-show="resultados.length > 0"
                        @click.outside="resultados = []"
                        class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-72 overflow-y-auto">
                        <template x-for="r in resultados" :key="r.id">
                            <button
                                type="button"
                                @click="agregarItem(r)"
                                class="w-full text-left px-4 py-3 hover:bg-indigo-50 border-b border-gray-100 last:border-0 transition">
                                <div class="font-semibold text-sm text-gray-800" x-text="r.descripcion"></div>
                                <div class="flex gap-3 text-xs text-gray-500 mt-0.5">
                                    <span class="font-mono" x-text="r.insumo_id ?? '—'"></span>
                                    <span>·</span>
                                    <span>Disponible:
                                        <span class="font-bold text-indigo-700"
                                              x-text="parseFloat(r.cantidad).toFixed(2) + ' ' + (r.unidad ?? '')">
                                        </span>
                                    </span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <div
                        x-show="busqueda.trim().length >= 2 && resultados.length === 0 && !buscando"
                        class="absolute z-30 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-sm px-4 py-3 text-sm text-gray-500">
                        No se encontraron insumos con stock disponible.
                    </div>
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━
                 3. TABLA CARRITO
                 ━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div x-show="carrito.length > 0" x-transition class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">
                        Insumos a transferir
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700"
                              x-text="carrito.length"></span>
                    </h3>
                    <div x-show="hayErrores" class="text-xs text-red-600 font-medium">⚠ Revisa cantidades en rojo</div>
                </div>

                {{-- Desktop --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Insumo</th>
                                <th class="px-4 py-3 text-right font-semibold">Disponible origen</th>
                                <th class="px-4 py-3 text-center font-semibold w-44">Cantidad a enviar</th>
                                <th class="px-4 py-3 text-right font-semibold">Origen final</th>
                                <th class="px-4 py-3 text-right font-semibold">Destino final</th>
                                <th class="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(item, idx) in carrito" :key="item.inventario_id">
                                <tr :class="itemTieneError(item) ? 'bg-red-50' : 'hover:bg-gray-50'">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800" x-text="item.descripcion"></div>
                                        <div class="text-xs text-gray-400 font-mono" x-text="item.insumo_id ?? '—'"></div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span class="font-semibold text-indigo-700" x-text="item.cantidad_disponible.toFixed(2)"></span>
                                        <span class="text-gray-400 text-xs ml-1" x-text="item.unidad ?? ''"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="number"
                                            x-model="item.cantidad_a_enviar"
                                            min="0.01" step="0.01"
                                            :max="item.cantidad_disponible"
                                            :class="itemTieneError(item) ? 'border-red-400 bg-red-50 focus:ring-red-300' : 'border-gray-300 focus:ring-indigo-300'"
                                            class="w-full border rounded-lg px-3 py-2 text-center text-sm focus:ring-2 focus:border-transparent outline-none tabular-nums">
                                        <div x-show="parseFloat(item.cantidad_a_enviar || 0) > item.cantidad_disponible"
                                             class="text-xs text-red-600 mt-1 text-center">Supera el disponible</div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span :class="origenFinal(item) < 0 ? 'text-red-600 font-bold' : 'text-gray-700'"
                                              x-text="origenFinal(item).toFixed(2)"></span>
                                        <span class="text-gray-400 text-xs ml-1" x-text="item.unidad ?? ''"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <template x-if="item.cargando_destino">
                                            <span class="text-gray-400 text-xs">…</span>
                                        </template>
                                        <template x-if="!item.cargando_destino && item.stock_destino !== null">
                                            <span>
                                                <span class="text-emerald-700 font-semibold" x-text="destinoFinal(item).toFixed(2)"></span>
                                                <span class="text-gray-400 text-xs ml-1" x-text="item.unidad ?? ''"></span>
                                            </span>
                                        </template>
                                        <template x-if="!item.cargando_destino && item.stock_destino === null">
                                            <span class="text-gray-400 text-xs italic">selecciona obra destino</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <button type="button" @click="quitarItem(idx)"
                                                class="text-gray-300 hover:text-red-500 transition text-lg">✕</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Móvil --}}
                <div class="md:hidden divide-y divide-gray-100">
                    <template x-for="(item, idx) in carrito" :key="item.inventario_id">
                        <div class="p-4" :class="itemTieneError(item) ? 'bg-red-50' : ''">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <div class="font-medium text-gray-800 text-sm" x-text="item.descripcion"></div>
                                    <div class="text-xs text-gray-400 font-mono" x-text="item.insumo_id ?? '—'"></div>
                                </div>
                                <button type="button" @click="quitarItem(idx)"
                                        class="text-gray-300 hover:text-red-500 transition text-lg shrink-0">✕</button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 mt-3 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500">Disponible</div>
                                    <div class="font-mono font-semibold text-indigo-700 mt-0.5"
                                         x-text="item.cantidad_disponible.toFixed(2) + ' ' + (item.unidad ?? '')"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 mb-0.5">A enviar</div>
                                    <input type="number" x-model="item.cantidad_a_enviar"
                                           min="0.01" step="0.01" :max="item.cantidad_disponible"
                                           :class="itemTieneError(item) ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                           class="w-full border rounded-lg px-3 py-2 text-sm outline-none">
                                    <div x-show="itemTieneError(item)" class="text-xs text-red-600 mt-1">Cantidad inválida</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Origen final</div>
                                    <div class="font-mono text-sm mt-0.5"
                                         :class="origenFinal(item) < 0 ? 'text-red-600 font-bold' : 'text-gray-700'"
                                         x-text="origenFinal(item).toFixed(2) + ' ' + (item.unidad ?? '')"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Destino final</div>
                                    <div class="font-mono text-sm text-emerald-700 font-semibold mt-0.5"
                                         x-text="item.stock_destino !== null ? destinoFinal(item).toFixed(2) + ' ' + (item.unidad ?? '') : '—'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 4. RESUMEN + OBSERVACIONES + ENVIAR
                 ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div x-show="carrito.length > 0" x-transition
                 class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
                <h3 class="text-sm font-semibold text-gray-700">Resumen</h3>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-3">
                        <div class="text-xs text-indigo-500 font-medium">Obra origen</div>
                        <div class="font-bold text-indigo-800 text-sm mt-0.5">{{ $obraOrigen->nombre }}</div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-100 rounded-lg p-3">
                        <div class="text-xs text-emerald-500 font-medium">Obra destino</div>
                        <div class="font-bold text-emerald-800 text-sm mt-0.5" x-text="obraDestinoNombre"></div>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div class="text-xs text-gray-500">Total insumos</div>
                        <div class="font-bold text-2xl text-gray-800" x-text="carrito.length"></div>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div class="text-xs text-gray-500">Total piezas</div>
                        <div class="font-bold text-2xl text-gray-800" x-text="totalPiezas.toFixed(2)"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Observaciones <span class="text-gray-400">(opcional)</span>
                    </label>
                    <textarea x-model="observaciones" rows="2" maxlength="500"
                              placeholder="Motivo de la transferencia…"
                              class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm resize-none focus:ring-2 focus:ring-indigo-300 outline-none">
                    </textarea>
                </div>

                <div class="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                    <span class="shrink-0">⚠</span>
                    <span>Esta acción actualizará el inventario de ambas obras. La operación es irreversible.</span>
                </div>
            </div>

        </div>{{-- /max-w-7xl --}}

        {{-- ── Barra fija inferior: botón Enviar ── --}}
        <div
            x-show="carrito.length > 0"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-full opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-lg px-4 py-3"
        >
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-800" x-text="carrito.length"></span> insumo(s) ·
                    <span class="font-semibold text-gray-800" x-text="totalPiezas.toFixed(2)"></span> piezas totales
                </div>
                <button type="button" @click="abrirModal()"
                        :disabled="!puedeEnviar"
                        :class="puedeEnviar ? 'bg-gray-800 hover:bg-gray-900 text-white shadow-sm active:bg-black' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                        class="px-7 py-3 rounded-lg font-semibold text-sm transition">
                    Enviar transferencia →
                </button>
            </div>
        </div>
        {{-- Espacio para que la barra fija no tape el contenido --}}
        <div x-show="carrito.length > 0" class="h-20"></div>

        {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
             5. MODAL DE CONFIRMACIÓN + FIRMA
             Optimizado para tablet (max-w-2xl)
             ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
        <div
            x-show="modalAbierto"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3 md:p-6"
            @click.self="if(!enviando) cerrarModal()">

            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[95vh] flex flex-col overflow-hidden">

                {{-- ── Header del modal ── --}}
                <div class="flex items-center justify-between px-5 md:px-6 py-4 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-base font-bold text-gray-800">Confirmar transferencia</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Revisa los datos y firma para autorizar</p>
                    </div>
                    <button type="button" @click="if(!enviando) cerrarModal()"
                            class="text-gray-400 hover:text-gray-600 text-xl leading-none p-1">✕</button>
                </div>

                {{-- ── Cuerpo (scroll) ── --}}
                <div class="overflow-y-auto flex-1 px-5 md:px-6 py-4 space-y-4">

                    {{-- Origen → Destino --}}
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
                            <div class="text-xs text-indigo-400 font-semibold uppercase tracking-wide">Origen</div>
                            <div class="font-bold text-indigo-800 mt-0.5 leading-snug">{{ $obraOrigen->nombre }}</div>
                        </div>
                        <span class="text-2xl text-gray-300 shrink-0">→</span>
                        <div class="flex-1 bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3">
                            <div class="text-xs text-emerald-400 font-semibold uppercase tracking-wide">Destino</div>
                            <div class="font-bold text-emerald-800 mt-0.5 leading-snug" x-text="obraDestinoNombre"></div>
                        </div>
                    </div>

                    {{-- Lista de insumos --}}
                    <div class="border border-gray-100 rounded-xl overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Insumo</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="item in carrito" :key="item.inventario_id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 text-gray-800 leading-snug" x-text="item.descripcion"></td>
                                        <td class="px-4 py-2.5 text-right font-mono font-semibold text-gray-900">
                                            <span x-text="parseFloat(item.cantidad_a_enviar || 0).toFixed(2)"></span>
                                            <span class="text-gray-400 ml-1 text-xs" x-text="item.unidad ?? ''"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Totales --}}
                    <div class="flex justify-between text-sm font-semibold text-gray-700 bg-gray-50 rounded-lg px-4 py-2.5">
                        <span><span x-text="carrito.length"></span> insumo(s)</span>
                        <span><span x-text="totalPiezas.toFixed(2)"></span> piezas</span>
                    </div>

                    {{-- ── FIRMA DEL RESPONSABLE ── --}}
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        {{-- Cabecera firma --}}
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <div>
                                <div class="text-sm font-semibold text-gray-700">Firma del responsable</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    Recepcionista o Gerente
                                    &nbsp;·&nbsp;
                                    <span x-show="!firmaCapturada" class="text-amber-600 font-medium">Pendiente</span>
                                    <span x-show="firmaCapturada" class="text-emerald-600 font-semibold">✓ Lista</span>
                                </div>
                            </div>
                            <button type="button" @click="limpiarFirma()"
                                    class="text-xs px-3 py-1.5 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 text-gray-600 transition">
                                Limpiar
                            </button>
                        </div>

                        {{-- Canvas --}}
                        <div class="bg-white p-3">
                            <canvas
                                x-ref="firmaCanvas"
                                class="w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 touch-none cursor-crosshair"
                                style="height: 180px; display: block;">
                            </canvas>
                            <p class="text-xs text-gray-400 mt-2 text-center">
                                Firma con el dedo o el lápiz · después toca <strong>Usar firma</strong>
                            </p>
                        </div>

                        {{-- Acción firma --}}
                        <div class="px-4 pb-4">
                            <button type="button" @click="usarFirma()"
                                    :class="firmaCapturada
                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                        : 'bg-gray-800 hover:bg-gray-900 text-white'"
                                    class="w-full py-2.5 rounded-lg text-sm font-semibold transition">
                                <span x-show="!firmaCapturada">Usar firma →</span>
                                <span x-show="firmaCapturada">✓ Firma capturada · Toca para reemplazar</span>
                            </button>
                        </div>
                    </div>

                    {{-- Aviso firma --}}
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 flex gap-2 items-start">
                        <span class="shrink-0 text-base">✍️</span>
                        <span><strong>Importante:</strong> Esta transferencia debe ser firmada por el <strong>Gerente</strong> o el <strong>Residente</strong> de obra. No firmar como almacenista.</span>
                    </div>

                    {{-- Aviso inventario --}}
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex gap-2">
                        <span class="shrink-0">⚠</span>
                        <span>Al confirmar, el inventario de ambas obras se actualizará de forma permanente.</span>
                    </div>

                </div>{{-- /overflow-y-auto --}}

                {{-- ── Footer (botones fijos) ── --}}
                <div class="flex gap-3 justify-end px-5 md:px-6 py-4 border-t border-gray-100 shrink-0 bg-white">
                    <button type="button" @click="cerrarModal()" :disabled="enviando"
                            class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition">
                        Cancelar
                    </button>
                    <button type="button" @click="confirmarTransferencia()"
                            :disabled="!puedeConfirmar"
                            :class="puedeConfirmar
                                ? 'bg-gray-800 hover:bg-gray-900 text-white'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                            class="px-6 py-2.5 rounded-lg text-sm font-semibold transition flex items-center gap-2">
                        <svg x-show="enviando" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-show="!firmaCapturada && !enviando">Falta la firma</span>
                        <span x-show="firmaCapturada && !enviando">Confirmar transferencia</span>
                        <span x-show="enviando">Procesando…</span>
                    </button>
                </div>

            </div>{{-- /modal card --}}
        </div>{{-- /modal overlay --}}

    </div>{{-- /x-data --}}

<script>
function transferencias(obras) {
    return {
        // ── estado ──────────────────────────────────────────
        obras:           obras,
        obraDestinoId:   '',
        busqueda:        '',
        resultados:      [],
        buscando:        false,
        carrito:         [],
        modalAbierto:    false,
        enviando:        false,
        observaciones:   '',

        // firma
        signaturePad:    null,
        firmaCapturada:  false,
        firmaDataUrl:    '',

        // ── computed ─────────────────────────────────────────
        get obraDestinoNombre() {
            var found = this.obras.find(o => String(o.id) === String(this.obraDestinoId));
            return found ? found.nombre : '—';
        },
        get totalInsumos() { return this.carrito.length; },
        get totalPiezas() {
            return this.carrito.reduce((s, i) => s + parseFloat(i.cantidad_a_enviar || 0), 0);
        },
        get hayErrores() {
            return this.carrito.some(i => this.itemTieneError(i));
        },
        get puedeEnviar() {
            return !!this.obraDestinoId && this.carrito.length > 0 && !this.hayErrores;
        },
        get puedeConfirmar() {
            return this.firmaCapturada && !this.enviando;
        },

        // ── ciclo de vida ────────────────────────────────────
        init() {
            this.$watch('modalAbierto', (isOpen) => {
                if (isOpen) {
                    this.$nextTick(() => {
                        // pequeño delay para esperar que el modal termine de aparecer
                        setTimeout(() => {
                            var canvas = this.$refs.firmaCanvas;
                            if (!canvas) return;
                            // ajustar dimensiones al contenedor real
                            canvas.width  = canvas.offsetWidth  || 560;
                            canvas.height = canvas.offsetHeight || 180;
                            this.signaturePad = new SignaturePad(canvas, {
                                minWidth: 1,
                                maxWidth: 2.5,
                                penColor: '#1e293b',
                            });
                        }, 80);
                    });
                } else {
                    if (this.signaturePad) { this.signaturePad.clear(); this.signaturePad = null; }
                }
            });
        },

        // ── firma ────────────────────────────────────────────
        limpiarFirma() {
            if (this.signaturePad) this.signaturePad.clear();
            this.firmaCapturada = false;
            this.firmaDataUrl   = '';
        },

        usarFirma() {
            if (!this.signaturePad || this.signaturePad.isEmpty()) {
                alert('Primero dibuja tu firma en el recuadro.');
                return;
            }
            this.firmaDataUrl   = this.signaturePad.toDataURL('image/png');
            this.firmaCapturada = true;
        },

        // ── helpers fila ─────────────────────────────────────
        itemTieneError(item) {
            var c = parseFloat(item.cantidad_a_enviar || 0);
            return isNaN(c) || c <= 0 || c > item.cantidad_disponible;
        },
        origenFinal(item) {
            return item.cantidad_disponible - parseFloat(item.cantidad_a_enviar || 0);
        },
        destinoFinal(item) {
            return parseFloat(item.stock_destino ?? 0) + parseFloat(item.cantidad_a_enviar || 0);
        },

        // ── búsqueda AJAX ────────────────────────────────────
        async buscarInsumos() {
            var q = this.busqueda.trim();
            if (q.length < 2) { this.resultados = []; return; }
            this.buscando = true;
            try {
                var res = await fetch('/transferencias/buscar?q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.resultados = await res.json();
            } catch (e) { this.resultados = []; }
            finally { this.buscando = false; }
        },

        // ── carrito ──────────────────────────────────────────
        agregarItem(insumo) {
            if (this.carrito.some(i => i.inventario_id === insumo.id)) {
                this.busqueda = ''; this.resultados = []; return;
            }
            this.carrito.push({
                inventario_id:       insumo.id,
                insumo_id:           insumo.insumo_id,
                descripcion:         insumo.descripcion,
                unidad:              insumo.unidad ?? '',
                cantidad_disponible: parseFloat(insumo.cantidad),
                cantidad_a_enviar:   '',
                stock_destino:       null,
                cargando_destino:    false,
            });
            this.busqueda = ''; this.resultados = [];
            if (this.obraDestinoId) this.cargarStockDestinoById(insumo.id);
        },
        quitarItem(idx) { this.carrito.splice(idx, 1); },

        // ── stock destino AJAX ───────────────────────────────
        async cargarStockDestinoById(inventarioId) {
            if (!this.obraDestinoId) return;
            var getItem = () => this.carrito.find(i => i.inventario_id === inventarioId);
            var item = getItem();
            if (!item) return;
            item.cargando_destino = true;
            item.stock_destino    = null;
            try {
                var res = await fetch(
                    '/transferencias/stock-destino?inventario_id=' + inventarioId + '&obra_destino_id=' + this.obraDestinoId,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                var data  = await res.json();
                var found = getItem();
                if (found) found.stock_destino = parseFloat(data.cantidad);
            } catch (e) {
                var found = getItem(); if (found) found.stock_destino = null;
            } finally {
                var found = getItem(); if (found) found.cargando_destino = false;
            }
        },
        async onObraDestinoChange() {
            var ids = this.carrito.map(i => i.inventario_id);
            for (var id of ids) await this.cargarStockDestinoById(id);
        },

        // ── modal ────────────────────────────────────────────
        abrirModal() {
            if (this.puedeEnviar) {
                this.firmaCapturada = false;
                this.firmaDataUrl   = '';
                this.modalAbierto   = true;
            }
        },
        cerrarModal() {
            if (!this.enviando) {
                this.modalAbierto   = false;
                this.firmaCapturada = false;
                this.firmaDataUrl   = '';
            }
        },

        // ── envío ────────────────────────────────────────────
        confirmarTransferencia() {
            if (!this.puedeConfirmar) return;
            this.enviando = true;

            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var items = this.carrito.map(i => ({
                inventario_id: i.inventario_id,
                cantidad:      parseFloat(i.cantidad_a_enviar),
            }));

            var form   = document.createElement('form');
            form.method = 'POST';
            form.action = '/transferencias';

            var fields = {
                '_token':          token,
                'obra_destino_id': this.obraDestinoId,
                'observaciones':   this.observaciones,
                'items':           JSON.stringify(items),
                'firma_base64':    this.firmaDataUrl,
            };

            Object.keys(fields).forEach(name => {
                var inp   = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = name;
                inp.value = fields[name];
                form.appendChild(inp);
            });

            document.body.appendChild(form);
            form.submit();
        },
    };
}
</script>

</x-app-layout>
