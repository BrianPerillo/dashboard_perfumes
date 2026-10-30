{{-- ════ TAB: Comparar precios ════════════════════════ --}}
<div x-show="tab==='comparar'" x-cloak>
    @php $comparar = json_decode($compararJson, true) ?? []; @endphp
    @if(empty($comparar))
        <div class="rounded-xl bg-[var(--c-card)] border border-[var(--c-border)] px-4 py-8 text-center text-[var(--c-muted)]">
            No hay productos disponibles en 2+ tiendas en el último snapshot.
        </div>
    @else
    <div class="grid grid-cols-2 gap-4 mb-5">
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 text-center">
            <p class="text-2xl font-bold text-violet-500 dark:text-violet-300">{{ count($comparar) }}</p>
            <p class="text-xs text-slate-400 uppercase tracking-wide mt-0.5">Productos comparables</p>
        </div>
        <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 text-center">
            <p class="text-2xl font-bold text-emerald-500 dark:text-emerald-400">${{ number_format($comparar[0]['ahorro'] ?? 0, 0, ',', '.') }}</p>
            <p class="text-xs text-slate-400 uppercase tracking-wide mt-0.5">Mayor diferencia encontrada</p>
        </div>
    </div>
    <div class="overflow-x-auto rounded-xl border border-[var(--c-border)]">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-primary-600)] text-white">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase min-w-44">Producto</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Marca</th>
                    @if($rubro === 'ropa')<th class="px-3 py-2.5 text-left text-xs font-semibold uppercase min-w-56">Precio por tienda</th>@endif
                    <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase"># Tiendas</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Más barata en</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">Mín.</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">Máx.</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">Ahorrás</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase">Link</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--c-border)]">
                @foreach($comparar as $i => $r)
                <tr class="hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5">
                    <td class="px-3 py-2 font-medium text-[var(--c-text)]">
                        @if(!empty($r['imagen']))
                        <img src="{{ $r['imagen'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="inline-block w-9 h-9 rounded object-cover bg-[var(--c-bg)] mr-2 align-middle">
                        @endif
                        {{ $r['producto'] }}
                    </td>
                    <td class="px-3 py-2 text-slate-500 text-xs">{{ $r['marca'] }}</td>
                    @if($rubro === 'ropa')
                    <td class="px-3 py-2 text-xs">
                        @foreach($r['detalle'] ?? [] as $k => $d)
                        <div class="whitespace-nowrap {{ $k === 0 ? 'font-bold text-emerald-500 dark:text-emerald-400' : 'text-[var(--c-muted)]' }}">
                            @if($d['url'])<a href="{{ $d['url'] }}" target="_blank" rel="noopener" class="hover:underline">{{ $d['tienda'] }}</a>@else{{ $d['tienda'] }}@endif
                            ${{ number_format($d['precio'], 0, ',', '.') }} <span class="opacity-70">(-{{ $d['dto'] }}%)</span>
                            @if(!empty($d['talles']))<span class="opacity-70">· {{ $d['talles'] }}</span>@endif
                        </div>
                        @endforeach
                    </td>
                    @endif
                    <td class="px-3 py-2 text-center text-[var(--c-muted)]">{{ $r['tiendas'] }}</td>
                    <td class="px-3 py-2 text-xs font-medium text-violet-500 dark:text-violet-300">{{ $r['tienda_min'] }}</td>
                    <td class="px-3 py-2 text-right font-bold text-emerald-500 dark:text-emerald-400">${{ number_format($r['precio_min'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right text-slate-400 text-xs line-through">${{ number_format($r['precio_max'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right"><span class="font-bold text-emerald-500 dark:text-emerald-400">${{ number_format($r['ahorro'], 0, ',', '.') }}</span><span class="text-xs text-slate-400 ml-1">({{ $r['spread_pct'] }}%)</span></td>
                    <td class="px-3 py-2 text-center">
                        @if($r['url'])<a href="{{ $r['url'] }}" target="_blank" rel="noopener" class="text-xs whitespace-nowrap text-violet-500 dark:text-violet-400 underline underline-offset-2">ver →</a>@endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
