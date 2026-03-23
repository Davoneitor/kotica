<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Control Salida Camiones
                </h2>
                <div class="text-sm text-gray-500 mt-0.5">
                    {{ $obraActual?->nombre ?? 'Sin obra asignada' }}
                </div>
            </div>
        </div>
    </x-slot>

    {{-- ══ TODO el componente, incluido el modal, dentro del mismo x-data ══ --}}
    <div class="py-4 px-4 max-w-xl mx-auto"
         x-data="escombro()"
         x-init="init()">

        {{-- ══ BANNER TOTAL DEL DÍA ══ --}}
        <div class="bg-gray-900 text-white rounded-2xl p-4 mb-5 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide opacity-70">Fecha</div>
                <div class="text-lg font-bold" x-text="fechaDisplay"></div>
            </div>
            <div class="text-right">
                <div class="text-xs uppercase tracking-wide opacity-70">Total del día</div>
                <div class="text-3xl font-extrabold" x-text="totalDia + ' m³'"></div>
            </div>
        </div>

        {{-- ══ MENSAJES ══ --}}
        <div x-show="exito" x-cloak
             class="mb-4 bg-green-50 border border-green-300 text-green-800 rounded-xl px-4 py-3 text-sm font-medium">
            Registro guardado correctamente.
        </div>
        <div x-show="error" x-cloak
             class="mb-4 bg-red-50 border border-red-300 text-red-800 rounded-xl px-4 py-3 text-sm"
             x-text="error">
        </div>

        {{-- ══ FORMULARIO ══ --}}
        <form x-ref="formulario" @submit.prevent="guardar()" enctype="multipart/form-data"
              class="space-y-4">
            @csrf
            <input type="hidden" name="fecha" :value="fecha">

            {{-- ══ HORAS ══ --}}
            <div class="grid grid-cols-2 gap-3">
                {{-- Hora entrada --}}
                <button type="button"
                        @click="marcarHoraEntrada()"
                        :disabled="horaEntradaBloqueada"
                        :class="horaEntradaBloqueada
                            ? 'bg-green-600 text-white cursor-not-allowed'
                            : 'bg-white border-2 border-gray-900 text-gray-900 active:bg-gray-100'"
                        class="rounded-2xl p-4 text-center transition-all">
                    <div class="text-2xl font-extrabold tracking-tight"
                         x-text="horaEntradaBloqueada ? formatHora(hora_entrada) : horaActual"></div>
                    <div class="text-xs mt-1 font-semibold uppercase tracking-wide opacity-80">
                        <span x-text="horaEntradaBloqueada ? '✔ Entrada registrada' : 'Hora de entrada'"></span>
                    </div>
                </button>

                {{-- Hora salida —habilitado solo si ya hay entrada --}}
                <button type="button"
                        @click="marcarHoraSalida()"
                        :disabled="!horaEntradaBloqueada || horaSalidaBloqueada"
                        :class="horaSalidaBloqueada
                            ? 'bg-red-600 text-white cursor-not-allowed'
                            : (!horaEntradaBloqueada
                                ? 'bg-gray-100 border-2 border-gray-200 text-gray-300 cursor-not-allowed'
                                : 'bg-white border-2 border-gray-900 text-gray-900 active:bg-gray-100')"
                        class="rounded-2xl p-4 text-center transition-all">
                    <div class="text-2xl font-extrabold tracking-tight"
                         x-text="horaSalidaBloqueada ? formatHora(hora_salida) : (!horaEntradaBloqueada ? '——' : horaActual)"></div>
                    <div class="text-xs mt-1 font-semibold uppercase tracking-wide opacity-80">
                        <span x-text="horaSalidaBloqueada ? '✔ Salida registrada' : (!horaEntradaBloqueada ? 'Primero: entrada' : 'Hora de salida')"></span>
                    </div>
                </button>
            </div>

            {{-- ══ TIPO DE MATERIAL ══ --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Tipo de material <span class="text-red-500">*</span>
                </label>
                <select name="tipo_material"
                        x-model="tipo_material"
                        class="w-full border-2 border-gray-200 rounded-xl px-4 py-3.5 text-base bg-white focus:border-gray-900 focus:outline-none">
                    <option value="">— Selecciona —</option>
                    <option value="Escombro">Escombro</option>
                    <option value="Material producto de excavación finos">Material producto de excavación finos</option>
                    <option value="Material producto de excavación piedra">Material producto de excavación piedra</option>
                </select>
            </div>

            {{-- ══ PLACAS ══ --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Placas <span class="text-red-500">*</span>
                    <span class="text-xs text-gray-400 font-normal ml-1">(editable)</span>
                </label>
                <input type="text"
                       name="placas"
                       x-model="placas"
                       autocomplete="off"
                       placeholder="Ej: ABC-123"
                       class="w-full border-2 border-gray-200 rounded-xl px-4 py-3.5 text-base focus:border-gray-900 focus:outline-none">
            </div>

            {{-- ══ METROS CÚBICOS ══ --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Metros cúbicos <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       name="metros_cubicos"
                       x-model="metros_cubicos"
                       step="0.5"
                       min="0"
                       inputmode="decimal"
                       placeholder=""
                       class="w-full border-2 border-gray-200 rounded-xl px-4 py-4 text-2xl font-bold focus:border-gray-900 focus:outline-none text-center">
            </div>

            {{-- ══ FOLIO RECIBO ══ --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Folio del recibo <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="folio_recibo"
                       x-model="folio_recibo"
                       placeholder="Ej: REC-48392"
                       class="w-full border-2 border-gray-200 rounded-xl px-4 py-3.5 text-base focus:border-gray-900 focus:outline-none">
            </div>

            {{-- ══ FOTOS ══ --}}
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-sm font-semibold text-gray-700 mb-1">
                        Foto del vale <span class="text-red-500">*</span>
                    </span>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center cursor-pointer hover:border-gray-500 transition-colors"
                         :class="fotoValeNombre ? 'border-green-400 bg-green-50' : ''">
                        <div x-show="!fotoValeNombre" class="text-gray-400">
                            <div class="text-2xl">📷</div>
                            <div class="text-xs mt-1">Tomar / subir</div>
                        </div>
                        <div x-show="fotoValeNombre" class="text-green-700 text-xs font-medium break-all" x-text="fotoValeNombre"></div>
                        <input type="file" name="foto_vale" x-ref="inputFotoVale"
                               accept="image/*" capture="environment" class="hidden"
                               @change="fotoValeNombre = $event.target.files[0]?.name ?? ''">
                    </div>
                </label>

                <label class="block">
                    <span class="block text-sm font-semibold text-gray-700 mb-1">
                        Foto del camión <span class="text-red-500">*</span>
                    </span>
                    <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center cursor-pointer hover:border-gray-500 transition-colors"
                         :class="fotoCamionNombre ? 'border-green-400 bg-green-50' : ''">
                        <div x-show="!fotoCamionNombre" class="text-gray-400">
                            <div class="text-2xl">🚛</div>
                            <div class="text-xs mt-1">Tomar / subir</div>
                        </div>
                        <div x-show="fotoCamionNombre" class="text-green-700 text-xs font-medium break-all" x-text="fotoCamionNombre"></div>
                        <input type="file" name="foto_camion" x-ref="inputFotoCamion"
                               accept="image/*" capture="environment" class="hidden"
                               @change="fotoCamionNombre = $event.target.files[0]?.name ?? ''">
                    </div>
                </label>
            </div>

            {{-- ══ BOTÓN GUARDAR ══ --}}
            <button type="submit"
                    :disabled="guardando"
                    class="w-full bg-gray-900 text-white rounded-2xl py-5 text-xl font-bold
                           active:bg-gray-700 disabled:opacity-50 transition-all mt-2">
                <span x-show="!guardando">Guardar registro</span>
                <span x-show="guardando" x-cloak>Guardando...</span>
            </button>
        </form>

        {{-- ══ REGISTROS DEL DÍA ══ --}}
        <div class="mt-8" x-show="registrosHoy.length > 0" x-cloak>
            <div class="flex items-center justify-between mb-3">
                <div class="font-semibold text-gray-800">Registros de hoy</div>
                <div class="text-sm text-gray-500" x-text="registrosHoy.length + ' viaje(s)'"></div>
            </div>
            <div class="space-y-2">
                <template x-for="r in registrosHoy" :key="r.id">
                    <div class="bg-white border rounded-xl p-3 flex items-center justify-between text-sm">
                        <div>
                            <div class="font-semibold" x-text="r.tipo_material || '—'"></div>
                            <div class="text-gray-500 text-xs">
                                <span x-text="r.hora_entrada ? formatHora(r.hora_entrada) : ''"></span>
                                <span x-show="r.hora_salida"> – <span x-text="formatHora(r.hora_salida)"></span></span>
                                <span x-show="r.placas"> · <span x-text="r.placas"></span></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-extrabold text-gray-900" x-text="r.metros_cubicos + ' m³'"></div>
                            <div class="text-xs text-gray-400" x-text="r.folio_recibo || ''"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>{{-- fin x-data="escombro()" --}}

    <script>
    function escombro() {
        const hoy = new Date();
        const fechaHoy = hoy.getFullYear() + '-'
            + String(hoy.getMonth() + 1).padStart(2, '0') + '-'
            + String(hoy.getDate()).padStart(2, '0');

        return {
            fecha: fechaHoy,
            fechaDisplay: '',

            horaActual: '',
            hora_entrada: null,
            hora_salida: null,
            horaEntradaBloqueada: false,
            horaSalidaBloqueada: false,

            tipo_material: '',
            placas: '',
            metros_cubicos: '',
            folio_recibo: '',
            fotoValeNombre: '',
            fotoCamionNombre: '',

            totalDia: 0,
            registrosHoy: [],

            guardando: false,
            exito: false,
            error: '',

            init() {
                this.setFechaDisplay();
                this.setHoraActual();
                setInterval(() => this.setHoraActual(), 30000);
                this.actualizarTotal();
                this.cargarRegistrosHoy();
            },

            setFechaDisplay() {
                const [y, m, d] = this.fecha.split('-');
                this.fechaDisplay = `${d}/${m}/${y}`;
            },

            setHoraActual() {
                const now = new Date();
                let h = now.getHours();
                const min = String(now.getMinutes()).padStart(2, '0');
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                this.horaActual = `${String(h).padStart(2, '0')}:${min} ${ampm}`;
            },

            formatHora(h24) {
                if (!h24) return '--:--';
                const parts = h24.split(':');
                let h = parseInt(parts[0]);
                const min = parts[1] || '00';
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${String(h).padStart(2, '0')}:${min} ${ampm}`;
            },

            marcarHoraEntrada() {
                if (this.horaEntradaBloqueada) return;
                const now = new Date();
                this.hora_entrada = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
                this.horaEntradaBloqueada = true;
            },

            marcarHoraSalida() {
                if (!this.horaEntradaBloqueada || this.horaSalidaBloqueada) return;
                const now = new Date();
                this.hora_salida = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
                this.horaSalidaBloqueada = true;
            },

            async actualizarTotal() {
                try {
                    const res = await fetch('/control-camiones/total-dia?fecha=' + this.fecha, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    this.totalDia = data.total || 0;
                } catch {}
            },

            async cargarRegistrosHoy() {
                try {
                    const res = await fetch(
                        '/control-camiones/explore?desde=' + this.fecha + '&hasta=' + this.fecha,
                        { headers: { 'Accept': 'application/json' } }
                    );
                    this.registrosHoy = await res.json();
                } catch {}
            },

            mostrarError(msg) {
                this.error = msg;
                this.$nextTick(() => {
                    document.querySelector('[x-show="error"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            },

            async guardar() {
                this.error = '';

                if (!this.hora_entrada) {
                    this.mostrarError('Registra la hora de entrada antes de guardar.'); return;
                }
                if (!this.hora_salida) {
                    this.mostrarError('Registra la hora de salida antes de guardar.'); return;
                }
                if (!this.tipo_material) {
                    this.mostrarError('Selecciona el tipo de material.'); return;
                }
                if (!this.placas.trim()) {
                    this.mostrarError('El campo Placas es obligatorio.'); return;
                }
                if (!this.metros_cubicos || parseFloat(this.metros_cubicos) <= 0) {
                    this.mostrarError('Ingresa los metros cúbicos (mayor a 0).'); return;
                }
                if (!this.folio_recibo.trim()) {
                    this.mostrarError('El campo Folio del recibo es obligatorio.'); return;
                }
                const fotoVale   = this.$refs.inputFotoVale?.files[0];
                const fotoCamion = this.$refs.inputFotoCamion?.files[0];
                if (!fotoVale) {
                    this.mostrarError('La foto del vale es obligatoria.'); return;
                }
                if (!fotoCamion) {
                    this.mostrarError('La foto del camión es obligatoria.'); return;
                }

                this.guardando = true;
                this.exito = false;

                try {
                    const fd = new FormData(this.$refs.formulario);
                    fd.set('hora_entrada',    this.hora_entrada);
                    fd.set('hora_salida',     this.hora_salida);
                    fd.set('tipo_material',   this.tipo_material);
                    fd.set('placas',          this.placas);
                    fd.set('metros_cubicos',  this.metros_cubicos);
                    fd.set('folio_recibo',    this.folio_recibo);

                    const res = await fetchConCsrf('/control-camiones', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: fd,
                    });

                    let data;
                    try {
                        data = await res.json();
                    } catch {
                        throw new Error('El servidor devolvió una respuesta inesperada. Revisa tu conexión.');
                    }

                    if (res.ok && data.ok) {
                        this.exito    = true;
                        this.totalDia = data.total_dia;

                        // Reset formulario
                        this.hora_entrada        = null;
                        this.hora_salida         = null;
                        this.horaEntradaBloqueada = false;
                        this.horaSalidaBloqueada  = false;
                        this.tipo_material       = '';
                        this.placas              = '';
                        this.metros_cubicos      = '';
                        this.folio_recibo        = '';
                        this.fotoValeNombre      = '';
                        this.fotoCamionNombre    = '';
                        this.$refs.formulario.reset();

                        setTimeout(() => this.exito = false, 3000);
                        await this.cargarRegistrosHoy();
                    } else {
                        // Mostrar errores de validación campo por campo si los hay
                        if (data.errors) {
                            const msgs = Object.values(data.errors).flat();
                            this.error = msgs.join(' ');
                        } else {
                            this.error = data.message || 'Error al guardar. Inténtalo de nuevo.';
                        }
                        this.$nextTick(() => {
                            document.querySelector('[x-show="error"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        });
                    }
                } catch (e) {
                    this.error = e.message || 'Error de conexión. Verifica tu red e inténtalo de nuevo.';
                    this.$nextTick(() => {
                        document.querySelector('[x-show="error"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                } finally {
                    this.guardando = false;
                }
            },
        };
    }
    </script>

    <style>[x-cloak]{display:none!important}</style>

</x-app-layout>
