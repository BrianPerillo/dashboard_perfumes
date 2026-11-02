{{-- ════ TAB: Transferencia ═══════════════════════════ --}}
<div x-show="tab==='transfer'" x-cloak>
    @php $transfer = json_decode($transferJson, true) ?? []; @endphp
    @if(empty($transfer))
        <div class="rounded-xl bg-[var(--c-card)] border border-[var(--c-border)] px-4 py-8 text-center text-[var(--c-muted)]">
            No hay ofertas por transferencia en el último snapshot. Aparecen cuando el scraper levanta tiendas con precio diferenciado por pago en efectivo/transferencia.
        </div>
    @else
    <div class="mb-4">
        <p class="text-sm text-slate-500">Productos sin rebaja en el precio de lista, pero más baratos pagando por transferencia o efectivo.</p>
    </div>

    {{-- Vista mobile: cards --}}
    <div class="lg:hidden mobile-cards-list -mx-5 overflow-hidden">
        @foreach($transfer as $r)
        <div class="px-5 py-4 bg-[var(--c-card)] border-b border-[var(--c-border)]">
            <div class="flex items-start gap-3">
                <span class="rounded-full flex items-center justify-center text-xs font-bold tabular-nums text-white"
                      style="width:54px;height:54px;flex-shrink:0;background:#10b981;">
                    {{ $r['ahorro_pct'] }}%
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[var(--c-text)] leading-snug">{{ $r['producto'] }}</p>
                    <p class="text-xs text-[var(--c-muted)] mt-1">
                        {{ $r['tienda'] }}@if($r['marca']) · {{ $r['marca'] }}@endif
                    </p>
                </div>
                @if($r['url'])
                <a href="{{ $r['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"
                   class="shrink-0 whitespace-nowrap text-xs font-semibold text-violet-500 dark:text-violet-400 hover:underline">ver →</a>
                @endif
            </div>
            <div class="flex items-baseline gap-3 mt-3">
                <span class="text-base font-bold text-emerald-500 dark:text-emerald-400">{{ $r['precio_transf'] }}</span>
                <span class="text-xs text-slate-400 line-through">{{ $r['precio_normal'] }}</span>
                <span class="text-xs font-semibold text-emerald-500 dark:text-emerald-400">↓ transf.</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Vista desktop: tabla --}}
    <div class="hidden lg:block overflow-x-auto rounded-xl border border-[var(--c-border)]">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-primary-600)] text-white">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase min-w-44">Producto</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Tienda</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Marca</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">P. normal</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">P. transf.</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">Ahorro %</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase">Link</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--c-border)]">
                @foreach($transfer as $r)
                <tr class="hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5">
                    <td class="px-3 py-2 font-medium text-[var(--c-text)]">{{ $r['producto'] }}</td>
                    <td class="px-3 py-2 text-xs text-[var(--c-muted)]">{{ $r['tienda'] }}</td>
                    <td class="px-3 py-2 text-xs text-slate-400">{{ $r['marca'] }}</td>
                    <td class="px-3 py-2 text-right text-slate-400 line-through text-xs">{{ $r['precio_normal'] }}</td>
                    <td class="px-3 py-2 text-right font-bold text-emerald-500 dark:text-emerald-400">{{ $r['precio_transf'] }}</td>
                    <td class="px-3 py-2 text-right"><span class="text-xs font-bold text-emerald-500 dark:text-emerald-400">{{ $r['ahorro_pct'] }}%</span></td>
                    <td class="px-3 py-2 text-center">
                        @if($r['url'])<a href="{{ $r['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" class="text-xs whitespace-nowrap text-violet-500 dark:text-violet-400 underline underline-offset-2">ver →</a>@endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
