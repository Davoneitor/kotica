<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    @php
        $hasProfile = \Illuminate\Support\Facades\Route::has('profile.edit');
        $enInventario = request()->routeIs('inventario.*');

        // ✅ SOLO ESTE USUARIO VE "CREAR USUARIOS"
        $isAdmin = auth()->check() && auth()->user()->email === 'admin@kotica.com';
    @endphp

    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- Logo + Links -->
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('explore.index') }}" class="flex items-center gap-2">
                        <img
                            src="{{ asset('images/logo-kotica.png') }}"
                            alt="Kotica"
                            class="h-10 w-auto"
                        >
                    </a>
                </div>

                <!-- Navigation Links (DESKTOP) -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                    <x-nav-link
                        :href="route('inventario.index')"
                        :active="request()->routeIs('inventario.*')">
                        Inventario
                    </x-nav-link>

                    <x-nav-link
                        :href="route('ordenes-compra.index')"
                        :active="request()->routeIs('ordenes-compra.*')">
                        Órdenes de compra
                    </x-nav-link>

                    <x-nav-link
                        :href="route('retornables.index')"
                        :active="request()->routeIs('retornables.*')">
                        Retornables
                    </x-nav-link>

                    <x-nav-link
                        :href="route('explore.index')"
                        :active="request()->routeIs('explore.*')">
                        Explore
                    </x-nav-link>
                </div>
            </div>

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

                        {{-- ✅ SOLO ADMIN: Crear usuarios --}}
                        @if($isAdmin)
                            <x-dropdown-link :href="route('register')">
                                Crear usuarios
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
        <div class="pt-2 pb-3 space-y-1">

            {{-- ✅ Acciones SOLO aquí (móvil) --}}
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
                :href="route('ordenes-compra.index')"
                :active="request()->routeIs('ordenes-compra.*')">
                Órdenes de compra
            </x-responsive-nav-link>

            <x-responsive-nav-link
                :href="route('retornables.index')"
                :active="request()->routeIs('retornables.*')">
                Retornables
            </x-responsive-nav-link>

            <x-responsive-nav-link
                :href="route('explore.index')"
                :active="request()->routeIs('explore.*')">
                Explore
            </x-responsive-nav-link>
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

                {{-- ✅ SOLO ADMIN (móvil): Crear usuarios --}}
                @if($isAdmin)
                    <x-responsive-nav-link :href="route('register')">
                        Crear usuarios
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
