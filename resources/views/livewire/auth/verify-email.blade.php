<div>
    <div class="text-center mb-6">
        <div class="text-4xl mb-3">📬</div>
        <h2 class="text-lg font-bold text-[var(--c-text)]">Verificá tu email</h2>
        <p class="text-sm text-[var(--c-muted)] mt-2">
            Te enviamos un enlace de verificación a<br>
            <strong class="text-[var(--c-text)]">{{ auth()->user()->email }}</strong>
        </p>
    </div>

    @if($mensaje)
    <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800
                px-4 py-2.5 text-sm text-emerald-700 dark:text-emerald-400 text-center">
        {{ $mensaje }}
    </div>
    @endif

    <div class="space-y-3">
        <button wire:click="reenviar"
                wire:loading.attr="disabled"
                class="w-full bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white
                       font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            <span wire:loading.remove>Reenviar enlace</span>
            <span wire:loading>Enviando…</span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full text-sm text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors py-1.5">
                Cerrar sesión
            </button>
        </form>
    </div>
</div>
