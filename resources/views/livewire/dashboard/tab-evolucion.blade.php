{{-- ════ TAB: Evolución ════════════════════════════════ --}}
<div x-show="tab==='evolucion'" x-cloak>
    {{-- En mobile: -mx-5 cancela el p-5 del card padre para aprovechar todo el ancho --}}
    <div class="-mx-5 md:mx-0 grid grid-cols-1 gap-0 md:gap-5">
        <div class="border-t border-[var(--c-border)] md:rounded-xl md:border bg-[var(--c-card)] pt-3 pb-2 px-2 md:p-4">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2 px-2 md:px-0">{{ config("rubros.rubros.$rubro.items_label") }} por corrida</h3>
            <div id="chart-evo-prod"></div>
        </div>
        <div class="border-t border-[var(--c-border)] md:rounded-xl md:border bg-[var(--c-card)] pt-3 pb-2 px-2 md:p-4">
            <h3 class="text-sm font-semibold text-[var(--c-muted)] mb-2 px-2 md:px-0">Descuento % en el tiempo</h3>
            <div id="chart-evo-desc"></div>
        </div>
        {{-- chart-evo-ahorro oculto --}}
    </div>
</div>
