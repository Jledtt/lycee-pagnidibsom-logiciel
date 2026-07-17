<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StaffRoleWebController extends Controller
{
    public function index(): View
    {
        $this->ensurePermissionsExist();

        return view('staff.roles.index', [
            'academicYear' => $this->activeAcademicYear(),
            'permissionGroups' => $this->permissionGroups(),
            'roleLabels' => $this->roleLabels(),
            'roles' => Role::query()
                ->with('permissions')
                ->whereIn('name', array_keys($this->roleLabels()))
                ->orderByRaw($this->roleOrderSql())
                ->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_unless(array_key_exists($role->name, $this->roleLabels()), 404);

        $this->ensurePermissionsExist();
        $role->load('permissions');

        return view('staff.roles.edit', [
            'academicYear' => $this->activeAcademicYear(),
            'permissionGroups' => $this->permissionGroups(),
            'role' => $role,
            'roleLabels' => $this->roleLabels(),
            'selectedPermissions' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(array_key_exists($role->name, $this->roleLabels()), 404);

        $availablePermissions = $this->permissionNames();

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($availablePermissions)],
        ]);

        $this->ensurePermissionsExist();

        if ($role->name === 'admin') {
            $role->syncPermissions($availablePermissions);

            return redirect()
                ->route('staff.roles.index')
                ->with('success', 'Le role Admin conserve tous les acces.');
        }

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route('staff.roles.index')
            ->with('success', 'Acces du role mis a jour.');
    }

    private function permissionGroups(): array
    {
        return [
            'Eleves' => [
                'students.view' => 'Voir les dossiers eleves',
                'students.create' => 'Ajouter un eleve',
                'students.update' => 'Modifier un eleve',
                'students.delete' => 'Archiver un eleve',
                'students.export' => 'Imprimer fiches et certificats',
            ],
            'Inscriptions' => [
                'enrollments.view' => 'Voir les inscriptions',
                'enrollments.create' => 'Creer une inscription',
                'enrollments.update' => 'Modifier une inscription',
                'enrollments.cancel' => 'Annuler une inscription',
            ],
            'Paiements' => [
                'payments.view' => 'Voir les paiements',
                'payments.create' => 'Enregistrer un paiement',
                'payments.cancel' => 'Annuler un paiement',
                'payments.print_receipt' => 'Imprimer les recus',
                'payments.reports' => 'Voir les impayes et rapports',
            ],
            'Notes et bulletins' => [
                'grades.view' => 'Voir les notes',
                'grades.create' => 'Saisir les notes',
                'grades.update' => 'Modifier les notes',
                'grades.lock' => 'Verrouiller les notes',
                'grades.unlock' => 'Deverrouiller les notes',
                'report_cards.view' => 'Voir les bulletins',
                'report_cards.generate' => 'Generer les bulletins',
                'report_cards.validate' => 'Valider les bulletins',
                'report_cards.publish' => 'Publier les bulletins',
                'report_cards.print' => 'Imprimer les bulletins',
            ],
            'Absences' => [
                'attendance.view' => 'Voir les absences',
                'attendance.create' => 'Saisir les absences',
                'attendance.update' => 'Modifier les absences',
                'attendance.justify' => 'Justifier les absences',
                'attendance.reports' => 'Voir les rapports d absences',
            ],
            'Administration' => [
                'users.manage' => 'Gerer les comptes du personnel',
                'roles.manage' => 'Modifier les roles et acces',
                'settings.manage' => 'Modifier les parametres et tarifs',
                'academic_years.manage' => 'Gerer les annees scolaires',
                'classes.manage' => 'Gerer les classes',
                'subjects.manage' => 'Gerer les matieres',
            ],
        ];
    }

    private function permissionNames(): array
    {
        return collect($this->permissionGroups())
            ->flatMap(fn (array $permissions) => array_keys($permissions))
            ->values()
            ->all();
    }

    private function ensurePermissionsExist(): void
    {
        foreach ($this->permissionNames() as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach ($this->roleLabels() as $role => $label) {
            Role::findOrCreate($role);
        }
    }

    private function roleLabels(): array
    {
        return [
            'admin' => 'Admin',
            'direction' => 'Direction',
            'secretariat' => 'Secretariat',
            'comptable' => 'Comptabilite',
            'enseignant' => 'Enseignant',
            'surveillant' => 'Surveillant',
        ];
    }

    private function roleOrderSql(): string
    {
        return "case name when 'admin' then 1 when 'direction' then 2 when 'secretariat' then 3 when 'comptable' then 4 when 'enseignant' then 5 when 'surveillant' then 6 else 99 end";
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
