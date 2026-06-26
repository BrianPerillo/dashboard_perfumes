{{-- ════ TAB: Distribución ════════════════════════════ --}}
<div x-show="tab==='distribucion'" x-cloak>
    <div class="-mx-5 md:mx-0 grid grid-cols-1 md:grid-cols-2 md:gap-5">
        <div class="border-t border-[var(--c-border)] md:rounded-xl md:border bg-[var(--c-card)] pt-3 pb-2 px-2 md:p-4">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2 px-2 md:px-0">Histograma de descuentos · último snapshot</h3>
            <div id="chart-distribucion"></div>
        </div>
        <div class="border-t border-[var(--c-border)] md:rounded-xl md:border bg-[var(--c-card)] p-4 md:p-4">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2">Rangos de descuento</h3>
            @php $dist = json_decode($distribucionJson, true) ?? []; $maxN = !empty($dist) ? max(array_column($dist,'n')) : 1; @endphp
            <div class="space-y-2 mt-2">
                @foreach($dist as $b)
                <div class="flex items-center gap-3">
                    <span class="w-14 text-xs text-[var(--c-muted)] text-right">{{ $b['rango'] }}</span>
                    <div class="rango-track flex-1 h-5 rounded-full bg-slate-200 overflow-hidden">
                        <div class="h-full rounded-full bg-[var(--color-primary-400)] transition-all"
                             style="width: {{ round($b['n'] / $maxN * 100) }}%"></div>
                    </div>
                    <span class="w-8 text-xs font-semibold text-[var(--c-muted)] text-right">{{ $b['n'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
