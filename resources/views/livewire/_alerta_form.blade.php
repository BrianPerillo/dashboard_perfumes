{{-- Partial: formulario de alerta (crear o editar)
     Variables esperadas: $titulo (string), $esEdicion (bool)
     Usa wire:model.live para tipo/canal — seguro porque solo un form en DOM a la vez (@if en notificaciones.blade.php)
--}}

<div class="px-5 py-4 border-b border-[var(--c-border)]">
    <p class="text-sm font-semibold text-[var(--c-text)]">{{ $titulo }}</p>
</div>

<div class="px-5 py-5 space-y-5">

    {{-- Rubro --}}
    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">Rubro</label>
        <div class="flex gap-2">
            @foreach(config('rubros.rubros') as $slug => $r)
            <label class="flex-1 cursor-pointer">
                <input type="radio" wire:model.live="rubro" value="{{ $slug }}" class="sr-only peer">
                <span class="block text-center rounded-xl border border-[var(--c-border)] px-3 py-2 text-xs font-semibold text-[var(--c-muted)]
                             peer-checked:bg-violet-600 peer-checked:text-white peer-checked:border-violet-600 transition-colors">
                    {{ $r['icono'] }} {{ $r['nombre'] }}
                </span>
            </label>
            @endforeach
        </div>
    </div>

    {{-- Tipo de alerta --}}
    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
            ¿Qué querés monitorear?
        </label>
        <div class="grid grid-cols-3 gap-2">
            @foreach([
                ['producto', '🧴', 'Perfume', 'Un perfume específico por nombre'],
                ['marca',   '🏷️', 'Marca',   'Todos los descuentos de una marca'],
                ['tienda',  '🏪', 'Tienda',  'Todos los descuentos de una tienda'],
            ] as [$val, $icon, $lbl, $desc])
            <label class="relative flex flex-col items-center gap-1.5 rounded-xl border-2 p-3 cursor-pointer transition-all"
                   :class="$wire.tipo === '{{ $val }}'
                       ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                       : 'border-[var(--c-border)] hover:border-violet-300 dark:hover:border-violet-700'">
                <input type="radio"
                       wire:model.live="tipo"
                       value="{{ $val }}"
                       class="sr-only">
                <span class="text-xl">{{ $icon }}</span>
                <span class="text-xs font-semibold text-[var(--c-text)]">{{ $lbl }}</span>
                <span class="text-[10px] text-[var(--c-muted)] text-center leading-tight">{{ $desc }}</span>
            </label>
            @endforeach
        </div>
    </div>

    {{-- Valor --}}
    <div>
        <label for="alerta-valor-{{ $esEdicion ? 'edit' : 'new' }}"
               class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2"
               x-text="$wire.tipo === 'producto' ? ($wire.rubro === 'ropa' ? '¿Qué producto?' : '¿Qué perfume?') : ($wire.tipo === 'marca' ? '¿Qué marca?' : '¿Qué tienda?')">
            @if($tipo === 'producto') {{ $rubro === 'ropa' ? '¿Qué producto?' : '¿Qué perfume?' }}
            @elseif($tipo === 'marca') ¿Qué marca?
            @else ¿Qué tienda?
            @endif
        </label>
        <input type="text"
               id="alerta-valor-{{ $esEdicion ? 'edit' : 'new' }}"
               list="sugerencias-list-{{ $esEdicion ? 'edit' : 'new' }}"
               wire:model.live.debounce.350ms="valor"
               :placeholder="$wire.tipo === 'producto' ? ($wire.rubro === 'ropa' ? 'Ej: Air Force, Jordan, Dunk…' : 'Ej: Dylan Blue, Bleu de Chanel…') : ($wire.tipo === 'marca' ? 'Ej: Versace, Chanel…' : 'Ej: Juleriaque, Rouge…')"
               autocomplete="off"
               class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)]
                      text-[var(--c-text)] placeholder:text-[var(--c-muted)]
                      px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
        <datalist id="sugerencias-list-{{ $esEdicion ? 'edit' : 'new' }}">
            @foreach($sugerencias as $s)
            <option value="{{ $s }}">
            @endforeach
        </datalist>
        <p x-show="$wire.tipo === 'producto'" class="mt-1.5 text-[11px] text-[var(--c-muted)]">
            Escribí al menos 3 letras para ver sugerencias.
        </p>
        @error('valor')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Tamaño, Marca filtro y Solo mejoras (solo para tipo producto) --}}
    <div x-show="$wire.tipo === 'producto'">

        {{-- Tamaño (ml: solo perfumes) --}}
        <div class="mb-5" x-show="$wire.rubro !== 'ropa'">
            <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                Tamaño <span class="normal-case font-normal">(opcional)</span>
            </label>
            @if(count($tamanosDisponibles) > 0)
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('tamanoMl', null)"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer
                               {{ is_null($tamanoMl)
                                  ? 'bg-violet-600 text-white'
                                  : 'border border-[var(--c-border)] text-[var(--c-muted)] hover:border-violet-400' }}">
                    Todos los tamaños
                </button>
                @foreach($tamanosDisponibles as $ml)
                <button type="button" wire:click="$set('tamanoMl', {{ $ml }})"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer
                               {{ $tamanoMl === $ml
                                  ? 'bg-violet-600 text-white'
                                  : 'border border-[var(--c-border)] text-[var(--c-muted)] hover:border-violet-400' }}">
                    {{ $ml }}ml
                </button>
                @endforeach
            </div>
            @else
            <p class="text-[11px] text-[var(--c-muted)]">
                {{ mb_strlen($valor) >= 3 ? 'No se encontraron tamaños registrados para este producto.' : 'Ingresá el nombre del perfume para ver los tamaños disponibles.' }}
            </p>
            @endif
        </div>

        {{-- Marca --}}
        <div class="mb-5">
            <label for="marca-filtro-{{ $esEdicion ? 'edit' : 'new' }}"
                   class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                Marca <span class="normal-case font-normal">(opcional — dejá vacío para todas)</span>
            </label>
            <input type="text"
                   id="marca-filtro-{{ $esEdicion ? 'edit' : 'new' }}"
                   list="marcas-list-{{ $esEdicion ? 'edit' : 'new' }}"
                   wire:model.live.debounce.300ms="marcaFiltro"
                   placeholder="Ej: Versace, Chanel… o dejá vacío para todas"
                   autocomplete="off"
                   class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)]
                          text-[var(--c-text)] placeholder:text-[var(--c-muted)]
                          px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
            <datalist id="marcas-list-{{ $esEdicion ? 'edit' : 'new' }}">
                @foreach($marcasSugerencias as $m)
                <option value="{{ $m }}">
                @endforeach
            </datalist>
        </div>

        {{-- Solo mejoras --}}
        <div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox"
                       wire:model.live="soloMejoras"
                       class="mt-0.5 w-4 h-4 rounded accent-violet-600 cursor-pointer">
                <div>
                    <p class="text-sm font-medium text-[var(--c-text)]">Solo notificarme si el precio mejora</p>
                    <p class="text-[11px] text-[var(--c-muted)] mt-0.5 leading-relaxed">
                        Solo recibirás alerta si el precio baja respecto al último ya notificado.
                    </p>
                </div>
            </label>
        </div>

    </div>{{-- /tipo producto --}}

    {{-- Talles (solo ropa) y descuento mínimo --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div x-show="$wire.rubro === 'ropa'">
            <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                Talles <span class="normal-case font-normal">(opcional)</span>
            </label>
            <input type="text" wire:model="talles" placeholder="Ej: M, XL, H10, M7"
                   class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)] px-3 py-2 text-sm text-[var(--c-text)]">
            <p class="mt-1 text-[11px] text-[var(--c-muted)]">Solo avisamos productos con alguno de esos talles disponibles. En calzado: <strong>H10</strong> = talle de hombre, <strong>M7</strong> = talle de mujer; un número solo (10) vale para ambos.</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
                Descuento mínimo % <span class="normal-case font-normal">(opcional)</span>
            </label>
            <input type="number" min="5" max="95" wire:model="descuentoMin" placeholder="Ej: 40"
                   class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)] px-3 py-2 text-sm text-[var(--c-text)]">
            @error('descuentoMin')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
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
            <label class="relative flex items-start gap-3 rounded-xl border-2 p-3.5 cursor-pointer transition-all"
                   :class="$wire.canal === '{{ $val }}'
                       ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/30'
                       : 'border-[var(--c-border)] hover:border-violet-300 dark:hover:border-violet-700'">
                <input type="radio"
                       wire:model.live="canal"
                       value="{{ $val }}"
                       class="mt-0.5 accent-violet-600">
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
    <div x-show="$wire.canal === 'email'">
        <label for="email-destino-{{ $esEdicion ? 'edit' : 'new' }}"
               class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
            Email donde recibir las alertas
        </label>
        <input type="email"
               id="email-destino-{{ $esEdicion ? 'edit' : 'new' }}"
               wire:model="emailDestino"
               placeholder="tu@email.com"
               class="w-full rounded-xl border border-[var(--c-border)] bg-[var(--c-input)]
                      text-[var(--c-text)] placeholder:text-[var(--c-muted)]
                      px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
        @error('emailDestino')
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
        @enderror
    </div>

    {{-- Telegram chat ID --}}
    <div x-show="$wire.canal === 'telegram'">
        <label for="tg-chat-id-{{ $esEdicion ? 'edit' : 'new' }}"
               class="block text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-2">
            Tu Chat ID de Telegram
        </label>
        <input type="text"
               id="tg-chat-id-{{ $esEdicion ? 'edit' : 'new' }}"
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

</div>

{{-- Acciones --}}
<div class="px-5 py-4 border-t border-[var(--c-border)] flex items-center gap-3">
    <button wire:click="guardar" wire:loading.attr="disabled"
            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold
                   bg-violet-600 hover:bg-violet-700 text-white transition-colors
                   disabled:opacity-60 cursor-pointer">
        <svg wire:loading wire:target="guardar" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <span wire:loading.remove wire:target="guardar">
            {{ $esEdicion ? 'Guardar cambios' : 'Activar alerta 🔔' }}
        </span>
        <span wire:loading wire:target="guardar">Guardando…</span>
    </button>

    <button wire:click="cancelarEdicion"
            class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-[var(--c-border)]
                   text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors cursor-pointer">
        Cancelar
    </button>
</div>

