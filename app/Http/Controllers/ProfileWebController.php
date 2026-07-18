<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileWebController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load(['roles', 'loginHistories' => fn ($query) => $query->latest()->limit(10)]);

        return view('profile.show', [
            'academicYear' => AcademicYear::query()->where('is_active', true)->first(),
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $user->update($data);

        return redirect()
            ->route('profile.show')
            ->with('success', 'Profil mis a jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $data['password'],
        ]);

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'password_changed',
            'auditable_type' => $request->user()::class,
            'auditable_id' => (string) $request->user()->id,
            'auditable_label' => $request->user()->name,
            'description' => 'Changement du mot de passe personnel - User - ' . $request->user()->name,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return redirect()
            ->route('profile.show')
            ->with('success', 'Mot de passe mis a jour.');
    }
}
