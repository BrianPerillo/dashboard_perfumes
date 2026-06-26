{{-- ════ TAB: Cambios ═════════════════════════════════ --}}
<div x-show="tab==='cambios'" x-cloak>
    @php
        $cambios = json_decode($cambiosJson, true) ?? ['nuevos'=>[],'salidos'=>[],'subas'=>[],'bajas'=>[],'totalNuevos'=>0,'totalSalidos'=>0];
        $totalNuevos  = $cambios['totalNuevos']  ?? count($cambios['nuevos']);
        $totalSalidos = $cambios['totalSalidos'] ?? count($cambios['salidos']);
    @endphp

    <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-5">
        <div class="card-nuevos-oferta rounded-xl border border-emerald-200 bg-emerald-50 p-2 sm:p-4 text-center">
            <p class="text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalNuevos }}</p>
            <p class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 uppercase tracking-wide mt-0.5 leading-tight">🆕 Nuevos en oferta</p>
        </div>
        <div class="rounded-xl border border-red-200 dark:border-red-700/40 bg-red-50 dark:bg-red-900/20 p-2 sm:p-4 text-center">
            <p class="text-xl sm:text-2xl font-bold text-red-600 dark:text-red-400">{{ $totalSalidos }}</p>
            <p class="text-[10px] sm:text-xs text-red-600 dark:text-red-400 uppercase tracking-wide mt-0.5 leading-tight">❌ Salieron de oferta</p>
        </div>
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-2 sm:p-4 text-center">
            <p class="text-xl sm:text-2xl font-bold text-violet-500 dark:text-violet-300">{{ count($cambios['subas']) }}</p>
            <p class="text-[10px] sm:text-xs text-slate-400 uppercase tracking-wide mt-0.5 leading-tight">⬆️ Subieron desc.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
        {{-- Mayores subas --}}
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4">
            <h3 class="text-sm font-semibold text-emerald-500 dark:text-emerald-400 mb-3">⬆️ Mayores subas de descuento</h3>
            @forelse($cambios['subas'] as $r)
            <div class="flex justify-between items-center py-1.5 border-b border-[var(--c-border)]">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-[var(--c-text)] truncate">{{ $r['producto'] }}</p>
                    <p class="text-xs text-[var(--c-muted)]">{{ $r['tienda'] }}</p>
                </div>
                <div class="text-right shrink-0 ml-3">
                    <span class="text-sm font-bold text-emerald-500 dark:text-emerald-400">+{{ $r['delta'] }} pts</span>
                    <p class="text-xs text-slate-400">{{ $r['desc_antes'] }}→{{ $r['desc_ahora'] }}%</p>
                </div>
            </div>
            @empty
            <p class="text-slate-400 text-sm">Sin subas en el período.</p>
            @endforelse
        </div>

        {{-- Mayores bajas --}}
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4">
            <h3 class="text-sm font-semibold text-red-700 dark:text-red-400 mb-3">⬇️ Mayores bajas de descuento</h3>
            @forelse($cambios['bajas'] as $r)
            <div class="flex justify-between items-center py-1.5 border-b border-[var(--c-border)]">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-[var(--c-text)] truncate">{{ $r['producto'] }}</p>
                    <p class="text-xs text-[var(--c-muted)]">{{ $r['tienda'] }}</p>
                </div>
                <div class="text-right shrink-0 ml-3">
                    <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ $r['delta'] }} pts</span>
                    <p class="text-xs text-slate-400">{{ $r['desc_antes'] }}→{{ $r['desc_ahora'] }}%</p>
                </div>
            </div>
            @empty
            <p class="text-slate-400 text-sm">Sin bajas en el período.</p>
            @endforelse
        </div>
    </div>

    {{-- Nuevos / Salidos (expandibles con animación) --}}
    <div x-data="{ open: false }" class="rounded-xl border border-emerald-200 dark:border-emerald-700/40 overflow-hidden mb-3">
        <button @click="open = !open"
                class="w-full flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3 text-sm font-semibold text-emerald-500 dark:text-emerald-400 text-left">
            <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200" :class="open ? 'rotate-90' : ''"
                 fill="currentColor" viewBox="0 0 20 20"><path d="M6 4l8 6-8 6V4z"/></svg>
            🆕 Productos nuevos en oferta ({{ $totalNuevos }}{{ $totalNuevos > 60 ? ' · mostrando 60' : '' }})
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner overflow-x-auto p-4">
                @if(!empty($cambios['nuevos']))
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-[var(--c-border)] text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">
                        <th class="py-2 text-left">Producto</th><th class="py-2 text-left">Tienda</th><th class="py-2 text-right">Desc.</th><th class="py-2 text-right">Precio</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--c-border)]">
                    @foreach($cambios['nuevos'] as $r)
                    <tr class="hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5"><td class="py-1.5 text-[var(--c-text)]">{{ $r['producto'] }}</td><td class="py-1.5 text-[var(--c-muted)] text-xs">{{ $r['tienda'] }}</td><td class="py-1.5 text-right font-bold text-emerald-500 dark:text-emerald-400">{{ $r['descuento_pct'] }}%</td><td class="py-1.5 text-right text-[var(--c-text)]">${{ number_format($r['precio_actual'] ?? 0, 0, ',', '.') }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
                @else <p class="text-slate-400 text-sm">Ninguno.</p> @endif
            </div>
        </div>
    </div>

    <div x-data="{ open: false }" class="rounded-xl border border-red-200 dark:border-red-700/40 overflow-hidden">
        <button @click="open = !open"
                class="w-full flex items-center gap-2 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm font-semibold text-red-700 dark:text-red-400 text-left">
            <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200" :class="open ? 'rotate-90' : ''"
                 fill="currentColor" viewBox="0 0 20 20"><path d="M6 4l8 6-8 6V4z"/></svg>
            ❌ Productos que salieron de oferta ({{ $totalSalidos }}{{ $totalSalidos > 60 ? ' · mostrando 60' : '' }})
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner overflow-x-auto p-4">
                @if(!empty($cambios['salidos']))
                <table class="w-full text-sm">
                    <thead><tr class="border-b border-[var(--c-border)] text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">
                        <th class="py-2 text-left">Producto</th><th class="py-2 text-left">Tienda</th><th class="py-2 text-right">Desc.</th>
                    </tr></thead>
                    <tbody class="divide-y divide-[var(--c-border)]">
                    @foreach($cambios['salidos'] as $r)
                    <tr class="hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5"><td class="py-1.5 text-[var(--c-text)]">{{ $r['producto'] }}</td><td class="py-1.5 text-[var(--c-muted)] text-xs">{{ $r['tienda'] }}</td><td class="py-1.5 text-right font-bold text-red-600 dark:text-red-400">{{ $r['descuento_pct'] }}%</td></tr>
                    @endforeach
                    </tbody>
                </table>
                @else <p class="text-slate-400 text-sm">Ninguno.</p> @endif
            </div>
        </div>
    </div>
</div>
