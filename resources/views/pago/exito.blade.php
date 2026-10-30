<x-layouts.app activeTab="planes">
<div class="max-w-lg mx-auto py-20 px-4 text-center space-y-5">
    <div class="text-6xl">🎉</div>
    <h1 class="text-2xl font-bold text-[var(--c-text)]">¡Pago aprobado!</h1>
    <p class="text-[var(--c-muted)]">
        Tu suscripción al plan
        @if($suscripcion)
            <strong class="text-violet-500">{{ $suscripcion->plan->nombre }}</strong>
        @endif
        está activa.
    </p>
    <a href="{{ url('/joyas') }}"
       class="inline-block mt-4 px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-colors">
        Ir al dashboard
    </a>
</div>
</x-layouts.app>
