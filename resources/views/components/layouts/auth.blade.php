<!DOCTYPE html>
<html lang="es" class="h-full"
      x-data="{ dark: localStorage.getItem('dark') !== 'false' }"
      x-init="if (dark) document.documentElement.classList.add('dark');"
      :class="{ dark: dark }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} · Acceso</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased bg-[var(--c-bg)] text-[var(--c-text)] flex items-center justify-center p-4">

    <div class="w-full max-w-sm">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <span class="text-5xl">🧴</span>
            <h1 class="mt-3 text-2xl font-bold text-violet-500 dark:text-violet-300">Descuentos · Perfumes</h1>
            <p class="text-sm text-[var(--c-muted)] mt-1">Análisis de ofertas en perfumería argentina</p>
        </div>

        <div class="rounded-2xl border border-[var(--c-border)] bg-[var(--c-card)] shadow-lg p-6">
            {{ $slot }}
        </div>

        <p class="text-center text-xs text-[var(--c-muted)] mt-6">
            <a href="{{ route('dashboard') }}" class="hover:text-[var(--color-primary-600)] transition-colors">
                ← Volver al dashboard sin iniciar sesión
            </a>
        </p>
    </div>

    @livewireScripts
</body>
</html>
