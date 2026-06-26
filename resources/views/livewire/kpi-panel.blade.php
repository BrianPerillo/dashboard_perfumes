<div>
    {{-- Encabezado de fechas movido al botón de Indicadores en dashboard.blade.php --}}

    {{-- KPI Cards --}}
    @if(empty($kpis))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-sm">
            No hay datos disponibles. Asegurate de que la base de datos tenga corridas cargadas.
        </div>
    @else
    <div class="grid gap-2.5" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))">
        @foreach($kpis as $kpi)
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] px-4 py-3 flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--c-muted)] leading-none mb-1">
                    {{ $kpi['label'] }}
                </p>
                <p class="text-lg font-bold text-violet-500 dark:text-violet-300 leading-tight">
                    {{ $kpi['value'] }}
                </p>
                @if($kpi['delta'] !== null)
                <div class="mt-0.5 text-[10px] font-medium
                    {{ $kpi['delta'] >= 0 ? 'text-emerald-500 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                    {{ $kpi['delta'] >= 0 ? '▲' : '▼' }}
                    {{ abs($kpi['delta']) }}{{ $kpi['suffix'] ?? '' }}
                    <span class="text-[var(--c-muted)] font-normal">vs anterior</span>
                </div>
                @else
                <div class="mt-0.5 h-3.5"></div>
                @endif
            </div>
            <span class="text-5xl leading-none flex-shrink-0 select-none">{{ $kpi['icon'] }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>
