<?php

namespace App\Http\Controllers;

use App\Models\Suscripcion;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoController extends Controller
{
    public function exito(Request $request)
    {
        $suscripcion = null;

        if ($ref = $request->query('external_reference')) {
            $suscripcion = Suscripcion::with('plan')->find($ref);
        }

        return view('pago.exito', compact('suscripcion'));
    }

    public function pendiente(Request $request)
    {
        return view('pago.pendiente');
    }

    public function error(Request $request)
    {
        return view('pago.error');
    }

    /**
     * Webhook de MercadoPago — recibe notificaciones IPN y de pago.
     * MP espera siempre un 200 OK rápido; el procesamiento real va al log.
     */
    public function webhook(Request $request, MercadoPagoService $mp)
    {
        Log::debug('[MP Webhook]', $request->all());

        // Validar firma (nuevo estilo de webhooks MP)
        $xSignature  = $request->header('x-signature', '');
        $xRequestId  = $request->header('x-request-id', '');
        $dataId      = $request->input('data.id', '');

        if ($xSignature && !$mp->validarFirmaWebhook($xSignature, $xRequestId, $dataId)) {
            Log::warning('[MP Webhook] Firma inválida');
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $type = $request->input('type') ?? $request->query('topic');

        if ($type === 'payment') {
            $paymentId = $dataId ?: $request->query('id');
            if ($paymentId) {
                $mp->procesarPago((string) $paymentId);
            }
        }

        return response()->json(['ok' => true]);
    }
}
