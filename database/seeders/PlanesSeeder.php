<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanesSeeder extends Seeder
{
    public function run(): void
    {
        $planes = [
            [
                'slug'            => 'gratis',
                'nombre'          => 'Gratis',
                'precio'          => 0,
                'moneda'          => 'ARS',
                'max_alertas'     => 1,
                'canal_telegram'  => false,
                'features'        => ['1 alerta activa', 'Notificación por email', 'Acceso al dashboard'],
                'activo'          => true,
            ],
            [
                'slug'            => 'pro',
                'nombre'          => 'Pro',
                'precio'          => 1500,
                'moneda'          => 'ARS',
                'max_alertas'     => 10,
                'canal_telegram'  => true,
                'features'        => ['10 alertas activas', 'Email + Telegram', 'Historial de precios', 'Soporte prioritario'],
                'activo'          => true,
            ],
            [
                'slug'            => 'premium',
                'nombre'          => 'Premium',
                'precio'          => 3500,
                'moneda'          => 'ARS',
                'max_alertas'     => -1,
                'canal_telegram'  => true,
                'features'        => ['Alertas ilimitadas', 'Email + Telegram', 'Historial completo', 'Acceso anticipado a nuevas funciones', 'Soporte prioritario'],
                'activo'          => true,
            ],
        ];

        foreach ($planes as $data) {
            $data['features'] = json_encode($data['features']);
            Plan::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
