<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Inicio de sesión con Google. Por defecto solo entra quien ya está registrado como empleado
 * (mismo correo); la regla "google_signup" permite crear la cuenta como empleado.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect|RedirectResponse
    {
        if (! config('services.google.client_id')) {
            return redirect()->route('login')->withErrors(['email' => 'El inicio de sesión con Google no está configurado.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'No se pudo validar la cuenta de Google. Inténtalo de nuevo.']);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $google->getEmail())->first();

        if (! $user && setting('google_signup')) {
            $user = User::create([
                'name' => $google->getName() ?: $google->getEmail(),
                'email' => $google->getEmail(),
                'password' => Str::random(40),
                'role_id' => Role::where('slug', 'employee')->value('id'),
                'status' => 'active',
                'hire_date' => today(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Tu correo de Google no está registrado. Pide a RR. HH. que cree tu cuenta.']);
        }

        if ($user->status !== 'active') {
            return redirect()->route('login')->withErrors(['email' => 'Tu cuenta no está activa. Contacta a RR. HH.']);
        }

        $user->forceFill(['google_id' => $google->getId(), 'avatar' => $google->getAvatar()])->save();

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
