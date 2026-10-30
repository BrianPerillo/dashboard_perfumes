<x-layouts.app activeTab="planes">
<div class="max-w-lg mx-auto py-20 px-4 text-center space-y-5">
    <div class="text-6xl">⏳</div>
    <h1 class="text-2xl font-bold text-[var(--c-text)]">Pago pendiente</h1>
    <p class="text-[var(--c-muted)]">Tu pago está siendo procesado. Te avisaremos cuando se confirme.</p>
    <a href="{{ url('/joyas') }}"
       class="inline-block mt-4 px-6 py-2.5 border border-[var(--c-border)] hover:bg-[var(--c-bg)] text-[var(--c-text)] text-sm font-semibold rounded-xl transition-colors">
        Volver al inicio
    </a>
</div>
</x-layouts.app>
