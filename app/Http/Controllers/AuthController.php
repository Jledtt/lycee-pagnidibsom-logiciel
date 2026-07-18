<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function login(LoginRequest $request, LoginHistoryService $loginHistoryService): RedirectResponse
    {
        if (! Auth::attempt($request->credentials() + ['status' => 'active'], $request->boolean('remember'))) {
            $loginHistoryService->record($request, 'failed');

            return back()
                ->withErrors(['username' => 'Identifiant ou mot de passe incorrect.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        $loginHistoryService->record($request, 'success', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, LoginHistoryService $loginHistoryService): RedirectResponse
    {
        $loginHistoryService->record($request, 'logout', $request->user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
