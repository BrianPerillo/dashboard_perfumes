<div wire:poll.5s="checkVerified">
    <div class="text-center mb-6">
        <div class="text-4xl mb-3">🔐</div>
        <h2 class="text-lg font-bold text-[var(--c-text)]">Verificá tu cuenta</h2>
        <p class="text-sm text-[var(--c-muted)] mt-2">
            Ingresá el código de 6 dígitos que enviamos a<br>
            <strong class="text-[var(--c-text)]">{{ auth()->user()->email }}</strong>
        </p>
    </div>

    @if($mensaje)
    <div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800
                px-4 py-2.5 text-sm text-emerald-700 dark:text-emerald-400 text-center">
        {{ $mensaje }}
    </div>
    @endif

    @if($error)
    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800
                px-4 py-2.5 text-sm text-red-700 dark:text-red-400 text-center">
        {{ $error }}
    </div>
    @endif

    <div
        x-data="{
            digits: @entangle('digits'),
            handleInput(e, i) {
                const val = e.target.value.replace(/\D/g, '');
                if (val.length > 1) {
                    // pasted multi-char into a single box — ignore, paste handler covers it
                    e.target.value = this.digits[i] || '';
                    return;
                }
                this.digits[i] = val;
                if (val && i < 5) {
                    $el.querySelectorAll('input')[i + 1].focus();
                }
            },
            handleKeydown(e, i) {
                if (e.key === 'Backspace' && !this.digits[i] && i > 0) {
                    this.digits[i - 1] = '';
                    $el.querySelectorAll('input')[i - 1].focus();
                }
            },
            handlePaste(e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                for (let i = 0; i < 6; i++) {
                    this.digits[i] = text[i] || '';
                }
                const next = Math.min(text.length, 5);
                $el.querySelectorAll('input')[next].focus();
            }
        }"
        class="mb-5"
    >
        <div class="flex justify-center gap-2">
            @for ($i = 0; $i < 6; $i++)
            <input
                type="text"
                inputmode="numeric"
                maxlength="1"
                x-model="digits[{{ $i }}]"
                @input="handleInput($event, {{ $i }})"
                @keydown="handleKeydown($event, {{ $i }})"
                @paste="handlePaste($event)"
                style="width:48px;height:58px;flex:none;"
                class="text-center text-2xl font-bold rounded-lg border-2
                       border-[var(--color-primary-600)] bg-[var(--c-surface)]
                       text-[var(--c-text)] focus:outline-none focus:border-[var(--color-primary-500)]
                       focus:ring-2 focus:ring-[var(--color-primary-500)]/30 transition-all"
            >
            @endfor
        </div>
    </div>

    <div class="space-y-3">
        <button wire:click="verificar"
                wire:loading.attr="disabled"
                class="w-full bg-[var(--color-primary-600)] hover:bg-[var(--color-primary-700)] text-white
                       font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors cursor-pointer">
            <span wire:loading.remove wire:target="verificar">Verificar código</span>
            <span wire:loading wire:target="verificar">Verificando…</span>
        </button>

        <button wire:click="reenviar"
                wire:loading.attr="disabled"
                class="w-full text-sm text-[var(--color-primary-500)] hover:text-[var(--color-primary-600)] transition-colors py-1.5">
            <span wire:loading.remove wire:target="reenviar">Reenviar código</span>
            <span wire:loading wire:target="reenviar">Enviando…</span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="w-full text-sm text-[var(--c-muted)] hover:text-[var(--c-text)] transition-colors py-1.5">
                Cancelar y salir
            </button>
        </form>
    </div>
</div>
