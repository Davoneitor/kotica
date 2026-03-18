<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Usuarios</h2>
                <p class="text-sm text-gray-600 mt-1">Gestión de usuarios del sistema.</p>
            </div>

            <a href="{{ route('register') }}"
               class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm hover:bg-gray-900">
                + Registrar nuevo usuario
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">

                @if(session('success'))
                    <div class="m-4 p-3 bg-green-100 text-green-800 rounded-lg text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="m-4 p-3 bg-red-100 text-red-800 rounded-lg text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Nombre</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Correo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700">Obra(s)</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">Admin</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">Multiobra</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">Solo Explore</th>
                                <th class="px-4 py-3 text-center font-semibold text-gray-700">Acciones</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @forelse($users as $u)
                                @php $esSesion = $u->id === auth()->id(); @endphp
                                <tr class="{{ $esSesion ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        {{ $u->name }}
                                        @if($esSesion)
                                            <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-600 text-white">Tú</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $u->email }}</td>

                                    <td class="px-4 py-3 text-gray-700">
                                        @if($u->is_multiobra)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                Todas las obras
                                            </span>
                                        @elseif($u->obraActual)
                                            {{ $u->obraActual->nombre }}
                                        @elseif($u->obras->count())
                                            {{ $u->obras->first()->nombre }}
                                            @if($u->obras->count() > 1)
                                                <span class="text-gray-400 text-xs">(+{{ $u->obras->count() - 1 }} más)</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        @if($u->is_admin)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                Sí
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-xs">No</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        @if($u->is_multiobra)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                Sí
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-xs">No</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        @if($u->solo_explore)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                Sí
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-xs">No</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('users.edit', $u) }}"
                                               class="px-3 py-1.5 text-xs rounded border border-gray-300 bg-white hover:bg-gray-50 text-gray-700">
                                                Editar
                                            </a>

                                            @if($esSesion)
                                                <span class="px-3 py-1.5 text-xs rounded border border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed"
                                                      title="No puedes eliminar tu propio usuario">
                                                    Eliminar
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('users.destroy', $u) }}"
                                                      onsubmit="return confirm('¿Eliminar al usuario «{{ $u->name }}»? Esta acción no se puede deshacer.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="px-3 py-1.5 text-xs rounded border border-red-200 bg-red-50 hover:bg-red-100 text-red-700">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        No hay usuarios registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
