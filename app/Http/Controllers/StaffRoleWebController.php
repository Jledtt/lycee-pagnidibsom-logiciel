<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\AuditTrailService;
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
            'actionLabels' => $this->actionLabels(),
            'academicYear' => $this->activeAcademicYear(),
            'permissionActions' => $this->permissionActions(),
            'permissionGroups' => $this->permissionGroups(),
            'roleDescriptions' => $this->roleDescriptions(),
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
            'actionLabels' => $this->actionLabels(),
            'academicYear' => $this->activeAcademicYear(),
            'permissionActions' => $this->permissionActions(),
            'permissionGroups' => $this->permissionGroups(),
            'role' => $role,
            'roleDescriptions' => $this->roleDescriptions(),
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
            $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
            $newPermissions = collect($availablePermissions)->sort()->values()->all();
            $role->syncPermissions($newPermissions);
            app(AuditTrailService::class)->record('permissions_updated', $role, [
                'permissions' => $oldPermissions,
            ], [
                'permissions' => $newPermissions,
            ]);

            return redirect()
                ->route('staff.roles.index')
                ->with('success', 'Le rôle Admin conserve tous les accès.');
        }

        $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        $newPermissions = collect($data['permissions'] ?? [])->sort()->values()->all();
        $role->syncPermissions($newPermissions);
        app(AuditTrailService::class)->record('permissions_updated', $role, [
            'permissions' => $oldPermissions,
        ], [
            'permissions' => $newPermissions,
        ]);

        return redirect()
            ->route('staff.roles.index')
            ->with('success', 'Accès du rôle mis à jour.');
    }

    private function permissionGroups(): array
    {
        return [
            'Élèves' => [
                'students.view' => 'Voir les dossiers élèves',
                'students.create' => 'Ajouter un élève',
                'students.update' => 'Modifier un élève',
                'students.delete' => 'Archiver un élève',
                'students.export' => 'Imprimer fiches et certificats',
                'students.import' => 'Importer des élèves en masse',
            ],
            'Inscriptions' => [
                'enrollments.view' => 'Voir les inscriptions',
                'enrollments.create' => 'Créer une inscription',
                'enrollments.update' => 'Modifier une inscription',
                'enrollments.cancel' => 'Annuler une inscription',
            ],
            'Paiements' => [
                'payments.view' => 'Voir les paiements',
                'payments.create' => 'Enregistrer un paiement',
                'payments.cancel' => 'Annuler un paiement',
                'payments.print_receipt' => 'Imprimer les reçus',
                'payments.reports' => 'Voir encaissements, impayés et rapports financiers',
            ],
            'Notes et bulletins' => [
                'grades.view' => 'Voir les notes',
                'grades.create' => 'Saisir les notes',
                'grades.update' => 'Modifier les notes',
                'grades.lock' => 'Verrouiller les notes',
                'grades.unlock' => 'Déverrouiller les notes',
                'report_cards.view' => 'Voir les bulletins',
                'report_cards.generate' => 'Générer les bulletins',
                'report_cards.validate' => 'Valider les bulletins',
                'report_cards.publish' => 'Publier les bulletins',
                'report_cards.print' => 'Imprimer les bulletins',
            ],
            'Absences' => [
                'attendance.view' => 'Voir les absences',
                'attendance.create' => 'Saisir les absences',
                'attendance.update' => 'Modifier les absences',
                'attendance.justify' => 'Justifier les absences',
                'attendance.reports' => 'Voir les rapports d’absences',
            ],
            'Emplois du temps' => [
                'timetables.view' => 'Voir les emplois du temps',
                'timetables.manage' => 'Créer et modifier les emplois du temps',
                'timetables.print' => 'Imprimer les emplois du temps',
            ],
            'Administration' => [
                'users.manage' => 'Gérer les comptes du personnel',
                'roles.manage' => 'Modifier les rôles et accès',
                'activity_logs.view' => 'Consulter le journal d’activité',
                'settings.manage' => 'Modifier les paramètres et tarifs',
                'academic_years.manage' => 'Gérer les années scolaires',
                'classes.manage' => 'Gérer les classes',
                'subjects.manage' => 'Gérer les matières',
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

    private function permissionActions(): array
    {
        return [
            'students.view' => 'view',
            'students.create' => 'modify',
            'students.update' => 'modify',
            'students.delete' => 'modify',
            'students.export' => 'print',
            'students.import' => 'modify',
            'enrollments.view' => 'view',
            'enrollments.create' => 'modify',
            'enrollments.update' => 'modify',
            'enrollments.cancel' => 'modify',
            'payments.view' => 'view',
            'payments.create' => 'modify',
            'payments.cancel' => 'modify',
            'payments.print_receipt' => 'print',
            'payments.reports' => 'report',
            'grades.view' => 'view',
            'grades.create' => 'modify',
            'grades.update' => 'modify',
            'grades.lock' => 'manage',
            'grades.unlock' => 'manage',
            'report_cards.view' => 'view',
            'report_cards.generate' => 'modify',
            'report_cards.validate' => 'manage',
            'report_cards.publish' => 'manage',
            'report_cards.print' => 'print',
            'attendance.view' => 'view',
            'attendance.create' => 'modify',
            'attendance.update' => 'modify',
            'attendance.justify' => 'modify',
            'attendance.reports' => 'report',
            'timetables.view' => 'view',
            'timetables.manage' => 'modify',
            'timetables.print' => 'print',
            'users.manage' => 'manage',
            'roles.manage' => 'manage',
            'activity_logs.view' => 'view',
            'settings.manage' => 'manage',
            'academic_years.manage' => 'manage',
            'classes.manage' => 'manage',
            'subjects.manage' => 'manage',
        ];
    }

    private function actionLabels(): array
    {
        return [
            'view' => 'Voir',
            'modify' => 'Modifier',
            'print' => 'Imprimer',
            'report' => 'Rapports',
            'manage' => 'Administrer',
        ];
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
            'comptable' => 'Comptabilité',
            'enseignant' => 'Professeur',
            'surveillant' => 'Vie scolaire',
        ];
    }

    private function roleDescriptions(): array
    {
        return [
            'admin' => 'Contrôle complet du logiciel, des utilisateurs, des paramètres et des corrections.',
            'direction' => 'Suivi global de l etablissement, rapports, bulletins et controles sans saisie financiere.',
            'secretariat' => 'Gestion quotidienne des dossiers élèves, inscriptions, imports et documents administratifs.',
            'comptable' => 'Paiements, reçus, impayés et rapports financiers, sans accès aux notes ni aux paramètres.',
            'enseignant' => 'Saisie pedagogique: notes, absences et consultation des dossiers utiles.',
            'surveillant' => 'Vie scolaire: absences, retards, justificatifs et rapports d assiduite.',
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
