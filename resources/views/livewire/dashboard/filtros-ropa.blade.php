{{-- Filtros de ropa y calzado: categoría, tipo de prenda, disciplina, precio y talles (agrupados por sistema).
     Se incluye en el panel del sidebar y en el del menú móvil. Opciones calculadas en Dashboard::cargarOpcionesRopa(). --}}
@if(config("rubros.rubros.$rubro.filtro_categoria"))
@php
    $chip = fn($activo) => $activo
        ? 'bg-[var(--color-primary-600)] text-white border-[var(--color-primary-600)]'
        : 'text-[var(--c-muted)] border-[var(--c-border)] hover:border-[var(--color-primary-400)] hover:text-[var(--color-primary-600)]';
    // Nombres de prenda más amigables (el valor guardado/filtrado sigue siendo el original de la tienda)
    $nombreTipo = fn($t) => [
        'Shorts-bermudas' => 'Shorts y bermudas', 'Tops-deportivos' => 'Tops deportivos',
        'Pollera-vestido' => 'Polleras y vestidos', 'Calzado' => 'Zapatillas', 'Botines' => 'Botines',
    ][$t] ?? str_replace('-', ' ', $t);
@endphp

{{-- Categoría --}}
<div class="border-b border-[var(--c-border)] px-4 py-3" wire:loading.class="opacity-50 pointer-events-none">
    <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)] mb-2">Categoría</p>
    <div class="flex flex-wrap gap-1">
        @foreach(['' => 'Todas', 'ropa' => 'Ropa', 'calzado' => 'Calzado', 'accesorios' => 'Accesorios'] as $ckey => $clabel)
        <button wire:click="setCategoria('{{ $ckey }}')"
                class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all {{ $chip($categoriaFiltro === $ckey) }}">
            {{ $clabel }}
        </button>
        @endforeach
    </div>
</div>

{{-- Tipo de prenda (remera, campera, short, zapatillas, botines…) --}}
@if(!empty($tiposDisponibles))
<div x-data="{ open: false, id: 'tipo' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
    <button @click="open = !open" :class="!open ? 'filter-section-closed' : ''"
            class="w-full flex items-center justify-between px-4 pt-3 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
            Prenda / tipo
            <span class="font-normal normal-case opacity-60">({{ count($tiposDisponibles) - count($tiposExcluidos) }}/{{ count($tiposDisponibles) }})</span>
        </p>
        <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </button>
    <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
        <div class="collapsible-inner">
            <div class="px-4 pb-3 flex flex-wrap gap-1">
                @foreach($tiposDisponibles as $t)
                <button wire:click="toggleTipo(@js($t['v']))"
                        class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all {{ $chip(!in_array($t['v'], $tiposExcluidos, true)) }}">
                    {{ $nombreTipo($t['v']) }} <span class="opacity-60 font-normal">{{ $t['n'] }}</span>
                </button>
                @endforeach
            </div>
            <div class="px-4 pb-3 -mt-1">
                <button wire:click="toggleTodosAtributo('tipo')" class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                    {{ empty($tiposExcluidos) ? '× Ninguno' : '✓ Todos' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Disciplina / deporte (Fútbol, Running, Moda, Básquet…) --}}
@if(!empty($disciplinasDisponibles))
<div x-data="{ open: false, id: 'deporte' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
    <button @click="open = !open" :class="!open ? 'filter-section-closed' : ''"
            class="w-full flex items-center justify-between px-4 pt-3 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
            Deporte
            <span class="font-normal normal-case opacity-60">({{ count($disciplinasDisponibles) - count($disciplinasExcluidas) }}/{{ count($disciplinasDisponibles) }})</span>
        </p>
        <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </button>
    <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
        <div class="collapsible-inner">
            <div class="px-4 pb-3 flex flex-wrap gap-1">
                @foreach($disciplinasDisponibles as $d)
                <button wire:click="toggleDisciplina(@js($d['v']))"
                        class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all {{ $chip(!in_array($d['v'], $disciplinasExcluidas, true)) }}">
                    {{ $d['v'] }} <span class="opacity-60 font-normal">{{ $d['n'] }}</span>
                </button>
                @endforeach
            </div>
            <div class="px-4 pb-3 -mt-1">
                <button wire:click="toggleTodosAtributo('disciplina')" class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                    {{ empty($disciplinasExcluidas) ? '× Ninguno' : '✓ Todos' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Línea de producto (Air Max, Mercurial, Air Force 1…) --}}
