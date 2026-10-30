<div x-data="{
    tab: @entangle('tabActiva'),
    charts: {},

    _destroy(id) {
        try { if (this.charts[id]) { this.charts[id].destroy(); delete this.charts[id]; } } catch(e) {}
    },

    init() {
        this.$nextTick(() => {
            if (this.tab === 'evolucion')    this.buildEvolucion();
            if (this.tab === 'distribucion') this.buildDistribucion();
            if (this.tab === 'seguir')       this.buildSeguir();
        });
        window.addEventListener('charts-refresh', () => {
            if (this.tab === 'evolucion')    this.buildEvolucion();
            if (this.tab === 'distribucion') this.buildDistribucion();
        });
        window.addEventListener('seguir-refresh', () => {
            if (this.tab === 'seguir') this.buildSeguir();
        });
        this.$watch('tab', newTab => {
            window.dispatchEvent(new CustomEvent('tab-changed', { detail: { tab: newTab } }));
            const area = this.$el.querySelector('.tab-content-area');
            if (area) {
                area.classList.remove('tab-animate-in');
                void area.offsetWidth;
                area.classList.add('tab-animate-in');
            }
            this.$nextTick(() => {
                if (newTab === 'evolucion')    this.buildEvolucion();
                if (newTab === 'distribucion') this.buildDistribucion();
                if (newTab === 'seguir')       this.buildSeguir();
            });
        });
    },

    /* ── Evolución ─────────────────────────────────────────────── */
    buildEvolucion(jsonStr = null) {
        const dark = document.documentElement.classList.contains('dark');
        const gridColor = dark ? '#2d3148' : '#ece5f7';
        const purple = dark ? '#8b5cf6' : '#5B3A8C';
        const gold   = dark ? '#f97316' : '#ea6e00';
        const data = JSON.parse(jsonStr ?? this.$wire.serieEvolucionJson ?? '[]');
        const cats = data.map(d => d.fecha);
        const isMob = window.innerWidth < 640;
        const h = isMob ? 160 : 200;
        ['chart-evo-prod','chart-evo-desc','chart-evo-ahorro'].forEach(id => this._destroy(id));
        if (!data.length || !document.querySelector('#chart-evo-prod')) return;

        this.charts['chart-evo-prod'] = new ApexCharts(document.querySelector('#chart-evo-prod'), {
            chart: { type:'area', height:h, width:'100%', toolbar:{show:false}, fontFamily:'Inter,sans-serif', background:'transparent' },
            series: [{ name: @js(config("rubros.rubros.$rubro.items_label")), data: data.map(d=>d.total) }],
            xaxis: { categories: cats, labels:{rotate:-30, style:{fontSize:'10px'}} },
            yaxis: { labels:{formatter:v=>v.toFixed(0)} },
            colors:[purple], fill:{type:'gradient',gradient:{opacityFrom:.3,opacityTo:.05}},
            stroke:{width:2}, markers:{size:isMob?2:3}, tooltip:{shared:true}, grid:{borderColor:gridColor},
        });
        this.charts['chart-evo-prod'].render();

        this.charts['chart-evo-desc'] = new ApexCharts(document.querySelector('#chart-evo-desc'), {
            chart: { type:'line', height:h, width:'100%', toolbar:{show:false}, fontFamily:'Inter,sans-serif', background:'transparent' },
            series: [
                { name:'Promedio %', data: data.map(d=>d.desc_prom) },
                { name:'Máximo %',   data: data.map(d=>d.desc_max) },
            ],
            xaxis: { categories: cats, labels:{rotate:-30, style:{fontSize:'10px'}} },
            yaxis: { labels:{formatter:v=>v.toFixed(1)+'%'} },
            colors:[purple,gold], stroke:{width:[2,2], dashArray:[0,4]},
            markers:{size:isMob?2:3}, tooltip:{shared:true}, legend:{position:'top'}, grid:{borderColor:gridColor},
        });
        this.charts['chart-evo-desc'].render();

        this.charts['chart-evo-ahorro'] = new ApexCharts(document.querySelector('#chart-evo-ahorro'), {
            chart: { type:'area', height:h, width:'100%', toolbar:{show:false}, fontFamily:'Inter,sans-serif', background:'transparent' },
            series: [{ name:'Ahorro total $', data: data.map(d=>d.ahorro_total) }],
            xaxis: { categories: cats, labels:{rotate:-30, style:{fontSize:'10px'}} },
            yaxis: { labels:{formatter:v=>'$'+(v/1000).toFixed(0)+'k'} },
            colors:[gold], fill:{type:'gradient',gradient:{opacityFrom:.3,opacityTo:.05}},
            stroke:{width:2}, markers:{size:isMob?2:3}, grid:{borderColor:gridColor},
        });
        this.charts['chart-evo-ahorro'].render();
    },

    /* ── Distribución ──────────────────────────────────────────── */
    buildDistribucion(jsonStr = null) {
        const dark = document.documentElement.classList.contains('dark');
        const gridColor = dark ? '#2d3148' : '#ece5f7';
        const purple = dark ? '#8b5cf6' : '#5B3A8C';
        const data = JSON.parse(jsonStr ?? this.$wire.distribucionJson ?? '[]');
        const id = 'chart-distribucion';
        this._destroy(id);
        if (!data.length || !document.querySelector('#'+id)) return;
        this.charts[id] = new ApexCharts(document.querySelector('#'+id), {
            chart:{type:'bar',height:240,width:'100%',toolbar:{show:false},fontFamily:'Inter,sans-serif',background:'transparent'},
            series:[{name:'Productos',data:data.map(d=>d.n)}],
            xaxis:{categories:data.map(d=>d.rango),labels:{style:{fontSize:'10px'}}},
            colors:[purple], plotOptions:{bar:{borderRadius:5,columnWidth:'60%'}},
            tooltip:{y:{formatter:v=>v+' productos'}}, grid:{borderColor:gridColor},
        });
        this.charts[id].render();
    },

    /* ── Seguir ────────────────────────────────────────────────── */
    buildSeguir(jsonStr = null) {
        const dark = document.documentElement.classList.contains('dark');
        const gridColor = dark ? '#2d3148' : '#ece5f7';
        const purple = dark ? '#8b5cf6' : '#5B3A8C';
        const gold   = dark ? '#e8c040' : '#C9A227';
        const muted  = dark ? '#64748b' : '#ccc';
        const data = JSON.parse(jsonStr ?? this.$wire.seguirJson ?? '[]');
        const id = 'chart-seguir';
        this._destroy(id);
        if (!data.length || !document.querySelector('#'+id)) return;
        const cats = data.map(d=>d.fecha);
        this.charts[id] = new ApexCharts(document.querySelector('#'+id), {
            chart:{type:'line',height:280,width:'100%',toolbar:{show:false},fontFamily:'Inter,sans-serif',background:'transparent'},
            series:[
                {name:'Descuento %',    data:data.map(d=>d.descuento_pct), type:'line'},
                {name:'Precio actual $', data:data.map(d=>d.precio_actual), type:'line'},
                {name:'Precio lista $',  data:data.map(d=>d.precio_lista),  type:'line'},
            ],
            xaxis:{categories:cats, labels:{rotate:-30,style:{fontSize:'10px'}}},
            yaxis:[
                {title:{text:'Descuento %'}, labels:{formatter:v=>v+'%'}, seriesName:'Descuento %'},
                {opposite:true, title:{text:'Precio $'}, labels:{formatter:v=>'$'+v.toLocaleString('es-AR')}, seriesName:'Precio actual $'},
                {opposite:true, show:false, seriesName:'Precio lista $'},
            ],
            colors:[purple,gold,muted],
            stroke:{width:[3,2,1], dashArray:[0,0,4]},
            markers:{size:4}, legend:{position:'top'}, grid:{borderColor:gridColor},
        });
        this.charts[id].render();
    },
}"
>

