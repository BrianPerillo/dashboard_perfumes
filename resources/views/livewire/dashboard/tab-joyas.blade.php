{{-- ════ TAB: Joyas ═══════════════════════════════════ --}}
<style>
    .joya-card {
        box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        will-change: transform;
    }
    .dark .joya-card {
        box-shadow: 0 2px 10px rgba(0,0,0,0.45);
    }
    .joya-card:hover {
        transform: scale(1.03) translateZ(0);
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        position: relative;
        z-index: 1;
    }
    .dark .joya-card:hover {
        box-shadow: 0 6px 22px rgba(0,0,0,0.55);
    }
</style>
<div x-show="tab==='joyas'" x-cloak
     x-data="{ _cargando: false }"
     x-init="
         $nextTick(() => {
             const tryLoad = () => {
                 const s = document.getElementById('joyas-sentinel');
                 if (this._cargando || !s || s.dataset.mas !== '1' || s.getBoundingClientRect().top > window.innerHeight + 300) return;
                 this._cargando = true;
                 $wire.cargarMasJoyas().then(() => { this._cargando = false; $nextTick(tryLoad); });
             };
             const sentinel = document.getElementById('joyas-sentinel');
             if (sentinel) new IntersectionObserver((e) => { if (e[0].isIntersecting && tab === 'joyas') tryLoad(); }, { rootMargin: '300px' }).observe(sentinel);
             window.addEventListener('scroll', () => { if (tab === 'joyas') tryLoad(); }, { passive: true });
         });
     ">
    @php
        $joyas = json_decode($joyasJson, true) ?? [];
        $marcasConJoyas = collect($joyas)->groupBy('marca');
        $joyasBrandOrder = [
            'Versace'=>1,'Yves Saint Laurent'=>2,'Armani'=>3,'Dior'=>4,
            'Chanel'=>5,'Dolce & Gabbana'=>6,'Hugo Boss'=>7,'Rabanne'=>8,
            'Jean Paul Gaultier'=>9,'Givenchy'=>10,'Montblanc'=>11,'Carolina Herrera'=>12,
        ];
        // Orden de display (marca por prioridad, mayor descuento primero); solo se renderizan las primeras $joyasVisibles
        $joyasOrden   = $marcasConJoyas->sortBy(fn($g, $m) => $joyasBrandOrder[$m] ?? 99)->flatMap(fn($g) => $g->sortByDesc('descuento_pct')->values());
        $joyasHayMas  = $joyasOrden->count() > $joyasVisibles;
        $joyasGrupos  = $joyasOrden->take($joyasVisibles)->groupBy('marca');
        // fotos solo de las cards visibles (una consulta por PK)
        $joyasFotos   = $this->imagenesProductos($joyasGrupos->flatten(1)->pluck('pid')->all());
    @endphp

    @if(empty($joyas))
        <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900 px-4 py-8 text-center text-amber-700 dark:text-amber-400">
            No hay joyas con {{ $joyasUmbral }}% de descuento o más. Bajá el umbral.
        </div>
    @else
        {{-- KPIs de joyas --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 text-center">
                <p class="text-2xl font-bold text-violet-500 dark:text-violet-300">{{ count($joyas) }}</p>
                <p class="text-xs text-[var(--c-muted)] uppercase tracking-wide mt-0.5">Ofertas Destacadas</p>
            </div>
            <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 text-center">
                <p class="text-2xl font-bold" style="color:#2ce427">{{ collect($joyas)->where('descuento_pct','>=',40)->count() }}</p>
                <p class="text-xs text-[var(--c-muted)] uppercase tracking-wide mt-0.5">Con 40% o más</p>
            </div>
            <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 text-center">
                <p class="text-2xl font-bold text-violet-500 dark:text-violet-300">{{ $marcasConJoyas->count() }}</p>
                <p class="text-xs text-[var(--c-muted)] uppercase tracking-wide mt-0.5">Marcas destacadas</p>
            </div>
        </div>

        {{-- Cards por marca --}}
        @foreach($joyasGrupos as $marca => $items)
        <div class="mb-6">
            <h3 class="text-base font-bold text-violet-500 dark:text-violet-300 mb-3 flex items-center gap-2">
                <span class="inline-block w-1 h-5 rounded-full bg-[var(--color-primary-600)]"></span>
                {{ $marca }}
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($items as $j)
                @php
                    $pct  = $j['descuento_pct'];
                    $freq = $j['freq_pct'] ?? 0;
                    $dias = $j['dias_en_oferta'] ?? 0;
                    $borderColor = $pct >= 40 ? '#2ce427' : ($pct >= 20 ? '#b427e4' : '#94a3b8');
                    $tier = $pct >= 40 ? '🔥🔥' : ($pct >= 30 ? '🔥' : '✅');

                    if ($dias >= 365) {
                        $badgeLabel = '⚠️ siempre en oferta';
                        $badgeClass = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
                    } elseif ($dias >= 30) {
                        $badgeLabel = '📌 habitualmente en oferta';
                        $badgeClass = 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
                    } elseif ($dias > 0 && $dias < 7) {
                        $badgeLabel = '🆕 oferta reciente';
                        $badgeClass = 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
                    } elseif ($freq > 0) {
                        $badgeLabel = "en oferta {$freq}% del tiempo";
                        $badgeClass = 'bg-[var(--c-bg)] text-[var(--c-muted)]';
                    } else {
                        $badgeLabel = null;
                        $badgeClass = '';
                    }
                @endphp
                <div class="joya-card rounded-xl border border-[var(--c-border)] p-4 bg-[var(--c-card)]"
                     style="border-left: 4px solid {{ $borderColor }}">
                    @php $foto = $joyasFotos[$j['pid'] ?? 0] ?? ''; @endphp
                    <div class="flex items-start gap-3">
                        {{-- Foto (tamaño fijo: el layout no salta al cargar); sin foto, ícono del rubro --}}
                        <a @if(!empty($j['url'])) href="{{ $j['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" @endif
                           class="shrink-0 flex items-center justify-center overflow-hidden rounded-lg bg-white border border-[var(--c-border)]" style="width:72px;height:72px;">
                            @if($foto)
                            <img src="{{ $foto }}" alt="{{ $j['producto'] }}" width="72" height="72" loading="lazy" decoding="async" referrerpolicy="no-referrer"
                                 class="h-full w-full object-contain p-1">
                            @else
                            <span class="text-2xl text-slate-300" aria-hidden="true">{{ config("rubros.rubros.{$rubro}.icono", '🛍️') }}</span>
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between items-start gap-2">
                                <span class="font-bold text-[var(--c-text)] text-sm leading-tight">{{ $tier }} {{ $j['linea'] }}</span>
                                <span class="text-lg font-extrabold shrink-0" style="color:{{ $borderColor }}">{{ $pct }}%</span>
                            </div>
                            <p class="text-sm text-[var(--c-muted)] mt-1"><span class="font-semibold text-[var(--c-text)]">{{ $j['tienda'] }}</span> · ${{ number_format($j['precio_actual'], 0, ',', '.') }}</p>
                            <p class="text-xs text-[var(--c-muted)] mt-0.5 truncate" title="{{ $j['producto'] }}">{{ $j['producto'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        @if(!empty($j['url']))
                        <a href="{{ $j['url'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer"
                           class="text-xs text-violet-500 dark:text-violet-400 hover:underline">ver producto →</a>
                        @else<span></span>@endif
                        @if($badgeLabel)
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium {{ $badgeClass }}">
                            {{ $badgeLabel }}
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        {{-- Scroll infinito: al acercarse al final se pide otra tanda de cards --}}
        <div id="joyas-sentinel" data-mas="{{ $joyasHayMas ? '1' : '0' }}" class="py-4 text-center text-xs text-[var(--c-muted)]" aria-hidden="true">
            @if($joyasHayMas)Cargando más ofertas…@endif
        </div>
    @endif
</div>