@if(!empty($lineasDisponibles))
<div x-data="{ open: false, id: 'linea' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
    <button @click="open = !open" :class="!open ? 'filter-section-closed' : ''"
            class="w-full flex items-center justify-between px-4 pt-3 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
            Línea
            <span class="font-normal normal-case opacity-60">({{ count($lineasDisponibles) - count($lineasExcluidas) }}/{{ count($lineasDisponibles) }})</span>
        </p>
        <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </button>
    <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
        <div class="collapsible-inner">
            <div class="px-4 pb-3 flex flex-wrap gap-1">
                @foreach($lineasDisponibles as $l)
                <button wire:click="toggleLinea(@js($l['v']))"
                        class="px-2 py-0.5 rounded text-[10px] font-semibold border transition-all {{ $chip(!in_array($l['v'], $lineasExcluidas, true)) }}">
                    {{ $l['v'] }} <span class="opacity-60 font-normal">{{ $l['n'] }}</span>
                </button>
                @endforeach
            </div>
            <div class="px-4 pb-3 -mt-1">
                <button wire:click="toggleTodosAtributo('linea')" class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
                    {{ empty($lineasExcluidas) ? '× Ninguno' : '✓ Todos' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Precio y agrupación --}}
<div x-data="{ open: false, id: 'precio' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
    <button @click="open = !open" :class="!open ? 'filter-section-closed' : ''"
            class="w-full flex items-center justify-between px-4 pt-3 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
            Precio y colores
            @if($precioMin || $precioMax)<span class="font-normal normal-case opacity-60">(precio filtrado)</span>@endif
        </p>
        <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </button>
    <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
        <div class="collapsible-inner">
            <div class="px-4 pb-3">
                <p class="text-[10px] text-[var(--c-muted)] mb-1">Precio actual ($)</p>
                <div class="flex items-center gap-2">
                    <input type="number" min="0" step="1000" placeholder="Mín" wire:model.live.debounce.700ms="precioMin"
                           class="w-1/2 rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] px-2 py-1 text-xs text-[var(--c-text)]">
                    <span class="text-[var(--c-muted)] text-xs">–</span>
                    <input type="number" min="0" step="1000" placeholder="Máx" wire:model.live.debounce.700ms="precioMax"
                           class="w-1/2 rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] px-2 py-1 text-xs text-[var(--c-text)]">
                </div>
                <label class="mt-3 flex items-center gap-2 text-[11px] text-[var(--c-muted)] cursor-pointer">
                    <input type="checkbox" wire:model.live="agruparColores" class="rounded accent-[var(--color-primary-600)]">
                    Agrupar colores del mismo modelo
                </label>
            </div>
        </div>
    </div>
</div>

{{-- Talles --}}
@if(!empty($tallesDisponibles))
<div x-data="{ open: false, id: 'talle' }" @acc-abrir.window="if ($event.detail !== id) open = false" x-effect="if (open) $dispatch('acc-abrir', id)" class="border-b border-[var(--c-border)]" wire:loading.class="opacity-50 pointer-events-none">
    <button @click="open = !open" :class="!open ? 'filter-section-closed' : ''"
            class="w-full flex items-center justify-between px-4 pt-3 pb-2">
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--c-muted)]">
            Talle
            @if(count($tallesSeleccionados))<span class="font-normal normal-case opacity-60">({{ count($tallesSeleccionados) }} elegidos)</span>@endif
        </p>
        <span class="text-[var(--c-muted)] transition-transform duration-200" :class="open ? 'rotate-180' : ''">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </button>
    <div class="collapsible-wrap" :class="open ? 'is-open' : ''">
        <div class="collapsible-inner">
            <div class="px-4 pb-3 space-y-2.5">
                @foreach($tallesDisponibles as $grupo)
                <div>
                    <p class="text-[10px] text-[var(--c-muted)] mb-1">{{ $grupo['grupo'] }}</p>
                    <div class="flex flex-wrap gap-1">
                        @foreach($grupo['items'] as $t)
                        <button wire:click="toggleTalle(@js($t['v']))" title="{{ $t['n'] }} producto(s) con este talle disponible"
                                class="min-w-[2rem] px-1.5 py-0.5 rounded text-[10px] font-semibold border transition-all {{ $chip(in_array($t['v'], $tallesSeleccionados, true)) }}">
                            {{ $t['e'] ?? $t['v'] }}
                        </button>
                        @endforeach
                    </div>
                </div>
                @endforeach
                @if(collect($tallesSeleccionados)->contains(fn($t) => preg_match('/^[HM]\d/', $t)))
                <label class="flex items-start gap-1.5 text-[10px] text-[var(--c-muted)] cursor-pointer" title="El talle de mujer (M) es el de hombre (H) + 1,5: H8 = M9.5">
                    <input type="checkbox" wire:model.live="tallesEquivalentes" class="mt-0.5 rounded border-[var(--c-border)] accent-violet-600">
                    <span>Incluir también el talle equivalente (H ↔ M, +1,5)</span>
                </label>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

@if(count($tallesSeleccionados) || count($tiposExcluidos) || count($disciplinasExcluidas) || count($lineasExcluidas))
<div class="px-4 py-2 border-b border-[var(--c-border)]">
    <button wire:click="limpiarTallesTipos" class="text-[10px] font-semibold text-[var(--color-primary-600)] hover:underline">
        ↺ Restablecer prenda, deporte, línea y talles
    </button>
</div>
@endif
@endif
