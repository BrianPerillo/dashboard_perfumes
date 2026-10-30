<div>
    <h2 class="text-lg font-bold text-[var(--c-text)] mb-5">Iniciar sesión</h2>

    @if($error)
    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-2.5 text-sm text-red-700 dark:text-red-400">
        {{ $error }}
    </div>
    @endif

    <form wire:submit="submit" class="space-y-4"
          x-data="{
              loading: false,
              dots: '',
              _timer: null,
              init() {
                  window.addEventListener('login-failed', () => this._reset());
              },
              _start() {
                  this.loading = true;
                  this._timer = setInterval(() => {
                      this.dots = this.dots.length >= 3 ? '' : this.dots + '.';
                  }, 380);
              },
              _reset() {
                  this.loading = false;
                  clearInterval(this._timer);
                  this.dots = '';
              }
          }"
          @submit="if (!loading) _start()">

        <div>
            <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide mb-1.5">Email</label>
            <input type="email" wire:model="email" autocomplete="email"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="tu@email.com">
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-semibold text-[var(--c-muted)] uppercase tracking-wide">Contraseña</label>
                <a href="{{ route('password.request') }}"
                   class="text-xs text-[var(--color-primary-500)] hover:underline">¿Olvidaste tu contraseña?</a>
            </div>
            <input type="password" wire:model="password" autocomplete="current-password"
                   class="w-full rounded-lg border border-[var(--c-border)] bg-[var(--c-input)] text-[var(--c-text)]
                          px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-400)] focus:border-transparent"
                   placeholder="••••••••">
            @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" wire:model="remember" class="rounded border-[var(--c-border)] text-[var(--color-primary-500)]">
            <span class="text-sm text-[var(--c-muted)]">Recordarme</span>
        </label>

        <button type="submit"
                :disabled="loading"
                :class="loading ? 'opacity-75 cursor-wait' : 'hover:bg-[var(--color-primary-700)] cursor-pointer'"
                class="w-full bg-[var(--color-primary-600)] text-white
                       font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors
                       flex items-center justify-center">
            <span x-show="!loading">Ingresar</span>
            <span x-show="loading" x-cloak>Ingresando<span x-text="dots"></span></span>
        </button>
    </form>

    <p class="text-center text-sm text-[var(--c-muted)] mt-5">
        ¿No tenés cuenta?
        <a href="{{ route('register') }}" class="text-[var(--color-primary-500)] font-semibold hover:underline">Crear una</a>
    </p>
</div>
