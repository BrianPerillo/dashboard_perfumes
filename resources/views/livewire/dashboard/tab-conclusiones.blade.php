{{-- ════ TAB: Conclusiones ════════════════════════════ --}}
<div x-show="tab==='conclusiones'" x-cloak>
    @php
        $concl     = json_decode($conclusionesJson, true) ?? ['puntos'=>[],'joyas'=>[],'narrativa'=>''];
        $puntos    = $concl['puntos']    ?? [];
        $joyasD    = $concl['joyas']     ?? [];
        $narrativa = $concl['narrativa'] ?? '';

        $bold = fn(string $t) => preg_replace(
            '/\*\*(.+?)\*\*/',
            '<strong class="font-semibold text-violet-500 dark:text-violet-300">$1</strong>',
            e($t)
        );
    @endphp

    {{-- Joyas destacadas del día --}}
    @if(!empty($joyasD))
    <div class="rounded-2xl border border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-900/10 p-4 mb-5">
        <h3 class="text-xs font-bold text-amber-800 dark:text-amber-400 uppercase tracking-wide mb-3">💎 Joyas del día</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($joyasD as $j)
            @php $pct = $j['descuento_pct']; $tier = $pct>=40?'🔥🔥':($pct>=30?'🔥':'✅'); @endphp
            <div class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 dark:border-amber-800 bg-[var(--c-card)] px-3 py-1.5 text-xs">
                <span>{{ $tier }}</span>
                <span class="font-bold text-[var(--c-text)]">{{ $j['marca'] }} {{ $j['linea'] }}</span>
                <span class="font-extrabold text-red-600">{{ $pct }}%</span>
                <span class="text-[var(--c-muted)]">{{ $j['tienda'] }} · ${{ number_format($j['precio_actual'],0,',','.') }}</span>
                @if(!empty($j['url']))
                <a href="{{ $j['url'] }}" target="_blank" rel="noopener" class="text-violet-500 dark:text-violet-400 hover:underline">ver →</a>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Lectura general (narrativa en prosa) --}}
    @if($narrativa)
    <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-5 mb-5">
        <h3 class="text-sm font-bold text-[var(--c-text)] mb-3">📖 Lectura general</h3>
        @foreach(explode("\n\n", $narrativa) as $parrafo)
        <p class="text-sm text-[var(--c-text)] leading-relaxed mb-3 last:mb-0">
            {!! $bold($parrafo) !!}
        </p>
        @endforeach
    </div>
    @endif

    {{-- Puntos destacados --}}
    <h3 class="text-xs font-bold text-[var(--c-muted)] uppercase tracking-wide mb-3">📌 Puntos clave</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @forelse($puntos as $p)
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] px-4 py-3 flex gap-3 items-start">
            <span class="text-xl shrink-0 mt-0.5">{{ $p['emoji'] }}</span>
            <p class="text-sm text-[var(--c-text)] leading-relaxed">
                {!! $bold($p['texto']) !!}
            </p>
        </div>
        @empty
        <p class="text-[var(--c-muted)] text-sm col-span-2 text-center py-8">Cargando análisis…</p>
        @endforelse
    </div>

    <p class="text-xs text-[var(--c-muted)] mt-4 text-center">
        Se recalcula automáticamente al cambiar filtros de tiendas o descuento.
    </p>
</div>
