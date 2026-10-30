<div class="max-w-6xl mx-auto space-y-8">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <span class="text-2xl">🛡️</span>
                <h1 class="text-2xl font-bold text-[var(--c-text)]">Panel de Administración</h1>
                <span class="text-[10px] font-bold uppercase tracking-widest text-white
                             bg-violet-600 rounded-full px-2.5 py-1">ADMIN</span>
            </div>
            <p class="text-sm text-[var(--c-muted)]">Gestión de usuarios · Google Analytics</p>
        </div>
        <a href="{{ url('/joyas') }}"
           class="text-xs text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors">
            ← Volver al dashboard
        </a>
    </div>

    {{-- ── Stat cards DB ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach([
            ['👥', 'Usuarios', $dbStats['usuarios']],
            ['🛡️', 'Admins',   $dbStats['admins']],
            ['🔔', 'Alertas',  $dbStats['alertas']],
            ['📧', 'Por email', $dbStats['alertas_mail']],
        ] as [$icon, $label, $val])
        <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] px-5 py-4">
            <div class="text-2xl mb-1">{{ $icon }}</div>
            <p class="text-2xl font-bold text-[var(--c-text)]">{{ $val }}</p>
            <p class="text-xs text-[var(--c-muted)]">{{ $label }}</p>
        </div>
        @endforeach
    </div>

    {{-- ══════════════════ GOOGLE ANALYTICS ══════════════════ --}}
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden">

        {{-- Header GA --}}
        <div class="px-5 py-4 border-b border-[var(--c-border)] flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none">
                    <rect x="2" y="12" width="4" height="10" rx="1" fill="#e37400"/>
                    <rect x="10" y="7" width="4" height="15" rx="1" fill="#e37400"/>
                    <rect x="18" y="2" width="4" height="20" rx="1" fill="#e37400"/>
                </svg>
                <p class="font-semibold text-sm text-[var(--c-text)]">Google Analytics</p>
                @if($gaOk)
                <span class="text-[10px] bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400
                             font-bold rounded-full px-2 py-0.5">CONECTADO</span>
                @else
                <span class="text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400
                             font-bold rounded-full px-2 py-0.5">SIN DATOS</span>
                @endif
            </div>

            @if($gaOk)
            <div class="flex gap-1">
                @foreach([['7','7 días'],['14','14 días'],['30','30 días'],['90','90 días']] as [$val,$lbl])
                <button wire:click="$set('periodo','{{ $val }}')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors
                               {{ $periodo === $val
                                  ? 'bg-violet-600 text-white'
                                  : 'text-[var(--c-muted)] hover:bg-[var(--color-primary-50)] dark:hover:bg-white/5' }}">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>
            @endif
        </div>

        @if(!$gaOk)
        <div class="px-5 py-10 text-center">
            <div class="text-4xl mb-3">📊</div>
            <p class="text-sm font-semibold text-[var(--c-text)] mb-1">Sin datos de Analytics</p>
            <p class="text-xs text-[var(--c-muted)]">Verificá que las credenciales y el Property ID estén configurados en <code>.env</code>.</p>
        </div>

        @else
        <div class="p-5 space-y-6">

            {{-- ── KPI Cards ── --}}
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach([
                    ['🔗', 'Sesiones',      $gaMetrics['sessions']    ?? '—', null],
                    ['📄', 'Páginas vistas', $gaMetrics['pageviews']   ?? '—', null],
                    ['👤', 'Usuarios',       $gaMetrics['users']       ?? '—', null],
                    ['⏱️', 'Sesión prom.',   $gaMetrics['avg_session'] ?? '—', null],
                    ['🟢', 'Activos ahora',  $gaActiveUsers,              'realtime'],
                ] as [$icon, $label, $val, $type])
                <div class="rounded-xl border {{ $type === 'realtime' ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/20' : 'border-[var(--c-border)] bg-[var(--c-bg)]' }} px-4 py-3 text-center">
                    <div class="text-xl mb-1">{{ $icon }}</div>
                    <p class="text-lg font-bold {{ $type === 'realtime' ? 'text-emerald-600 dark:text-emerald-400' : 'text-[var(--c-text)]' }}">{{ $val }}</p>
                    <p class="text-[10px] text-[var(--c-muted)]">{{ $label }}</p>
                </div>
                @endforeach
            </div>

            {{-- ── Visitas por día ── --}}
            @if(!empty($gaDailyVisits))
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-3">
                    Visitas por día · últimos {{ $periodo }} días
                </p>
                @php
                    $maxSessions = max(array_column($gaDailyVisits, 'sessions') ?: [1]);
                @endphp
                <div class="flex items-end gap-0.5 h-24 w-full overflow-x-auto">
                    @foreach($gaDailyVisits as $day)
                    @php $pct = $maxSessions > 0 ? round($day['sessions'] / $maxSessions * 100) : 0; @endphp
                    <div class="group relative flex-1 min-w-[6px] flex flex-col items-center justify-end h-full">
                        <div class="w-full rounded-t bg-violet-500/70 hover:bg-violet-500 transition-colors cursor-default"
                             style="height: {{ max($pct, 2) }}%"></div>
                        <div class="absolute bottom-full mb-1 hidden group-hover:flex flex-col items-center z-10 pointer-events-none">
                            <div class="bg-[var(--c-card)] border border-[var(--c-border)] rounded px-2 py-1 text-[10px] whitespace-nowrap shadow-lg">
                                <span class="font-semibold text-[var(--c-text)]">{{ number_format($day['sessions'], 0, ',', '.') }}</span>
                                <span class="text-[var(--c-muted)] ml-1">{{ $day['date'] }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[9px] text-[var(--c-muted)] mt-1">
                    <span>{{ $gaDailyVisits[0]['date'] ?? '' }}</span>
                    <span>{{ $gaDailyVisits[count($gaDailyVisits)-1]['date'] ?? '' }}</span>
                </div>
            </div>
            @endif

            {{-- ── Top páginas + Dispositivos ── --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- Top páginas --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-3">Páginas más visitadas</p>
                    <div class="space-y-1.5">
                        @forelse($gaPages as $page)
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-[var(--c-border)] last:border-0">
                            <span class="text-[var(--c-text)] truncate max-w-[70%]" title="{{ $page['path'] }}">
                                {{ $page['path'] }}
                            </span>
                            <span class="font-semibold text-violet-500 shrink-0 ml-2">{{ $page['views'] }}</span>
                        </div>
                        @empty
                        <p class="text-xs text-[var(--c-muted)]">Sin datos</p>
                        @endforelse
                    </div>
                </div>

                {{-- Dispositivos --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-3">Dispositivos</p>
                    @php
                        $deviceColors = ['mobile' => 'bg-violet-500', 'desktop' => 'bg-blue-500', 'tablet' => 'bg-amber-500'];
                        $deviceLabels = ['mobile' => 'Mobile', 'desktop' => 'Desktop', 'tablet' => 'Tablet'];
                    @endphp
                    <div class="space-y-3">
                        @forelse($gaDevices as $dev)
                        @php
                            $key   = strtolower($dev['device']);
                            $color = $deviceColors[$key] ?? 'bg-gray-400';
                            $label = $deviceLabels[$key] ?? ucfirst($dev['device']);
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-[var(--c-text)] font-medium">{{ $label }}</span>
                                <span class="text-[var(--c-muted)]">{{ $dev['sessions'] }} sess. · <span class="font-semibold text-[var(--c-text)]">{{ $dev['pct'] }}%</span></span>
                            </div>
                            <div class="w-full bg-[var(--c-border)] rounded-full h-2">
                                <div class="{{ $color }} h-2 rounded-full transition-all duration-500"
                                     style="width: {{ $dev['pct'] }}%"></div>
                            </div>
                        </div>
                        @empty
                        <p class="text-xs text-[var(--c-muted)]">Sin datos</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
        @endif

    </div>

    {{-- ══════════════════ ESTADO DE LOS SCRAPERS ══════════════════ --}}
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--c-border)] flex items-center gap-2">
            <span class="text-lg">🕷️</span>
            <p class="font-semibold text-sm text-[var(--c-text)]">Estado de los scrapers</p>
            <span class="text-[11px] text-[var(--c-muted)]">última corrida de cada rubro y estado por sitio/sección</span>
        </div>
        <div class="p-5 space-y-5">
            @foreach($saludScrapers as $sc)
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <p class="text-sm font-semibold text-[var(--c-text)]">{{ $sc['icono'] }} {{ $sc['nombre'] }}</p>
                    @if(!$sc['corrida'])
                        <span class="text-[11px] rounded-full px-2 py-0.5 bg-slate-500/15 text-slate-500">sin corridas</span>
                    @else
                        <span class="text-[11px] text-[var(--c-muted)]">corrida #{{ $sc['corrida']->id }} · {{ $sc['corrida']->fecha_hora->format('d/m/Y H:i') }}
                            · hace {{ $sc['horas'] }} h · {{ number_format($sc['corrida']->total_productos, 0, ',', '.') }} productos</span>
                        @if($sc['vieja'])
                            <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-red-500/15 text-red-500">⚠ desactualizada (&gt; 36 h)</span>
                        @elseif($sc['problemas'] > 0)
                            <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-amber-500/15 text-amber-500">⚠ {{ $sc['problemas'] }} sitio(s) con problemas</span>
                        @else
                            <span class="text-[11px] font-semibold rounded-full px-2 py-0.5 bg-emerald-500/15 text-emerald-500">✓ todo ok</span>
                        @endif
                    @endif
                </div>
                @if($sc['corrida'] && count($sc['sitios']))
                <div class="overflow-x-auto rounded-lg border border-[var(--c-border)]">
                    <table class="w-full text-xs">
                        <thead class="bg-[var(--c-bg)] text-[var(--c-muted)]">
                            <tr><th class="text-left px-3 py-1.5">Sitio / sección</th><th class="text-left px-3 py-1.5">Plataforma</th>
                                <th class="text-right px-3 py-1.5">Encontrados</th><th class="text-right px-3 py-1.5">Con desc.</th>
                                <th class="text-right px-3 py-1.5">Seg.</th><th class="text-left px-3 py-1.5">Estado</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--c-border)]">
                            @foreach($sc['sitios'] as $x)
                            <tr>
                                <td class="px-3 py-1.5 text-[var(--c-text)]">{{ $x->tienda }}</td>
                                <td class="px-3 py-1.5 text-[var(--c-muted)]">{{ $x->plataforma }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ $x->total_encontrados }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ $x->con_descuento }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-[var(--c-muted)]">{{ $x->segundos !== null ? number_format($x->segundos, 0) : '—' }}</td>
                                <td class="px-3 py-1.5">
                                    <span class="rounded-full px-2 py-0.5 font-semibold
                                        {{ $x->estado === 'ok' ? 'bg-emerald-500/15 text-emerald-500' : ($x->estado === 'vacio' ? 'bg-amber-500/15 text-amber-500' : 'bg-red-500/15 text-red-500') }}">{{ $x->estado }}</span>
                                    @if($x->mensaje)<span class="text-[var(--c-muted)] ml-1">{{ \Illuminate\Support\Str::limit($x->mensaje, 60) }}</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════ TEST DE INTEGRACIONES ══════════════════ --}}
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden">

        <div class="px-5 py-4 border-b border-[var(--c-border)]">
            <div class="flex items-center gap-2">
                <span class="text-lg">🧪</span>
                <p class="font-semibold text-sm text-[var(--c-text)]">Test de integraciones</p>
                <span class="text-[10px] text-[var(--c-muted)] ml-1">Probá cada canal antes de activarlo</span>
            </div>
        </div>

        <div class="p-5 space-y-5">

            {{-- Feedback --}}
            @if($mensajeTest)
            <div class="rounded-lg px-4 py-2.5 text-sm
                {{ $tipoMensajeTest === 'success'
                    ? 'bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400'
                    : 'bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400' }}">
                {{ $mensajeTest }}
            </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- ── Email ── --}}
                <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📧</span>
                        <div>
                            <p class="text-sm font-semibold text-[var(--c-text)]">Email</p>
                            <p class="text-[11px] text-[var(--c-muted)]">Envía un mail de prueba a tu cuenta</p>
                        </div>
                    </div>
                    <p class="text-[11px] text-[var(--c-muted)] truncate">→ {{ auth()->user()->email }}</p>
                    <button wire:click="testEmail"
                            wire:loading.attr="disabled"
                            wire:target="testEmail"
                            class="w-full py-2 rounded-lg text-xs font-semibold transition-colors
                                   bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="testEmail">Enviar mail de prueba</span>
                        <span wire:loading wire:target="testEmail">Enviando…</span>
                    </button>
                </div>

                {{-- ── Telegram ── --}}
                <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">✈️</span>
                        <div>
                            <p class="text-sm font-semibold text-[var(--c-text)]">Telegram</p>
                            <p class="text-[11px] text-[var(--c-muted)]">Ingresá tu Chat ID para probar</p>
                        </div>
                    </div>
                    <input wire:model="telegramChatTest"
                           type="text"
                           placeholder="Tu Chat ID (ej: 123456789)"
                           class="w-full text-xs rounded-lg border border-[var(--c-border)] bg-[var(--c-card)]
                                  text-[var(--c-text)] px-3 py-2 focus:outline-none focus:ring-1 focus:ring-violet-500" />
                    <button wire:click="testTelegram"
                            wire:loading.attr="disabled"
                            wire:target="testTelegram"
                            class="w-full py-2 rounded-lg text-xs font-semibold transition-colors
                                   bg-sky-500 hover:bg-sky-600 text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="testTelegram">Enviar mensaje de prueba</span>
                        <span wire:loading wire:target="testTelegram">Enviando…</span>
                    </button>
                </div>

                {{-- ── MercadoPago ── --}}
                <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💳</span>
                        <div>
                            <p class="text-sm font-semibold text-[var(--c-text)]">MercadoPago</p>
                            <p class="text-[11px] text-[var(--c-muted)]">Simula un pago del Plan Pro</p>
                        </div>
                    </div>
                    <p class="text-[11px] text-[var(--c-muted)]">→ Te redirige al checkout de MP (sandbox)</p>
                    <button wire:click="testMercadoPago"
                            wire:loading.attr="disabled"
                            wire:target="testMercadoPago"
                            class="w-full py-2 rounded-lg text-xs font-semibold transition-colors
                                   bg-yellow-500 hover:bg-yellow-600 text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="testMercadoPago">Ir al checkout de prueba</span>
                        <span wire:loading wire:target="testMercadoPago">Redirigiendo…</span>
                    </button>
                </div>

            </div>

            <p class="text-[11px] text-[var(--c-muted)]">
                💡 Para obtener tu Chat ID de Telegram: hablale a tu bot y visitá
                <code class="bg-[var(--c-border)] rounded px-1">https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code>
                — buscá <code class="bg-[var(--c-border)] rounded px-1">"chat":{"id": ...}</code>
            </p>

            {{-- ── Envío manual de alertas ── --}}
            <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-[var(--c-text)]">🔔 Enviar alertas a usuarios</p>
                        <p class="text-[11px] text-[var(--c-muted)] mt-0.5">
                            Procesa todas las alertas activas y envía las novedades por email o Telegram (igual que el cron automático).
                        </p>
                    </div>
                    <button wire:click="procesarAlertasEmail"
                            wire:loading.attr="disabled"
                            wire:target="procesarAlertasEmail"
                            class="shrink-0 px-4 py-2 rounded-lg text-xs font-semibold transition-colors cursor-pointer
                                   bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="procesarAlertasEmail">Enviar ahora</span>
                        <span wire:loading wire:target="procesarAlertasEmail">Procesando…</span>
                    </button>
                </div>

                @if($mensajeAlertas)
                <div class="mt-3">
                    <pre class="text-[11px] leading-relaxed whitespace-pre-wrap rounded-lg p-3
                        {{ $tipoMensajeAlertas === 'success'
                            ? 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400'
                            : 'bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-400' }}">{{ $mensajeAlertas }}</pre>
                </div>
                @endif
            </div>

        </div>
    </div>

    {{-- ══════════════════ USUARIOS ══════════════════ --}}
    <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] overflow-hidden">

        <div class="px-5 py-4 border-b border-[var(--c-border)] flex items-center justify-between">
            <p class="font-semibold text-sm text-[var(--c-text)]">Gestión de usuarios</p>
            <span class="text-xs text-[var(--c-muted)]">{{ $usuarios->count() }} usuario(s)</span>
        </div>

        @if($mensajeU)
        <div class="px-5 pt-3">
            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40
                        px-4 py-2 text-xs text-emerald-700 dark:text-emerald-400">
                {{ $mensajeU }}
            </div>
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[var(--c-bg)] border-b border-[var(--c-border)]">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)]">Usuario</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)]">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)]">Registro</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)]">Alertas</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)]">Rol</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)]">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--c-border)]">
                    @foreach($usuarios as $usuario)
                    <tr class="hover:bg-[var(--c-bg)] transition-colors">
                        <td class="px-5 py-3 font-medium text-[var(--c-text)]">{{ $usuario->name }}</td>
                        <td class="px-5 py-3 text-xs text-[var(--c-muted)]">{{ $usuario->email }}</td>
                        <td class="px-5 py-3 text-xs text-[var(--c-muted)]">{{ $usuario->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-3 text-xs text-center text-[var(--c-muted)]">
                            {{ $usuario->alertas()->count() }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($usuario->is_admin)
                            <span class="inline-block text-[10px] font-bold uppercase tracking-wide
                                         bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400
                                         rounded-full px-2.5 py-1">Admin</span>
                            @else
                            <span class="inline-block text-[10px] font-bold uppercase tracking-wide
                                         bg-[var(--c-bg)] text-[var(--c-muted)]
                                         rounded-full px-2.5 py-1 border border-[var(--c-border)]">Usuario</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($usuario->id !== auth()->id())
                            <button wire:click="toggleAdmin({{ $usuario->id }})"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors
                                           {{ $usuario->is_admin
                                              ? 'text-red-500 hover:bg-red-50 dark:hover:bg-red-950/20'
                                              : 'text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-950/20' }}">
                                {{ $usuario->is_admin ? 'Quitar admin' : 'Hacer admin' }}
                            </button>
                            @else
                            <span class="text-[10px] text-[var(--c-muted)]">Vos</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

</div>