{{-- ═══════════════ BÚSQUEDA GLOBAL (producto + marcas) ═══════════════ --}}
@include('livewire.dashboard.barra-busqueda')

{{-- ═══════════════ CARD PRINCIPAL ══════════════════════ --}}
<div class="rounded-2xl border border-[var(--c-border)] bg-white dark:bg-[var(--c-card)] shadow-sm">
    <div class="p-5 relative">

    {{-- Loading overlay: glass + spinner centrado en el viewport --}}
    <div wire:loading.delay
         class="absolute inset-0 z-30 rounded-2xl flex items-start justify-center pointer-events-none"
         style="background: color-mix(in srgb, var(--c-card) 80%, transparent); backdrop-filter: blur(2px);">
        <div style="margin-top:30vh;display:flex;flex-direction:column;align-items:center;gap:14px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 width="52" height="52"
                 style="animation:spin 1s linear infinite;flex-shrink:0;">
                <circle cx="12" cy="12" r="10" stroke="#8b5cf6" stroke-width="3" opacity="0.25"/>
                <path fill="#8b5cf6" opacity="0.9" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span style="font-size:14px;font-weight:700;color:var(--c-text);">Cargando…</span>
        </div>
    </div>
    <style>@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}</style>

    <div class="tab-content-area">
    @include('livewire.dashboard.tab-joyas')
    @include('livewire.dashboard.tab-tabla')
    @include('livewire.dashboard.tab-cambios')
    @include('livewire.dashboard.tab-comparar')
    @include('livewire.dashboard.tab-seguir')
    @include('livewire.dashboard.tab-tiendas')
    @include('livewire.dashboard.tab-distribucion')
    @include('livewire.dashboard.tab-evolucion')
    @include('livewire.dashboard.tab-transfer')
    @include('livewire.dashboard.tab-marcas')
    @include('livewire.dashboard.tab-conclusiones')
    </div>

    </div>{{-- /p-5 --}}
