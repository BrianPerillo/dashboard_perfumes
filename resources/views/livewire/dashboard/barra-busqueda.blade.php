{{-- Barra de búsqueda global (producto + marcas). Va debajo de "Indicadores", visible en todas las secciones del
     dashboard; filtra todas las consultas y se conserva al cambiar de página (se guarda en la sesión por rubro).
     Estado: tablaFiltroBuscar (texto) y tablaFiltroMarcas (varias marcas). --}}
<div class="mb-4">
    <div class="flex flex-wrap items-center gap-2">
        {{-- Producto --}}
        <div class="relative w-full sm:w-72">
            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--c-muted)] text-sm">🔍</span>
            <input type="text" wire:model.live.debounce.400ms="tablaFiltroBuscar" placeholder="Buscar producto…" style="padding-left:2.1rem"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] pl-8 pr-2.5 py-1.5 text-sm text-[var(--c-text)]
                          focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-500)]">
        </div>

        {{-- Marca (autocompletado, se pueden juntar varias).
             El desplegable es absoluto dentro del campo (se mueve con la página) y se cierra al hacer clic fuera,
             con Esc o al elegir una marca. --}}
        <div class="relative w-full sm:w-60"
             x-data="{ q: '', open: false }"
             @click.outside="open = false; q = ''"
             @keydown.escape.window="open = false; q = ''">
            <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--c-muted)] text-sm">🏷️</span>
            <input type="text" x-model="q" @focus="open = true" @input="open = true"
                   placeholder="Agregar marca…" style="padding-left:2.1rem" autocomplete="off"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] pl-8 pr-2.5 py-1.5 text-sm text-[var(--c-text)]
                          focus:outline-none focus:ring-1 focus:ring-[var(--color-primary-500)]">
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                 class="absolute left-0 right-0 top-full mt-1 z-40 max-h-64 overflow-y-auto rounded-lg border border-[var(--c-border)] bg-[var(--c-card)] shadow-xl">
                <template x-for="m in @js($marcasDisponibles).filter(m => m.toLowerCase().includes(q.toLowerCase())).slice(0, 40)" :key="m">
                    <button type="button" x-on:click="$wire.toggleTablaFiltroMarca(m); q = ''; open = false"
                            class="w-full px-2.5 py-1.5 text-left text-sm hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5 text-[var(--c-text)]"
                            x-text="m"></button>
                </template>
                <p x-show="@js($marcasDisponibles).filter(m => m.toLowerCase().includes(q.toLowerCase())).length === 0"
                   class="px-2.5 py-2 text-xs text-[var(--c-muted)]">Sin coincidencias</p>
            </div>
        </div>

        @if(!empty($tablaFiltroMarcas) || $tablaFiltroBuscar !== '')
        <button wire:click="limpiarBusqueda" class="text-xs text-[var(--c-muted)] hover:text-[var(--c-text)] underline whitespace-nowrap">↺ Limpiar búsqueda</button>
        @endif
    </div>

    {{-- Marcas elegidas --}}
    @if(!empty($tablaFiltroMarcas))
    <div class="mt-2 flex flex-wrap gap-1.5">
        @foreach($tablaFiltroMarcas as $m)
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium
                     bg-[var(--color-primary-100)] dark:bg-[var(--color-primary-900)]/40
                     text-[var(--color-primary-800)] dark:text-[var(--color-primary-300)] border border-[var(--color-primary-300)]">
            {{ $m }}
            <button wire:click="toggleTablaFiltroMarca(@js($m))" class="font-bold hover:text-red-500 leading-none" title="Quitar">×</button>
        </span>
        @endforeach
    </div>
    @endif
</div>
