<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_sensitive_management_areas(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->get(route('staff.roles.index'))->assertOk();
        $this->actingAs($user)->get(route('settings.edit'))->assertOk();
        $this->actingAs($user)->get(route('settings.backups.index'))->assertOk();
        $this->actingAs($user)->get(route('payments.create'))->assertOk();
        $this->actingAs($user)->get(route('grades.index'))->assertOk();
        $this->actingAs($user)->get(route('attendance.index'))->assertOk();
    }

    public function test_secretariat_access_is_limited_to_student_and_enrollment_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $this->actingAs($user)->get(route('students.index'))->assertOk();
        $this->actingAs($user)->get(route('students.create'))->assertOk();
        $this->actingAs($user)->get(route('enrollments.index'))->assertOk();
        $this->actingAs($user)->get(route('classes.index'))->assertOk();

        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('accounting.cash-journal'))->assertForbidden();
        $this->actingAs($user)->get(route('grades.index'))->assertForbidden();
        $this->actingAs($user)->get(route('activity-logs.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.backups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.roles.index'))->assertForbidden();
    }

    public function test_comptable_access_is_limited_to_financial_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');

        $this->actingAs($user)->get(route('students.index'))->assertOk();
        $this->actingAs($user)->get(route('payments.index'))->assertOk();
        $this->actingAs($user)->get(route('payments.create'))->assertOk();
        $this->actingAs($user)->get(route('accounting.cash-journal'))->assertOk();

        $this->actingAs($user)->get(route('classes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('grades.index'))->assertForbidden();
        $this->actingAs($user)->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.backups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.roles.index'))->assertForbidden();
    }

    public function test_surveillant_access_is_limited_to_attendance_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('surveillant');

        $this->actingAs($user)->get(route('students.index'))->assertOk();
        $this->actingAs($user)->get(route('attendance.index'))->assertOk();

        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('grades.index'))->assertForbidden();
        $this->actingAs($user)->get(route('report-cards.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.backups.index'))->assertForbidden();
    }

    public function test_enseignant_access_is_limited_to_pedagogical_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('enseignant');

        $this->actingAs($user)->get(route('students.index'))->assertOk();
        $this->actingAs($user)->get(route('grades.index'))->assertOk();
        $this->actingAs($user)->get(route('attendance.index'))->assertOk();

        $this->actingAs($user)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($user)->get(route('accounting.cash-journal'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.backups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.roles.index'))->assertForbidden();
    }

    public function test_direction_can_consult_reports_without_operational_mutations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('direction');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('reports.payment-situation'))->assertOk();
        $this->actingAs($user)->get(route('report-cards.index'))->assertOk();
        $this->actingAs($user)->get(route('activity-logs.index'))->assertOk();

        $this->actingAs($user)->get(route('payments.create'))->assertForbidden();
        $this->actingAs($user)->get(route('grades.index'))->assertOk();
        $this->actingAs($user)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($user)->get(route('settings.backups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.roles.index'))->assertForbidden();
    }

    public function test_roles_screen_explains_what_roles_can_view_modify_and_print(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        $secretariat = Role::query()->where('name', 'secretariat')->firstOrFail();

        $this->actingAs($user)
            ->get(route('staff.roles.index'))
            ->assertOk()
            ->assertSee('Ce rôle peut')
            ->assertSee('Voir')
            ->assertSee('Modifier')
            ->assertSee('Imprimer')
            ->assertSee('Administrer')
            ->assertSee('Gestion quotidienne des dossiers élèves');

        $this->actingAs($user)
            ->get(route('staff.roles.edit', $secretariat))
            ->assertOk()
            ->assertSee('Secretariat')
            ->assertSee('Voir')
            ->assertSee('Modifier')
            ->assertSee('Imprimer')
            ->assertSee('students.view');
    }

    public function test_admin_can_change_role_permissions_from_role_screen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        $secretariat = Role::query()->where('name', 'secretariat')->firstOrFail();

        $this->actingAs($user)
            ->put(route('staff.roles.update', $secretariat), [
                'permissions' => [
                    'students.view',
                    'students.export',
                    'enrollments.view',
                ],
            ])
            ->assertRedirect(route('staff.roles.index'));

        $secretariat->refresh();

        $this->assertTrue($secretariat->hasPermissionTo('students.view'));
        $this->assertTrue($secretariat->hasPermissionTo('students.export'));
        $this->assertTrue($secretariat->hasPermissionTo('enrollments.view'));
        $this->assertFalse($secretariat->hasPermissionTo('students.create'));
        $this->assertFalse($secretariat->hasPermissionTo('classes.manage'));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'permissions_updated',
            'auditable_type' => Role::class,
            'auditable_id' => (string) $secretariat->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'lpp',
            'event' => 'permissions_updated',
            'subject_type' => Role::class,
            'subject_id' => $secretariat->id,
            'causer_id' => $user->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . '-matrix-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
