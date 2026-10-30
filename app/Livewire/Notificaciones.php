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
    // Form fields
    public string $tipo           = 'producto';
    public string $valor          = '';
    public string $canal          = 'email';
    public string $emailDestino   = '';
    public string $telegramChatId = '';
    public bool   $soloMejoras    = false;
    public ?int   $tamanoMl       = null;
    public string $marcaFiltro    = '';
    public string $talles         = '';     // ropa: "M, XL, 42" (opcional)
    public ?int   $descuentoMin   = null;   // % mínimo para avisar (opcional)
    public string $rubro          = 'perfumes';   // rubro al que aplica la alerta (config/rubros.php)

    // UI state
    public ?int $editandoId   = null;
    public bool $creandoNueva = false;

    // Suggestions
    public array $sugerencias        = [];
    public array $tamanosDisponibles = [];
    public array $marcasSugerencias  = [];

    public string $mensajeError = '';

    public function mount(): void
    {
        if (!auth()->check()) return;
        $this->emailDestino = auth()->user()->email;

        if (!Alerta::where('user_id', auth()->id())->exists()) {
            $this->creandoNueva = true;
        }
    }

    public function updatedTipo(): void
    {
        if ($this->editandoId) {
            $alerta = Alerta::find($this->editandoId);
            if ($alerta && $alerta->tipo === $this->tipo) {
                $this->valor       = $alerta->valor;
                $this->tamanoMl    = $alerta->tamano_ml;
                $this->soloMejoras = (bool) $alerta->solo_mejoras;
                $this->marcaFiltro = $alerta->marca_filtro ?? '';
            } else {
                $this->valor       = '';
                $this->tamanoMl    = null;
                $this->soloMejoras = false;
                $this->marcaFiltro = '';
            }
        } else {
            $this->valor       = '';
            $this->tamanoMl    = null;
            $this->soloMejoras = false;
            $this->marcaFiltro = '';
        }

        $this->sugerencias        = [];
        $this->tamanosDisponibles = [];
        $this->marcasSugerencias  = [];
        $this->cargarSugerencias();
        $this->cargarTamanos();
        $this->cargarMarcas();
    }

    public function updatedRubro(): void
    {
        if (!config("rubros.rubros.{$this->rubro}")) {
            $this->rubro = config('rubros.default');
        }
        $this->valor              = '';
        $this->tamanoMl           = null;
        $this->marcaFiltro        = '';
        $this->sugerencias        = [];
        $this->tamanosDisponibles = [];
        $this->marcasSugerencias  = [];
        $this->cargarSugerencias();
        $this->cargarMarcas();
    }

    private function idsTiendasRubro(): array
    {
        return \App\Models\Tienda::deRubro($this->rubro)->pluck('id')->all();
    }

    public function updatedValor(): void
    {
        $this->cargarSugerencias();
        $this->cargarTamanos();
    }

    public function updatedMarcaFiltro(): void
    {
        $this->cargarMarcas();
    }

    private function cargarSugerencias(): void
    {
        if ($this->tipo === 'marca') {
            $this->sugerencias = DB::table('productos')->whereIn('tienda_id', $this->idsTiendasRubro())
                ->whereNotNull('marca')->where('marca', '!=', '')
                ->when($this->valor, fn($q) => $q->where('marca', 'like', '%' . $this->valor . '%'))
                ->distinct()->orderBy('marca')->limit(60)->pluck('marca')->values()->toArray();
        } elseif ($this->tipo === 'tienda') {
            $this->sugerencias = DB::table('tiendas')->where('rubro', $this->rubro)
                ->when($this->valor, fn($q) => $q->where('nombre', 'like', '%' . $this->valor . '%'))
                ->orderBy('nombre')->pluck('nombre')->toArray();
        } else {
            if (mb_strlen($this->valor) >= 3) {
                $this->sugerencias = DB::table('productos')->whereIn('tienda_id', $this->idsTiendasRubro())
                    ->where('nombre', 'like', '%' . $this->valor . '%')
                    ->distinct()->orderBy('nombre')->limit(20)->pluck('nombre')->toArray();
            } else {
                $this->sugerencias = [];
            }
        }
    }

    private function cargarMarcas(): void
    {
        if (!in_array($this->tipo, ['producto', 'produto'])) {
            $this->marcasSugerencias = [];
            return;
        }
        $this->marcasSugerencias = DB::table('productos')->whereIn('tienda_id', $this->idsTiendasRubro())
            ->whereNotNull('marca')->where('marca', '!=', '')
            ->when($this->marcaFiltro, fn($q) => $q->where('marca', 'like', '%' . $this->marcaFiltro . '%'))
            ->distinct()->orderBy('marca')->limit(60)->pluck('marca')->toArray();
    }

    private function cargarTamanos(): void
    {
        if (!in_array($this->tipo, ['producto', 'produto']) || mb_strlen($this->valor) < 3) {
            $this->tamanosDisponibles = [];
            return;
        }
        $this->tamanosDisponibles = DB::table('productos')->whereIn('tienda_id', $this->idsTiendasRubro())
            ->where('nombre', 'like', '%' . $this->valor . '%')
            ->whereNotNull('tamano_ml')->where('tamano_ml', '>', 0)
            ->distinct()->orderBy('tamano_ml')->pluck('tamano_ml')->toArray();

        if ($this->tamanoMl && !in_array($this->tamanoMl, $this->tamanosDisponibles)) {
            $this->tamanoMl = null;
        }
    }

    public function nuevaAlerta(): void
    {
        $this->resetFormFields();
        $this->editandoId   = null;
        $this->creandoNueva = true;
        $this->mensajeError = '';
    }

    public function activarEdicion(int $id): void
    {
        $alerta = Alerta::where('user_id', auth()->id())->find($id);
        if (!$alerta) return;

        $this->editandoId     = $id;
        $this->creandoNueva   = false;
        $this->tipo           = in_array($alerta->tipo, ['marca', 'tienda']) ? $alerta->tipo : 'producto';
        $this->rubro          = $alerta->rubro ?: 'perfumes';
        $this->valor          = $alerta->valor;
        $this->canal          = $alerta->canal;
        $this->emailDestino   = $alerta->email_destino ?? auth()->user()->email;
        $this->telegramChatId = $alerta->telegram_chat_id ?? '';
        $this->soloMejoras    = (bool) $alerta->solo_mejoras;
        $this->tamanoMl       = $alerta->tamano_ml;
        $this->marcaFiltro    = $alerta->marca_filtro ?? '';
        $this->talles         = $alerta->talles ?? '';
        $this->descuentoMin   = $alerta->descuento_min;
        $this->mensajeError   = '';
        $this->cargarSugerencias();
        $this->cargarTamanos();
        $this->cargarMarcas();
    }

    public function cancelarEdicion(): void
    {
        $this->resetFormFields();
        $this->editandoId   = null;
        $this->creandoNueva = false;
        $this->mensajeError = '';
    }

    public function guardar(): void
    {
        if (!auth()->check()) return;

        $this->validate([
            'tipo'           => 'required|in:marca,tienda,producto,produto',
            'rubro'          => 'required|in:' . implode(',', array_keys(config('rubros.rubros'))),
            'valor'          => 'required|string|min:2|max:200',
            'canal'          => 'required|in:email,telegram',
            'emailDestino'   => 'required_if:canal,email|nullable|email|max:200',
            'telegramChatId' => 'required_if:canal,telegram|nullable|string|max:100',
            'tamanoMl'       => 'nullable|integer|min:1|max:9999',
            'talles'         => 'nullable|string|max:255',
            'descuentoMin'   => 'nullable|integer|min:5|max:95',
        ], [
            'valor.required'             => 'Ingresá qué querés monitorear.',
            'valor.min'                  => 'El término debe tener al menos 2 caracteres.',
            'emailDestino.required_if'   => 'Ingresá un email para recibir las notificaciones.',
            'emailDestino.email'         => 'El email no es válido.',
            'telegramChatId.required_if' => 'Ingresá tu Chat ID de Telegram.',
        ]);

        $data = [
            'tipo'             => $this->tipo,
            'rubro'            => $this->rubro,
            'valor'            => trim($this->valor),
            'canal'            => $this->canal,
            'email_destino'    => $this->canal === 'email' ? $this->emailDestino : null,
            'telegram_chat_id' => $this->canal === 'telegram' ? $this->telegramChatId : null,
            'activo'           => true,
            'solo_mejoras'     => in_array($this->tipo, ['producto', 'produto']) ? $this->soloMejoras : false,
            'tamano_ml'        => in_array($this->tipo, ['producto', 'produto']) && $this->rubro === 'perfumes' ? $this->tamanoMl : null,
            'marca_filtro'     => in_array($this->tipo, ['producto', 'produto']) && trim($this->marcaFiltro) !== '' ? trim($this->marcaFiltro) : null,
            'talles'           => $this->rubro === 'ropa' ? $this->normalizarTalles($this->talles) : null,
            'descuento_min'    => $this->descuentoMin ?: null,
        ];

        $esEdicion = $this->editandoId !== null;

        // Límites del plan (ver User::limitesPlan)
        $limites = auth()->user()->limitesPlan();

        if ($this->canal === 'telegram' && !$limites['telegram']) {
            $this->mensajeError = "Tu plan {$limites['plan']} no incluye notificaciones por Telegram. Mejorá tu plan en /planes o elegí el canal Email.";
            return;
        }

        if (!$esEdicion && $limites['max_alertas'] !== -1) {
            $activas = Alerta::where('user_id', auth()->id())->count();
            if ($activas >= $limites['max_alertas']) {
                $this->mensajeError = "Llegaste al límite de {$limites['max_alertas']} alerta(s) de tu plan {$limites['plan']}. Eliminá una o mejorá tu plan en /planes.";
                return;
            }
        }

        $this->mensajeError = '';

        if ($esEdicion) {
            $alerta = Alerta::where('user_id', auth()->id())->find($this->editandoId);
            if ($alerta) {
                $alerta->update($data);
            }
        } else {
            Alerta::create(['user_id' => auth()->id(), 'ultima_notificacion_at' => null] + $data);
        }

        $this->cancelarEdicion();
        $this->dispatch('toast', tipo: 'success', mensaje: $esEdicion
            ? 'Alerta actualizada correctamente.'
            : '¡Alerta activada! Te avisaremos cuando detectemos descuentos.');
    }

    public function eliminar(int $id): void
    {
        if (!auth()->check()) return;
        Alerta::where('user_id', auth()->id())->where('id', $id)->delete();
        if ($this->editandoId === $id) {
            $this->cancelarEdicion();
        }
        $this->dispatch('toast', tipo: 'success', mensaje: 'Alerta eliminada.');
    }

    public function enviarPrueba(int $id): void
    {
        if (!auth()->check()) return;

        $alerta = Alerta::where('user_id', auth()->id())->find($id);
        if (!$alerta) return;

        try {
            if ($alerta->canal === 'email') {
                Mail::to($alerta->email_destino)->send(new AlertaDescuento($alerta, true));
                $this->dispatch('toast', tipo: 'success', mensaje: "Email de prueba enviado a {$alerta->email_destino}. ¡Revisá tu bandeja!");
                $alerta->forceFill(['prueba_enviada' => true])->save();
            } elseif ($alerta->canal === 'telegram') {
                $this->enviarTelegramPrueba($alerta);
            }
        } catch (\Throwable $e) {
            $this->mensajeError = 'Error al enviar: ' . $e->getMessage();
        }
    }

    private function enviarTelegramPrueba(Alerta $alerta): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->mensajeError = 'Configurá TELEGRAM_BOT_TOKEN en .env para usar Telegram.';
            return;
        }

        $texto = "🔔 *Prueba de alerta — Descuentos Perfumes*\n\n"
               . "Tu alerta para *{$alerta->tipoLabel()}: " . Alerta::escaparMarkdown($alerta->valor) . "* está activa.\n"
               . "Cuando detectemos descuentos, recibirás un mensaje como este.";

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $alerta->telegram_chat_id,
            'text'       => $texto,
            'parse_mode' => 'Markdown',
        ]);

        if ($response->successful() && $response->json('ok')) {
            $this->dispatch('toast', tipo: 'success', mensaje: 'Mensaje de prueba enviado a Telegram.');
            $alerta->forceFill(['prueba_enviada' => true])->save();
        } else {
            $this->mensajeError = 'Error Telegram: ' . ($response->json('description') ?? 'respuesta inválida');
        }
    }

    private function resetFormFields(): void
    {
        $this->tipo               = 'producto';
        $this->rubro              = 'perfumes';
        $this->valor              = '';
        $this->canal              = 'email';
        $this->emailDestino       = auth()->user()?->email ?? '';
        $this->telegramChatId     = '';
        $this->soloMejoras        = false;
        $this->tamanoMl           = null;
        $this->marcaFiltro        = '';
        $this->talles             = '';
        $this->descuentoMin       = null;
        $this->sugerencias        = [];
        $this->tamanosDisponibles = [];
        $this->marcasSugerencias  = [];
    }

    /** "m, xl ,42" -> "M, XL, 42" (mayúsculas, sin vacíos ni repetidos) o null. */
    private function normalizarTalles(string $texto): ?string
    {
        $t = array_values(array_unique(array_filter(array_map(fn($x) => mb_strtoupper(trim($x)), explode(',', $texto)))));
        return $t ? implode(', ', $t) : null;
    }

    public function render()
    {
        $alertas = auth()->check()
            ? Alerta::where('user_id', auth()->id())->orderBy('created_at')->get()
            : collect();

        return view('livewire.notificaciones', compact('alertas'));
    }
}

