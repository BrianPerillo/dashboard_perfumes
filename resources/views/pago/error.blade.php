<x-layouts.app activeTab="planes">
<div class="max-w-lg mx-auto py-20 px-4 text-center space-y-5">
    <div class="text-6xl">❌</div>
    <h1 class="text-2xl font-bold text-[var(--c-text)]">El pago no se pudo completar</h1>
    <p class="text-[var(--c-muted)]">Podés reintentar o elegir otro método de pago.</p>
    <a href="{{ route('planes') }}"
       class="inline-block mt-4 px-6 py-2.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-colors">
        Ver planes
    </a>
</div>
</x-layouts.app>