</div>{{-- /card --}}

{{-- ═══ FILTROS → sidebar (x-teleport al ítem activo del nav) ═══ --}}
@php
    $_fc = 0;
    if (count($tiendasSeleccionadas) < count($tiendas)) $_fc++;
    if (count($generosSeleccionados) < count($generosDisponibles)) $_fc++;
    if ($categoriaFiltro !== '') $_fc++;
    $_fc += count($tiposExcluidos) + count($tallesSeleccionados) + count($disciplinasExcluidas) + count($lineasExcluidas);
    if ($precioMin) $_fc++;
    if ($precioMax) $_fc++;
    if ($descMin > 0)  $_fc++;
    if ($descMax < 90) $_fc++;
    if (!empty($tablaFiltroBuscar))  $_fc++;
    if (!empty($tablaFiltroMarcas))  $_fc += count($tablaFiltroMarcas);
    if (!empty($tablaFiltroTiendas)) $_fc++;
    if ($joyasOcultarFreq) $_fc++;
    if (!empty($mlDisponibles) && count($mlSeleccionados) < count($mlDisponibles)) $_fc++;
@endphp
@if(!in_array($tabActiva, ['marcas', 'distribucion']))
<template x-teleport="#sidebar-filters">
<div class="sidebar-filter-panel border-t border-[var(--c-border)] bg-[var(--color-primary-50)]/40 dark:bg-white/[0.02]">

    @if($_fc > 0)
    <div class="px-3 pt-2.5 pb-1">
        <button wire:click="resetearFiltros"
                class="w-full flex items-center justify-between text-[10px] font-semibold
                       text-red-500 dark:text-red-400 hover:text-red-600
                       py-1.5 px-2.5 rounded-lg bg-red-50 dark:bg-red-900/20
                       border border-red-200 dark:border-red-800/40 transition-colors">
            <span>↺ Limpiar filtros activos</span>
            <span class="font-bold bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center">{{ $_fc }}</span>
        </button>
    </div>
    @endif

    {{-- ── Género ───────────────────────────────────────────── --}}
    <div x-data="{ open: true }" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <button @click="open = !open"
                :class="!open ? 'filter-section-closed' : ''"
                class="w-full flex items-center justify-between px-4 pt-3 pb-2 group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
                Género <span class="font-normal normal-case opacity-60">({{ count($generosSeleccionados) }}/{{ count($generosDisponibles) }})</span>
            </p>
            <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner">
                <div class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach(config("rubros.rubros.$rubro.generos") as $gkey => $glabel)
                        <button wire:click="toggleGenero('{{ $gkey }}')"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all
                                       {{ in_array($gkey, $generosSeleccionados)
                                           ? 'bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white border-[var(--color-primary-600)] hover:border-[var(--color-primary-700)]'
                                           : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]' }}">
                            {{ $glabel }}
                        </button>
                        @endforeach
                    </div>
                    <button wire:click="toggleTodosGeneros"
                            class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                        {{ count($generosSeleccionados) === count($generosDisponibles) ? '× Ninguno' : '✓ Todos' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Presentación ─────────────────────────────────────── --}}
    @if(config("rubros.rubros.$rubro.filtro_ml") && !empty($mlDisponibles))
    <div x-data="{ open: false, id: 'ml' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <button @click="open = !open"
                :class="!open ? 'filter-section-closed' : ''"
                class="w-full flex items-center justify-between px-4 pt-3 pb-2 group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
                Presentación <span class="font-normal normal-case opacity-60">({{ count($mlSeleccionados) }}/{{ count($mlDisponibles) }})</span>
            </p>
            <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner">
                <div class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach($mlDisponibles as $ml)
                        <button wire:click="toggleMl({{ $ml }})"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all
                                       {{ in_array($ml, $mlSeleccionados)
                                           ? 'bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white border-[var(--color-primary-600)] hover:border-[var(--color-primary-700)]'
                                           : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]' }}">
                            {{ $ml }} ml
                        </button>
                        @endforeach
                    </div>
                    <button wire:click="toggleTodosMl"
                            class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                        {{ count($mlSeleccionados) === count($mlDisponibles) ? '× Ninguno' : '✓ Todos' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @include('livewire.dashboard.filtros-ropa')

    {{-- ── Tiendas ──────────────────────────────────────── --}}
    <div x-data="{ open: false, id: 'tiendas' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <button @click="open = !open"
                :class="!open ? 'filter-section-closed' : ''"
                class="w-full flex items-center justify-between px-4 pt-3 pb-2 group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
                Tiendas <span class="font-normal normal-case opacity-60">({{ count($tiendasSeleccionadas) }}/{{ count($tiendas) }})</span>
            </p>
            <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner">
                <div class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach($tiendas as $tid => $tnombre)
                        <button wire:click="toggleTienda({{ $tid }})"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all
                                       {{ in_array($tid, $tiendasSeleccionadas)
                                           ? 'bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white border-[var(--color-primary-600)] hover:border-[var(--color-primary-700)]'
                                           : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]' }}">
                            {{ $tnombre }}
                        </button>
                        @endforeach
                    </div>
                    <button wire:click="toggleTodasTiendas"
                            class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                        {{ count($tiendasSeleccionadas) === count($tiendas) ? '× Ninguna' : '✓ Todas' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Descuento ─────────────────────────────────────── --}}
    @if(!in_array($tabActiva, ['seguir','cambios','conclusiones','comparar']))
    <div class="px-4 py-2 space-y-1.5 border-t border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">Descuento</p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-[var(--c-muted)] w-6 shrink-0">Mín</span>
            <input type="range" wire:model.live.debounce.400ms="descMin" min="0" max="89" step="1" class="flex-1 accent-[var(--color-primary-600)]">
            <span class="text-[10px] font-bold text-violet-500 dark:text-violet-300 w-7 text-right">{{ $descMin }}%</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-[var(--c-muted)] w-6 shrink-0">Máx</span>
            <input type="range" wire:model.live.debounce.400ms="descMax" min="1" max="90" step="1" class="flex-1 accent-[var(--color-primary-600)]">
            <span class="text-[10px] font-bold text-violet-500 dark:text-violet-300 w-7 text-right">{{ $descMax }}%</span>
        </div>
    </div>
    @endif

    {{-- ── Opciones · Joyas ─────────────────────────────── --}}
    @if($tabActiva === 'joyas')
    <div class="px-4 py-2 border-t border-[var(--c-border)]">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" wire:model.live="joyasOcultarFreq" class="rounded accent-[var(--color-primary-600)]">
            <span class="text-xs text-[var(--c-text)]">Ocultar siempre en oferta</span>
        </label>
    </div>
    @endif

    {{-- ── Período · Cambios ────────────────────────────── --}}
    @if($tabActiva === 'cambios')
    <div class="px-4 py-2 space-y-2 border-t border-[var(--c-border)]">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">Período</p>
        <div>
            <label class="text-[10px] text-[var(--c-muted)] block mb-0.5">Desde</label>
            <select wire:model.live="cambiosDesdeCorrida"
                    class="w-full rounded border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)] px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-300)]">
                @foreach($corridas as $c)<option value="{{ $c['id'] }}">{{ $c['label'] }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] text-[var(--c-muted)] block mb-0.5">Hasta</label>
            <select wire:model.live="cambiosHastaCorrida"
                    class="w-full rounded border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)] px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-300)]">
                @foreach($corridas as $c)<option value="{{ $c['id'] }}">{{ $c['label'] }}</option>@endforeach
            </select>
        </div>
    </div>
    @endif

    <div class="pb-2"></div>
</div>
</template>
@endif

{{-- ═══ FILTROS → mobile dropdown (x-teleport al #mobile-filters) ═══ --}}
<template x-teleport="#mobile-filters">
<div class="bg-[var(--color-primary-50)]/40 dark:bg-white/[0.02]">

    @if($_fc > 0)
    <div class="px-3 pt-2.5 pb-1">
        <button wire:click="resetearFiltros"
                class="w-full flex items-center justify-between text-[10px] font-semibold
                       text-red-500 dark:text-red-400 hover:text-red-600
                       py-1.5 px-2.5 rounded-lg bg-red-50 dark:bg-red-900/20
                       border border-red-200 dark:border-red-800/40 transition-colors">
            <span>↺ Limpiar filtros activos</span>
            <span class="font-bold bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center">{{ $_fc }}</span>
        </button>
    </div>
    @endif

    {{-- ── Género ───────────────────────────────────────────── --}}
    <div x-data="{ open: true }" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <button @click="open = !open"
                :class="!open ? 'filter-section-closed' : ''"
                class="w-full flex items-center justify-between px-4 pt-3 pb-2 group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
                Género <span class="font-normal normal-case opacity-60">({{ count($generosSeleccionados) }}/{{ count($generosDisponibles) }})</span>
            </p>
            <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner">
                <div class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach(config("rubros.rubros.$rubro.generos") as $gkey => $glabel)
                        <button wire:click="toggleGenero('{{ $gkey }}')"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all
                                       {{ in_array($gkey, $generosSeleccionados)
                                           ? 'bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white border-[var(--color-primary-600)] hover:border-[var(--color-primary-700)]'
                                           : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]' }}">
                            {{ $glabel }}
                        </button>
                        @endforeach
                    </div>
                    <button wire:click="toggleTodosGeneros"
                            class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                        {{ count($generosSeleccionados) === count($generosDisponibles) ? '× Ninguno' : '✓ Todos' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Presentación ─────────────────────────────────────── --}}
    @if(config("rubros.rubros.$rubro.filtro_ml") && !empty($mlDisponibles))
    <div x-data="{ open: false, id: 'ml' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <button @click="open = !open"
                :class="!open ? 'filter-section-closed' : ''"
                class="w-full flex items-center justify-between px-4 pt-3 pb-2 group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
                Presentación <span class="font-normal normal-case opacity-60">({{ count($mlSeleccionados) }}/{{ count($mlDisponibles) }})</span>
            </p>
            <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner">
                <div class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach($mlDisponibles as $ml)
                        <button wire:click="toggleMl({{ $ml }})"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all
                                       {{ in_array($ml, $mlSeleccionados)
                                           ? 'bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white border-[var(--color-primary-600)] hover:border-[var(--color-primary-700)]'
                                           : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]' }}">
                            {{ $ml }} ml
                        </button>
                        @endforeach
                    </div>
                    <button wire:click="toggleTodosMl"
                            class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                        {{ count($mlSeleccionados) === count($mlDisponibles) ? '× Ninguno' : '✓ Todos' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @include('livewire.dashboard.filtros-ropa')

    {{-- ── Tiendas ──────────────────────────────────────── --}}
    <div x-data="{ open: false, id: 'tiendas' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <button @click="open = !open"
                :class="!open ? 'filter-section-closed' : ''"
                class="w-full flex items-center justify-between px-4 pt-3 pb-2 group">
            <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
                Tiendas <span class="font-normal normal-case opacity-60">({{ count($tiendasSeleccionadas) }}/{{ count($tiendas) }})</span>
            </p>
            <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </span>
        </button>
        <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
            <div class="collapsible-inner">
                <div class="px-4 pb-3">
                    <div class="flex flex-wrap gap-1 mb-2">
                        @foreach($tiendas as $tid => $tnombre)
                        <button wire:click="toggleTienda({{ $tid }})"
                                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all
                                       {{ in_array($tid, $tiendasSeleccionadas)
                                           ? 'bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white border-[var(--color-primary-600)] hover:border-[var(--color-primary-700)]'
                                           : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]' }}">
                            {{ $tnombre }}
                        </button>
                        @endforeach
                    </div>
                    <button wire:click="toggleTodasTiendas"
                            class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                        {{ count($tiendasSeleccionadas) === count($tiendas) ? '× Ninguna' : '✓ Todas' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Descuento ─────────────────────────────────────── --}}
    @if(!in_array($tabActiva, ['seguir','cambios','conclusiones','comparar']))
    <div class="px-4 py-2 space-y-1.5 border-t border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">Descuento</p>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-[var(--c-muted)] w-6 shrink-0">Mín</span>
            <input type="range" wire:model.live.debounce.400ms="descMin" min="0" max="89" step="1" class="flex-1 accent-[var(--color-primary-600)]">
            <span class="text-[10px] font-bold text-violet-500 dark:text-violet-300 w-7 text-right">{{ $descMin }}%</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-[var(--c-muted)] w-6 shrink-0">Máx</span>
            <input type="range" wire:model.live.debounce.400ms="descMax" min="1" max="90" step="1" class="flex-1 accent-[var(--color-primary-600)]">
            <span class="text-[10px] font-bold text-violet-500 dark:text-violet-300 w-7 text-right">{{ $descMax }}%</span>
        </div>
    </div>
    @endif

    {{-- ── Opciones · Joyas ─────────────────────────────── --}}
    @if($tabActiva === 'joyas')
    <div class="px-4 py-2 border-t border-[var(--c-border)]">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" wire:model.live="joyasOcultarFreq" class="rounded accent-[var(--color-primary-600)]">
            <span class="text-xs text-[var(--c-text)]">Ocultar siempre en oferta</span>
        </label>
    </div>
    @endif

    {{-- ── Período · Cambios ────────────────────────────── --}}
    @if($tabActiva === 'cambios')
    <div class="px-4 py-2 space-y-2 border-t border-[var(--c-border)]">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">Período</p>
        <div>
            <label class="text-[10px] text-[var(--c-muted)] block mb-0.5">Desde</label>
            <select wire:model.live="cambiosDesdeCorrida"
                    class="w-full rounded border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)] px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-300)]">
                @foreach($corridas as $c)<option value="{{ $c['id'] }}">{{ $c['label'] }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="text-[10px] text-[var(--c-muted)] block mb-0.5">Hasta</label>
            <select wire:model.live="cambiosHastaCorrida"
                    class="w-full rounded border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)] px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-300)]">
                @foreach($corridas as $c)<option value="{{ $c['id'] }}">{{ $c['label'] }}</option>@endforeach
            </select>
        </div>
    </div>
    @endif

    <div class="pb-2"></div>
</div>
</template>

</div>{{-- /x-data --}}

@script
<script>
    window.dispatchEvent(new CustomEvent('filter-count-changed', { detail: {{ $_fc }} }));
</script>
@endscript
