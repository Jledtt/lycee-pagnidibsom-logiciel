<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['status' => 'active'], $request->boolean('remember'))) {
            $this->recordLoginHistory($request, 'failed');

            return back()
                ->withErrors(['username' => 'Identifiant ou mot de passe incorrect.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $this->recordLoginHistory($request, 'success', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->recordLoginHistory($request, 'logout', $request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function recordLoginHistory(Request $request, string $status, ?User $user = null): void
    {
        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'username' => $user?->username ?? $request->string('username')->toString(),
            'status' => $status,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'created_at' => now(),
        ]);
    }
}
