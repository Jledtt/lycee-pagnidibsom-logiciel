<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\ResetStaffPasswordRequest;
use App\Http\Requests\Staff\StaffUserRequest;
use App\Models\AcademicYear;
use App\Models\User;
use App\Services\StaffUserService;
use App\Services\UserAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class StaffUserWebController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role')->toString(), fn ($query, string $role) => $query->role($role))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('staff.index', [
            'academicYear' => $this->activeAcademicYear(),
            'filters' => $request->only(['search', 'role', 'status']),
            'roleLabels' => $this->roleLabels(),
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('staff.create', [
            'academicYear' => $this->activeAcademicYear(),
            'roleLabels' => $this->roleLabels(),
            'user' => new User(['status' => 'active']),
            'selectedRole' => 'secretariat',
        ]);
    }

    public function store(StaffUserRequest $request, StaffUserService $staffUserService): RedirectResponse
    {
        $user = $staffUserService->create($request->validated());

        return redirect()
            ->route('staff.show', $user)
            ->with('success', 'Compte personnel créé avec succès.');
    }

    public function show(User $user): View
    {
        $user->load('roles');

        return view('staff.show', [
            'academicYear' => $this->activeAcademicYear(),
            'roleLabels' => $this->roleLabels(),
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('staff.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'roleLabels' => $this->roleLabels(),
            'selectedRole' => $user->roles->first()?->name,
            'user' => $user,
        ]);
    }

    public function update(StaffUserRequest $request, User $user, StaffUserService $staffUserService): RedirectResponse
    {
        $staffUserService->update($user, $request->validated(), $request->user());

        return redirect()
            ->route('staff.show', $user)
            ->with('success', 'Compte personnel mis à jour.');
    }

    public function destroy(Request $request, User $user, StaffUserService $staffUserService): RedirectResponse
    {
        Gate::authorize('deactivate-staff-user', $user);
        $staffUserService->deactivate($user, $request->user());

        return redirect()
            ->route('staff.index')
            ->with('success', 'Compte personnel désactivé.');
    }

    public function resetPassword(ResetStaffPasswordRequest $request, User $user, UserAuditService $userAuditService): RedirectResponse
    {
        $data = $request->validated();

        $password = $data['password'] ?? Str::password(10, true, true, false, false);

        $user->update([
            'password' => $password,
        ]);

        $userAuditService->recordPasswordReset($request, $request->user(), $user);

        return redirect()
            ->route('staff.show', $user)
            ->with('success', 'Mot de passe réinitialisé. Nouveau mot de passe temporaire : '.$password);
    }

    private function roleLabels(): array
    {
        $labels = [
            'admin' => 'Admin',
            'direction' => 'Direction',
            'secretariat' => 'Secretariat',
            'comptable' => 'Comptabilité',
            'enseignant' => 'Enseignant',
            'surveillant' => 'Surveillant',
        ];

        foreach ($labels as $role => $label) {
            Role::findOrCreate($role);
        }

        return $labels;
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
