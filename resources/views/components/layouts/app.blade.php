@props(['activeTab' => 'joyas', 'rubro' => 'perfumes'])
@php
    $verifiedUser   = auth()->check() && auth()->user()->hasVerifiedEmail();
    $unverifiedUser = auth()->check() && !auth()->user()->hasVerifiedEmail();

    // Rubros (perfumes, ropa, ...) y sus pestañas: ver config/rubros.php
    $rubros   = config('rubros.rubros');
    $rubro    = isset($rubros[$rubro]) ? $rubro : config('rubros.default');
    $rubroCfg = $rubros[$rubro];
    $tabUrl   = fn($key) => url(($rubroCfg['prefijo'] ? '/' . $rubroCfg['prefijo'] : '') . '/' . $key);
    $rubroUrl = fn($slug) => url(($rubros[$slug]['prefijo'] ? '/' . $rubros[$slug]['prefijo'] : '') . '/' . $rubros[$slug]['tab_inicial']);
    $barTabs     = collect($rubroCfg['tabs'])->map(fn($t, $k) => [$k, $t[0]])->values()->all();
@endphp
<!DOCTYPE html>
<html lang="es" class="h-full"
      x-data="{
          dark:             localStorage.getItem('dark') !== 'false',
          sidebar:          false,
          sidebarCollapsed: false,
          mobileMenuOpen:   false,
          filterCount:      0,
      }"
      x-init="
          $watch('dark', v => {
              localStorage.setItem('dark', v);
              const el  = document.documentElement;
              const flash = document.getElementById('theme-flash');
              // Captura el color de fondo actual ANTES del cambio
              const bg = el.classList.contains('dark') ? '#0d0f1a' : '#ede9f8';
              flash.style.background  = bg;
              flash.style.transition  = 'none';
              flash.style.opacity     = '1';
              // Espera dos frames para que el navegador pinte el overlay opaco
              requestAnimationFrame(() => requestAnimationFrame(() => {
                  el.classList.toggle('dark', v);
                  flash.style.transition = 'opacity 280ms ease';
                  flash.style.opacity    = '0';
              }));
          });
          if (dark) document.documentElement.classList.add('dark');

          $watch('sidebarCollapsed', v => {
              document.documentElement.classList.toggle('sidebar-hidden', v);
          });

          window.addEventListener('filter-count-changed', e => { filterCount = e.detail; });

          if (sessionStorage.getItem('mobileMenuOpen')) {
              sessionStorage.removeItem('mobileMenuOpen');
              mobileMenuOpen = true;
          }
      "
      :class="{ dark: dark }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} · Descuentos Perfumes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;1,14..32,400&display=swap" rel="stylesheet">
    {{-- Script síncrono: aplica clases antes del primer paint para evitar FOUC --}}
    <script>
        (function () {
            if (localStorage.getItem('dark') !== 'false') document.documentElement.classList.add('dark');
        })();
    </script>
    {{-- Google Analytics --}}
    @if(config('services.google.analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google.analytics_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('services.google.analytics_id') }}');
    </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .apexcharts-text, .apexcharts-legend-text { font-family: 'Inter', sans-serif !important; }

        /* ── ApexCharts dark mode ──────────────────────────── */
        html.dark .apexcharts-text tspan,
        html.dark .apexcharts-xaxis-label tspan,
        html.dark .apexcharts-yaxis-label tspan,
        html.dark .apexcharts-datalabel tspan,
        html.dark .apexcharts-datalabels tspan { fill: #cbd5e1 !important; }

        html.dark .apexcharts-legend-text { color: #cbd5e1 !important; }

        html.dark .apexcharts-gridline { stroke: #2d3148 !important; }
        html.dark .apexcharts-xcrosshairs,
        html.dark .apexcharts-ycrosshairs { stroke: #4a5070 !important; }

        html.dark .apexcharts-tooltip {
            background: #1a1d2e !important;
            border-color: #2d3148 !important;
            box-shadow: 0 4px 16px rgba(0,0,0,.4) !important;
        }
        html.dark .apexcharts-tooltip-title {
            background: #0d0f1a !important;
            border-color: #2d3148 !important;
            color: #e2e8f0 !important;
        }
        html.dark .apexcharts-tooltip-text-y-label,
        html.dark .apexcharts-tooltip-text-y-value,
        html.dark .apexcharts-tooltip-text-z-value { color: #e2e8f0 !important; }

        /* ── ApexCharts: canvas transparent ───────────────── */
        .apexcharts-canvas { background: transparent !important; }

        /* ── Cambios cards: dark mode fix (Tailwind v4 specificity) ── */
        html.dark .card-nuevos-oferta {
            background: rgba(6, 78, 59, 0.3) !important;
            border-color: rgba(21, 128, 61, 0.4) !important;
        }

        /* ── Animación de cambio de tab ── */
        @keyframes tabFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .tab-animate-in {
            animation: tabFadeIn 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        }

        /* ── Colapsables: max-height + opacity sin display:none ── */
        .collapsible-wrap {
            display: grid;
            grid-template-rows: 0fr;
            opacity: 0;
            transition:
                grid-template-rows 0.28s cubic-bezier(0.4, 0, 0.2, 1),
                opacity            0.22s ease;
        }
        .collapsible-wrap.is-open {
            grid-template-rows: 1fr;
            opacity: 1;
        }
        .collapsible-wrap > .collapsible-inner {
            overflow: hidden;
        }

        /* tema: el cambio es instantáneo bajo el overlay — sin transiciones en * */

        /* ── Rangos de descuento: track oscuro en dark mode ── */
        html.dark .rango-track { background: #2d3148 !important; }

        /* ── Bordes: forzar color en modo claro (Tailwind v4 no detecta var() como color) ── */
        html:not(.dark) [class*="c-border"] { border-color: #c7c7c7; }

        /* ── Sombra con relieve en cards (solo modo claro) ── */
        html:not(.dark) .rounded-xl,
        html:not(.dark) .rounded-2xl {
            box-shadow:
                0 1px 2px  rgba(0, 0, 0, 0.03),
                0 4px 12px rgba(0, 0, 0, 0.04),
                0 12px 24px -4px rgba(0, 0, 0, 0.04) !important;
        }

        /* ── Cursor pointer en todos los elementos interactivos ── */
        button:not([disabled]), select, input[type="range"],
        [role="button"], summary, label[for], label:has(input) {
            cursor: pointer !important;
        }

        /* ── Tab nav: hover en pestañas inactivas ─────────── */
        .tab-nav-btn:not(.tab-active):hover {
            background-color: #ddd6fe !important;
            color: #5B3A8C !important;
        }
        html.dark .tab-nav-btn:not(.tab-active):hover {
            background-color: rgba(109, 40, 217, 0.25) !important;
            color: #a78bfa !important;
        }

        /* ── Glassmorphism nav ─────────────────────────────── */
        .glass-nav {
            background: rgba(250, 248, 255, 0.65);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
        }
        html.dark .glass-nav {
            background: rgba(26, 29, 46, 0.65);
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.35);
        }

        /* ── Sidebar filter panel: animaciones open/close ─── */
        /* ── Panel de filtros del sidebar ──
           Solo desvanece al entrar (antes limitaba el alto a 800px y cortaba los filtros largos).
           Cuando un acordeón (.acc-sec.acc-sec-open) está abierto (se detecta con :has) el panel pasa a ocupar todo el alto disponible: los bloques fijos
           (género, categoría, descuento…) se pliegan, las cabeceras de los otros acordeones quedan visibles y el abierto
           se estira hasta el final con scroll propio. Al cerrarlo todo vuelve a la normalidad. */
        @keyframes sidebarFilterIn { from { opacity: 0; } to { opacity: 1; } }
        #sidebar-filters { height: 100%; }
        .sidebar-filter-panel {
            display: flex; flex-direction: column; min-height: 100%;
            animation: sidebarFilterIn 0.22s ease both;
        }
        .sidebar-filter-panel:has(> .acc-sec-open) { height: 100%; overflow: hidden; }
        .sidebar-filter-panel > * { flex: 0 0 auto; }
        .sidebar-filter-panel > :not(.acc-sec) {
            max-height: 700px; overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease, padding 0.3s ease;
        }
        .sidebar-filter-panel:has(> .acc-sec-open) > :not(.acc-sec) { max-height: 0; opacity: 0; pointer-events: none; border-width: 0; padding-top: 0; padding-bottom: 0; }
        .sidebar-filter-panel > .acc-sec { transition: flex-grow 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        /* los otros acordeones (cerrados) también se pliegan mientras uno está abierto: el abierto usa todo el alto */
        .sidebar-filter-panel > .acc-sec:not(.acc-sec-open) {
            max-height: 80px; overflow: hidden;
            transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease, padding 0.3s ease;
        }
        .sidebar-filter-panel:has(> .acc-sec-open) > .acc-sec:not(.acc-sec-open) { max-height: 0; opacity: 0; pointer-events: none; border-width: 0; padding-top: 0; padding-bottom: 0; }
        .sidebar-filter-panel > .acc-sec.acc-sec-open { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; }
        .sidebar-filter-panel > .acc-sec.acc-sec-open > .collapsible-wrap { flex: 1 1 0; min-height: 0; }
        .sidebar-filter-panel > .acc-sec.acc-sec-open > .collapsible-wrap > .collapsible-inner { min-height: 0; overflow-y: auto; }
        .mobile-tab-bar::-webkit-scrollbar { height: 3px; display: block; }
        .mobile-tab-bar::-webkit-scrollbar-track { background: transparent; }
        .mobile-tab-bar::-webkit-scrollbar-thumb { background-color: #a78bfa; border-radius: 9999px; }
        .mobile-tab-bar { scrollbar-width: thin; scrollbar-color: #a78bfa transparent; }

        .mobile-cards-list > * + * { border-top: 1px solid #e9e9e9; }
        html.dark .mobile-cards-list > * + * { border-top: 1px solid rgba(255,255,255,0.06); }
    </style>
</head>
<body class="h-full font-sans antialiased bg-[var(--c-bg)] text-[var(--c-text)]">

{{-- ── Theme flash overlay (cubre el cambio de tema para que sea atómico) ── --}}
<div id="theme-flash"
     aria-hidden="true"
     style="position:fixed;inset:0;z-index:99999;pointer-events:none;opacity:0;will-change:opacity;"></div>

{{-- ── Overlay móvil ─────────────────────────────────────────── --}}
<div x-show="sidebar" x-cloak @click="sidebar=false"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

{{-- ══════════════════════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════════════════════ --}}
<aside class="app-sidebar fixed left-0 top-0 bottom-0 w-60 z-50 flex flex-col
              bg-[var(--c-sidebar)] border-r border-[var(--c-border)]
              shadow-xl -translate-x-full lg:translate-x-0"
       :class="{ 'translate-x-0': sidebar }">

    {{-- Selector de rubro --}}
    <div class="px-3 py-2.5 border-b border-[var(--c-border)]">
        <div class="flex rounded-xl bg-[var(--c-bg)] p-1 gap-1">
            @foreach($rubros as $slug => $r)
            <a href="{{ $rubroUrl($slug) }}"
               class="flex-1 text-center rounded-lg px-2 py-1.5 text-xs font-semibold transition-colors
                      {{ $slug === $rubro ? 'bg-[var(--color-primary-600)] text-white shadow' : 'text-[var(--c-muted)] hover:text-[var(--c-text)]' }}">
                {{ $r['icono'] }} {{ $r['nombre_corto'] ?? $r['nombre'] }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- Filtros de la página actual (scrollable).
         La navegación entre secciones está en el header; cada página teleporta acá su panel de filtros
         (livewire/dashboard.blade.php → x-teleport="#sidebar-filters"). --}}
    <div class="flex-1 overflow-y-auto">
        <div id="sidebar-filters"></div>
        @if(in_array($activeTab, ['planes', 'notificaciones', 'admin', 'marcas', 'distribucion'], true))
        <p class="px-4 py-6 text-xs text-[var(--c-muted)] leading-relaxed">
            Esta sección no tiene filtros. Usá el menú de arriba para moverte entre secciones.
        </p>
        @endif
    </div>

    {{-- Bottom: cuenta + dark mode + versión --}}
    <div class="border-t border-[var(--c-border)] divide-y divide-[var(--c-border)]">

        @if($verifiedUser)
        <div class="px-4 py-2.5 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-violet-500 dark:text-violet-300 truncate">{{ auth()->user()->name }}</p>
                <p class="text-[10px] text-[var(--c-muted)] truncate">{{ auth()->user()->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="shrink-0 text-[10px] font-semibold text-[var(--c-muted)] hover:text-red-500 transition-colors px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20">
                    Salir
                </button>
            </form>
        </div>
        @else
        <div class="px-4 py-2.5 flex gap-3">
            <a href="{{ route('login') }}"
               class="text-xs font-medium text-[var(--color-primary-600)] hover:underline">
                Iniciar sesión
            </a>
            <a href="{{ route('register') }}"
               class="text-xs font-medium text-[var(--c-muted)] hover:underline">
                Registrarse
            </a>
        </div>
        @endif

        <div class="px-4 py-2.5 flex items-center justify-between">
            <button @click="dark = !dark"
                    class="flex items-center gap-2 text-xs text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors">
                <span x-show="!dark">🌙</span>
                <span x-show="dark" x-cloak>☀️</span>
                <span x-text="dark ? 'Modo claro' : 'Modo oscuro'"></span>
            </button>
            <span class="text-[10px] text-[var(--c-muted)] opacity-40">v1.0</span>
        </div>

    </div>
</aside>

{{-- ══════════════════════════════════════════════════════════════
     CONTENIDO PRINCIPAL
═══════════════════════════════════════════════════════════════ --}}
<div class="app-content lg:pl-60 flex flex-col min-h-screen">

    {{-- ── Navbar (sticky, contiene tabs en desktop y dropdown en mobile) ── --}}
    <nav class="glass-nav sticky top-0 z-30" @click.outside="mobileMenuOpen = false">

        {{-- Fila principal --}}
        <div class="flex h-14 items-center gap-0 px-3 sm:px-4">

            {{-- Izquierda: collapse (desktop) + logo (mobile) --}}
            <div class="flex items-center gap-2 shrink-0 mr-1">
                <button @click="sidebarCollapsed = !sidebarCollapsed"
                        class="hidden lg:flex p-2 rounded-lg text-[var(--c-muted)] hover:bg-[var(--c-border)] transition-colors"
                        :title="sidebarCollapsed ? 'Abrir menú' : 'Colapsar menú'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="flex items-center gap-2 lg:hidden">
                    <span class="text-xl">{{ $rubroCfg['icono'] }}</span>
                    <span class="font-bold text-sm text-[var(--color-primary-600)]">Descuentos ARG</span>
                </div>
                <a href="{{ $rubroUrl($rubro) }}" class="hidden xl:flex items-center gap-2 pr-2" title="Descuentos ARG · {{ $rubroCfg['nombre'] }}">
                    <span class="text-xl">{{ $rubroCfg['icono'] }}</span>
                    <span class="font-bold text-sm text-[#a82ed2] whitespace-nowrap">Descuentos ARG</span>
                </a>
            </div>

            {{-- Centro: Nav links (solo desktop, fill ancho) --}}
            <div class="hidden lg:flex flex-1 h-14 items-stretch">
                @foreach($barTabs as [$key, $label])
                <a href="{{ $tabUrl($key) }}"
                   class="tab-nav-btn flex-1 flex items-center justify-center whitespace-nowrap h-full
                          text-xs font-semibold transition-colors
                          {{ $activeTab === $key ? 'tab-active bg-[var(--color-primary-600)] text-white' : 'text-[var(--c-muted)]' }}">
                    {{ $label }}
                </a>
                @endforeach
            </div>

            {{-- Spacer (mobile) --}}
            <div class="flex-1 lg:hidden"></div>

            {{-- Derecha: controles --}}
            <div class="flex items-center gap-2 shrink-0">
                {{-- <span class="hidden lg:block text-xs text-[var(--c-muted)] mr-1" id="nav-timestamp"></span> --}}

                {{-- Dark mode (desktop) --}}
                <button @click="dark = !dark"
                        class="hidden lg:flex p-2 rounded-lg text-[var(--c-muted)] hover:bg-[var(--c-border)] transition-colors"
                        :title="dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'">
                    <span x-show="!dark" class="text-lg leading-none">🌙</span>
                    <span x-show="dark"  class="text-lg leading-none" x-cloak>☀️</span>
                </button>

                {{-- Botón Notificaciones --}}
                <a href="{{ route('notificaciones') }}"
                   class="hidden lg:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                          bg-violet-600 hover:bg-violet-700 text-white transition-colors whitespace-nowrap">
                    🔥 Alertas
                </a>

                {{-- Botón Planes --}}
                <a href="{{ route('planes') }}"
                   class="hidden lg:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                          {{ $activeTab === 'planes' ? 'bg-violet-800 text-white' : 'bg-[var(--c-border)] text-[var(--c-text)] hover:bg-violet-100 dark:hover:bg-violet-900/40' }}
                          transition-colors whitespace-nowrap">
                    💳 Planes
                </a>

                @if($verifiedUser)
                @if(auth()->user()->is_admin)
                <a href="{{ route('admin.dashboard') }}"
                   class="hidden lg:flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold
                          {{ $activeTab === 'admin' ? 'bg-violet-800 text-white' : 'bg-[var(--c-border)] text-[var(--c-text)] hover:bg-violet-100 dark:hover:bg-violet-900/40' }}
                          transition-colors whitespace-nowrap">
                    🛡️ Admin
                </a>
                @endif
                @endif

                <div class="hidden lg:block w-px h-6 bg-[var(--c-border)]"></div>

                @if($verifiedUser)
                <div class="hidden lg:flex items-center gap-2">
                    <span class="text-xs font-medium text-[var(--c-text)]">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-[var(--c-muted)] hover:text-red-500 transition-colors px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                            Salir
                        </button>
                    </form>
                </div>
                @elseif($unverifiedUser)
                <a href="{{ route('verification.notice') }}"
                   class="hidden lg:flex items-center gap-1.5 text-xs font-medium text-amber-500 hover:text-amber-400 transition-colors px-2 py-1 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-900/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Verificar email
                </a>
                @else
                <a href="{{ route('login') }}"
                   class="hidden lg:flex items-center gap-1.5 text-xs font-medium text-[var(--color-primary-600)] hover:text-violet-500 transition-colors px-2 py-1 rounded-lg hover:bg-[var(--color-primary-50)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Ingresar
                </a>
                @endif

                {{-- Dark mode (mobile) --}}
                <button @click="dark = !dark"
                        class="lg:hidden p-2 rounded-lg text-[var(--c-muted)] hover:bg-[var(--c-border)] transition-colors">
                    <span x-show="!dark" class="text-lg leading-none">🌙</span>
                    <span x-show="dark"  class="text-lg leading-none" x-cloak>☀️</span>
                </button>

                {{-- Botón menú mobile --}}
                <button @click="mobileMenuOpen = !mobileMenuOpen"
                        class="lg:hidden relative p-2 rounded-lg text-[var(--c-muted)] hover:bg-[var(--c-border)] transition-colors">
                    <svg x-show="!mobileMenuOpen" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileMenuOpen" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span x-show="filterCount > 0 && !mobileMenuOpen" x-cloak
                          class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500 ring-2 ring-[var(--c-nav)]"></span>
                </button>
            </div>
        </div>

        {{-- Dropdown móvil (tabs + filtros + user) --}}
        <div x-show="mobileMenuOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="lg:hidden border-t border-[var(--c-border)] bg-[var(--c-nav)] overflow-y-auto"
             style="max-height: calc(100dvh - 56px)">
            <div id="mobile-filters"></div>
            <div class="border-t border-[var(--c-border)] px-4 py-3">
            <a href="{{ route('notificaciones') }}"
               class="flex items-center justify-center gap-2 w-full px-4 py-2.5 mb-3 rounded-xl
                      bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
                🔥 Activar Alertas de Descuentos
            </a>
            <a href="{{ route('planes') }}"
               class="flex items-center justify-center gap-2 w-full px-4 py-2.5 mb-3 rounded-xl
                      bg-[var(--c-border)] text-[var(--c-text)] text-sm font-semibold transition-colors hover:bg-violet-100 dark:hover:bg-violet-900/30">
                💳 Planes
            </a>
            @if($verifiedUser)
            @if(auth()->user()->is_admin)
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center justify-center gap-2 w-full px-4 py-2.5 mb-3 rounded-xl
                      bg-[var(--c-border)] text-[var(--c-text)] text-sm font-semibold transition-colors hover:bg-violet-100 dark:hover:bg-violet-900/30">
                🛡️ Panel Admin
            </a>
            @endif
            @endif
        </div>
        <div class="border-t border-[var(--c-border)] px-4 py-3 flex items-center justify-between">
                @if($verifiedUser)
                <span class="text-sm font-medium text-[var(--c-text)]">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-red-500 hover:text-red-600 font-semibold px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        ↪ Salir
                    </button>
                </form>
                @elseif($unverifiedUser)
                <a href="{{ route('verification.notice') }}" class="text-sm font-medium text-amber-500">✉ Verificar email</a>
                @else
                <a href="{{ route('login') }}" class="text-sm font-medium text-[var(--color-primary-600)]">→ Iniciar sesión</a>
                @endif
            </div>
        </div>

    </nav>

    {{-- ── Barra de secciones mobile ──────────────────────────── --}}
    <div class="lg:hidden flex gap-1.5 px-3 py-2 bg-[var(--c-nav)] border-b border-[var(--c-border)]">
        @foreach($rubros as $slug => $r)
        <a href="{{ $rubroUrl($slug) }}"
           class="flex-1 text-center rounded-lg px-2 py-1.5 text-xs font-semibold
                  {{ $slug === $rubro ? 'bg-[var(--color-primary-600)] text-white' : 'bg-[var(--c-bg)] text-[var(--c-muted)]' }}">
            {{ $r['icono'] }} {{ $r['nombre_corto'] ?? $r['nombre'] }}
        </a>
        @endforeach
    </div>
    <div class="lg:hidden relative bg-[var(--c-nav)] border-b border-[var(--c-border)]">
        <div class="mobile-tab-bar flex overflow-x-auto gap-1.5"
             style="padding: 10px 40px 14px 12px; gap: 6px;">
            @foreach($barTabs as [$key, $label])
            <a href="{{ $tabUrl($key) }}"
               class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors
                      {{ $activeTab === $key
                          ? 'bg-[var(--color-primary-600)] text-white'
                          : 'text-[var(--c-muted)] hover:text-[var(--c-text)]' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
        <div class="pointer-events-none absolute right-0 top-0 h-full w-10 z-10"
             style="background: linear-gradient(to left, var(--c-nav) 30%, transparent)"></div>
    </div>

    {{-- ── Slot de contenido ──────────────────────────────────── --}}
    <main class="flex-1 px-4 sm:px-6 py-6">
        {{ $slot }}

        {{-- Spinner de scroll infinito: controlado directamente por JS (getElementById),
             sin wire:loading ni x-show para evitar problemas de timing/display --}}
        <div id="tabla-cargando" style="display:none"
             class="justify-center items-center py-10">
            <span class="inline-flex items-center gap-3 rounded-full px-6 py-3 text-sm font-semibold
                         shadow-xl bg-[var(--c-card)] border-2 border-violet-500/60 text-[var(--c-text)]">
                <svg class="animate-spin w-5 h-5 text-violet-500 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Cargando más resultados…
            </span>
        </div>
    </main>

    {{-- ── Footer ─────────────────────────────────────────────── --}}
    <footer class="border-t border-[var(--c-border)] bg-[var(--c-nav)] mt-8">
        <div class="px-4 sm:px-6 py-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xl">🧴</span>
                        <span class="font-bold text-sm text-[var(--color-primary-600)]">Descuentos · Perfumes</span>
                    </div>
                    <p class="text-xs text-[var(--c-muted)] leading-relaxed">
                        Dashboard de análisis de descuentos de perfumería en Argentina. Datos actualizados automáticamente de 12 tiendas online.
                    </p>
                </div>
                <div>
                    <p class="font-semibold text-xs uppercase tracking-wide text-[var(--c-muted)] mb-3">Secciones</p>
                    <ul class="space-y-1.5 text-xs text-[var(--c-muted)]">
                        <li>📈 Evolución temporal de descuentos</li>
                        <li>💎 Destacados — perfumes premium en oferta</li>
                        <li>🔁 Cambios entre snapshots</li>
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-xs uppercase tracking-wide text-[var(--c-muted)] mb-3">Tiendas monitoreadas</p>
                    <p class="text-xs text-[var(--c-muted)] leading-relaxed">
                        Ambrosia · Beauty24 · Farmalife · Fiorani · GetTheLook · Juleriaque · Normy · Parfumerie · Pigmento · Rouge · RuizYRoca · Unishop
                    </p>
                    <p class="text-xs text-[var(--c-muted)] mt-2">Actualización cada 12–24 hs.</p>
                </div>
            </div>
            <div class="border-t border-[var(--c-border)] pt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-[11px] text-[var(--c-muted)]">
                    Los precios son orientativos. Verificá siempre en el sitio oficial antes de comprar.
                </p>
                <p class="text-[11px] text-[var(--c-muted)] opacity-60">
                    Hecho con Laravel + Livewire + TailwindCSS
                </p>
            </div>
        </div>
    </footer>

</div>{{-- /app-content --}}

@livewireScripts
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3"></script>
<script>
    /* ── SweetAlert2: helper global con tema dinámico ── */
    window.swalConfirmar = function({ titulo, texto, btnConfirmar, icono }, onConfirm) {
        const dark = document.documentElement.classList.contains('dark');
        Swal.fire({
            title:             titulo   || '¿Estás seguro?',
            text:              texto    || '',
            icon:              icono    || 'warning',
            showCancelButton:  true,
            confirmButtonText: btnConfirmar || 'Confirmar',
            cancelButtonText:  'Cancelar',
            reverseButtons:    true,
            focusCancel:       true,
            background:        dark ? '#16182a' : '#ffffff',
            color:             dark ? '#e2e8f0' : '#1e1b4b',
            iconColor:         '#f59e0b',
            customClass: {
                popup:         'swal-app-popup',
                title:         'swal-app-title',
                htmlContainer: 'swal-app-text',
                confirmButton: 'swal-app-confirm',
                cancelButton:  'swal-app-cancel',
                actions:       'swal-app-actions',
            },
            buttonsStyling: false,
        }).then(result => {
            if (result.isConfirmed && typeof onConfirm === 'function') onConfirm();
        });
    };
</script>
<style>
    /* ── SweetAlert2: estilos custom ─────────────────── */
    .swal-app-popup {
        border-radius: 1rem !important;
        border: 1px solid rgba(139, 92, 246, 0.25) !important;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45) !important;
        font-family: 'Inter', sans-serif !important;
        padding: 2rem !important;
    }
    .swal-app-title {
        font-size: 1.1rem !important;
        font-weight: 700 !important;
    }
    .swal-app-text {
        font-size: 0.875rem !important;
        opacity: 0.75 !important;
    }
    .swal-app-actions {
        gap: 0.625rem !important;
        margin-top: 1.5rem !important;
    }
    .swal-app-confirm {
        background: #dc2626 !important;
        color: #fff !important;
        border-radius: 0.625rem !important;
        padding: 0.5rem 1.25rem !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        border: none !important;
        cursor: pointer !important;
        transition: background 0.15s !important;
    }
    .swal-app-confirm:hover { background: #b91c1c !important; }
    .swal-app-cancel {
        background: transparent !important;
        color: inherit !important;
        border: 1px solid rgba(139, 92, 246, 0.3) !important;
        border-radius: 0.625rem !important;
        padding: 0.5rem 1.25rem !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        opacity: 0.8 !important;
        transition: opacity 0.15s !important;
    }
    .swal-app-cancel:hover { opacity: 1 !important; }
</style>
<script>
    /* timestamp del nav deshabilitado
    (function() {
        const el = document.getElementById('nav-timestamp');
        if (!el) return;
        const now = new Date();
        el.textContent = now.toLocaleDateString('es-AR', {day:'2-digit',month:'2-digit',year:'numeric'})
            + ' ' + now.toLocaleTimeString('es-AR', {hour:'2-digit',minute:'2-digit'});
    })();
    */
</script>
@stack('scripts')


{{-- Zoom de imágenes de producto: las miniaturas (tabla de ofertas) emiten img-zoom-show / img-zoom-hide al pasar el cursor --}}
<div x-data="{ src: '', open: false, x: 0, y: 0,
               mostrar(d) { const w = 320, h = 320; let x = d.r + 12; if (x + w > innerWidth - 8) x = d.l - w - 12;
                            this.x = Math.max(8, x); this.y = Math.max(8, Math.min(d.t + (d.b - d.t) / 2 - h / 2, innerHeight - h - 8));
                            this.src = d.src; this.open = true; } }"
     @img-zoom-show.window="mostrar($event.detail)" @img-zoom-hide.window="open = false" @scroll.window="open = false"
     x-show="open" x-cloak x-transition.opacity.duration.100ms
     :style="`position:fixed;left:${x}px;top:${y}px;width:320px;height:320px;z-index:70`"
     class="pointer-events-none rounded-xl overflow-hidden shadow-2xl border border-[var(--c-border)] bg-white">
    <img :src="src" alt="" referrerpolicy="no-referrer" class="w-full h-full object-contain">
</div>
</body>
</html>
