<?php

namespace App\Http\Controllers\Regie;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Fingerprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Connexion des 2 modérateurs. Aucune inscription publique n'existe :
 * les comptes sont seedés.
 */
class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('regie.dashboard');
        }

        return view('regie.connexion');
    }

    public function login(Request $request): RedirectResponse
    {
        $throttleKey = 'admin-login:'.Fingerprint::ip($request);
        $maxAttempts = (int) config('tembo.rate_limits.admin_login.attempts');

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($throttleKey) / 60));

            return back()->withErrors([
                'email' => "Trop de tentatives. Patientez {$minutes} min avant de réessayer.",
            ])->onlyInput('email');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Saisissez votre adresse e-mail.',
            'email.email' => 'Cette adresse e-mail n’est pas valide.',
            'password.required' => 'Saisissez votre mot de passe.',
        ]);

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey, (int) config('tembo.rate_limits.admin_login.decay_minutes') * 60);

            return back()->withErrors([
                'email' => 'E-mail ou mot de passe incorrect.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        AuditLog::write('regie.login', Auth::user()->name);

        return redirect()->route('regie.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLog::write('regie.logout', Auth::user()->name);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('regie.connexion');
    }
}
