<div class="max-w-2xl mx-auto">

    {{-- ── Toast notifications ── --}}
    <div x-data="{
            toasts: [],
            add(tipo, msg) {
                const id = Date.now();
                this.toasts.push({ id, tipo, msg, visible: false });
                this.$nextTick(() => {
                    const t = this.toasts.find(t => t.id === id);
                    if (t) t.visible = true;
                });
                setTimeout(() => this.remove(id), 5000);
            },
            remove(id) {
                const t = this.toasts.find(t => t.id === id);
                if (t) {
                    t.visible = false;
                    setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 300);
                }
            }
         }"
         @toast.window="add($event.detail.tipo, $event.detail.mensaje)"
         class="fixed top-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"
         style="max-width: 360px;">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="toast.visible"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-8"
                 class="pointer-events-auto flex items-start gap-3 rounded-xl shadow-xl px-4 py-3 text-sm font-medium text-white"
                 :class="{
                     'bg-emerald-600': toast.tipo === 'success',
                     'bg-blue-600':    toast.tipo === 'info',
                     'bg-red-600':     toast.tipo === 'error',
                 }">
                <svg x-show="toast.tipo === 'success'" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg x-show="toast.tipo === 'info'" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="toast.msg" class="flex-1 leading-snug"></span>
                <button @click="remove(toast.id)" class="flex-shrink-0 opacity-70 hover:opacity-100 transition-opacity ml-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </template>
    </div>

    {{-- ── Header ── --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <span class="text-3xl">🔔</span>
            <h1 class="text-2xl font-bold text-[var(--c-text)]">Alertas de Descuentos</h1>
        </div>
        <p class="text-sm text-[var(--c-muted)] leading-relaxed">
            Configurá alertas y te avisamos por email o Telegram cuando aparezca un descuento
            en la marca, tienda o perfume que estás buscando.
        </p>
    </div>

    {{-- ── Plan card ── --}}
    <div class="rounded-2xl border border-violet-200 dark:border-violet-800/50
                bg-gradient-to-br from-violet-50 to-purple-50
                dark:from-violet-950/30 dark:to-purple-950/20
                p-5 mb-6 relative overflow-hidden">
        <div class="absolute top-3 right-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-violet-500
                         bg-violet-100 dark:bg-violet-900/50 rounded-full px-2.5 py-1">
                GRATIS
            </span>
        </div>
        <p class="font-bold text-base text-[var(--c-text)] mb-3">Plan Básico</p>
        <ul class="space-y-1.5 text-sm text-[var(--c-muted)]">
            @foreach([
                'Alertas ilimitadas',
                'Notificaciones por email o Telegram',
                'Por marca, tienda o perfume específico',
                'Monitoreo en 12 tiendas argentinas',
            ] as $item)
            <li class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full bg-violet-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                {{ $item }}
            </li>
            @endforeach
        </ul>
    </div>

    {{-- ── Guest ── --}}
    @guest
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] p-8 text-center">
        <div class="text-4xl mb-4">🔒</div>
        <h2 class="text-lg font-semibold text-[var(--c-text)] mb-2">Necesitás una cuenta</h2>
        <p class="text-sm text-[var(--c-muted)] mb-6 leading-relaxed">
            Para activar alertas y recibir notificaciones, iniciá sesión o creá tu cuenta gratuita.
        </p>
        <div class="flex gap-3 justify-center">
            <a href="{{ route('register') }}"
               class="px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
                Crear cuenta gratis
            </a>
            <a href="{{ route('login') }}"
               class="px-5 py-2.5 rounded-xl border border-[var(--c-border)] bg-[var(--c-card)]
                      hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5
                      text-sm font-semibold text-[var(--c-text)] transition-colors">
                Iniciar sesión
            </a>
        </div>
    </div>
    @endguest

    {{-- ── Authenticated ── --}}
    @auth
    <div wire:key="auth-content">

    {{-- Error inline --}}
    @if($mensajeError)
    <div class="flex items-start gap-3 rounded-xl border border-red-200 dark:border-red-800/40
                bg-red-50 dark:bg-red-950/20 px-4 py-3 mb-4 text-sm text-red-700 dark:text-red-400">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span>{{ $mensajeError }}</span>
    </div>
    @endif

    {{-- ── Lista de alertas existentes ── --}}
    @if($alertas->isNotEmpty())
    <div class="space-y-3 mb-4">
        @foreach($alertas as $alerta)
        <div wire:key="alert-item-{{ $alerta->id }}">

        @if($editandoId === $alerta->id)
        {{-- Formulario edición: solo en DOM cuando está activo → wire:model.live seguro --}}
        <div wire:key="alert-edit-{{ $alerta->id }}"
             class="rounded-2xl border-2 border-violet-500 bg-[var(--c-card)] overflow-hidden">
            @include('livewire._alerta_form', ['titulo' => 'Editar alerta', 'esEdicion' => true])
        </div>
        @else
        <div wire:key="alert-view-{{ $alerta->id }}"
             x-data="{ open: false }"
             class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden">

            {{-- Fila compacta --}}
            <div class="px-4 py-3 flex items-center gap-3">

                {{-- Dot activa --}}
                <span class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0 mt-0.5"></span>

                {{-- Resumen (clickable para expandir) --}}
                <button @click="open = !open" class="flex-1 min-w-0 text-left">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-[var(--c-text)]">
                            @if($alerta->tipo === 'marca') 🏷️
                            @elseif($alerta->tipo === 'tienda') 🏪
                            @else 🧴
                            @endif
                            {{ $alerta->valor }}
                        </span>
                        @if($alerta->marca_filtro)
                        <span class="text-[11px] font-medium text-violet-400 bg-violet-400/10 rounded-full px-2 py-0.5">
                            {{ $alerta->marca_filtro }}
                        </span>
                        @endif
                        @if($alerta->talles)
                        <span class="text-[11px] font-medium text-emerald-400 bg-emerald-400/10 rounded-full px-2 py-0.5">Talles {{ $alerta->talles }}</span>
                        @endif
                        @if($alerta->descuento_min)
                        <span class="text-[11px] font-medium text-amber-400 bg-amber-400/10 rounded-full px-2 py-0.5">≥ {{ $alerta->descuento_min }}%</span>
                        @endif
                        @if($alerta->tamano_ml)
                        <span class="text-[11px] font-medium text-blue-400 bg-blue-400/10 rounded-full px-2 py-0.5">
                            {{ $alerta->tamano_ml }}ml
                        </span>
                        @endif
                        @if($alerta->solo_mejoras)
                        <span class="text-[11px] font-medium text-emerald-500 bg-emerald-500/10 rounded-full px-2 py-0.5">
                            📉 solo mejoras
                        </span>
                        @endif
                    </div>
                    <p class="text-xs text-[var(--c-muted)] mt-0.5">
                        @if($alerta->canal === 'email')
                        📧 {{ $alerta->email_destino }}
                        @else
                        📱 Telegram · {{ $alerta->telegram_chat_id }}
                        @endif
                    </p>
                </button>

                {{-- Botones de acción --}}
                <div class="flex items-center gap-0.5 flex-shrink-0">
                    {{-- Expandir --}}
                    <button @click="open = !open"
                            class="p-2 rounded-lg hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5
                                   text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors cursor-pointer">
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    {{-- Editar --}}
                    <button @click="$wire.activarEdicion({{ $alerta->id }})"
                            class="p-2 rounded-lg hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5
                                   text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors cursor-pointer"
                            title="Editar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    {{-- Eliminar --}}
                    <button @click="swalConfirmar({
                                titulo: '¿Eliminar alerta?',
                                texto: 'Dejarás de recibir notificaciones para &quot;{{ addslashes($alerta->valor) }}&quot;.',
                                btnConfirmar: 'Sí, eliminar',
                            }, () => $wire.eliminar({{ $alerta->id }}))"
                            class="p-2 rounded-lg hover:bg-red-500/10
                                   text-[var(--c-muted)] hover:text-red-400 transition-colors cursor-pointer"
                            title="Eliminar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Detalle expandido --}}
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="border-t border-[var(--c-border)] px-4 py-4">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-1">Monitoreo</p>
                        <p class="text-sm font-medium text-[var(--c-text)]">{{ $alerta->tipoLabel() }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-1">Buscando</p>
                        <p class="text-sm font-medium text-[var(--c-text)]">{{ $alerta->valor }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-1">Canal</p>
                        <p class="text-sm font-medium text-[var(--c-text)]">
                            @if($alerta->canal === 'email') 📧 {{ $alerta->email_destino }}
                            @else 📱 Telegram · {{ $alerta->telegram_chat_id }}
                            @endif
                        </p>
                    </div>
                    @if(!in_array($alerta->tipo, ['marca', 'tienda']))
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-1">Marca</p>
                        <p class="text-sm font-medium text-[var(--c-text)]">{{ $alerta->marca_filtro ?: 'Todas las marcas' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-1">Tamaño</p>
                        <p class="text-sm font-medium text-[var(--c-text)]">{{ $alerta->tamano_ml ? $alerta->tamano_ml . 'ml' : 'Todos los tamaños' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-1">Modo</p>
                        <p class="text-sm font-medium text-[var(--c-text)]">
                            {{ $alerta->solo_mejoras ? '📉 Solo si mejora el precio' : '🔔 Cualquier novedad' }}
                        </p>
                    </div>
                    @endif
                </div>

                @if($alerta->ultima_notificacion_at)
                <p class="text-xs text-[var(--c-muted)] mb-3">
                    Última notificación enviada: {{ $alerta->ultima_notificacion_at->diffForHumans() }}
                </p>
                @endif

                @if(!$alerta->prueba_enviada)
                <button wire:click="enviarPrueba({{ $alerta->id }})" wire:loading.attr="disabled"
                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold
                               border border-violet-400/50 text-violet-400 hover:bg-violet-400/10
                               transition-colors disabled:opacity-60 cursor-pointer">
                    <svg wire:loading wire:target="enviarPrueba({{ $alerta->id }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span wire:loading.remove wire:target="enviarPrueba({{ $alerta->id }})">Enviar prueba</span>
                    <span wire:loading wire:target="enviarPrueba({{ $alerta->id }})">Enviando…</span>
                </button>
                @endif

            </div>
        </div>{{-- alert-view --}}
        @endif
        </div>{{-- alert-item --}}

        @endforeach
    </div>
    @endif

    {{-- ── Formulario nueva alerta ── --}}
    @if($creandoNueva)
    <div wire:key="alert-new-form"
         class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden {{ $alertas->isNotEmpty() ? 'border-2 border-violet-500' : '' }}">
        @include('livewire._alerta_form', ['titulo' => $alertas->isEmpty() ? 'Configurar alerta' : 'Nueva alerta', 'esEdicion' => false])
    </div>
    @endif
    {{-- Botón agregar nueva --}}
    @if(!$creandoNueva)
    <button wire:click="nuevaAlerta"
            class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-2xl
                   border-2 border-dashed border-[var(--c-border)]
                   hover:border-violet-400 hover:text-violet-400
                   text-sm font-semibold text-[var(--c-muted)] transition-colors cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva alerta
    </button>
    @endif

    {{-- ── Info ── --}}
    <div class="mt-8 rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-3">¿Cómo funciona?</p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
            @foreach([
                ['1️⃣', 'Configurás', 'Elegís qué querés monitorear y cómo querés recibir el aviso.'],
                ['2️⃣', 'Detectamos', 'Nuestro scraper analiza 12 tiendas cada 12-24 horas.'],
                ['3️⃣', 'Te avisamos', 'Si hay descuento, te enviamos una notificación al instante.'],
            ] as [$num, $titulo, $desc])
            <div>
                <div class="text-2xl mb-2">{{ $num }}</div>
                <p class="text-xs font-semibold text-[var(--c-text)] mb-1">{{ $titulo }}</p>
                <p class="text-[11px] text-[var(--c-muted)] leading-relaxed">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>

    </div>{{-- /x-data entangle --}}
    @endauth

</div>
