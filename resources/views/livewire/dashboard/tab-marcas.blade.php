{{-- ════ TAB: Marcas ══════════════════════════════════ --}}
<div x-show="tab==='marcas'" x-cloak>
    @php $marcas = json_decode($marcasJson, true) ?? []; @endphp
    @if(empty($marcas))
        <p class="text-[var(--c-muted)] text-center py-8">Sin marcas disponibles para los filtros actuales.</p>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4"
             x-data="{
                 chart: null,
                 build() {
                     if (this.chart) { try { this.chart.destroy(); } catch(e) {} this.chart = null; }
                     const dark = document.documentElement.classList.contains('dark');
                     const gridColor = dark ? '#2d3148' : '#ece5f7';
                     const all = JSON.parse(this.$wire.marcasJson || '[]');
                     const data = all.slice(0, 20);
                     if (!data.length) return;
                     this.chart = new ApexCharts(this.$refs.canvas, {
                         chart:{type:'bar',height:380,toolbar:{show:false},fontFamily:'Inter,sans-serif',background:'transparent'},
                         series:[{name:'Productos en oferta',data:data.map(d=>d.total)}],
                         xaxis:{categories:data.map(d=>d.marca),labels:{formatter:v=>Math.round(v),style:{fontSize:'10px'}}},
                         yaxis:{},
                         colors:data.map(d=>{ const p=Math.min(1,d.desc_prom/60); return dark ? 'rgb('+Math.round(139+57*p)+','+Math.round(92+109*p)+','+Math.round(246-46*p)+')' : 'rgb('+Math.round(91+99*p)+',58,140)'; }),
                         plotOptions:{bar:{horizontal:true,borderRadius:3,distributed:true}},
                         legend:{show:false},tooltip:{y:{formatter:v=>v+' productos'}},grid:{borderColor:gridColor},
                     });
                     this.chart.render();
                 },
                 init() {
                     this.$nextTick(() => {
                         if (this.$el.offsetParent !== null) this.build();
                     });
                     window.addEventListener('tab-changed', e => {
                         if (e.detail.tab === 'marcas') this.$nextTick(() => this.build());
                     });
                     window.addEventListener('charts-refresh', () => {
                         if (this.$el.offsetParent !== null) this.$nextTick(() => this.build());
                     });
                 }
             }">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2">Top marcas por cantidad en oferta</h3>
            <div x-ref="canvas"></div>
        </div>
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4 flex flex-col">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2 shrink-0">Tabla de marcas</h3>
            <div class="overflow-y-auto overflow-x-auto" style="max-height: 468px;">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-[var(--c-card)] z-10">
                        <tr class="border-b border-[var(--c-border)]">
                            <th class="py-2 text-left text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Marca</th>
                            <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Prod.</th>
                            <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Prom.</th>
                            <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Máx.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--c-border)]">
                    @foreach($marcas as $m)
                    <tr class="hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5">
                        <td class="py-1.5 font-medium text-[var(--c-text)]">{{ $m['marca'] }}</td>
                        <td class="py-1.5 text-right text-[var(--c-text)]">{{ $m['total'] }}</td>
                        <td class="py-1.5 text-right"><span class="text-xs rounded-full bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300 px-2 py-0.5 font-semibold">{{ $m['desc_prom'] }}%</span></td>
                        <td class="py-1.5 text-right font-bold text-violet-500 dark:text-violet-300">{{ $m['desc_max'] }}%</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
