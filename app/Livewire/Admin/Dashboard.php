<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;
use App\Services\MercadoPagoService;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['activeTab' => 'admin'])]
#[Title('Admin · Descuentos Perfumes')]
class Dashboard extends Component
{
    public string $periodo      = '30';
    public string $mensajeU     = '';
    public bool   $gaOk         = false;

    // Test de integraciones
    public string $mensajeTest      = '';
    public string $tipoMensajeTest  = '';
    public string $telegramChatTest = '';

    // Envío manual de alertas
    public string $mensajeAlertas     = '';
    public string $tipoMensajeAlertas = '';
    public array  $gaMetrics    = [];
    public array  $gaPages      = [];
    public array  $gaDevices    = [];
    public array  $gaDailyVisits = [];
    public int    $gaActiveUsers = 0;

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
        $credentialsPath = base_path(config('services.google.analytics_credentials'));
        $propertyId      = config('services.google.analytics_property_id');

        if (!$propertyId || !file_exists($credentialsPath)) {
            $this->gaOk = false;
            return;
        }

        try {
            $client   = new BetaAnalyticsDataClient(['credentials' => $credentialsPath]);
            $days     = (int) $this->periodo;
            $range    = new DateRange(['start_date' => "{$days}daysAgo", 'end_date' => 'today']);
            $property = "properties/{$propertyId}";

            // ── Métricas generales
            $res = $client->runReport(new RunReportRequest([
                'property'    => $property,
                'date_ranges' => [$range],
                'metrics'     => [
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'totalUsers']),
                    new Metric(['name' => 'averageSessionDuration']),
                ],
            ]));

            if ($res->getRows()->count() > 0) {
                $vals = [];
                foreach ($res->getRows()[0]->getMetricValues() as $v) {
                    $vals[] = $v->getValue();
                }
                $this->gaMetrics = [
                    'sessions'    => number_format((int)($vals[0] ?? 0), 0, ',', '.'),
                    'pageviews'   => number_format((int)($vals[1] ?? 0), 0, ',', '.'),
                    'users'       => number_format((int)($vals[2] ?? 0), 0, ',', '.'),
                    'avg_session' => gmdate('i:s', (int)($vals[3] ?? 0)),
                ];
            }

            // ── Visitas por día
            $dimOrder = new OrderBy();
            $dimOrder->setDimension(new DimensionOrderBy(['dimension_name' => 'date']));

            $dailyRes = $client->runReport(new RunReportRequest([
                'property'    => $property,
                'date_ranges' => [$range],
                'dimensions'  => [new Dimension(['name' => 'date'])],
                'metrics'     => [new Metric(['name' => 'sessions'])],
                'order_bys'   => [$dimOrder],
            ]));

            $daily = [];
            foreach ($dailyRes->getRows() as $row) {
                $d = $row->getDimensionValues()[0]->getValue(); // YYYYMMDD
                $daily[] = [
                    'date'     => substr($d, 6, 2) . '/' . substr($d, 4, 2),
                    'sessions' => (int)$row->getMetricValues()[0]->getValue(),
                ];
            }
            $this->gaDailyVisits = $daily;

            // ── Top páginas
            $metOrder = new OrderBy();
            $metOrder->setMetric(new MetricOrderBy(['metric_name' => 'screenPageViews']));
            $metOrder->setDesc(true);

            $pagesRes = $client->runReport(new RunReportRequest([
                'property'    => $property,
                'date_ranges' => [$range],
                'dimensions'  => [new Dimension(['name' => 'pagePath'])],
                'metrics'     => [new Metric(['name' => 'screenPageViews'])],
                'order_bys'   => [$metOrder],
                'limit'       => 8,
            ]));

            $this->gaPages = [];
            foreach ($pagesRes->getRows() as $row) {
                $this->gaPages[] = [
                    'path'  => $row->getDimensionValues()[0]->getValue(),
                    'views' => number_format((int)$row->getMetricValues()[0]->getValue(), 0, ',', '.'),
                ];
            }

            // ── Dispositivos
            $devRes = $client->runReport(new RunReportRequest([
                'property'    => $property,
                'date_ranges' => [$range],
                'dimensions'  => [new Dimension(['name' => 'deviceCategory'])],
                'metrics'     => [new Metric(['name' => 'sessions'])],
            ]));

            $devices = [];
            $total   = 0;
            foreach ($devRes->getRows() as $row) {
                $s        = (int)$row->getMetricValues()[0]->getValue();
                $total   += $s;
                $devices[] = ['device' => $row->getDimensionValues()[0]->getValue(), 'sessions' => $s];
            }
            $this->gaDevices = array_map(fn($d) => [
                'device'   => $d['device'],
                'sessions' => number_format($d['sessions'], 0, ',', '.'),
                'pct'      => $total > 0 ? round($d['sessions'] / $total * 100) : 0,
            ], $devices);

            // ── Activos ahora (realtime)
            $rtRes = $client->runRealtimeReport(new RunRealtimeReportRequest([
                'property' => $property,
                'metrics'  => [new Metric(['name' => 'activeUsers'])],
            ]));
            $this->gaActiveUsers = $rtRes->getRows()->count() > 0
                ? (int)$rtRes->getRows()[0]->getMetricValues()[0]->getValue()
                : 0;

            $this->gaOk = true;
            $client->close();

        } catch (\Throwable $e) {
            $this->gaOk = false;
            logger()->error('GA error: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Test de integraciones
    // ──────────────────────────────────────────────────────────────

    public function procesarAlertasEmail(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('alertas:procesar');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            $this->mensajeAlertas     = $output ?: 'No hay alertas activas o sin novedades.';
            $this->tipoMensajeAlertas = 'success';
        } catch (\Throwable $e) {
            $this->mensajeAlertas     = 'Error: ' . $e->getMessage();
            $this->tipoMensajeAlertas = 'error';
        }
    }

    public function testEmail(): void
    {
        try {
            $user = auth()->user();
            \Illuminate\Support\Facades\Mail::raw(
                "✅ Este es un mail de prueba desde el panel admin de Descuentos Perfumes.\n\nSi lo recibiste, el envío de mails está configurado correctamente.",
                fn($m) => $m->to($user->email)->subject('✅ Test de mail — Descuentos Perfumes')
            );
            $this->mensajeTest     = "Mail enviado a {$user->email}. Revisá tu bandeja de entrada.";
            $this->tipoMensajeTest = 'success';
        } catch (\Throwable $e) {
            $this->mensajeTest     = 'Error: ' . $e->getMessage();
            $this->tipoMensajeTest = 'error';
        }
    }

    public function testTelegram(): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->mensajeTest     = 'TELEGRAM_BOT_TOKEN no está configurado en .env';
            $this->tipoMensajeTest = 'error';
            return;
        }
        if (!$this->telegramChatTest) {
            $this->mensajeTest     = 'Ingresá tu Chat ID de Telegram antes de probar.';
            $this->tipoMensajeTest = 'error';
            return;
        }
        try {
            $response = \Illuminate\Support\Facades\Http::post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id'    => $this->telegramChatTest,
                    'text'       => "✅ Test desde el panel admin de *Descuentos Perfumes*\n\nSi recibís este mensaje, Telegram está configurado correctamente.",
                    'parse_mode' => 'Markdown',
                ]
            );
            if ($response->successful() && $response->json('ok')) {
                $this->mensajeTest     = "Mensaje enviado al chat {$this->telegramChatTest}. Revisá Telegram.";
                $this->tipoMensajeTest = 'success';
            } else {
                $this->mensajeTest     = 'Error Telegram: ' . ($response->json('description') ?? 'respuesta inválida');
                $this->tipoMensajeTest = 'error';
            }
        } catch (\Throwable $e) {
            $this->mensajeTest     = 'Error: ' . $e->getMessage();
            $this->tipoMensajeTest = 'error';
        }
    }

    public function testMercadoPago(): mixed
    {
        $token = config('services.mercadopago.access_token');
        if (!$token) {
            $this->mensajeTest     = 'MP_ACCESS_TOKEN no está configurado en .env';
            $this->tipoMensajeTest = 'error';
            return null;
        }
        try {
            $plan        = Plan::where('slug', 'pro')->firstOrFail();
            $user        = auth()->user();
            $suscripcion = Suscripcion::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'estado'  => 'pendiente',
                'monto'   => $plan->precio,
                'moneda'  => $plan->moneda,
            ]);
            $url = app(MercadoPagoService::class)->crearPreferencia($plan, $user, $suscripcion);
            return redirect()->away($url);
        } catch (\Throwable $e) {
            $this->mensajeTest     = 'Error MP: ' . $e->getMessage();
            $this->tipoMensajeTest = 'error';
            return null;
        }
    }

    public function toggleAdmin(int $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->id === auth()->id()) {
            $this->mensajeU = 'No podés quitarte el rol admin a vos mismo.';
            return;
        }

        $user->update(['is_admin' => !$user->is_admin]);
        $this->mensajeU = "Usuario {$user->name} actualizado.";
    }

    /**
     * Estado de la última corrida de cada rubro y de cada sitio/sección (tabla corrida_sitios).
     * Alerta si la última corrida es vieja (> 36 h) o si algún sitio quedó bloqueado/con error/vacío.
     */
    private function saludScrapers(): array
    {
        $out = [];
        foreach (config('rubros.rubros') as $slug => $cfg) {
            $corrida = \App\Models\Corrida::ultima($slug);
            if (!$corrida) {
                $out[] = ['rubro' => $slug, 'nombre' => $cfg['nombre'], 'icono' => $cfg['icono'], 'corrida' => null, 'sitios' => [], 'horas' => null, 'problemas' => 0];
                continue;
            }
            $sitios = \Illuminate\Support\Facades\DB::table('corrida_sitios')
                ->where('corrida_id', $corrida->id)->orderBy('tienda')->get()->all();
            $horas = (int) round($corrida->fecha_hora->diffInMinutes(now()) / 60);
            $problemas = count(array_filter($sitios, fn($x) => $x->estado !== 'ok'));
            $out[] = [
                'rubro' => $slug, 'nombre' => $cfg['nombre'], 'icono' => $cfg['icono'],
                'corrida' => $corrida, 'sitios' => $sitios, 'horas' => $horas, 'problemas' => $problemas,
                'vieja' => $horas > 36,
            ];
        }
        return $out;
    }

    public function render()
    {
        $usuarios = User::orderByDesc('is_admin')->orderBy('name')->get();

        $dbStats = [
            'usuarios'     => User::count(),
            'admins'       => User::where('is_admin', true)->count(),
            'alertas'      => \App\Models\Alerta::count(),
            'alertas_mail' => \App\Models\Alerta::where('canal', 'email')->count(),
        ];

        $saludScrapers = $this->saludScrapers();

        return view('livewire.admin.dashboard', compact('usuarios', 'dbStats', 'saludScrapers'));
    }
}
