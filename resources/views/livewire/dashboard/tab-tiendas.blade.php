{{-- ════ TAB: Por tienda ═══════════════════════════════ --}}
<div x-show="tab==='tiendas'" x-cloak>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4"
             x-data="{
                 chart: null,
                 build() {
                     if (this.chart) { try { this.chart.destroy(); } catch(e) {} this.chart = null; }
                     const dark = document.documentElement.classList.contains('dark');
                     const gridColor = dark ? '#2d3148' : '#ece5f7';
                     const data = JSON.parse(this.$wire.resumenTiendasJson || '[]');
                     if (!data.length) return;
                     this.chart = new ApexCharts(this.$refs.canvas, {
                         chart:{type:'bar',height:260,toolbar:{show:false},fontFamily:'Inter,sans-serif',background:'transparent'},
                         series:[{name:'Productos',data:data.map(d=>d.total)}],
                         xaxis:{categories:data.map(d=>d.tienda),labels:{formatter:v=>Math.round(v)}},
                         yaxis:{},
                         colors:data.map(d=>{ const p=Math.min(1,d.desc_prom/90); return dark ? 'rgb('+Math.round(139+57*p)+','+Math.round(92+109*p)+','+Math.round(246-46*p)+')' : 'rgb('+Math.round(91+108*p)+','+Math.round(82-24*(1-p))+','+Math.round(140+10*p)+')'; }),
                         plotOptions:{bar:{horizontal:true,borderRadius:4,distributed:true}},
                         legend:{show:false},tooltip:{y:{formatter:v=>v+' productos'}},grid:{borderColor:gridColor},
                     });
                     this.chart.render();
                 },
                 init() {
                     window.addEventListener('tab-changed', e => {
                         if (e.detail.tab === 'tiendas') this.$nextTick(() => this.build());
                     });
                     window.addEventListener('charts-refresh', () => {
                         if (this.$el.offsetParent !== null) this.$nextTick(() => this.build());
                     });
                 }
             }">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2">Productos por tienda (último snapshot)</h3>
            <div x-ref="canvas"></div>
        </div>
        <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-card)] p-4 overflow-x-auto">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2">Resumen por tienda</h3>
            @php $resumen = json_decode($resumenTiendasJson, true) ?? []; @endphp
            @if(!empty($resumen))
            <table class="w-full text-sm">
                <thead><tr class="border-b border-[var(--c-border)]">
                    <th class="py-2 text-left text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Tienda</th>
                    <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Prod.</th>
                    <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Prom.</th>
                    <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Máx.</th>
                    <th class="py-2 text-right text-xs font-semibold uppercase tracking-wide text-[var(--c-muted)]">Ahorro</th>
                </tr></thead>
                <tbody class="divide-y divide-[var(--c-border)]">
                @foreach($resumen as $row)
                <tr class="hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5">
                    <td class="py-1.5 font-medium text-[var(--c-text)]">{{ $row['tienda'] }}</td>
                    <td class="py-1.5 text-right text-[var(--c-text)]">{{ $row['total'] }}</td>
                    <td class="py-1.5 text-right"><span class="inline-block rounded-full bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300 px-2 py-0.5 text-xs font-semibold">{{ $row['desc_prom'] }}%</span></td>
                    <td class="py-1.5 text-right font-semibold text-violet-500 dark:text-violet-300">{{ $row['desc_max'] }}%</td>
                    <td class="py-1.5 text-right text-[var(--c-text)]">${{ number_format($row['ahorro_total'], 0, ',', '.') }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>
</div>
