<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

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
