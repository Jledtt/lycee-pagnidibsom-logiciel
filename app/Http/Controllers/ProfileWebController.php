<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\AcademicYear;
use App\Services\UserAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()
            ->route('profile.show')
            ->with('success', 'Profil mis à jour.');
    }

    public function updatePassword(UpdatePasswordRequest $request, UserAuditService $userAuditService): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->update([
            'password' => $data['password'],
        ]);

        $userAuditService->recordPasswordChanged($request, $request->user());

        return redirect()
            ->route('profile.show')
            ->with('success', 'Mot de passe mis à jour.');
    }
}
