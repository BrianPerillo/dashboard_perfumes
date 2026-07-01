<div>
    <h2 class="text-lg font-bold text-[var(--c-text)] mb-1">Restablecer contraseña</h2>
    <p class="text-sm text-[var(--c-muted)] mb-5">Ingresá tu email y te enviamos el enlace.</p>

    @if($mensaje)
    <div class="mb-4 rounded-lg px-4 py-2.5 text-sm
        {{ $tipoMensaje === 'success'
            ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400'
            : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400' }}">
        {{ $mensaje }}
    </div>
    @endif

    @if(!$enviado)
    <form wire:submit="submit" class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Email</label>
            <input type="email" wire:model="email" autocomplete="email"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="tu@email.com">
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                wire:loading.attr="disabled"
                class="w-full bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white
                       font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            <span wire:loading.remove>Enviar enlace</span>
            <span wire:loading>Enviando…</span>
        </button>
    </form>
    @endif

    <p class="text-center text-sm text-[var(--c-muted)] mt-5">
        <a href="{{ route('login') }}" class="text-[var(--color-primary-600)] font-semibold hover:underline">← Volver al login</a>
    </p>
</div>
