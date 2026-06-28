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

    {{-- ── Stat cards rápidas ── --}}
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
                             font-bold rounded-full px-2 py-0.5">SIN CONFIGURAR</span>
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
        {{-- Sin configurar --}}
        <div class="px-5 py-10 text-center">
            <div class="text-4xl mb-4">📊</div>
            <p class="text-sm font-semibold text-[var(--c-text)] mb-2">Conectá Google Analytics</p>
            <p class="text-xs text-[var(--c-muted)] max-w-lg mx-auto leading-relaxed mb-6">
                Para ver métricas en este panel, necesitás configurar la API de Analytics.
            </p>

            <div class="text-left max-w-lg mx-auto rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] p-4 text-xs space-y-2">
                <p class="font-semibold text-[var(--c-text)] mb-2">Pasos de configuración:</p>
                <ol class="list-decimal list-inside space-y-1.5 text-[var(--c-muted)] leading-relaxed">
                    <li>En <strong>Google Cloud Console</strong> creá un proyecto y habilitá <em>Google Analytics Data API</em></li>
                    <li>Creá una <strong>Cuenta de servicio</strong> y descargá el archivo <code class="bg-violet-100 dark:bg-violet-900/30 px-1 rounded">credentials.json</code></li>
                    <li>Colocá el JSON en <code class="bg-violet-100 dark:bg-violet-900/30 px-1 rounded">storage/app/analytics/</code></li>
                    <li>En GA4 → Administración → Gestión de acceso: agregá el email de la cuenta de servicio como <em>Lector</em></li>
                    <li>Agregá en <code class="bg-violet-100 dark:bg-violet-900/30 px-1 rounded">.env</code>:
                        <pre class="mt-1 bg-[var(--c-card)] border border-[var(--c-border)] rounded p-2 text-[10px] font-mono">GOOGLE_ANALYTICS_ID=G-XXXXXXXXXX
GOOGLE_ANALYTICS_PROPERTY_ID=123456789
ANALYTICS_SERVICE_ACCOUNT_CREDENTIALS_JSON={{ storage_path('app/analytics/credentials.json') }}</pre>
                    </li>
                    <li>Ejecutá <code class="bg-violet-100 dark:bg-violet-900/30 px-1 rounded">php artisan config:clear</code></li>
                </ol>
            </div>
        </div>

        @else
        {{-- Métricas ── --}}
        <div class="px-5 py-5">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                @foreach([
                    ['📄', 'Páginas vistas', $gaMetrics['pageviews']   ?? '—'],
                    ['🔗', 'Sesiones',       $gaMetrics['sessions']    ?? '—'],
                    ['👤', 'Usuarios',       $gaMetrics['users']       ?? '—'],
                    ['↩️', 'Rebote',         $gaMetrics['bounce']      ?? '—'],
                    ['⏱️', 'Sesión prom.',   $gaMetrics['avg_session'] ?? '—'],
                ] as [$icon, $label, $val])
                <div class="rounded-xl border border-[var(--c-border)] bg-[var(--c-bg)] px-4 py-3 text-center">
                    <div class="text-xl mb-1">{{ $icon }}</div>
                    <p class="text-lg font-bold text-[var(--c-text)]">{{ $val }}</p>
                    <p class="text-[10px] text-[var(--c-muted)]">{{ $label }}</p>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- Top páginas --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-3">Top páginas</p>
                    <div class="space-y-1.5">
                        @forelse($gaPages as $page)
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-[var(--c-border)] last:border-0">
                            <span class="text-[var(--c-text)] truncate max-w-[70%]" title="{{ $page['path'] }}">{{ $page['path'] }}</span>
                            <span class="font-semibold text-violet-500 shrink-0 ml-2">{{ $page['views'] }}</span>
                        </div>
                        @empty
                        <p class="text-xs text-[var(--c-muted)]">Sin datos</p>
                        @endforelse
                    </div>
                </div>

                {{-- Top países --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--c-muted)] mb-3">Top países</p>
                    <div class="space-y-1.5">
                        @forelse($gaCountries as $country)
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-[var(--c-border)] last:border-0">
                            <span class="text-[var(--c-text)]">{{ $country['country'] }}</span>
                            <span class="font-semibold text-violet-500">{{ $country['sessions'] }}</span>
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
