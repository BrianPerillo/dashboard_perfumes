<div>
    <h2 class="text-lg font-bold text-[var(--c-text)] mb-1">Nueva contraseña</h2>
    <p class="text-sm text-[var(--c-muted)] mb-5">Elegí una contraseña segura de al menos 8 caracteres.</p>

    @if($error)
    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800
                px-4 py-2.5 text-sm text-red-700 dark:text-red-400">
        {{ $error }}
    </div>
    @endif

    <form wire:submit="submit" class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Email</label>
            <input type="email" wire:model="email" readonly
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-bg)] text-[var(--c-muted)]
                          px-3 py-2 text-sm cursor-not-allowed">
        </div>

        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Nueva contraseña</label>
            <input type="password" wire:model="password" autocomplete="new-password"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="Mínimo 8 caracteres">
            @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Confirmar contraseña</label>
            <input type="password" wire:model="password_confirmation" autocomplete="new-password"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="Repetí la contraseña">
        </div>

        <button type="submit"
                wire:loading.attr="disabled"
                class="w-full bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white
                       font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
            <span wire:loading.remove>Guardar nueva contraseña</span>
            <span wire:loading>Guardando…</span>
        </button>
    </form>
</div>
