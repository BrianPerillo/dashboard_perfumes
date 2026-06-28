<?php

namespace App\Livewire;

use App\Mail\AlertaDescuento;
use App\Models\Alerta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app', ['activeTab' => 'notificaciones'])]
#[Title('Alertas · Descuentos Perfumes')]
class Notificaciones extends Component
{
    public string $tipo          = 'producto';
    public string $valor         = '';
    public string $canal         = 'email';
    public string $emailDestino  = '';
    public string $telegramChatId = '';
    public array  $sugerencias   = [];
    public bool   $editando      = false;
    public string $mensajeExito  = '';
    public string $mensajeError  = '';

    public function mount(): void
    {
        if (!auth()->check()) {
            return;
        }

        $alerta = Alerta::where('user_id', auth()->id())->first();

        if ($alerta) {
            $this->tipo           = $alerta->tipo;
            $this->valor          = $alerta->valor;
            $this->canal          = $alerta->canal;
            $this->emailDestino   = $alerta->email_destino ?? auth()->user()->email;
            $this->telegramChatId = $alerta->telegram_chat_id ?? '';
        } else {
            $this->emailDestino = auth()->user()->email;
            $this->editando = true;
        }

        $this->cargarSugerencias();
    }

    public function updatedTipo(): void
    {
        $this->valor = '';
        $this->sugerencias = [];
        $this->cargarSugerencias();
    }

    public function updatedValor(): void
    {
        $this->cargarSugerencias();
    }

    private function cargarSugerencias(): void
    {
        if ($this->tipo === 'marca') {
            $this->sugerencias = DB::table('productos')
                ->whereNotNull('marca')->where('marca', '!=', '')
                ->when($this->valor, fn($q) => $q->where('marca', 'like', '%' . $this->valor . '%'))
                ->distinct()->orderBy('marca')->limit(60)->pluck('marca')->values()->toArray();
        } elseif ($this->tipo === 'tienda') {
            $this->sugerencias = DB::table('tiendas')
                ->when($this->valor, fn($q) => $q->where('nombre', 'like', '%' . $this->valor . '%'))
                ->orderBy('nombre')->pluck('nombre')->toArray();
        } else {
            if (mb_strlen($this->valor) >= 3) {
                $this->sugerencias = DB::table('productos')
                    ->where('nombre', 'like', '%' . $this->valor . '%')
                    ->distinct()->orderBy('nombre')->limit(20)->pluck('nombre')->toArray();
            } else {
                $this->sugerencias = [];
            }
        }
    }

    public function activarEdicion(): void
    {
        $this->editando = true;
        $this->mensajeExito = '';
        $this->mensajeError = '';
        $this->cargarSugerencias();
    }

    public function cancelarEdicion(): void
    {
        $alerta = Alerta::where('user_id', auth()->id())->first();
        if (!$alerta) return;
        $this->tipo           = $alerta->tipo;
        $this->valor          = $alerta->valor;
        $this->canal          = $alerta->canal;
        $this->emailDestino   = $alerta->email_destino ?? auth()->user()->email;
        $this->telegramChatId = $alerta->telegram_chat_id ?? '';
        $this->editando       = false;
        $this->mensajeError   = '';
    }

    public function guardar(): void
    {
        if (!auth()->check()) return;

        $this->validate([
            'tipo'          => 'required|in:marca,tienda,producto',
            'valor'         => 'required|string|min:2|max:200',
            'canal'         => 'required|in:email,telegram',
            'emailDestino'  => 'required_if:canal,email|nullable|email|max:200',
            'telegramChatId'=> 'required_if:canal,telegram|nullable|string|max:100',
        ], [
            'valor.required'         => 'Ingresá qué querés monitorear.',
            'valor.min'              => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'emailDestino.required_if'  => 'Ingresá un email para recibir las notificaciones.',
            'emailDestino.email'     => 'El email no es válido.',
            'telegramChatId.required_if' => 'Ingresá tu Chat ID de Telegram.',
        ]);

        Alerta::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'tipo'               => $this->tipo,
                'valor'              => trim($this->valor),
                'canal'              => $this->canal,
                'email_destino'      => $this->canal === 'email' ? $this->emailDestino : null,
                'telegram_chat_id'   => $this->canal === 'telegram' ? $this->telegramChatId : null,
                'activo'             => true,
                'ultima_notificacion_at' => null,
            ]
        );

        $this->editando     = false;
        $this->mensajeExito = '¡Alerta activada! Te avisaremos cuando encontremos descuentos.';
        $this->mensajeError = '';
    }

    public function eliminar(): void
    {
        if (!auth()->check()) return;

        Alerta::where('user_id', auth()->id())->delete();

        $this->tipo           = 'producto';
        $this->valor          = '';
        $this->canal          = 'email';
        $this->emailDestino   = auth()->user()->email;
        $this->telegramChatId = '';
        $this->editando       = true;
        $this->mensajeExito   = 'Alerta eliminada.';
        $this->mensajeError   = '';
        $this->cargarSugerencias();
    }

    public function enviarPrueba(): void
    {
        if (!auth()->check()) return;

        $alerta = Alerta::where('user_id', auth()->id())->first();
        if (!$alerta) {
            $this->mensajeError = 'Primero configurá y guardá una alerta.';
            return;
        }

        try {
            if ($alerta->canal === 'email') {
                Mail::to($alerta->email_destino)->send(new AlertaDescuento($alerta, true));
                $driver = config('mail.default');
                if ($driver === 'log') {
                    $this->mensajeExito = 'Email generado (modo log). Configurá SMTP en .env para envíos reales. Podés verlo en storage/logs/laravel.log.';
                } else {
                    $this->mensajeExito = "Email de prueba enviado a {$alerta->email_destino}. ¡Revisá tu bandeja!";
                }
            } elseif ($alerta->canal === 'telegram') {
                $this->enviarTelegramPrueba($alerta);
            }
        } catch (\Throwable $e) {
            $this->mensajeError = 'Error al enviar: ' . $e->getMessage();
        }

        $this->mensajeError = $this->mensajeError ?: '';
    }

    private function enviarTelegramPrueba(Alerta $alerta): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->mensajeError = 'Configurá TELEGRAM_BOT_TOKEN en .env para usar Telegram.';
            return;
        }

        $texto = "🔔 *Prueba de alerta — Descuentos Perfumes*\n\n"
               . "Tu alerta para *{$alerta->tipoLabel()}: {$alerta->valor}* está activa.\n"
               . "Cuando detectemos descuentos, recibirás un mensaje como este.";

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $alerta->telegram_chat_id,
            'text'       => $texto,
            'parse_mode' => 'Markdown',
        ]);

        if ($response->successful() && $response->json('ok')) {
            $this->mensajeExito = 'Mensaje de prueba enviado a Telegram.';
        } else {
            $this->mensajeError = 'Error Telegram: ' . ($response->json('description') ?? 'respuesta inválida');
        }
    }

    public function render()
    {
        $alerta = auth()->check()
            ? Alerta::where('user_id', auth()->id())->first()
            : null;

        return view('livewire.notificaciones', compact('alerta'));
    }
}
