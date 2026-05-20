<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    @php
        $hasProfile         = \Illuminate\Support\Facades\Route::has('profile.edit');
        $enInventario       = request()->routeIs('inventario.*');
        $authUser           = auth()->user();
        $isAdmin            = $authUser?->isAdmin();
        $soloExplore        = $authUser?->solo_explore;
        $esOperadorCamiones = $authUser?->isOperadorCamiones();
        $isMultiobra        = (int)($authUser?->is_multiobra ?? 0) === 1;
        $obraActualNav      = ($authUser?->obra_actual_id)
                                ? \App\Models\Obra::find($authUser->obra_actual_id)
                                : null;
        $obrasNav           = ($isMultiobra && $enInventario)
                                ? \App\Models\Obra::orderBy('nombre')->get(['id','nombre'])
                                : collect();
    @endphp
    @if($isMultiobra && $enInventario)
    <script>
        window._navObras       = @json($obrasNav->values());
        window._navObraActual  = { id: {{ $obraActualNav?->id ?? 'null' }}, nombre: @json($obraActualNav?->nombre ?? 'Sin obra') };
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
                        {{-- Operador camiones: solo ve su módulo --}}
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

            <!-- Selector de obra (desktop, solo inventario + multiobra) -->
            @if($isMultiobra && $enInventario)
            <div class="hidden sm:flex sm:items-center sm:ml-4">
                <div class="relative"
                     x-data="{
                         open: false,
                         search: '',
                         selectedId:   window._navObraActual?.id,
                         selectedName: window._navObraActual?.nombre || 'Sin obra',
                         obras:        window._navObras || [],
                         get filtered() {
                             if (!this.search) return this.obras;
                             const q = this.search.toLowerCase();
                             return this.obras.filter(o => o.nombre.toLowerCase().includes(q));
                         },
                         seleccionar(obra) {
                             this.selectedId   = obra.id;
                             this.selectedName = obra.nombre;
                             this.open   = false;
                             this.search = '';
                             this.$refs.hiddenInput.value = obra.id;
                             this.$refs.navObraForm.submit();
                         }
                     }"
                     @click.outside="open = false; search = ''"
                     @keydown.escape.window="open = false; search = ''">

                    <form x-ref="navObraForm" method="POST" action="{{ route('inventario.cambiarObra') }}">
                        @csrf
                        <input type="hidden" name="obra_id" x-ref="hiddenInput" :value="selectedId">
                    </form>

                    {{-- Botón principal --}}
                    <button type="button"
                            @click="open = !open"
                            class="group flex items-center gap-2.5 pl-2.5 pr-3 py-1.5 rounded-xl bg-white border border-gray-200 shadow-sm hover:border-indigo-300 hover:shadow transition-all duration-150">
                        <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-100 group-hover:bg-indigo-200 transition-colors shrink-0">
                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[9px] font-bold text-indigo-400 uppercase tracking-widest leading-none mb-0.5">Obra</span>
                            <span class="block text-gray-800 font-semibold text-sm leading-tight truncate max-w-[160px]" x-text="selectedName"></span>
                        </span>
                        <span class="w-px h-5 bg-gray-200 shrink-0"></span>
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    {{-- Dropdown --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute end-0 mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden"
                         style="min-width:260px; display:none;">

                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Cambiar obra</span>
                        </div>

                        @if($obrasNav->count() > 5)
                        <div class="px-3 py-2 border-b border-gray-100">
                            <input x-model="search"
                                   x-ref="navSearchInput"
                                   x-init="$watch('open', v => v && $nextTick(() => $refs.navSearchInput?.focus()))"
                                   type="text"
                                   placeholder="Buscar obra..."
                                   class="w-full px-3 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:bg-white transition-colors">
                        </div>
                        @endif

                        <div class="max-h-60 overflow-y-auto py-1">
                            <template x-for="obra in filtered" :key="obra.id">
                                <button type="button"
                                        @click="seleccionar(obra)"
                                        class="w-full text-left px-3 py-2.5 text-sm flex items-center gap-3 transition-colors"
                                        :class="obra.id === selectedId ? 'bg-indigo-50 text-indigo-800' : 'text-gray-700 hover:bg-gray-50'">
                                    <span class="shrink-0 w-4 h-4 flex items-center justify-center">
                                        <svg x-show="obra.id === selectedId" class="w-4 h-4 text-indigo-600"
                                             fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </span>
                                    <span x-text="obra.nombre" class="truncate"
                                          :class="obra.id === selectedId ? 'font-semibold' : ''"></span>
                                </button>
                            </template>
                            <div x-show="filtered.length === 0" class="px-4 py-4 text-sm text-gray-400 text-center">
                                Sin resultados
                            </div>
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

                        {{-- ✅ SOLO ADMIN: Usuarios + Actualizar Reportes --}}
                        @if($isAdmin)
                            <x-dropdown-link :href="route('users.index')">
                                Usuarios
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('admin.actualizar-reportes')">
                                Actualizar Reportes
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

        {{-- Obra activa (móvil, solo inventario + multiobra) --}}
        @if($isMultiobra && $enInventario)
        <div class="px-4 py-2 border-b border-gray-100">
            <div class="text-xs font-semibold text-indigo-400 uppercase tracking-wide mb-1.5">Obra activa</div>
            <form method="POST" action="{{ route('inventario.cambiarObra') }}">
                @csrf
                <select name="obra_id"
                        onchange="this.form.submit()"
                        class="w-full border border-indigo-200 rounded-lg px-3 py-2 text-sm bg-indigo-50 text-gray-800 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-400">
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
                {{-- Operador camiones: solo ve su módulo --}}
                <x-responsive-nav-link
                    :href="route('control-camiones.index')"
                    :active="request()->routeIs('control-camiones.*')">
                    Control Salida Camiones
                </x-responsive-nav-link>
            @else
                @if(!$soloExplore)
                {{-- Acciones rápidas (móvil, solo en inventario) --}}
                @if($enInventario)
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

                {{-- ✅ SOLO ADMIN (móvil): Usuarios + Actualizar Reportes --}}
                @if($isAdmin)
                    <x-responsive-nav-link :href="route('users.index')">
                        Usuarios
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.actualizar-reportes')">
                        Actualizar Reportes
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
