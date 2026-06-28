<div class="max-w-2xl mx-auto">

    {{-- ── Header ── --}}
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <span class="text-3xl">🔔</span>
            <h1 class="text-2xl font-bold text-[var(--c-text)]">Alertas de Descuentos</h1>
        </div>
        <p class="text-sm text-[var(--c-muted)] leading-relaxed">
            Configurá una alerta y te avisamos por email o Telegram cuando aparezca un descuento
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
            <li class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full bg-violet-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                1 alerta activa
            </li>
            <li class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full bg-violet-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Notificaciones por email o Telegram
            </li>
            <li class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full bg-violet-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Por marca, tienda o perfume específico
            </li>
            <li class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full bg-violet-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </span>
                Monitoreo en 12 tiendas argentinas
            </li>
        </ul>
    </div>

    {{-- ── Guest: must login ── --}}
    @guest
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] p-8 text-center">
        <div class="text-4xl mb-4">🔒</div>
        <h2 class="text-lg font-semibold text-[var(--c-text)] mb-2">Necesitás una cuenta</h2>
        <p class="text-sm text-[var(--c-muted)] mb-6 leading-relaxed">
            Para activar alertas y recibir notificaciones de descuentos,
            iniciá sesión o creá tu cuenta gratuita.
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

    {{-- Mensajes flash --}}
    @if($mensajeExito)
    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 dark:border-emerald-800/40
                bg-emerald-50 dark:bg-emerald-950/20 px-4 py-3 mb-4 text-sm text-emerald-700 dark:text-emerald-400">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span>{{ $mensajeExito }}</span>
    </div>
    @endif

    @if($mensajeError)
    <div class="flex items-start gap-3 rounded-xl border border-red-200 dark:border-red-800/40
                bg-red-50 dark:bg-red-950/20 px-4 py-3 mb-4 text-sm text-red-700 dark:text-red-400">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <span>{{ $mensajeError }}</span>
    </div>
    @endif

    {{-- ── Alerta existente (modo visualización) ── --}}
    @if($alerta && !$editando)
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden mb-4">

        <div class="px-5 py-4 border-b border-[var(--c-border)] flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-sm font-semibold text-[var(--c-text)]">Alerta activa</span>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400
                         bg-emerald-50 dark:bg-emerald-950/30 rounded-full px-2.5 py-1">
                ACTIVA
            </span>
        </div>

        <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                    @if($alerta->canal === 'email')
                    📧 {{ $alerta->email_destino }}
                    @else
                    📱 Telegram · {{ $alerta->telegram_chat_id }}
                    @endif
                </p>
            </div>
        </div>

        @if($alerta->ultima_notificacion_at)
        <div class="px-5 pb-3">
            <p class="text-xs text-[var(--c-muted)]">
                Última notificación enviada: {{ $alerta->ultima_notificacion_at->diffForHumans() }}
            </p>
        </div>
        @endif

        <div class="px-5 py-4 border-t border-[var(--c-border)] flex flex-wrap gap-2">
            <button wire:click="enviarPrueba" wire:loading.attr="disabled"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold
                           bg-violet-600 hover:bg-violet-700 text-white transition-colors
                           disabled:opacity-60">
                <svg wire:loading wire:target="enviarPrueba" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span wire:loading.remove wire:target="enviarPrueba">Enviar prueba</span>
                <span wire:loading wire:target="enviarPrueba">Enviando…</span>
            </button>
            <button wire:click="activarEdicion"
                    class="px-4 py-2 rounded-lg text-sm font-semibold border border-[var(--c-border)]
                           bg-[var(--c-card)] hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5
                           text-[var(--c-text)] transition-colors">
                Editar
            </button>
            <button wire:click="eliminar"
                    wire:confirm="¿Eliminás esta alerta? Dejarás de recibir notificaciones."
                    class="px-4 py-2 rounded-lg text-sm font-semibold text-red-500
                           hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors">
                Eliminar
            </button>
        </div>

    </div>
    @endif

    {{-- ── Formulario de configuración ── --}}
    @if(!$alerta || $editando)
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden">

        <div class="px-5 py-4 border-b border-[var(--c-border)]">
            <p class="text-sm font-semibold text-[var(--c-text)]">
                {{ $alerta ? 'Editar alerta' : 'Configurar nueva alerta' }}
            </p>
        </div>

        <div class="px-5 py-5 space-y-5">

            {{-- Tipo de alerta --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                    ¿Qué querés monitorear?
                </label>
                <div class="grid grid-cols-3 gap-2">
                    @foreach([
                        ['producto', '🧴', 'Perfume', 'Un perfume específico por nombre'],
                        ['marca',    '🏷️', 'Marca',   'Todos los descuentos de una marca'],
                        ['tienda',   '🏪', 'Tienda',  'Todos los descuentos de una tienda'],
                    ] as [$val, $icon, $lbl, $desc])
                    <label class="relative flex flex-col items-center gap-1.5 rounded-xl border-2 p-3 cursor-pointer transition-all
                                  {{ $tipo === $val
                                     ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                                     : 'border-[var(--c-border)] hover:border-violet-300 dark:hover:border-violet-700' }}">
                        <input type="radio" wire:model.live="tipo" value="{{ $val }}" class="sr-only">
                        <span class="text-xl">{{ $icon }}</span>
                        <span class="text-xs font-semibold text-[var(--c-text)]">{{ $lbl }}</span>
                        <span class="text-[10px] text-[var(--c-muted)] text-center leading-tight">{{ $desc }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Valor --}}
            <div>
                <label for="alerta-valor" class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                    @if($tipo === 'producto') ¿Qué perfume?
                    @elseif($tipo === 'marca') ¿Qué marca?
                    @else ¿Qué tienda?
                    @endif
                </label>
                <input type="text"
                       id="alerta-valor"
                       list="sugerencias-list"
                       wire:model.live.debounce.350ms="valor"
                       placeholder="{{ $tipo === 'producto' ? 'Ej: Dylan Blue, Bleu de Chanel…' : ($tipo === 'marca' ? 'Ej: Versace, Chanel, Paco Rabanne…' : 'Ej: Juleriaque, Rouge…') }}"
                       autocomplete="off"
                       class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)]
                              text-[var(--c-text)] placeholder:text-[var(--c-muted)]
                              px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                <datalist id="sugerencias-list">
                    @foreach($sugerencias as $s)
                    <option value="{{ $s }}">
                    @endforeach
                </datalist>
                @if($tipo === 'producto')
                <p class="mt-1.5 text-[11px] text-[var(--c-muted)]">Escribí al menos 3 letras para ver sugerencias.</p>
                @endif
                @error('valor')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Canal --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                    ¿Cómo querés que te avisemos?
                </label>
                <div class="grid grid-cols-2 gap-2">
                    @foreach([
                        ['email',    '📧', 'Email',    'Recibís un email con los descuentos'],
                        ['telegram', '📱', 'Telegram', 'Mensaje en tu chat de Telegram'],
                    ] as [$val, $icon, $lbl, $desc])
                    <label class="relative flex items-start gap-3 rounded-xl border-2 p-3.5 cursor-pointer transition-all
                                  {{ $canal === $val
                                     ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                                     : 'border-[var(--c-border)] hover:border-violet-300 dark:hover:border-violet-700' }}">
                        <input type="radio" wire:model.live="canal" value="{{ $val }}" class="mt-0.5 accent-violet-600">
                        <div>
                            <span class="text-lg">{{ $icon }}</span>
                            <p class="text-xs font-semibold text-[var(--c-text)] mt-0.5">{{ $lbl }}</p>
                            <p class="text-[10px] text-[var(--c-muted)]">{{ $desc }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Email destino --}}
            @if($canal === 'email')
            <div>
                <label for="email-destino" class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                    Email donde recibir las alertas
                </label>
                <input type="email"
                       id="email-destino"
                       wire:model="emailDestino"
                       placeholder="tu@email.com"
                       class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)]
                              text-[var(--c-text)] placeholder:text-[var(--c-muted)]
                              px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                @error('emailDestino')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>
            @endif

            {{-- Telegram chat ID --}}
            @if($canal === 'telegram')
            <div>
                <label for="tg-chat-id" class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                    Tu Chat ID de Telegram
                </label>
                <input type="text"
                       id="tg-chat-id"
                       wire:model="telegramChatId"
                       placeholder="Ej: 123456789"
                       class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)]
                              text-[var(--c-text)] placeholder:text-[var(--c-muted)]
                              px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                @error('telegramChatId')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
                <div class="mt-2 rounded-lg bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-800/30 px-3 py-2.5 text-xs text-blue-700 dark:text-blue-400">
                    <p class="font-semibold mb-1">¿Cómo obtengo mi Chat ID?</p>
                    <ol class="list-decimal list-inside space-y-0.5 text-[11px] leading-relaxed">
                        <li>Abrí Telegram y buscá <strong>@userinfobot</strong></li>
                        <li>Mandá cualquier mensaje</li>
                        <li>El bot te responde con tu ID numérico</li>
                        <li>También necesitás iniciar una conversación con el bot de alertas</li>
                    </ol>
                </div>
            </div>
            @endif

        </div>

        {{-- Actions --}}
        <div class="px-5 py-4 border-t border-[var(--c-border)] flex items-center gap-3">
            <button wire:click="guardar" wire:loading.attr="disabled"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                           bg-violet-600 hover:bg-violet-700 text-white transition-colors
                           disabled:opacity-60">
                <svg wire:loading wire:target="guardar" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span wire:loading.remove wire:target="guardar">Activar alerta 🔔</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>

            @if($alerta && $editando)
            <button wire:click="cancelarEdicion"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-[var(--c-border)]
                           text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors">
                Cancelar
            </button>
            @endif
        </div>

    </div>
    @endif

    {{-- ── Info: cómo funciona ── --}}
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

    @endauth

</div>
