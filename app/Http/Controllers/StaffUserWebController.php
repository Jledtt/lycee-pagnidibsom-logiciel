<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'],
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('staff.show', $user)
            ->with('success', 'Compte personnel cree avec succes.');
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

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUser($request, $user);

        if ($user->is($request->user()) && $data['status'] !== 'active') {
            return back()
                ->withErrors(['status' => 'Tu ne peux pas desactiver ton propre compte.'])
                ->withInput();
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('staff.show', $user)
            ->with('success', 'Compte personnel mis a jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Tu ne peux pas desactiver ton propre compte.']);
        }

        $user->update(['status' => 'inactive']);

        return redirect()
            ->route('staff.index')
            ->with('success', 'Compte personnel desactive.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $roleNames = array_keys($this->roleLabels());

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'username' => ['required', 'string', 'max:80', Rule::unique('users', 'username')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'role' => ['required', Rule::in($roleNames)],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);
    }

    private function roleLabels(): array
    {
        $labels = [
            'admin' => 'Admin',
            'direction' => 'Direction',
            'secretariat' => 'Secretariat',
            'comptable' => 'Comptabilite',
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
