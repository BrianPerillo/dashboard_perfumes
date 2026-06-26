<x-layouts.app :activeTab="$activeTab ?? 'joyas'">

    {{-- Panel KPIs (colapsado por defecto en mobile, abierto en desktop) --}}
    @php
        $primerSnap  = \App\Models\Corrida::orderBy('fecha_hora')->first();
        $ultimoSnap  = \App\Models\Corrida::orderByDesc('fecha_hora')->first();
        $snapDesde   = $primerSnap  ? $primerSnap->fecha_hora->format('d/m/Y H:i')  : null;
        $snapHasta   = $ultimoSnap  ? $ultimoSnap->fecha_hora->format('d/m/Y H:i')  : null;
    @endphp
    <div x-data="{ kpiOpen: false }">

        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 mb-3">
            <button @click="kpiOpen = !kpiOpen"
                    class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider
                           text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors select-none self-start">
                <svg class="w-3.5 h-3.5 transition-transform duration-200"
                     :class="kpiOpen ? 'rotate-0' : '-rotate-90'"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                </svg>
                Indicadores
            </button>
            @if($snapDesde)
            <span class="text-[10px] sm:text-xs text-[var(--c-muted)] pl-5 sm:pl-0">
                datos desde <span class="font-semibold text-[var(--c-text)]">{{ $snapDesde }}</span>
                @if($snapHasta && $snapHasta !== $snapDesde)
                · hasta <span class="font-semibold text-[var(--c-text)]">{{ $snapHasta }}</span>
                @endif
            </span>
            @endif
        </div>

        <div class="collapsible-wrap" :class="kpiOpen ? 'is-open' : ''">
            <div class="collapsible-inner">
                @livewire('kpi-panel')
            </div>
        </div>
    </div>

    <div class="mt-6">
        @livewire('dashboard', ['activeTab' => $activeTab ?? 'joyas'])
    </div>

</x-layouts.app>
