<div class="max-w-5xl mx-auto py-10 px-4 space-y-8">

    {{-- Header --}}
    <div class="text-center space-y-2">
        <h1 class="text-3xl font-bold text-[var(--c-text)]">Elegí tu plan</h1>
        <p class="text-[var(--c-muted)] text-sm">Recibí alertas de descuentos en tus perfumes favoritos</p>
    </div>

    {{-- Mensaje flash --}}
    @if($mensaje)
    <div class="rounded-xl px-5 py-3 text-sm font-medium
        {{ $tipoMensaje === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : ($tipoMensaje === 'info' ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800' : 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800') }}">
        {{ $mensaje }}
    </div>
    @endif

    {{-- Plan activo actual --}}
    @if($suscripcionActiva)
    <div class="rounded-xl border border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950/20 px-5 py-4 flex items-center gap-3">
        <span class="text-violet-500 text-xl">✓</span>
        <div>
            <p class="text-sm font-semibold text-[var(--c-text)]">Plan activo: <span class="text-violet-600 dark:text-violet-400">{{ $suscripcionActiva->plan->nombre }}</span></p>
            @if($suscripcionActiva->vence_at)
            <p class="text-xs text-[var(--c-muted)]">Vence el {{ $suscripcionActiva->vence_at->format('d/m/Y') }}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- Cards de planes --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($planes as $plan)
        @php
            $esActivo    = $suscripcionActiva?->plan_id === $plan->id;
            $esPremium   = $plan->slug === 'premium';
            $esPro       = $plan->slug === 'pro';
        @endphp
        <div class="relative rounded-2xl border flex flex-col
            {{ $esPremium ? 'border-violet-400 dark:border-violet-600 shadow-lg shadow-violet-500/10' : 'border-[var(--c-border)]' }}
            bg-[var(--c-card)] overflow-hidden">

            {{-- Badge popular --}}
            @if($esPro)
            <div class="absolute top-4 right-4">
                <span class="text-[10px] font-bold uppercase tracking-wider bg-violet-600 text-white rounded-full px-2.5 py-1">Popular</span>
            </div>
            @endif

            <div class="p-6 flex flex-col flex-1">
                {{-- Nombre y precio --}}
                <div class="mb-5">
                    <p class="text-xs font-bold uppercase tracking-widest text-[var(--c-muted)] mb-1">{{ $plan->nombre }}</p>
                    <p class="text-3xl font-bold text-[var(--c-text)]">
                        {{ $plan->esGratis() ? 'Gratis' : '$' . number_format($plan->precio, 0, ',', '.') }}
                        @if(!$plan->esGratis())
                        <span class="text-sm font-normal text-[var(--c-muted)]">/mes</span>
                        @endif
                    </p>
                </div>

                {{-- Features --}}
                <ul class="space-y-2 mb-6 flex-1">
                    @foreach($plan->features ?? [] as $feature)
                    <li class="flex items-center gap-2 text-sm text-[var(--c-text)]">
                        <span class="text-emerald-500 shrink-0">✓</span>
                        {{ $feature }}
                    </li>
                    @endforeach
                </ul>

                {{-- Botón --}}
                @if($esActivo)
                <button disabled
                    class="w-full py-2.5 rounded-xl text-sm font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 cursor-default">
                    Plan actual ✓
                </button>
                @elseif(!auth()->check())
                <a href="{{ route('register') }}"
                   class="block text-center w-full py-2.5 rounded-xl text-sm font-semibold transition-colors
                       {{ $esPremium || $esPro ? 'bg-violet-600 hover:bg-violet-700 text-white' : 'border border-[var(--c-border)] hover:bg-[var(--c-bg)] text-[var(--c-text)]' }}">
                    Registrarse
                </a>
                @else
                <button wire:click="iniciarPago({{ $plan->id }})"
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold transition-colors
                            {{ $esPremium || $esPro ? 'bg-violet-600 hover:bg-violet-700 text-white' : 'border border-[var(--c-border)] hover:bg-[var(--c-bg)] text-[var(--c-text)]' }}">
                    <span wire:loading.remove wire:target="iniciarPago({{ $plan->id }})">
                        {{ $plan->esGratis() ? 'Activar gratis' : 'Suscribirme' }}
                    </span>
                    <span wire:loading wire:target="iniciarPago({{ $plan->id }})">
                        Redirigiendo…
                    </span>
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Nota --}}
    <p class="text-center text-xs text-[var(--c-muted)]">
        Los pagos son procesados de forma segura por MercadoPago. Podés cancelar en cualquier momento.
    </p>

</div>
