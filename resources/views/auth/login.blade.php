<x-guest-layout>
    <h2 class="text-xl font-bold text-gray-900">Iniciar sesión</h2>
    <p class="mt-1 text-sm text-gray-500">Ingresa con tu cuenta corporativa para registrar tu asistencia.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    @if (config('services.google.client_id'))
        <a href="{{ route('auth.google') }}" class="btn btn-secondary mt-6 w-full">
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
            Continuar con Google
        </a>
        <div class="my-5 flex items-center gap-3 text-xs uppercase text-gray-400">
            <span class="h-px flex-1 bg-gray-200"></span> o con correo <span class="h-px flex-1 bg-gray-200"></span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="{{ config('services.google.client_id') ? '' : 'mt-6' }} space-y-4">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="checkbox" name="remember">
                <span class="ms-2 text-sm text-gray-600">Recordarme</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm text-indigo-600 hover:text-indigo-800" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-full">Ingresar</button>
    </form>

    @if (app()->environment('local'))
        <div class="mt-6 rounded-lg bg-indigo-50 p-3 text-xs text-indigo-900">
            <div class="font-semibold">Cuentas de demostración (contraseña: <code>password</code>)</div>
            <div class="mt-1 grid grid-cols-2 gap-x-3">
                <span>admin@demo.test</span><span>Administrador</span>
                <span>rrhh@demo.test</span><span>RR. HH.</span>
                <span>gerente@demo.test</span><span>Gerente</span>
                <span>empleado@demo.test</span><span>Empleado</span>
            </div>
        </div>
    @endif
</x-guest-layout>
