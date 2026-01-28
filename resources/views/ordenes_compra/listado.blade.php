<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Órdenes de compra (ERP)
                </h2>

                {{-- ✅ SOLO nombre de la obra (sin "UN ERP: 48") --}}
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

            {{-- warnings --}}
            @if (session('warning'))
                <div class="mb-4 p-3 rounded border bg-yellow-50 text-yellow-900">
                    {{ session('warning') }}
                </div>
            @endif

            {{-- success --}}
            @if (session('success'))
                <div class="mb-4 p-3 rounded border bg-green-50 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- error --}}
            @if (session('error'))
                <div class="mb-4 p-3 rounded border bg-red-50 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

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

                    {{-- ❌ Quitado: checkbox "Solo parciales" --}}

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

                {{-- ✅ Botones estilo Explore (solo para esta sección) --}}
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

                {{-- ✅ Render estilo Explore: cards por orden --}}
                <div class="space-y-6">
                    @foreach($ordenes as $o)

                        @php
                            // ✅ Contadores por orden para NO mostrar órdenes vacías en los filtros
                            $pendCount = collect($o['items'])->filter(fn($it) => (float)($it['parcial_actual'] ?? 0) <= 0)->count();
                            $parcCount = collect($o['items'])->filter(function($it){
                                $rec = (float)($it['parcial_actual'] ?? 0);
                                $ped = (float)($it['cantidad'] ?? 0);
                                return $rec > 0 && $rec < $ped;
                            })->count();
                            $totalCount = collect($o['items'])->count();
                        @endphp

                        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden"
                             x-show="
                                (estado==='todas'    && {{ $totalCount }} > 0) ||
                                (estado==='pendiente' && {{ $pendCount }} > 0) ||
                                (estado==='parcial'   && {{ $parcCount }} > 0)
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
                                        <div class="text-gray-500 text-xs">
                                            Proveedor
                                        </div>
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
                                                {{ $it['vencida'] ? 'bg-red-50 border-red-200' : '' }}
                                                {{ $it['es_parcial'] ? 'bg-amber-50 border-amber-200' : '' }}"
                                            x-data="{ open:false }"
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

                                            <div class="mt-3">
                                                <button type="button"
                                                        class="w-full px-4 py-3 text-base rounded bg-emerald-600 text-white hover:bg-emerald-700"
                                                        @click="open=true">
                                                    Recibir
                                                </button>
                                            </div>

                                            {{-- MODAL --}}
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
                                                              class="space-y-4">
                                                            @csrf

                                                            <input type="hidden" name="items[0][idPedido]" value="{{ $o['idPedido'] }}">
                                                            <input type="hidden" name="items[0][pedido_det_id]" value="{{ $it['pedido_det_id'] }}">
                                                            <input type="hidden" name="items[0][idInsumo]" value="{{ $it['id'] }}">
                                                            <input type="hidden" name="items[0][descripcion]" value="{{ $it['descripcion'] }}">
                                                            <input type="hidden" name="items[0][unidad]" value="{{ $it['unidad'] }}">
                                                            <input type="hidden" name="items[0][cantidad_pedida]" value="{{ $it['cantidad'] }}">
                                                            <input type="hidden" name="items[0][parcial_actual]" value="{{ $it['parcial_actual'] }}">
                                                            <input type="hidden" name="items[0][razonSocial]" value="{{ $it['razonSocial'] }}">

                                                            <div class="space-y-2">
                                                                <label class="block text-base text-gray-700">
                                                                    ¿Cuánto llegó?
                                                                </label>

                                                                <input type="number"
                                                                       step="0.01"
                                                                       min="0.01"
                                                                       max="{{ $it['faltante'] }}"
                                                                       name="items[0][llego]"
                                                                       class="w-full md:w-56 border rounded px-4 py-3 text-lg text-right"
                                                                       placeholder="0.00"
                                                                       required>

                                                                <p class="text-sm text-gray-500">
                                                                    Máximo permitido (faltante): {{ number_format($it['faltante'], 2) }}
                                                                </p>
                                                            </div>

                                                            <div class="flex gap-3 pt-2">
                                                                <button type="button"
                                                                        class="w-1/2 md:w-auto px-5 py-3 text-base border rounded"
                                                                        @click="open=false">
                                                                    Cancelar
                                                                </button>

                                                                <button type="submit"
                                                                        class="w-1/2 md:w-auto px-5 py-3 text-base rounded bg-gray-800 text-white hover:bg-gray-700">
                                                                    Confirmar
                                                                </button>
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

        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function ocUI() {
            return {
                estado: 'todas', // todas|pendiente|parcial

                estadoItem(recibida, pedida) {
                    const rec = Number(recibida || 0);
                    const ped = Number(pedida || 0);
                    if (rec <= 0) return 'pendiente';
                    if (rec >= ped) return 'finalizada';
                    return 'parcial';
                },

                pasaFiltro(recibida, pedida) {
                    if (this.estado === 'todas') return true;
                    return this.estadoItem(recibida, pedida) === this.estado;
                }
            }
        }
    </script>
</x-app-layout>
