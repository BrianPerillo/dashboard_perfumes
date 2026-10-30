<div>
    <h2 class="text-lg font-bold text-[var(--c-text)] mb-5">Crear cuenta</h2>

    <form wire:submit="submit" class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Nombre</label>
            <input type="text" wire:model="name" autocomplete="name"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="Tu nombre">
            @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Email</label>
            <input type="email" wire:model="email" autocomplete="email"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="tu@email.com">
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Contraseña</label>
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
                class="w-full bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white
                       font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors
                       flex items-center justify-center gap-2"
                wire:loading.class="opacity-75 cursor-wait">
            <span wire:loading.class="hidden">Crear cuenta</span>
            <span wire:loading class="hidden">Creando…</span>
        </button>
    </form>

    <p class="text-center text-sm text-[var(--c-muted)] mt-5">
        ¿Ya tenés cuenta?
        <a href="{{ route('login') }}" class="text-[var(--color-primary-500)] font-semibold hover:underline">Ingresá acá</a>
    </p>
</div>
