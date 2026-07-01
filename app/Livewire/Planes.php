<?php

namespace App\Livewire;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['activeTab' => 'planes'])]
#[Title('Planes · Descuentos Perfumes')]
class Planes extends Component
{
    public string $mensaje = '';
    public string $tipoMensaje = '';

    public function iniciarPago(int $planId): mixed
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $plan = Plan::findOrFail($planId);
        $user = Auth::user();

        if ($plan->esGratis()) {
            // Plan gratis: asignar directamente sin pasar por MP
            Suscripcion::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'estado'  => 'aprobado',
                'monto'   => 0,
                'aprobado_at' => now(),
                'vence_at'    => null,
            ]);
            $this->mensaje     = 'Plan Gratis activado correctamente.';
            $this->tipoMensaje = 'success';
            return null;
        }

        try {
            // Crear suscripción pendiente y redirigir a MP
            $suscripcion = Suscripcion::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'estado'  => 'pendiente',
                'monto'   => $plan->precio,
                'moneda'  => $plan->moneda,
            ]);

            $mpService = app(MercadoPagoService::class);
            $checkoutUrl = $mpService->crearPreferencia($plan, $user, $suscripcion);

            return redirect()->away($checkoutUrl);

        } catch (\Throwable $e) {
            $this->mensaje     = 'No se pudo iniciar el pago. Intentá de nuevo más tarde.';
            $this->tipoMensaje = 'error';
            logger()->error('[Planes] Error MP: ' . $e->getMessage());
            return null;
        }
    }

    public function render()
    {
        $planes = Plan::where('activo', true)->orderBy('precio')->get();

        $suscripcionActiva = Auth::check()
            ? Suscripcion::where('user_id', Auth::id())
                ->where('estado', 'aprobado')
                ->with('plan')
                ->latest('aprobado_at')
                ->first()
            : null;

        return view('livewire.planes', compact('planes', 'suscripcionActiva'));
    }
}
