<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    @php
        $hasProfile         = \Illuminate\Support\Facades\Route::has('profile.edit');
        $authUser           = auth()->user();
        $isAdmin            = $authUser?->isAdmin();
        $soloExplore        = $authUser?->solo_explore;
        $esOperadorCamiones = $authUser?->isOperadorCamiones();
        $isMultiobra        = (int)($authUser?->is_multiobra ?? 0) === 1;
        $obraActualNav      = ($authUser?->obra_actual_id)
                                ? \App\Models\Obra::find($authUser->obra_actual_id)
                                : null;
        $obrasNav           = $isMultiobra
                                ? \App\Models\Obra::orderBy('nombre')->get(['id','nombre'])
                                : collect();
    @endphp

    @if($isMultiobra)
    <script>
        window._navObras      = @json($obrasNav->values());
        window._navObraActual = { id: {{ $obraActualNav?->id ?? 'null' }}, nombre: @json($obraActualNav?->nombre ?? 'Sin obra') };
    </script>
    @endif

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- Logo + Links -->
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="#"
                       id="logo-kotica"
                       onclick="toggleModoHerramientas(); return false;"
                       title="Activar / desactivar modo herramientas"
                       class="flex items-center gap-2">
                        <img
                            src="{{ asset('images/logo-menu.png') }}"
                            alt="Kotica"
                            class="h-10 w-auto"
                        >
                    </a>
                </div>
                <script>
                function toggleModoHerramientas() {
                    const activo = localStorage.getItem('modoHerramientas') === '1';
                    if (activo) {
                        localStorage.removeItem('modoHerramientas');
                        document.documentElement.classList.remove('modo-herramientas');
                    } else {
                        localStorage.setItem('modoHerramientas', '1');
                        document.documentElement.classList.add('modo-herramientas');
                    }
                }
                </script>

                <!-- Navigation Links (DESKTOP) -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                    @if($esOperadorCamiones)
                        <x-nav-link
                            :href="route('control-camiones.index')"
                            :active="request()->routeIs('control-camiones.*')">
                            Control Salida Camiones
                        </x-nav-link>
                    @else
                        @if(!$soloExplore)
                        <x-nav-link
                            :href="route('inventario.index')"
                            :active="request()->routeIs('inventario.*')">
                            Inventario
                        </x-nav-link>

                        <x-nav-link
                            :href="route('salidas.index')"
                            :active="request()->routeIs('salidas.*')">
                            Salidas
                        </x-nav-link>

                        <x-nav-link
                            :href="route('ordenes-compra.index')"
                            :active="request()->routeIs('ordenes-compra.*')">
                            Entradas
                        </x-nav-link>

                        <x-nav-link
                            :href="route('retornables.index')"
                            :active="request()->routeIs('retornables.*')">
                            Retornables
                        </x-nav-link>

                        <x-nav-link
                            :href="route('control-camiones.index')"
                            :active="request()->routeIs('control-camiones.*')">
                            Control Salida Camiones
                        </x-nav-link>
                        @endif

                        <x-nav-link
                            :href="route('explore.index')"
                            :active="request()->routeIs('explore.*')">
                            Reportes
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Selector de obra (desktop) — visible en todo el sistema para usuarios multiobra -->
            @if($isMultiobra)
            <div class="hidden sm:flex sm:items-center sm:ml-4">
                <div class="relative"
                     x-data="{
                         open: false,
                         selectedId:   window._navObraActual?.id,
                         selectedName: window._navObraActual?.nombre || 'Sin obra',
                         obras:        window._navObras || [],
                         seleccionar(obra) {
                             this.selectedId   = obra.id;
                             this.selectedName = obra.nombre;
                             this.open = false;
                             this.$refs.hiddenInput.value = obra.id;
                             this.$refs.navObraForm.submit();
                         }
                     }"
                     @click.outside="open = false"
                     @keydown.escape.window="open = false">

                    <form x-ref="navObraForm" method="POST" action="{{ route('inventario.cambiarObra') }}">
                        @csrf
                        <input type="hidden" name="obra_id" x-ref="hiddenInput" :value="selectedId">
                    </form>

                    {{-- Botón principal --}}
                    <button type="button"
                            @click="open = !open"
                            class="group flex items-center gap-2 pl-2.5 pr-3 py-1.5 rounded-lg bg-white border border-gray-200 hover:border-gray-300 shadow-sm hover:shadow transition-all duration-150">
                        <span class="min-w-0">
                            <span class="block text-[9px] font-semibold uppercase tracking-widest leading-none mb-0.5" style="color:#818cf8">Obra</span>
                            <span class="block text-gray-800 font-medium text-sm leading-tight truncate max-w-[150px]" x-text="selectedName"></span>
                        </span>
                        <svg class="w-3 h-3 text-gray-400 shrink-0 transition-transform duration-150"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    {{-- Dropdown --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-1"
                         class="absolute end-0 mt-1.5 bg-white rounded-xl shadow-lg border border-gray-100 z-50 overflow-hidden"
                         style="min-width:220px; display:none;">

                        <div class="px-3 py-2 border-b border-gray-50">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Cambiar obra</p>
                        </div>

                        <div class="max-h-64 overflow-y-auto py-1">
                            <template x-for="obra in obras" :key="obra.id">
                                <button type="button"
                                        @click="seleccionar(obra)"
                                        class="w-full text-left px-3 py-2 text-sm flex items-center gap-2.5 transition-colors hover:bg-gray-50"
                                        :class="obra.id === selectedId ? 'bg-indigo-50' : ''">
                                    <span class="shrink-0 w-3.5 h-3.5 flex items-center justify-center">
                                        <svg x-show="obra.id === selectedId"
                                             class="w-3.5 h-3.5" style="color:#4f46e5"
                                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </span>
                                    <span x-text="obra.nombre" class="truncate"
                                          :class="obra.id === selectedId ? 'font-semibold text-indigo-700' : 'text-gray-700'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Settings Dropdown (desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-transparent
                                   text-sm leading-4 font-medium rounded-md text-gray-500
                                   bg-white hover:text-gray-700 focus:outline-none transition">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ml-1">
                                <svg class="fill-current h-4 w-4"
                                     xmlns="http://www.w3.org/2000/svg"
                                     viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586
                                             l3.293-3.293a1 1 0 111.414 1.414
                                             l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0
                                             010-1.414z"
                                          clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if($hasProfile)
                            <x-dropdown-link :href="route('profile.edit')">
                                Perfil
                            </x-dropdown-link>
                        @endif

                        @if($isAdmin)
                            <x-dropdown-link :href="route('users.index')">
                                Usuarios
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('admin.actualizar-reportes')">
                                Actualizar Reportes
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('admin.importar-inventario')">
                                Importar Inventario
                            </x-dropdown-link>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link
                                :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button
                    @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md
                           text-gray-400 hover:text-gray-500 hover:bg-gray-100
                           focus:outline-none transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path
                            :class="{ 'hidden': open, 'inline-flex': ! open }"
                            class="inline-flex"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"/>
                        <path
                            :class="{ 'hidden': ! open, 'inline-flex': open }"
                            class="hidden"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (MÓVIL) -->
    <div :class="{ 'block': open, 'hidden': ! open }" class="hidden sm:hidden">

        {{-- Selector de obra (móvil) — visible en todo el sistema --}}
        @if($isMultiobra)
        <div class="px-4 py-2.5 border-b border-gray-100">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-1.5">Obra activa</p>
            <form method="POST" action="{{ route('inventario.cambiarObra') }}">
                @csrf
                <select name="obra_id"
                        onchange="this.form.submit()"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 font-medium focus:outline-none focus:ring-2 focus:border-indigo-300 bg-white">
                    @foreach($obrasNav as $obra)
                        <option value="{{ $obra->id }}" {{ $obraActualNav?->id == $obra->id ? 'selected' : '' }}>
                            {{ $obra->nombre }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
        @endif

        <div class="pt-2 pb-3 space-y-1">

            @if($esOperadorCamiones)
                <x-responsive-nav-link
                    :href="route('control-camiones.index')"
                    :active="request()->routeIs('control-camiones.*')">
                    Control Salida Camiones
                </x-responsive-nav-link>
            @else
                @if(!$soloExplore)
                @if(request()->routeIs('inventario.*'))
                    <x-responsive-nav-link :href="route('inventario.create')">
                        + Nuevo producto
                    </x-responsive-nav-link>

                    <button type="button"
                            x-data="{}"
                            @click="$store.salidas.show = true; open = false"
                            class="w-full text-left px-4 py-2 text-base text-white bg-gray-800 hover:bg-gray-700">
                        Salida
                    </button>
                @endif

                <x-responsive-nav-link
                    :href="route('inventario.index')"
                    :active="request()->routeIs('inventario.*')">
                    Inventario
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('salidas.index')"
                    :active="request()->routeIs('salidas.*')">
                    Salidas
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('ordenes-compra.index')"
                    :active="request()->routeIs('ordenes-compra.*')">
                    Entradas
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('retornables.index')"
                    :active="request()->routeIs('retornables.*')">
                    Retornables
                </x-responsive-nav-link>

                <x-responsive-nav-link
                    :href="route('control-camiones.index')"
                    :active="request()->routeIs('control-camiones.*')">
                    Control Salida Camiones
                </x-responsive-nav-link>
                @endif

                <x-responsive-nav-link
                    :href="route('explore.index')"
                    :active="request()->routeIs('explore.*')">
                    Reportes
                </x-responsive-nav-link>
            @endif

        </div>

        <!-- Responsive Settings -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">
                    {{ Auth::user()->name }}
                </div>
                <div class="font-medium text-sm text-gray-500">
                    {{ Auth::user()->email }}
                </div>
            </div>

            <div class="mt-3 space-y-1">
                @if($hasProfile)
                    <x-responsive-nav-link :href="route('profile.edit')">
                        Perfil
                    </x-responsive-nav-link>
                @endif

                @if($isAdmin)
                    <x-responsive-nav-link :href="route('users.index')">
                        Usuarios
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.actualizar-reportes')">
                        Actualizar Reportes
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.importar-inventario')">
                        Importar Inventario
                    </x-responsive-nav-link>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link
                        :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
