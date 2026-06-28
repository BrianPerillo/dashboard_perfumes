<?php

namespace App\Livewire;

use App\Models\Corrida;
use App\Models\Observacion;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class KpiPanel extends Component
{
    private const MARCAS_EXCLUIDAS = ['rimmel'];
    public array $kpis = [];
    public ?string $fechaUltima = null;
    public ?string $fechaAnterior = null;

    public function mount(): void
    {
        $this->cargarKpis();
    }

    private function cargarKpis(): void
    {
        // Las dos corridas más recientes
        $corridas = Corrida::orderByDesc('fecha_hora')->limit(2)->pluck('id', 'fecha_hora');

        if ($corridas->isEmpty()) {
            return;
        }

        $ids       = $corridas->values()->toArray();
        $fechas    = $corridas->keys()->toArray();
        $idActual  = $ids[0];
        $idAnterior = $ids[1] ?? null;

        $this->fechaUltima   = \Carbon\Carbon::parse($fechas[0])->format('d/m/Y H:i');
        $this->fechaAnterior = $idAnterior
            ? \Carbon\Carbon::parse($fechas[1])->format('d/m/Y H:i')
            : null;

        // Métricas del snapshot actual (solo rebajas para stats de descuento)
        $actual = Observacion::deCorrida($idActual)->rebajas()
            ->join('productos as p', 'p.id', '=', 'observaciones.producto_id')
            ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
            ->selectRaw('COUNT(*) AS total, AVG(descuento_pct) AS desc_prom, MAX(descuento_pct) AS desc_max, SUM(ahorro) AS ahorro_total')
            ->first();

        // Tiendas activas: todas las que tienen cualquier tipo de observación (incluye transferencia)
        $tiendasActivas = Observacion::deCorrida($idActual)
            ->join('productos as p', 'p.id', '=', 'observaciones.producto_id')
            ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
            ->distinct('p.tienda_id')
            ->count('p.tienda_id');

        // Métricas del snapshot anterior (para deltas)
        $anterior = null;
        if ($idAnterior) {
            $anterior = Observacion::deCorrida($idAnterior)->rebajas()
                ->join('productos as p', 'p.id', '=', 'observaciones.producto_id')
                ->when(!empty(self::MARCAS_EXCLUIDAS), fn($q) => $q->where(fn($q2) => $q2->whereNull('p.marca')->orWhereNotIn(DB::raw('LOWER(p.marca)'), self::MARCAS_EXCLUIDAS)))
                ->selectRaw('COUNT(*) AS total, AVG(descuento_pct) AS desc_prom, SUM(ahorro) AS ahorro_total')
                ->first();
        }

        $this->kpis = [
            [
                'label'  => 'Perfumes en oferta',
                'value'  => number_format($actual->total ?? 0, 0, ',', '.'),
                'delta'  => $anterior ? ($actual->total - $anterior->total) : null,
                'icon'   => '🧴',
                'prefix' => '',
            ],
            [
                'label'  => 'Descuento promedio',
                'value'  => number_format($actual->desc_prom ?? 0, 1, ',', '.') . '%',
                'delta'  => $anterior ? round(($actual->desc_prom ?? 0) - ($anterior->desc_prom ?? 0), 1) : null,
                'icon'   => '🏷️',
                'prefix' => '',
                'suffix' => 'pts',
            ],
            [
                'label'  => 'Descuento máximo',
                'value'  => number_format($actual->desc_max ?? 0, 1, ',', '.') . '%',
                'delta'  => null,
                'icon'   => '🔥',
                'prefix' => '',
            ],
            [
                'label'  => 'Tiendas activas',
                'value'  => $tiendasActivas,
                'delta'  => null,
                'icon'   => '🏪',
                'prefix' => '',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.kpi-panel');
    }
}
