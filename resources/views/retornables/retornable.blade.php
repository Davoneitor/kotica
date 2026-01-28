{{-- resources/views/retornable.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Retornables
                </h2>
                <div class="text-sm text-gray-600 mt-1">
                    Solo muestra devolvibles (=1) de tu obra actual.
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- flashes --}}
            @if (session('success'))
                <div class="mb-4 p-3 rounded border bg-green-50 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="mb-4 p-3 rounded border bg-yellow-50 text-yellow-900">
                    {{ session('warning') }}
                </div>
            @endif

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

            {{-- 🔎 filtros --}}
            <form method="GET" action="{{ route('retornables.index') }}"
                  class="mb-4 p-3 md:p-4 border rounded-lg bg-white">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-1">
                        <label class="block text-xs md:text-sm text-gray-600 mb-1">
                            Persona (quién se lo llevó)
                        </label>
                        <input type="text"
                               name="persona"
                               value="{{ $qPersona ?? '' }}"
                               class="w-full border rounded-lg px-4 py-3 text-base md:text-sm"
                               placeholder="Ej: Juan, Cabo Pérez">
                    </div>

                    <div class="md:col-span-1">
                        <label class="block text-xs md:text-sm text-gray-600 mb-1">
                            Insumo / descripción
                        </label>
                        <input type="text"
                               name="insumo"
                               value="{{ $qInsumo ?? '' }}"
                               class="w-full border rounded-lg px-4 py-3 text-base md:text-sm"
                               placeholder="Ej: rotomartillo, 303-ARF">
                    </div>

                    <div>
                        <label class="block text-xs md:text-sm text-gray-600 mb-1">
                            Desde
                        </label>
                        <input type="date"
                               name="from"
                               value="{{ $from ?? '' }}"
                               class="w-full border rounded-lg px-4 py-3 text-base md:text-sm">
                    </div>

                    <div>
                        <label class="block text-xs md:text-sm text-gray-600 mb-1">
                            Hasta
                        </label>
                        <input type="date"
                               name="to"
                               value="{{ $to ?? '' }}"
                               class="w-full border rounded-lg px-4 py-3 text-base md:text-sm">
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <button type="submit"
                            class="w-full md:w-auto px-5 py-3 rounded-lg bg-gray-800 text-white text-base md:text-sm hover:bg-gray-900">
                        Buscar
                    </button>

                    @if(($qPersona ?? '') !== '' || ($qInsumo ?? '') !== '' || ($from ?? '') !== '' || ($to ?? '') !== '')
                        <a href="{{ route('retornables.index') }}"
                           class="w-full md:w-auto px-5 py-3 rounded-lg border bg-gray-100 text-gray-800 text-base md:text-sm hover:bg-gray-200 text-center">
                            Limpiar
                        </a>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-700">
                        Tip: filtra por persona + rango de fechas para ubicar herramientas viejas.
                    </span>
                </div>
            </form>

            {{-- lista --}}
            @if($retornables->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <p class="text-gray-600">No hay retornables pendientes para esta obra.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($retornables as $r)
                        @php
                            // días ya viene como entero desde SQL (DATEDIFF day)
                            $dias = (int) ($r->dias ?? 0);

                            // badge por tiempo
                            $badge = 'bg-slate-100 text-slate-700';
                            if ($dias >= 14) $badge = 'bg-red-100 text-red-800';
                            elseif ($dias >= 7) $badge = 'bg-amber-100 text-amber-900';
                        @endphp

                        <div class="bg-white shadow-sm rounded-lg border p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-semibold text-base text-gray-800 truncate">
                                        {{ $r->descripcion }}
                                    </div>

                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium">Insumo:</span>
                                        <span class="font-mono">{{ $r->insumo_id ?? $r->inventario_id }}</span>
                                    </div>

                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium">Quién:</span> {{ $r->nombre_cabo ?? 'Sin nombre' }}
                                    </div>

                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium">Fecha:</span>
                                        {{ \Carbon\Carbon::parse($r->fecha)->format('Y-m-d') }}
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <div class="text-xs text-gray-500">Cantidad</div>
                                    <div class="font-bold text-lg">
                                        {{ number_format((float)$r->cantidad, 2) }}
                                    </div>
                                    <div class="text-xs text-gray-600">{{ $r->unidad }}</div>

                                    <div class="mt-2 inline-flex px-2 py-1 rounded text-xs font-semibold {{ $badge }}">
                                        {{ $dias }} día{{ $dias === 1 ? '' : 's' }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 flex justify-end">
                                <form method="POST" action="{{ route('retornables.recuperar', $r->detalle_id) }}"
                                      onsubmit="return confirm('¿Seguro que quieres recuperar este insumo? Se reintegrará al inventario.');">
                                    @csrf
                                    <button type="submit"
                                            class="px-4 py-2 text-base rounded bg-emerald-600 text-white hover:bg-emerald-700">
                                        Recuperar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
