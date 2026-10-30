<?php

namespace App\Console\Commands;

use App\Mail\AlertaDescuento;
use App\Models\Alerta;
use App\Models\Observacion;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ProcesarAlertas extends Command
{
    protected $signature   = 'alertas:procesar {--dry-run : Muestra qué enviaría sin enviar nada}';
    protected $description = 'Procesa alertas activas y envía notificaciones de descuentos';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $alertas = Alerta::with('user')->where('activo', true)->get();

        if ($alertas->isEmpty()) {
            $this->info('No hay alertas activas.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$alertas->count()} alerta(s)" . ($dryRun ? ' [DRY RUN]' : '') . '…');

        // Respeta el plan de cada usuario: solo las primeras N alertas (por id) y Telegram solo si el plan lo incluye
        foreach ($alertas->groupBy('user_id') as $deUsuario) {
            $limites = $deUsuario->first()->user?->limitesPlan() ?? ['max_alertas' => 1, 'telegram' => false, 'plan' => 'Gratis'];
            $permitidas = $limites['max_alertas'] === -1
                ? $deUsuario->sortBy('id')
                : $deUsuario->sortBy('id')->take($limites['max_alertas']);

            foreach ($deUsuario->sortBy('id') as $alerta) {
                if (!$permitidas->contains('id', $alerta->id)) {
                    $this->line("  ⏭  Alerta #{$alerta->id}: excede el límite de {$limites['max_alertas']} alerta(s) del plan {$limites['plan']}");
                    continue;
                }
                if ($alerta->canal === 'telegram' && !$limites['telegram']) {
                    $this->line("  ⏭  Alerta #{$alerta->id}: el plan {$limites['plan']} no incluye Telegram");
                    continue;
                }
                $this->procesarAlerta($alerta, $dryRun);
            }
        }

        $this->info('Listo.');
        return self::SUCCESS;
    }

    private function procesarAlerta(Alerta $alerta, bool $dryRun): void
    {
        $query = DB::table('observaciones as o')
            ->join('productos as p', 'p.id', '=', 'o.producto_id')
            ->join('tiendas as t',   't.id', '=', 'p.tienda_id')
            ->join('corridas as c',  'c.id', '=', 'o.corrida_id')
            ->where('c.rubro', $alerta->rubro ?: 'perfumes')   // cada alerta solo mira corridas de su rubro
            ->where('o.tipo_descuento', 'rebaja')
            ->whereBetween('o.descuento_pct', [max(5, (int) $alerta->descuento_min), 100])
            ->selectRaw('p.nombre as producto, p.marca, p.url, t.nombre as tienda,
                         o.precio_lista, o.precio_actual, o.descuento_pct, o.ahorro, c.fecha_hora, p.talles, p.imagen');

        // Mismos criterios que el dashboard: sin fuentes no confiables ni marcas que no son perfumes
        Observacion::filtrarFuentesValidas($query, 'o.fuente');
        Observacion::excluirMarcas($query, 'p.marca');

        // Ropa: solo productos con alguno de los talles pedidos disponible. Se busca en los talles normalizados
        // (productos.talles_norm; calzado: H10 = talle hombre, M7 = talle mujer); un número simple ("10") coincide con H10, M10 y 10.
        if ($alerta->talles) {
            $tokens = [];
            foreach (array_filter(array_map('trim', explode(',', $alerta->talles))) as $t) {
                $t = mb_strtoupper($t);
                $tokens[] = $t;
                if (preg_match('/^\d+(\.\d)?$/', $t)) {
                    array_push($tokens, 'H' . $t, 'M' . $t);
                }
            }
            $query->where(function ($w) use ($tokens) {
                foreach (array_unique($tokens) as $t) {
                    $w->orWhereRaw("UPPER(CONCAT(', ', COALESCE(p.talles_norm, p.talles), ', ')) LIKE ?", ['%, ' . addcslashes($t, '%_\\') . ', %']);
                }
            });
        }

        // Filtro por tamaño si el usuario lo especificó
        if ($alerta->tipo === 'producto' && $alerta->tamano_ml) {
            $query->where('p.tamano_ml', $alerta->tamano_ml);
        }

        // Solo novedades desde la última notificación (o todo si nunca se notificó)
        if ($alerta->ultima_notificacion_at) {
            $query->where('c.fecha_hora', '>', $alerta->ultima_notificacion_at);

            if ($alerta->solo_mejoras && $alerta->tipo === 'producto') {
                // solo_mejoras: solo avisa si el precio es estrictamente mejor (más bajo)
                // que cualquier precio ya notificado para ese producto
                $query->whereNotExists(function ($sub) use ($alerta) {
                    $sub->select(DB::raw(1))
                        ->from('observaciones as oprev')
                        ->join('corridas as cprev', 'cprev.id', '=', 'oprev.corrida_id')
                        ->whereColumn('oprev.producto_id', 'o.producto_id')
                        ->whereRaw('oprev.precio_actual <= o.precio_actual')
                        ->where('cprev.fecha_hora', '<=', $alerta->ultima_notificacion_at);
                });
            } else {
                // Dedup básico: evita reenviar el mismo producto al mismo precio
                $query->whereNotExists(function ($sub) use ($alerta) {
                    $sub->select(DB::raw(1))
                        ->from('observaciones as oprev')
                        ->join('corridas as cprev', 'cprev.id', '=', 'oprev.corrida_id')
                        ->whereColumn('oprev.producto_id', 'o.producto_id')
                        ->whereRaw('ABS(oprev.precio_actual - o.precio_actual) < 1')
                        ->where('cprev.fecha_hora', '<=', $alerta->ultima_notificacion_at);
                });
            }
        } else {
            // Primera vez: tomar la corrida más reciente
            $ultimaCorrida = DB::table('corridas')->where('rubro', $alerta->rubro ?: 'perfumes')->max('id');
            if ($ultimaCorrida) {
                $query->where('o.corrida_id', $ultimaCorrida);
            }
        }

        match ($alerta->tipo) {
            'marca'    => $query->where('p.marca', 'like', '%' . $alerta->valor . '%'),
            'tienda'   => $query->where('t.nombre', 'like', '%' . $alerta->valor . '%'),
            'producto' => $query->where('p.nombre', 'like', '%' . $alerta->valor . '%'),
        };

        // Filtro adicional de marca para alertas de producto
        if ($alerta->tipo === 'producto' && $alerta->marca_filtro) {
            $query->where('p.marca', 'like', '%' . $alerta->marca_filtro . '%');
        }

        $resultados = $query->orderByDesc('o.descuento_pct')->limit(10)->get();

        $destino = $alerta->canal === 'email' ? $alerta->email_destino : "Telegram:{$alerta->telegram_chat_id}";
        $label   = "Alerta #{$alerta->id} ({$alerta->tipo}: {$alerta->valor}) → {$destino}";

        if ($resultados->isEmpty()) {
            $this->line("  ⏭  {$label}: sin novedades");
            return;
        }

        $this->info("  ✉  {$label}: {$resultados->count()} coincidencia(s)");

        if ($dryRun) {
            foreach ($resultados as $r) {
                $this->line("     • {$r->producto} ({$r->tienda}) — {$r->descuento_pct}% OFF");
            }
            return;
        }

        try {
            if ($alerta->canal === 'email' && $alerta->email_destino) {
                Mail::to($alerta->email_destino)->send(new AlertaDescuento($alerta, $resultados));
            } elseif ($alerta->canal === 'telegram') {
                $this->enviarTelegram($alerta, $resultados);
            }

            $alerta->update(['ultima_notificacion_at' => now()]);
            $this->info("     ✓ Enviado.");
        } catch (\Throwable $e) {
            $this->error("     ✗ Error: {$e->getMessage()}");
        }
    }

    private function enviarTelegram(Alerta $alerta, Collection $resultados): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->error('     TELEGRAM_BOT_TOKEN no está configurado en .env');
            return;
        }

        $texto = "🔔 *Nuevos descuentos — {$alerta->tipoLabel()}: " . Alerta::escaparMarkdown($alerta->valor) . "*\n\n";
        foreach ($resultados->take(5) as $r) {
            $precio    = '$' . number_format($r->precio_actual, 0, ',', '.');
            $lista     = '$' . number_format($r->precio_lista, 0, ',', '.');
            $descuento = round($r->descuento_pct);
            $texto .= "• *" . Alerta::escaparMarkdown($r->producto) . "* — " . Alerta::escaparMarkdown($r->tienda) . "\n";
            $texto .= "  {$lista} → {$precio} (*{$descuento}% OFF*)\n";
            if (!empty($r->talles)) {
                $texto .= '  Talles: ' . Alerta::escaparMarkdown($r->talles) . "\n";
            }
            $texto .= "\n";
        }

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $alerta->telegram_chat_id,
            'text'       => $texto,
            'parse_mode' => 'Markdown',
        ]);

        if (!$response->successful() || !$response->json('ok')) {
            throw new \RuntimeException('Telegram error: ' . ($response->json('description') ?? 'unknown'));
        }
    }
}
