<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Entradas de inventario
                </h2>

                <p class="text-sm text-gray-600">
                    Obra actual:
                    <strong>{{ $obra->nombre ?? 'Sin obra asignada' }}</strong>
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="ocUI()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- errores --}}
            @if ($errors->any())
                <div class="mb-4 p-3 rounded border bg-red-50 text-red-800">
                    <div class="font-semibold mb-1">Revisa:</div>
                    <ul class="list-disc ml-5 text-sm">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('warning'))
                <div class="mb-4 p-3 rounded border bg-yellow-50 text-yellow-900">
                    {{ session('warning') }}
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 p-3 rounded border bg-green-50 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-3 rounded border bg-red-50 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            {{-- ── TABS ─────────────────────────────────────────────────── --}}
            <div class="flex mb-5 bg-white rounded-lg shadow-sm border overflow-hidden">
                <button type="button"
                        :class="tab === 'oc'
                            ? 'bg-gray-800 text-white'
                            : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors border-r"
                        @click="tab = 'oc'">
                    Órdenes de compra
                </button>
                <button type="button"
                        :class="tab === 'manual'
                            ? 'bg-gray-900 text-white'
                            : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors border-r"
                        @click="tab = 'manual'">
                    + Entrada manual
                </button>
                <button type="button"
                        :class="tab === 'transferencias'
                            ? 'bg-gray-900 text-white'
                            : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors relative"
                        @click="tab = 'transferencias'; cargarTransferencias()">
                    Transferencias
                    <span x-show="transPendientes.length > 0"
                          class="ml-1 inline-flex items-center justify-center bg-amber-500 text-white text-xs font-bold w-5 h-5 rounded-full"
                          x-text="transPendientes.length"></span>
                </button>
            </div>

            {{-- ═══════════════════════════════════════════════════════════
                 TAB 1 — ÓRDENES DE COMPRA
            ═══════════════════════════════════════════════════════════ --}}
            <div x-show="tab === 'oc'" x-cloak>

                {{-- 🔍 BUSCADOR --}}
                <form method="GET"
                      action="{{ route('ordenes-compra.index') }}"
                      class="mb-4 p-3 md:p-4 border rounded-lg bg-white">

                    <div class="flex flex-col md:flex-row md:items-end gap-3">

                        <div class="flex-1">
                            <label class="block text-xs md:text-sm text-gray-600 mb-1">
                                Buscar por insumo, descripción o proveedor
                            </label>

                            <input type="text"
                                   name="q"
                                   value="{{ request('q') }}"
                                   class="w-full border rounded-lg px-4 py-3 text-base md:text-sm"
                                   placeholder="Ej: varilla, brocha, ACME"
                                   inputmode="search">
                        </div>

                        <div class="flex gap-2">
                            <button type="submit"
                                    class="w-full md:w-auto px-5 py-3 rounded-lg bg-gray-800 text-white text-base md:text-sm hover:bg-gray-900">
                                Buscar
                            </button>

                            @if(request('q'))
                                <a href="{{ route('ordenes-compra.index') }}"
                                   class="w-full md:w-auto px-5 py-3 rounded-lg border bg-gray-100 text-gray-800 text-base md:text-sm hover:bg-gray-200 text-center">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Botones estilo Explore --}}
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <button type="button"
                                class="px-3 py-2 rounded border text-sm"
                                :class="estado==='pendiente' ? 'bg-slate-900 text-white' : 'bg-white'"
                                @click="estado='pendiente'">
                            Pendientes
                        </button>

                        <button type="button"
                                class="px-3 py-2 rounded border text-sm"
                                :class="estado==='parcial' ? 'bg-yellow-100 border-yellow-300' : 'bg-white'"
                                @click="estado='parcial'">
                            Parciales
                        </button>

                        <button type="button"
                                class="px-3 py-2 rounded border text-sm"
                                :class="estado==='todas' ? 'bg-gray-900 text-white' : 'bg-white'"
                                @click="estado='todas'">
                            Ver todo
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-sm">
                        @if(request('q'))
                            <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-700">
                                Búsqueda: <b>{{ request('q') }}</b>
                            </span>
                        @endif

                        <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-700">
                            * Pendiente: Recibido = 0 · Parcial: 0 &lt; Recibido &lt; Pedido
                        </span>
                    </div>
                </form>

                {{-- sin órdenes --}}
                @if($ordenes->isEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <p class="text-gray-600">
                            No hay órdenes pendientes para esta obra.
                        </p>
                    </div>
                @else

                    <div class="space-y-6">
                        @foreach($ordenes as $o)

                            @php
                                $pendCount  = collect($o['items'])->filter(fn($it) => (float)($it['parcial_actual'] ?? 0) <= 0)->count();
                                $parcCount  = collect($o['items'])->filter(function($it){
                                    $rec = (float)($it['parcial_actual'] ?? 0);
                                    $ped = (float)($it['cantidad'] ?? 0);
                                    return $rec > 0 && $rec < $ped;
                                })->count();
                                $totalCount = collect($o['items'])->count();
                            @endphp

                            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
                                 x-show="
                                    (estado==='todas'     && {{ $totalCount }} > 0) ||
                                    (estado==='pendiente' && {{ $pendCount }}  > 0) ||
                                    (estado==='parcial'   && {{ $parcCount }}  > 0)
                                 "
                                 x-cloak>

                                {{-- header pedido --}}
                                <div class="p-4 border-b">
                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                        <div class="text-sm">
                                            <div class="font-semibold text-gray-800 text-base md:text-sm">
                                                OC #{{ $o['idPedido'] }}
                                            </div>
                                            <div class="text-gray-600 text-base md:text-sm">
                                                Fecha: {{ $o['fecha']->format('Y-m-d') }}
                                            </div>
                                        </div>

                                        <div class="text-sm">
                                            <div class="text-gray-500 text-xs">Proveedor</div>
                                            <div class="font-medium text-gray-800 text-base md:text-sm">
                                                ({{ $o['proveedor']['id'] }}) {{ $o['proveedor']['razon'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2 text-xs text-gray-500">
                                        * Filas en rojo si la fecha tiene más de 2 semanas
                                    </div>
                                </div>

                                <div class="p-4">
                                    <div class="space-y-3">
                                        @foreach($o['items'] as $it)

                                            <div
                                                x-show="pasaFiltro({{ (float)$it['parcial_actual'] }}, {{ (float)$it['cantidad'] }})"
                                                x-cloak
                                                class="bg-white shadow-sm rounded-lg border p-4
                                                    {{ $it['vencida']    ? 'bg-red-50 border-red-200'     : '' }}
                                                    {{ $it['es_parcial'] ? 'bg-amber-50 border-amber-200' : '' }}"
                                                x-data="{ open:false, openFin:false, sendingFin:false }"
                                            >
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <div class="font-semibold text-base">
                                                            Det #{{ $it['pedido_det_id'] }}
                                                        </div>

                                                        <div class="text-sm text-gray-700">
                                                            <span class="font-semibold">{{ $it['insumo'] }}</span> —
                                                            <span>{{ $it['descripcion'] }}</span>
                                                        </div>

                                                        <div class="text-xs text-gray-600 mt-1">
                                                            Proveedor: <span class="font-medium">{{ $it['razonSocial'] }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="text-right">
                                                        <div class="text-xs text-gray-500">Recibido / Pedido</div>
                                                        <div class="font-bold text-lg">
                                                            {{ number_format($it['parcial_actual'], 2) }} / {{ number_format($it['cantidad'], 2) }}
                                                        </div>
                                                        <div class="text-xs text-gray-600">
                                                            Falta: {{ number_format($it['faltante'], 2) }} {{ $it['unidad'] }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mt-2 text-xs text-gray-600 flex items-center gap-2 flex-wrap">
                                                    <template x-if="estadoItem({{ (float)$it['parcial_actual'] }}, {{ (float)$it['cantidad'] }})==='pendiente'">
                                                        <span class="px-2 py-1 rounded bg-slate-100 text-slate-700 font-semibold">PENDIENTE</span>
                                                    </template>

                                                    <template x-if="estadoItem({{ (float)$it['parcial_actual'] }}, {{ (float)$it['cantidad'] }})==='parcial'">
                                                        <span class="px-2 py-1 rounded bg-orange-100 text-orange-800 font-semibold">PARCIAL</span>
                                                    </template>

                                                    @if($it['vencida'])
                                                        <span class="px-2 py-1 rounded bg-red-100 text-red-800 font-semibold">VENCIDA</span>
                                                    @endif
                                                </div>

                                                <div class="mt-3 flex gap-2">
                                                    <button type="button"
                                                            class="flex-1 px-4 py-3 text-base rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                                            @click="open=true">
                                                        Recibir
                                                    </button>
                                                    <button type="button"
                                                            class="px-4 py-3 text-sm rounded border border-gray-400 text-gray-600 hover:bg-gray-50"
                                                            title="Cerrar esta entrada aunque no se haya completado la cantidad"
                                                            @click="openFin=true">
                                                        Finiquitar
                                                    </button>
                                                </div>

                                                {{-- MODAL FINIQUITAR --}}
                                                <div x-show="openFin" x-cloak class="fixed inset-0 z-50">
                                                    <div class="absolute inset-0 bg-black/50" @click="openFin=false"></div>
                                                    <div class="relative bg-white shadow-lg overflow-hidden
                                                                w-full h-auto
                                                                md:max-w-lg md:mx-auto md:mt-24
                                                                md:rounded-lg">

                                                        <div class="p-4 border-b flex items-center justify-between">
                                                            <div class="font-semibold text-gray-800 text-lg">Finiquitar entrada</div>
                                                            <button type="button" class="px-3 py-1 rounded border text-sm" @click="openFin=false">✕</button>
                                                        </div>

                                                        <div class="p-5 space-y-4">
                                                            {{-- Resumen --}}
                                                            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm space-y-1">
                                                                <div><span class="text-gray-500">Insumo:</span> <strong>{{ $it['insumo'] }}</strong> — {{ $it['descripcion'] }}</div>
                                                                <div><span class="text-gray-500">Cantidad pedida:</span> <strong>{{ number_format($it['cantidad'], 4) }} {{ $it['unidad'] }}</strong></div>
                                                                <div><span class="text-gray-500">Cantidad recibida:</span> <strong>{{ number_format($it['parcial_actual'], 4) }} {{ $it['unidad'] }}</strong></div>
                                                                <div class="text-red-700 font-semibold">Diferencia que quedará sin recibir: {{ number_format($it['faltante'], 4) }} {{ $it['unidad'] }}</div>
                                                            </div>

                                                            <p class="text-sm text-gray-700 font-medium">
                                                                ⚠️ La orden quedará cerrada aunque exista diferencia pendiente. Esta acción no se puede deshacer desde la app.
                                                            </p>
                                                            <p class="text-sm text-gray-700">
                                                                ¿Deseas continuar?
                                                            </p>

                                                            <form method="POST"
                                                                  action="{{ route('ordenes-compra.finiquitar') }}"
                                                                  @submit.prevent="sendingFin=true; submitConTokenFresco($event.target)">
                                                                @csrf
                                                                <input type="hidden" name="pedido_det_id"     value="{{ $it['pedido_det_id'] }}">
                                                                <input type="hidden" name="id_pedido"         value="{{ $o['idPedido'] }}">
                                                                <input type="hidden" name="insumo"            value="{{ $it['insumo'] }}">
                                                                <input type="hidden" name="descripcion"       value="{{ $it['descripcion'] }}">
                                                                <input type="hidden" name="unidad"            value="{{ $it['unidad'] }}">
                                                                <input type="hidden" name="cantidad_pedida"   value="{{ number_format((float)$it['cantidad'], 4, '.', '') }}">
                                                                <input type="hidden" name="cantidad_recibida" value="{{ number_format((float)$it['parcial_actual'], 4, '.', '') }}">

                                                                <div>
                                                                    <label class="block text-sm text-gray-600 mb-1">Observaciones (opcional)</label>
                                                                    <textarea name="observaciones"
                                                                              rows="2"
                                                                              maxlength="500"
                                                                              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"
                                                                              placeholder="Ej: merma por corte, diferencia de báscula…"></textarea>
                                                                </div>

                                                                <div class="flex gap-3 mt-4">
                                                                    <button type="button"
                                                                            class="flex-1 px-4 py-3 rounded border text-sm text-gray-600 hover:bg-gray-50"
                                                                            @click="openFin=false">
                                                                        Cancelar
                                                                    </button>
                                                                    <button type="submit"
                                                                            :disabled="sendingFin"
                                                                            class="flex-1 px-4 py-3 rounded bg-gray-800 text-white text-sm font-semibold hover:bg-gray-700 disabled:opacity-60">
                                                                        <span x-show="!sendingFin">Sí, finiquitar</span>
                                                                        <span x-show="sendingFin" x-cloak>Procesando…</span>
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- MODAL RECIBIR --}}
                                                <div x-show="open" x-cloak class="fixed inset-0 z-50">
                                                    <div class="absolute inset-0 bg-black/50" @click="open=false"></div>

                                                    <div class="relative bg-white shadow-lg overflow-hidden
                                                                w-full h-full md:h-auto
                                                                md:max-w-2xl md:mx-auto md:mt-16
                                                                md:rounded-lg">

                                                        <div class="p-4 border-b flex items-center justify-between sticky top-0 bg-white">
                                                            <div class="pr-2">
                                                                <div class="font-semibold text-gray-800 text-lg">
                                                                    Recibir producto (OC #{{ $o['idPedido'] }})
                                                                </div>
                                                                <div class="text-sm text-gray-600">
                                                                    {{ $it['insumo'] }} — {{ $it['unidad'] }}
                                                                    — Pedido: {{ number_format($it['cantidad'], 2) }}
                                                                    — Recibido: {{ number_format($it['parcial_actual'], 2) }}
                                                                    — Faltante: {{ number_format($it['faltante'], 2) }}
                                                                </div>
                                                            </div>

                                                            <button type="button"
                                                                    class="px-4 py-2 rounded border text-base"
                                                                    @click="open=false">
                                                                Cerrar
                                                            </button>
                                                        </div>

                                                        <div class="p-4 space-y-4 overflow-auto"
                                                             style="max-height: calc(100vh - 130px);">

                                                            <form method="POST"
                                                                action="{{ route('ordenes-compra.recibir') }}"
                                                                enctype="multipart/form-data"
                                                                class="space-y-4"
                                                                x-data="{ preview:null, sending:false }"
                                                                @submit.prevent="sending=true; submitConTokenFresco($event.target)">

                                                            @csrf

                                                            <input type="hidden" name="items[0][idPedido]"       value="{{ $o['idPedido'] }}">
                                                            <input type="hidden" name="items[0][pedido_det_id]"  value="{{ $it['pedido_det_id'] }}">
                                                            <input type="hidden" name="items[0][idInsumo]"       value="{{ $it['id'] }}">
                                                            <input type="hidden" name="items[0][descripcion]"    value="{{ $it['descripcion'] }}">
                                                            <input type="hidden" name="items[0][unidad]"         value="{{ $it['unidad'] }}">
                                                            <input type="hidden" name="items[0][cantidad_pedida]" value="{{ number_format((float) $it['cantidad'],       4, '.', '') }}">
                                                            <input type="hidden" name="items[0][parcial_actual]"  value="{{ number_format((float) $it['parcial_actual'], 4, '.', '') }}">
                                                            <input type="hidden" name="items[0][faltante]"        value="{{ number_format((float) $it['faltante'],       4, '.', '') }}">
                                                            <input type="hidden" name="items[0][razonSocial]"    value="{{ $it['razonSocial'] }}">
                                                            <input type="hidden" name="items[0][fecha_oc]"       value="{{ $o['fecha']->format('Y-m-d') }}">
                                                            <input type="hidden" name="items[0][pu]"             value="{{ $it['pu'] }}">

                                                            @php $faltante = (float) $it['faltante']; @endphp

                                                            <div class="space-y-2">
                                                                <label class="block text-base text-gray-700">
                                                                    ¿Cuánto llegó?
                                                                </label>

                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <input type="number"
                                                                           step="0.001"
                                                                           min="0.001"
                                                                           max="{{ number_format($faltante, 4, '.', '') }}"
                                                                           name="items[0][llego]"
                                                                           class="w-36 border rounded px-3 py-3 text-lg text-right"
                                                                           placeholder="0.00"
                                                                           required>

                                                                    <button type="button"
                                                                            class="px-4 py-3 text-base border rounded"
                                                                            @click="open=false">
                                                                        Cancelar
                                                                    </button>
                                                                    <button type="submit"
                                                                            :disabled="sending"
                                                                            class="px-4 py-3 text-base rounded bg-gray-800 text-white hover:bg-gray-700 disabled:opacity-60">
                                                                        <span x-show="!sending">Confirmar</span>
                                                                        <span x-show="sending" x-cloak>Guardando...</span>
                                                                    </button>
                                                                </div>

                                                                <p class="text-sm text-gray-500">
                                                                    Máximo permitido (faltante): {{ number_format($faltante, 4) }}
                                                                </p>
                                                            </div>

                                                            <div class="space-y-2">
                                                                <label class="block text-base text-gray-700">Foto de recepción</label>

                                                                <input
                                                                    type="file"
                                                                    name="items[0][foto]"
                                                                    accept="image/*"
                                                                    capture="environment"
                                                                    class="w-full border rounded px-4 py-3"
                                                                    required
                                                                    @change="
                                                                        const f = $event.target.files[0];
                                                                        preview = f ? URL.createObjectURL(f) : null;
                                                                    "
                                                                >

                                                                <img
                                                                    x-show="preview"
                                                                    x-cloak
                                                                    :src="preview"
                                                                    class="mt-2 w-full max-h-64 object-contain rounded border bg-gray-50"
                                                                    alt="preview"
                                                                />

                                                                <p class="text-sm text-gray-500">
                                                                    En celular abre cámara; en PC te deja seleccionar archivo.
                                                                </p>
                                                            </div>
                                                        </form>

                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @endif

            </div>{{-- /tab oc --}}

            {{-- ═══════════════════════════════════════════════════════════
                 TAB 2 — ENTRADA MANUAL
            ═══════════════════════════════════════════════════════════ --}}
            <div x-show="tab === 'manual'" style="display:none">

                <div class="bg-white shadow-sm rounded-lg border p-5">
                    <h3 class="text-base font-semibold text-gray-800 mb-1">
                        Nueva entrada manual
                    </h3>
                    <p class="text-sm text-gray-500 mb-5">
                        Registra una entrada de inventario sin orden de compra.
                        Busca un insumo existente o ingresa los datos manualmente.
                    </p>

                    <form id="form-manual"
                          method="POST"
                          action="{{ route('entradas-manuales.store') }}"
                          class="space-y-4 pb-24"
                          @submit.prevent="sending = true; submitConTokenFresco($event.target)">
                        @csrf

                        {{-- Insumo fijado: botón para limpiar y buscar otro --}}
                        <div x-show="insumoFijado" x-cloak
                             class="flex items-center justify-between gap-3 p-3 rounded-lg bg-blue-50 border border-blue-200">
                            <span class="text-sm text-blue-800">
                                Insumo seleccionado: <strong x-text="descripcion"></strong>
                            </span>
                            <button type="button"
                                    @click="limpiar()"
                                    class="shrink-0 px-4 py-2 rounded-lg border border-blue-300 text-blue-700 text-sm font-medium hover:bg-blue-100">
                                × Buscar otro
                            </button>
                        </div>

                        {{-- DESCRIPCIÓN (autocomplete por descripción) --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 overflow-visible">
                            <div class="md:col-span-2 relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Descripción <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="descripcion"
                                       x-model="descripcion"
                                       @input.debounce.300ms="if(!insumoFijado) buscarPorDesc()"
                                       @keydown.escape="resultsDesc = []"
                                       @click.outside="resultsDesc = []"
                                       :readonly="insumoFijado"
                                       :class="insumoFijado ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                       class="w-full border rounded-lg px-4 py-3 text-sm"
                                       placeholder="Descripción del insumo"
                                       required>

                                <span x-show="loadingDesc" x-cloak
                                      class="absolute right-3 top-10 text-xs text-gray-400">Buscando...</span>

                                <div x-show="resultsDesc.length > 0" x-cloak
                                     class="absolute z-50 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-xl mt-1 max-h-64 overflow-y-auto">
                                    <template x-for="item in resultsDesc" :key="item.insumo_id || item.id || item.descripcion">
                                        <button type="button"
                                                @click="seleccionarDesc(item)"
                                                class="w-full text-left px-4 py-3 hover:bg-gray-50 border-b last:border-0">
                                            <div class="flex items-center gap-2">
                                                <span x-show="item.insumo_id"
                                                      class="text-xs font-mono bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded"
                                                      x-text="item.insumo_id"></span>
                                                <span class="text-sm font-medium text-gray-800" x-text="item.descripcion"></span>
                                                <span x-show="item.from_erp"
                                                      class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">ERP</span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                <span x-text="item.unidad"></span>
                                                <span x-show="!item.from_erp">&nbsp;· Stock: <span class="font-medium" x-text="item.cantidad"></span></span>
                                                <span x-show="item.from_erp" class="text-blue-500">&nbsp;· Insumo del catálogo ERP</span>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Unidad <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="unidad"
                                       x-model="unidad"
                                       :readonly="insumoFijado"
                                       :class="insumoFijado ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                       class="w-full border rounded-lg px-4 py-3 text-sm"
                                       placeholder="PZA, M2, KG…"
                                       required>
                            </div>
                        </div>

                        {{-- CÓDIGO DE INSUMO (autocomplete por código) --}}
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Código de insumo
                                <span class="text-gray-400 font-normal">(opcional — búsqueda por código ERP)</span>
                            </label>
                            <input type="text"
                                   name="insumo_id"
                                   :value="selectedInsumoId"
                                   @input="if(!insumoFijado){ selectedInsumoId = $event.target.value.toUpperCase(); buscarPorCode(); }"
                                   @keydown.escape="resultsCode = []"
                                   @click.outside="resultsCode = []"
                                   :readonly="insumoFijado"
                                   :class="insumoFijado ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                   class="w-full border rounded-lg px-4 py-3 text-sm font-mono uppercase"
                                   placeholder="Ej: 02ON-VAR-00001"
                                   style="text-transform:uppercase">

                            <span x-show="loadingCode" x-cloak
                                  class="absolute right-3 top-10 text-xs text-gray-400">Buscando...</span>

                            <div x-show="resultsCode.length > 0" x-cloak
                                 class="absolute z-50 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-xl mt-1 max-h-64 overflow-y-auto">
                                <template x-for="item in resultsCode" :key="item.insumo_id">
                                    <button type="button"
                                            @click="seleccionarCode(item)"
                                            class="w-full text-left px-4 py-3 hover:bg-gray-50 border-b last:border-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded"
                                                  x-text="item.insumo_id"></span>
                                            <span class="text-sm font-medium text-gray-800" x-text="item.descripcion"></span>
                                            <span x-show="item.from_erp"
                                                  class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">ERP</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <span x-text="item.unidad"></span>
                                            <span x-show="!item.from_erp">&nbsp;· Stock: <span class="font-medium" x-text="item.cantidad"></span></span>
                                            <span x-show="item.from_erp" class="text-blue-500">&nbsp;· Insumo del catálogo ERP</span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- CANTIDAD + COSTO --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Cantidad <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="cantidad"
                                       step="any"
                                       min="0"
                                       class="w-full border rounded-lg px-4 py-3 text-sm text-right"
                                       placeholder="Ej: 5 ó 2.5"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Costo unitario <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="costo_unitario"
                                       x-model="costoUnitario"
                                       step="any"
                                       min="0"
                                       class="w-full border rounded-lg px-4 py-3 text-sm text-right"
                                       placeholder="Ej: 150 ó 89.50"
                                       required>
                            </div>
                        </div>

                        {{-- FECHA --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fecha de entrada <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   name="fecha_entrada"
                                   value="{{ date('Y-m-d') }}"
                                   :readonly="insumoFijado"
                                   :class="insumoFijado ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                   class="w-full border rounded-lg px-4 py-3 text-sm"
                                   required>
                        </div>

                        {{-- FAMILIA + SUBFAMILIA --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Familia
                                </label>
                                <input type="text"
                                       name="familia"
                                       x-model="familia"
                                       :readonly="insumoFijado"
                                       :class="insumoFijado ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                       class="w-full border rounded-lg px-4 py-3 text-sm"
                                       placeholder="SIN FAMILIA">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Subfamilia
                                </label>
                                <input type="text"
                                       name="subfamilia"
                                       x-model="subfamilia"
                                       :readonly="insumoFijado"
                                       :class="insumoFijado ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                       class="w-full border rounded-lg px-4 py-3 text-sm"
                                       placeholder="SIN SUBFAMILIA">
                            </div>
                        </div>

                        {{-- OBSERVACIONES --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Observaciones
                            </label>
                            <textarea name="observaciones"
                                      rows="2"
                                      class="w-full border rounded-lg px-4 py-3 text-sm resize-none"
                                      placeholder="Notas adicionales (opcional)"></textarea>
                        </div>

                        {{-- Botón limpiar inline --}}
                        <div class="flex justify-end pt-1">
                            <button type="button"
                                    @click="limpiar()"
                                    :disabled="sending"
                                    class="px-4 py-2 rounded-lg border text-sm text-gray-500 hover:bg-gray-50 disabled:opacity-60">
                                Limpiar formulario
                            </button>
                        </div>

                    </form>
                </div>

            </div>{{-- /tab manual --}}

            {{-- ═══════════════════════════════════════════════════════════
                 TAB 3 — TRANSFERENCIAS PENDIENTES
            ═══════════════════════════════════════════════════════════ --}}
            <div x-show="tab === 'transferencias'" style="display:none">

                {{-- Cargando --}}
                <div x-show="transLoading" class="text-center py-10 text-gray-500 text-sm">
                    Cargando transferencias pendientes...
                </div>

                {{-- Sin pendientes --}}
                <div x-show="!transLoading && transPendientes.length === 0" class="bg-white rounded-lg border p-8 text-center text-gray-500 text-sm">
                    No hay transferencias pendientes para esta obra.
                </div>

                {{-- Órdenes de transferencia agrupadas --}}
                <div x-show="!transLoading && transPendientes.length > 0" class="space-y-4">
                    <template x-for="t in transPendientes" :key="t.id">
                        <div class="bg-white rounded-lg border shadow-sm overflow-hidden">

                            {{-- Encabezado de la orden --}}
                            <div class="px-4 py-3 bg-gray-50 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <div class="text-xs text-gray-500 mb-0.5">
                                        Orden de transferencia
                                        <span class="font-mono font-semibold text-gray-700" x-text="'#' + t.id"></span>
                                    </div>
                                    <div class="font-semibold text-gray-800" x-text="t.obra_origen"></div>
                                    <div class="text-sm text-gray-500 mt-0.5">
                                        Enviado por <span x-text="t.usuario_envia"></span>
                                        · <span x-text="t.fecha"></span>
                                        · <span class="font-medium" x-text="t.total_items + ' insumo(s)'"></span>
                                    </div>
                                    <div x-show="t.observaciones" class="text-xs text-gray-400 mt-0.5 italic" x-text="t.observaciones"></div>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold">PENDIENTE</span>
                                    <button type="button"
                                            @click="abrirModalTransfer(t)"
                                            class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm hover:bg-gray-700 active:bg-black">
                                        Recibir
                                    </button>
                                </div>
                            </div>

                            {{-- Lista de insumos de esta orden --}}
                            <div class="divide-y divide-gray-100">
                                <template x-for="item in t.items" :key="item.id">
                                    <div class="px-4 py-2.5 flex items-center gap-3 text-sm">
                                        <span class="text-xs text-gray-400 font-mono w-24 shrink-0 truncate" x-text="item.insumo_id || '—'"></span>
                                        <span class="flex-1 text-gray-800 min-w-0 truncate" x-text="item.descripcion"></span>
                                        <span class="shrink-0 text-right whitespace-nowrap">
                                            <span class="font-semibold text-gray-700" x-text="item.cantidad"></span>
                                            <span class="text-xs text-gray-400 ml-1" x-text="item.unidad"></span>
                                        </span>
                                    </div>
                                </template>
                            </div>

                        </div>
                    </template>
                </div>

            </div>{{-- /tab transferencias --}}

            {{-- ── MODAL RECEPCIÓN DE TRANSFERENCIA ──────────────────────── --}}
            <div x-show="transModal !== null" style="display:none" class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/50" @click="transModal = null"></div>

                <div class="relative bg-white shadow-xl overflow-hidden
                            w-full h-full md:h-auto md:max-w-2xl md:mx-auto md:mt-10 md:rounded-xl">

                    {{-- Header modal --}}
                    <div class="p-4 border-b flex items-center justify-between sticky top-0 bg-white z-10" x-show="transModal !== null">
                        <div>
                            <div class="font-semibold text-gray-800 text-base">
                                Recibir transferencia <span x-text="transModal ? '#' + transModal.id : ''"></span>
                            </div>
                            <div class="text-sm text-gray-500" x-show="transModal">
                                Origen: <span x-text="transModal?.obra_origen"></span>
                                · <span x-text="transModal?.fecha"></span>
                            </div>
                        </div>
                        <button type="button" @click="transModal = null"
                                class="px-3 py-2 rounded border text-sm text-gray-600 hover:bg-gray-50">
                            Cerrar
                        </button>
                    </div>

                    {{-- Body modal --}}
                    <div class="p-4 overflow-auto" style="max-height: calc(100vh - 180px);" x-show="transModal">

                        <p class="text-sm text-gray-600 mb-4">
                            Verifica las cantidades recibidas. Puedes editarlas si llegó diferente a lo enviado.
                        </p>

                        <template x-for="d in (transModal?.detalles ?? [])" :key="d.id">
                            <div class="flex items-center gap-3 py-3 border-b last:border-0">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 truncate" x-text="d.descripcion"></div>
                                    <div class="text-xs text-gray-500">
                                        <span x-text="d.insumo_id || 'Sin código'"></span>
                                        · <span x-text="d.unidad"></span>
                                        · Enviado: <span class="font-semibold" x-text="d.cantidad"></span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1 shrink-0">
                                    <label class="text-xs text-gray-500">Recibido</label>
                                    <input type="number"
                                           x-model="d.cantidad_recibida"
                                           step="any"
                                           min="0"
                                           class="w-28 border rounded-lg px-3 py-2 text-sm text-right font-mono">
                                </div>
                            </div>
                        </template>

                        <div x-show="transModal?.observaciones" class="mt-3 text-sm text-gray-500 italic">
                            Nota: <span x-text="transModal?.observaciones"></span>
                        </div>

                        {{-- Acciones --}}
                        <div class="flex gap-3 mt-5 pt-4 border-t">
                            <button type="button"
                                    :disabled="transSending"
                                    @click="aceptarTransfer()"
                                    class="flex-1 py-3 rounded-xl bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800 disabled:opacity-60">
                                <span x-show="!transSending">✓ Aceptar recepción</span>
                                <span x-show="transSending" style="display:none">Procesando...</span>
                            </button>
                            <button type="button"
                                    :disabled="transSending"
                                    @click="rechazarTransfer()"
                                    class="px-5 py-3 rounded-xl border border-red-300 text-red-700 text-sm font-semibold hover:bg-red-50 disabled:opacity-60">
                                Rechazar
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── BOTÓN FLOTANTE GUARDAR (visible siempre en tab manual) ─── --}}
            <div x-show="tab === 'manual'"
                 style="display:none"
                 class="fixed bottom-0 left-0 right-0 z-40 px-4 pb-4 pt-3 bg-white border-t shadow-2xl">
                <button form="form-manual"
                        type="submit"
                        class="w-full py-4 rounded-xl bg-emerald-600 text-white text-base font-bold tracking-wide
                               hover:bg-emerald-700 active:bg-emerald-800 shadow-lg transition-all">
                    ✓ Guardar entrada
                </button>
            </div>

        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function ocUI() {
            return {
                // ── estado tabs ──────────────────────────────────────────
                tab:    '{{ request()->get("tab", session("tab", "oc")) }}',
                estado: 'todas',

                // ── datos formulario entrada manual ──────────────────────
                sending:          false,
                insumoFijado:     false,
                selectedInsumoId: '',
                descripcion:      '',
                unidad:           '',
                proveedor:        '',
                costoUnitario:    '',
                familia:          '',
                subfamilia:       '',
                // autocomplete descripción
                resultsDesc:  [],
                loadingDesc:  false,
                // autocomplete código
                resultsCode:  [],
                loadingCode:  false,

                // ── métodos tab OC ────────────────────────────────────────
                estadoItem(recibida, pedida) {
                    const rec = Number(recibida || 0);
                    const ped = Number(pedida   || 0);
                    if (rec <= 0)   return 'pendiente';
                    if (rec >= ped) return 'finalizada';
                    return 'parcial';
                },

                pasaFiltro(recibida, pedida) {
                    if (this.estado === 'todas') return true;
                    return this.estadoItem(recibida, pedida) === this.estado;
                },

                // ── métodos formulario manual ─────────────────────────────
                async buscarPorDesc() {
                    if (this.descripcion.length < 2) { this.resultsDesc = []; return; }
                    this.loadingDesc = true;
                    try {
                        const r = await fetchConCsrf(`/salidas/buscar-productos?q=${encodeURIComponent(this.descripcion)}&mode=desc&src=erp`);
                        const data = await r.json();
                        this.resultsDesc = Array.isArray(data) ? data : [];
                    } catch (e) {
                        this.resultsDesc = [];
                    }
                    this.loadingDesc = false;
                },

                async buscarPorCode() {
                    if (this.selectedInsumoId.length < 2) { this.resultsCode = []; return; }
                    this.loadingCode = true;
                    try {
                        const r = await fetchConCsrf(`/salidas/buscar-productos?q=${encodeURIComponent(this.selectedInsumoId)}&mode=code&src=erp`);
                        const data = await r.json();
                        this.resultsCode = Array.isArray(data) ? data : [];
                    } catch (e) {
                        this.resultsCode = [];
                    }
                    this.loadingCode = false;
                },

                _poblarCampos(item) {
                    this.selectedInsumoId = item.insumo_id    || '';
                    this.descripcion      = item.descripcion  || '';
                    this.unidad           = item.unidad       || '';
                    this.proveedor        = item.proveedor    || '';
                    this.costoUnitario    = item.costo_promedio > 0 ? item.costo_promedio : '';
                    this.familia          = item.familia      || '';
                    this.subfamilia       = item.subfamilia   || '';
                    this.insumoFijado     = true;
                },

                seleccionarDesc(item) {
                    this._poblarCampos(item);
                    this.resultsDesc = [];
                },

                seleccionarCode(item) {
                    this._poblarCampos(item);
                    this.resultsCode = [];
                },

                limpiar() {
                    this.selectedInsumoId = '';
                    this.descripcion      = '';
                    this.unidad           = '';
                    this.proveedor        = '';
                    this.costoUnitario    = '';
                    this.familia          = '';
                    this.subfamilia       = '';
                    this.resultsDesc      = [];
                    this.resultsCode      = [];
                    this.sending          = false;
                    this.insumoFijado     = false;
                },

                // ── estado tab transferencias ─────────────────────────────
                transPendientes:  [],
                transLoading:     false,
                transLoaded:      false,
                transModal:       null,
                transSending:     false,

                async cargarTransferencias() {
                    if (this.transLoaded) return;
                    this.transLoading = true;
                    try {
                        const r = await fetchConCsrf('/transferencias/pendientes');
                        if (r.ok) this.transPendientes = await r.json();
                    } catch (e) {}
                    this.transLoading = false;
                    this.transLoaded  = true;
                },

                abrirModalTransfer(t) {
                    this.transModal = {
                        id:            t.id,
                        fecha:         t.fecha,
                        obra_origen:   t.obra_origen,
                        usuario_envia: t.usuario_envia,
                        observaciones: t.observaciones,
                        detalles:      JSON.parse(JSON.stringify(t.items)),
                    };
                },

                async aceptarTransfer() {
                    if (! this.transModal) return;
                    const items = this.transModal.detalles.map(d => ({
                        detalle_id:        d.id,
                        cantidad_recibida: d.cantidad_recibida,
                    }));
                    this.transSending = true;
                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    try {
                        const r = await fetchConCsrf(`/transferencias/${this.transModal.id}/recibir`, {
                            method:  'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body:    JSON.stringify({ items }),
                        });
                        const res = await r.json();
                        if (res.ok) {
                            this.transModal  = null;
                            this.transLoaded = false;
                            await this.cargarTransferencias();
                        } else {
                            alert(res.message || 'Error al procesar.');
                        }
                    } catch (e) { alert('Error de conexión.'); }
                    this.transSending = false;
                },

                async rechazarTransfer() {
                    if (! this.transModal) return;
                    if (! confirm('¿Rechazar esta transferencia? El stock se devolverá al origen.')) return;
                    this.transSending = true;
                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    try {
                        const r = await fetchConCsrf(`/transferencias/${this.transModal.id}/rechazar`, {
                            method:  'POST',
                            headers: { 'X-CSRF-TOKEN': token },
                        });
                        const res = await r.json();
                        if (res.ok) {
                            this.transModal  = null;
                            this.transLoaded = false;
                            await this.cargarTransferencias();
                        } else {
                            alert(res.message || 'Error al rechazar.');
                        }
                    } catch (e) { alert('Error de conexión.'); }
                    this.transSending = false;
                }
            }
        }
    </script>
</x-app-layout>
