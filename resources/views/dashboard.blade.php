<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Órdenes de compra
            </h2>

            <a href="{{ route('ordenes-compra.create') }}"
               class="px-3 py-2 border rounded bg-gray-800 text-white hover:bg-gray-700">
                + Nueva orden
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-3 rounded border bg-green-50 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-700">
                    Aquí irá la tabla de órdenes de compra  
                    (folio, proveedor, fecha, total, estatus).
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
