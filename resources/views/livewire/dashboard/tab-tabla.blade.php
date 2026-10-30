{{-- ════ TAB: Tabla ═══════════════════════════════════ --}}
<style>
    .tabla-fila {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        will-change: transform;
    }
    .tabla-fila:hover {
        transform: scale(1.012) translateZ(0);
        position: relative;
        z-index: 1;
    }
</style>
<div x-show="tab==='tabla'" x-cloak
     x-data="{ tablaOrdOpen: false, _cargando: false, _tablaThrottle: 0 }"
     x-init="
         $nextTick(() => {
             const spShow = () => { const el = document.getElementById('tabla-cargando'); if (el) el.style.display = 'flex'; };
             const spHide = () => { const el = document.getElementById('tabla-cargando'); if (el) el.style.display = 'none'; };

             const tryLoad = () => {
                 if (this._cargando || !$wire.tablaTieneMas) return;
                 const s = document.getElementById('tabla-sentinel');
                 if (!s || s.getBoundingClientRect().top > window.innerHeight + 300) return;
                 this._cargando = true;
                 spShow();
                 $wire.cargarMasTabla().then(() => {
                     this._cargando = false;
                     spHide();
                     tryLoad();
                 });
             };

             const sentinel = document.getElementById('tabla-sentinel');
             if (sentinel) {
                 new IntersectionObserver(
                     (entries) => { if (entries[0].isIntersecting) tryLoad(); },
                     { rootMargin: '300px' }
                 ).observe(sentinel);
             }

             window.addEventListener('scroll', () => {
                 const now = Date.now();
                 if (now - this._tablaThrottle < 100) return;
                 this._tablaThrottle = now;
                 if (tab === 'tabla') tryLoad();
             }, { passive: true });
         });
     ">

    {{-- Toggle ordenamiento --}}
    <button @click="tablaOrdOpen = !tablaOrdOpen"
            class="flex items-center gap-1.5 mb-3 text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors select-none">
        <svg class="w-3.5 h-3.5 transition-transform duration-200"
             :class="tablaOrdOpen ? 'rotate-0' : '-rotate-90'"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
        </svg>
        Favoritos y ordenamiento
    </button>

    <div class="collapsible-wrap" :class="tablaOrdOpen ? 'is-open' : ''">
    <div class="collapsible-inner">

    {{-- ── Favoritos (solo autenticados) ── --}}
    @auth
    <div class="border-b border-[var(--c-border)] pb-3 mb-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)] mb-2">⭐ Configuraciones guardadas</p>
        @if(!empty($favoritosLista))
        <div class="flex flex-wrap gap-1.5 mb-2"
             x-data="{
                 selectedFav: localStorage.getItem('fav-selected') || '',
                 elegir(fav) {
                     if (this.selectedFav === fav) {
                         this.selectedFav = '';
                         localStorage.setItem('fav-selected', '');
                         $wire.limpiarFiltrosTabla();
                     } else {
                         this.selectedFav = fav;
                         localStorage.setItem('fav-selected', fav);
                         $wire.aplicarFavorito(fav);
                     }
                 }
             }">
            @foreach($favoritosLista as $fav)
            <span class="inline-flex items-stretch rounded-md overflow-hidden border border-amber-500 text-xs font-medium">
                <button @click="elegir(@js($fav))"
                        :class="selectedFav !== @js($fav) ? 'text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30' : ''"
                        :style="selectedFav === @js($fav) ? 'background-color:#d97706;color:#ffffff;' : ''"
                        class="px-3 py-1.5 transition-colors cursor-pointer">{{ $fav }}</button>
                <button wire:click="borrarFavorito(@js($fav))"
                        class="px-2 py-1.5 bg-transparent text-red-500 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 hover:text-red-600 dark:hover:text-red-400 border-l border-amber-400 dark:border-amber-600/70 transition-colors font-bold"
                        title="Borrar">×</button>
            </span>
            @endforeach
        </div>
        @endif
        <div class="flex gap-2 items-center">
            <input type="text" wire:model="favoritoNombre" placeholder="Nombre para guardar esta configuración…"
                   class="flex-1 rounded-lg border border-amber-200 dark:border-amber-700/40 bg-[var(--c-input)] px-3 py-1.5 text-sm text-[var(--c-text)] focus:outline-none focus:ring-2 focus:ring-amber-300"
                   wire:keydown.enter="guardarFavorito">
            <button wire:click="guardarFavorito"
                    class="flex-shrink-0 rounded-lg bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 text-xs font-semibold transition-colors">
                💾 Guardar
            </button>
        </div>
    </div>
    @endauth
    @guest
    <p class="text-xs text-slate-400 mb-3 italic">💡 Iniciá sesión para guardar configuraciones de filtros y orden como favoritos.</p>
    @endguest

    {{-- ── Ordenamiento multi-nivel ── --}}
    @php
        $sortCols = [
            ''              => '— sin ordenar —',
            'descuento_pct' => 'Desc. %',
            'precio_actual' => 'Precio actual',
            'precio_lista'  => 'Precio lista',
            'ahorro'        => 'Ahorro',
            'marca'         => 'Marca',
            'tienda'        => 'Tienda',
            'producto'      => 'Producto',
            'fecha'         => 'Fecha',
        ];
    @endphp
    <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4 mb-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)] mb-2">Ordenar por <span class="font-normal normal-case">(prioridad de izquierda a derecha)</span></p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach([
                [1, $tablaSortCol1, $tablaSortDir1],
                [2, $tablaSortCol2, $tablaSortDir2],
                [3, $tablaSortCol3, $tablaSortDir3],
            ] as [$nivel, $col, $dir])
            <div class="flex gap-2 items-center">
                <span class="text-xs text-slate-400 w-14 shrink-0">Nivel {{ $nivel }}:</span>
                <select wire:model.live="tablaSortCol{{ $nivel }}"
                        class="flex-1 rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)] px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-300)]">
                    @foreach($sortCols as $val => $lbl)
                    <option value="{{ $val }}" @selected($col === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
                @if($col)
                <button wire:click="$set('tablaSortDir{{ $nivel }}', '{{ $dir === 'asc' ? 'desc' : 'asc' }}')"
                        title="{{ $dir === 'asc' ? 'Ascendente · click para cambiar' : 'Descendente · click para cambiar' }}"
                        class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg border border-[var(--c-border)] bg-[var(--c-card)] hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5 text-[var(--c-muted)] text-base transition-colors">
                    {{ $dir === 'asc' ? '↑' : '↓' }}
                </button>
                @else
                <span class="w-8"></span>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    </div>{{-- /collapsible-inner --}}
    </div>{{-- /collapsible-wrap --}}

    {{-- ── Tabla de resultados ── --}}
    @php $tabla = json_decode($tablaDatosJson, true) ?? []; @endphp
    <div class="mb-3">
        <p class="text-sm text-slate-500">
            @php
                $n = count($tabla);
                $sortDesc = collect([
                    [$tablaSortCol1, $tablaSortDir1],
                    [$tablaSortCol2, $tablaSortDir2],
                    [$tablaSortCol3, $tablaSortDir3],
                ])->filter(fn($s) => $s[0])->map(fn($s) => ($sortCols[$s[0]] ?? $s[0]) . ' ' . ($s[1] === 'asc' ? '↑' : '↓'))->implode(' › ');
            @endphp
            <span class="font-semibold text-[var(--c-text)]">{{ $n }}</span>
            @if($tablaTotalCount > $n)
                de <span class="font-semibold text-[var(--c-text)]">{{ $tablaTotalCount }}</span>
                resultados cargados
                <span class="ml-1 text-violet-500 dark:text-violet-400 text-[10px] font-semibold">↓ seguí scrolleando</span>
            @else
                resultado{{ $n !== 1 ? 's' : '' }}
                @if($tablaTotalCount > 0 && $n === $tablaTotalCount)
                <span class="text-[10px] text-[var(--c-muted)]">· todos cargados</span>
                @endif
            @endif
            @if($sortDesc) · orden: <span class="text-[var(--color-primary-600)] font-medium">{{ $sortDesc }}</span>@endif
        </p>
        {{-- fecha del snapshot retirada --}}
    </div>

    {{-- ── Vista mobile: lista dividida ── --}}
    <div class="lg:hidden mobile-cards-list -mx-5 mt-3 overflow-hidden">
        @forelse($tabla as $row)
        @php
            $dto = (float)$row['descuento'];
            $badgeColor = $dto >= 40 ? '#2ce427' : ($dto >= 20 ? '#b427e4' : '#94a3b8');
            $badgeText  = $dto >= 40 ? '#1a3300' : '#ffffff';
        @endphp
        <div class="px-5 py-4 bg-[var(--c-card)]">
            <div class="flex items-start gap-3">
                {{-- Badge descuento --}}
                <span class="rounded-full flex items-center justify-center text-xs font-bold tabular-nums"
                     style="width:54px;height:54px;flex-shrink:0;background:{{ $badgeColor }};color:{{ $badgeText }}">
                    {{ $row['descuento'] }}
                </span>
                @if(!empty($row['imagen']))
                <img src="{{ $row['imagen'] }}" alt="" loading="lazy" referrerpolicy="no-referrer"
                     class="w-14 h-14 rounded-lg object-cover bg-[var(--c-bg)] shrink-0">
                @endif
                {{-- Info producto --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[var(--c-text)] leading-snug">{{ $row['producto'] }}</p>
                    <p class="text-xs text-[var(--c-muted)] mt-1">
                        <button wire:click="toggleTablaFiltroTiendaTabla('{{ $row['tienda'] }}')"
                                class="hover:text-violet-500 hover:underline underline-offset-2 transition-colors">{{ $row['tienda'] }}</button>
                        @if($row['marca'])
                        · <button wire:click="toggleTablaFiltroMarca('{{ addslashes($row['marca']) }}')"
                                  class="hover:text-violet-500 hover:underline underline-offset-2 transition-colors">{{ $row['marca'] }}</button>
                        @endif
                    </p>
                    @if(!empty($row['talles']))
                    <p class="text-[11px] text-[var(--c-muted)] mt-0.5">
                        {{ $row['tipo'] ? $row['tipo'] . ' · ' : '' }}@if(($row['variantes'] ?? 1) > 1)<span class="font-semibold text-violet-500">{{ $row['variantes'] }} colores</span> · @endif{{ $row['color'] ? $row['color'] . ' · ' : '' }}Talles: {{ $row['talles'] }}
                    </p>
                    @endif
                </div>
                {{-- Link --}}
                @if($row['url'])
                <a href="{{ $row['url'] }}" target="_blank" rel="noopener"
                   class="shrink-0 whitespace-nowrap text-xs font-semibold text-violet-500 dark:text-violet-400 hover:underline">ver →</a>
                @endif
            </div>
            <div class="flex items-baseline gap-3 mt-3">
                <span class="text-base font-bold text-violet-500 dark:text-violet-300">{{ $row['precio_actual'] }}</span>
                <span class="text-xs text-slate-400 line-through">{{ $row['precio_lista'] }}</span>
                <span class="text-xs font-semibold text-emerald-500 dark:text-emerald-400">↓ {{ $row['ahorro'] }}</span>
            </div>
        </div>
        @empty
        <div class="px-4 py-10 text-center text-slate-400 bg-[var(--c-card)]">Sin productos con esos filtros.</div>
        @endforelse
    </div>

    {{-- ── Vista desktop: tabla ── --}}
    <div class="hidden lg:block overflow-x-auto rounded-xl border border-[var(--c-border)] bg-[var(--c-card)]">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-primary-600)] text-white sticky top-0">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Dto.</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Fecha</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Tienda</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Marca</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase min-w-52">Producto</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">P. lista</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">P. actual</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">Ahorro</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Fuente</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase">Link</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--c-border)]">
                @forelse($tabla as $i => $row)
                @php
                    $dto2 = (float)$row['descuento'];
                    $bc2  = $dto2 >= 40 ? '#2ce427' : ($dto2 >= 20 ? '#b427e4' : '#94a3b8');
                    $bt2  = $dto2 >= 40 ? '#1a3300' : '#ffffff';
                @endphp
                <tr class="tabla-fila hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5">
                    <td class="px-3 py-2 text-center">
                        <span class="rounded-full inline-flex items-center justify-center text-xs font-bold"
                              style="width:44px;height:44px;background:{{ $bc2 }};color:{{ $bt2 }};flex-shrink:0;">
                            {{ $row['descuento'] }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-xs text-[var(--c-muted)] whitespace-nowrap">{{ $row['fecha'] }}</td>
                    <td class="px-3 py-2 text-xs font-medium text-[var(--c-muted)]">
                        <button wire:click="toggleTablaFiltroTiendaTabla('{{ $row['tienda'] }}')"
                                class="hover:text-violet-500 dark:text-violet-400 hover:underline underline-offset-2 transition-colors"
                                title="Filtrar por esta tienda">{{ $row['tienda'] }}</button>
                    </td>
                    <td class="px-3 py-2 text-xs text-[var(--c-muted)]">
                        @if($row['marca'])
                        <button wire:click="toggleTablaFiltroMarca('{{ addslashes($row['marca']) }}')"
                                class="hover:text-violet-500 dark:text-violet-400 hover:underline underline-offset-2 transition-colors"
                                title="Filtrar por esta marca">{{ $row['marca'] }}</button>
                        @endif
                    </td>
                    <td class="px-3 py-2 font-medium text-[var(--c-text)]">
                        @if(!empty($row['imagen']))
                        <img src="{{ $row['imagen'] }}" alt="" loading="lazy" referrerpolicy="no-referrer"
                             class="inline-block w-9 h-9 rounded object-cover bg-[var(--c-bg)] mr-2 align-middle">
                        @endif
                        {{ $row['producto'] }}
                        @if(!empty($row['talles']))
                        <span class="block text-[11px] font-normal text-[var(--c-muted)]">{{ $row['tipo'] ? $row['tipo'] . ' · ' : '' }}@if(($row['variantes'] ?? 1) > 1)<span class="font-semibold text-violet-500">{{ $row['variantes'] }} colores</span> · @endif{{ $row['color'] ? $row['color'] . ' · ' : '' }}Talles: {{ $row['talles'] }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right text-slate-400 text-xs line-through">{{ $row['precio_lista'] }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-violet-500 dark:text-violet-300">{{ $row['precio_actual'] }}</td>
                    <td class="px-3 py-2 text-right text-emerald-500 dark:text-emerald-400 font-medium text-xs">{{ $row['ahorro'] }}</td>
                    <td class="px-3 py-2 text-xs text-slate-400">{{ $row['fuente'] }}</td>
                    <td class="px-3 py-2 text-center">
                        @if($row['url'])<a href="{{ $row['url'] }}" target="_blank" rel="noopener" class="text-xs whitespace-nowrap text-violet-500 dark:text-violet-400 underline underline-offset-2">ver →</a>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-[var(--c-muted)]">Sin productos con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Infinite scroll: sentinel + estados ── --}}
    <div id="tabla-sentinel" class="py-1" aria-hidden="true"></div>


    {{-- Fin de resultados --}}
    @if(!$tablaTieneMas && count($tabla) > 0)
    <p class="py-4 text-center text-xs text-[var(--c-muted)]">
        ✓ {{ number_format(count($tabla), 0, ',', '.') }} resultados cargados
        @if($tablaTotalCount > count($tabla))
        de {{ number_format($tablaTotalCount, 0, ',', '.') }}
        @endif
    </p>
    @endif
</div>
