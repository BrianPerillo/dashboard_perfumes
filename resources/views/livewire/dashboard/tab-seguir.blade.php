{{-- ════ TAB: Seguir un perfume ═══════════════════════ --}}
<div x-show="tab==='seguir'" x-cloak>
    @if(empty($seguirOpciones))
        <p class="text-[var(--c-muted)] text-center py-8">Cargando opciones…</p>
    @else

    {{-- Selector de perfume (dentro de la sección para evitar clipping del sidebar) --}}
    <div class="mb-5 max-w-xl"
         x-data="{
             query: '',
             open: false,
             selected: @js($seguirOpciones ? collect($seguirOpciones)->firstWhere('key', $seguirProdKey)['label'] ?? '' : ''),
             opciones: @js($seguirOpciones ?? []),
             inicial: @js($seguirOpciones ?? []),
             buscando: false,
             _t: null,
             get filtradas() { return this.opciones; },
             // La lista completa tiene miles de productos: se busca en el servidor (con pausa al tipear)
             buscar() {
                 clearTimeout(this._t);
                 const q = this.query.trim();
                 if (!q) { this.opciones = this.inicial; return; }
                 this._t = setTimeout(() => {
                     this.buscando = true;
                     $wire.buscarSeguir(q).then(r => { if (q === this.query.trim()) this.opciones = r; }).finally(() => this.buscando = false);
                 }, 250);
             },
             elegir(op) {
                 this.selected = op.label;
                 this.query = '';
                 this.open = false;
                 $wire.set('seguirProdKey', op.key);
             }
         }"
         @click.outside="open = false">
        <label class="text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)] block mb-1.5">Elegí un perfume</label>
        <div class="relative">
            <input type="text"
                   x-model="query"
                   @focus="open = true"
                   @input="open = true; buscar()"
                   :placeholder="selected || 'Buscar perfume…'"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)] px-3 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-300)] placeholder:text-[var(--c-muted)]">
            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-[var(--c-muted)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </span>
            <ul x-show="open" x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-card)] shadow-lg py-1 overflow-y-auto"
                style="max-height: 260px;">
                <template x-for="op in filtradas" :key="op.key">
                    <li @click="elegir(op)"
                        :class="op.key === $wire.seguirProdKey ? 'bg-[var(--color-primary-600)] text-white' : 'text-[var(--c-text)] hover:bg-[var(--c-bg)]'"
                        class="px-3 py-1.5 text-sm cursor-pointer"
                        x-text="op.label">
                    </li>
                </template>
                <li x-show="filtradas.length === 0 && !buscando" class="px-3 py-2 text-sm text-[var(--c-muted)]">Sin resultados</li>
                <li x-show="buscando" class="px-3 py-2 text-sm text-[var(--c-muted)]">Buscando…</li>
            </ul>
        </div>
    </div>

    @php $seguir = json_decode($seguirJson, true) ?? []; @endphp

    @if(!empty($seguir))
        @php
            $seguirDescMax = max(array_column($seguir, 'descuento_pct'));
            $descHoy = end($seguir)['descuento_pct'];
            $precioMin = min(array_column($seguir, 'precio_actual'));
        @endphp

        @if($descHoy >= $seguirDescMax)
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-emerald-800 text-sm font-medium mb-4">
            🔥 Está en su <strong>mejor descuento histórico</strong> ({{ $descHoy }}%) dentro de los snapshots cargados.
        </div>
        @else
        <div class="rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 text-blue-800 text-sm mb-4">
            El mejor descuento que tuvo fue <strong>{{ $seguirDescMax }}%</strong> · precio más bajo: ${{ number_format($precioMin, 0, ',', '.') }}. Hoy está en {{ $descHoy }}%.
        </div>
        @endif
    @endif

    <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4 mb-5" x-init="$nextTick(()=>buildSeguir())">
        <div id="chart-seguir"></div>
    </div>

    @if(!empty($seguir))
    <div class="overflow-x-auto rounded-xl border border-[var(--c-border)]">
        <table class="w-full text-sm">
            <thead class="bg-[var(--color-primary-600)] text-white">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase">Fecha</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">P. lista</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">P. actual</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase">Desc.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--c-border)]">
                @foreach(array_reverse($seguir) as $r)
                <tr class="hover:bg-[var(--color-primary-50)]">
                    <td class="px-3 py-2 text-[var(--c-muted)]">{{ $r['fecha'] }}</td>
                    <td class="px-3 py-2 text-right text-slate-400 line-through text-xs">${{ number_format($r['precio_lista'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-semibold text-violet-500 dark:text-violet-300">${{ number_format($r['precio_actual'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right font-bold"
                        style="color:{{ $r['descuento_pct'] >= 40 ? '#2ce427' : ($r['descuento_pct'] >= 20 ? '#b427e4' : '#94a3b8') }}">{{ $r['descuento_pct'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endif
</div>
