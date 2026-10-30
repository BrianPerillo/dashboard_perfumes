<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    /**
     * Crea una preferencia de pago en MP y devuelve la URL de checkout.
     */
    public function crearPreferencia(Plan $plan, User $user, Suscripcion $suscripcion): string
    {
        $client = new PreferenceClient();

        $preference = $client->create([
            'items' => [[
                'id'          => $plan->slug,
                'title'       => "Plan {$plan->nombre} — Descuentos Perfumes",
                'currency_id' => $plan->moneda,
                'unit_price'  => (float) $plan->precio,
                'quantity'    => 1,
            ]],
            'payer' => [
                'email' => $user->email,
                'name'  => $user->name,
            ],
            'back_urls' => [
                'success' => route('pago.exito'),
                'failure' => route('pago.error'),
                'pending' => route('pago.pendiente'),
            ],
            'auto_return'        => 'approved',
            'notification_url'   => route('webhook.mercadopago'),
            'external_reference' => (string) $suscripcion->id,
            'statement_descriptor' => 'Descuentos Perfumes',
            'expires'            => false,
        ]);

        $suscripcion->update([
            'mp_preference_id'      => $preference->id,
            'mp_external_reference' => (string) $suscripcion->id,
        ]);

        // En sandbox usa sandbox_init_point, en producción init_point
        $esSandbox = str_contains(config('services.mercadopago.access_token', ''), 'TEST');

        return $esSandbox ? $preference->sandbox_init_point : $preference->init_point;
    }

    /**
     * Obtiene y procesa el pago recibido por webhook.
     * Devuelve el estado resultante o null si hubo error.
     */
    public function procesarPago(string $paymentId): ?string
    {
        try {
            $client  = new PaymentClient();
            $payment = $client->get((int) $paymentId);

            $suscripcion = Suscripcion::where('mp_external_reference', $payment->external_reference)
                ->latest()
                ->first();

            if (!$suscripcion) {
                Log::warning("[MP] Pago {$paymentId}: no se encontró suscripcion con ref={$payment->external_reference}");
                return null;
            }

            // Idempotencia: si ya fue aprobado con este mismo pago, no reprocess
            if ($suscripcion->mp_payment_id == $payment->id && $suscripcion->estado === 'aprobado') {
                Log::info("[MP] Pago {$paymentId} ya procesado anteriormente, ignorando replay");
                return 'aprobado';
            }

            $estado = match ($payment->status) {
                'approved'    => 'aprobado',
                'rejected'    => 'rechazado',
                'cancelled'   => 'cancelado',
                'in_process'  => 'en_proceso',
                'pending'     => 'pendiente',
                default       => 'pendiente',
            };

            // Validar que el monto cobrado coincida con el precio del plan
            if ($estado === 'aprobado' && $suscripcion->plan) {
                $diferencia = abs($payment->transaction_amount - (float) $suscripcion->plan->precio);
                if ($diferencia > 0.01) {
                    Log::error("[MP] Pago {$paymentId}: monto {$payment->transaction_amount} no coincide con precio del plan {$suscripcion->plan->precio}");
                    return null;
                }
            }

            $updates = [
                'mp_payment_id' => $payment->id,
                'estado'        => $estado,
                'monto'         => $payment->transaction_amount,
                'mp_data'       => (array) $payment,
            ];

            if ($estado === 'aprobado') {
                $updates['aprobado_at'] = Carbon::now();
                $updates['vence_at']    = Carbon::now()->addMonth();
            }

            $suscripcion->update($updates);

            Log::info("[MP] Pago {$paymentId} procesado → estado: {$estado}");

            return $estado;

        } catch (\Throwable $e) {
            Log::error("[MP] Error procesando pago {$paymentId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Valida la firma HMAC del webhook de MP (nuevo estilo).
     * Devuelve true si la firma es válida (o si no hay secret configurado).
     */
    public function validarFirmaWebhook(string $xSignature, string $xRequestId, string $dataId): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        if (!$secret) {
            Log::error('[MP Webhook] MP_WEBHOOK_SECRET no configurado — rechazando webhook');
            return false;
        }

        // Extraer ts y v1 de x-signature: "ts=...,v1=..."
        $parts = [];
        foreach (explode(',', $xSignature) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($k)] = trim($v);
        }

        $ts = $parts['ts'] ?? '';
        $v1 = $parts['v1'] ?? '';

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $expected  = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $v1);
    }
}
