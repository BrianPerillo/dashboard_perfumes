<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['activeTab' => 'admin'])]
#[Title('Admin · Descuentos Perfumes')]
class Dashboard extends Component
{
    public string $periodo   = '7';
    public string $mensajeU  = '';
    public bool   $gaOk      = false;
    public array  $gaMetrics = [];
    public array  $gaPages   = [];
    public array  $gaCountries = [];

    public function mount(): void
    {
        $this->cargarAnalytics();
    }

    public function updatedPeriodo(): void
    {
        $this->cargarAnalytics();
    }

    private function cargarAnalytics(): void
    {
        $propertyId = config('analytics.property_id');
        if (!$propertyId || !class_exists(\Spatie\Analytics\Facades\Analytics::class)) {
            $this->gaOk = false;
            return;
        }

        try {
            $days   = (int) $this->periodo;
            $period = \Spatie\Analytics\Period::days($days);

            // Métricas generales
            $general = \Spatie\Analytics\Facades\Analytics::get(
                $period,
                ['screenPageViews', 'sessions', 'totalUsers', 'bounceRate', 'averageSessionDuration']
            );

            if ($general->isNotEmpty()) {
                $row = $general->first();
                $this->gaMetrics = [
                    'pageviews'   => number_format($row['screenPageViews'] ?? 0, 0, ',', '.'),
                    'sessions'    => number_format($row['sessions'] ?? 0, 0, ',', '.'),
                    'users'       => number_format($row['totalUsers'] ?? 0, 0, ',', '.'),
                    'bounce'      => round(($row['bounceRate'] ?? 0) * 100, 1) . '%',
                    'avg_session' => gmdate('i:s', (int) ($row['averageSessionDuration'] ?? 0)),
                ];
            }

            // Top páginas
            $pages = \Spatie\Analytics\Facades\Analytics::get(
                $period,
                ['screenPageViews'],
                ['pagePath'],
                10
            );

            $this->gaPages = $pages->map(fn ($r) => [
                'path'  => $r['pagePath'] ?? '/',
                'views' => number_format($r['screenPageViews'] ?? 0, 0, ',', '.'),
            ])->toArray();

            // Top países
            $countries = \Spatie\Analytics\Facades\Analytics::get(
                $period,
                ['sessions'],
                ['country'],
                8
            );

            $this->gaCountries = $countries->map(fn ($r) => [
                'country'  => $r['country'] ?? 'Unknown',
                'sessions' => number_format($r['sessions'] ?? 0, 0, ',', '.'),
            ])->toArray();

            $this->gaOk = true;
        } catch (\Throwable $e) {
            $this->gaOk = false;
            logger()->error('GA error: ' . $e->getMessage());
        }
    }

    public function toggleAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);

        // No permitir que el admin se quite el rol a sí mismo
        if ($user->id === auth()->id()) {
            $this->mensajeU = 'No podés quitarte el rol admin a vos mismo.';
            return;
        }

        $user->update(['is_admin' => !$user->is_admin]);
        $this->mensajeU = "Usuario {$user->name} actualizado.";
    }

    public function render()
    {
        $usuarios = User::orderByDesc('is_admin')->orderBy('name')->get();

        // Estadísticas rápidas de la DB
        $dbStats = [
            'usuarios'     => User::count(),
            'admins'       => User::where('is_admin', true)->count(),
            'alertas'      => \App\Models\Alerta::count(),
            'alertas_mail' => \App\Models\Alerta::where('canal', 'email')->count(),
        ];

        return view('livewire.admin.dashboard', compact('usuarios', 'dbStats'));
    }
}
