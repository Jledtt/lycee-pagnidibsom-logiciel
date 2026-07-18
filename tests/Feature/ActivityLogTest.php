<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_creation_is_recorded_in_activity_log(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $response = $this->actingAs($user)->post(route('students.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
        ]);

        $student = Student::query()->where('first_name', 'Awa')->first();

        $response->assertRedirect(route('students.show', $student));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'created',
            'auditable_type' => Student::class,
            'auditable_id' => (string) $student->id,
        ]);
    }

    public function test_only_authorized_roles_can_open_activity_log(): void
    {
        $this->seed(DatabaseSeeder::class);

        $direction = $this->userWithRole('direction');
        $secretariat = $this->userWithRole('secretariat');

        ActivityLog::query()->create([
            'user_id' => $direction->id,
            'action' => 'updated',
            'auditable_type' => Student::class,
            'auditable_id' => '1',
            'auditable_label' => 'Test',
            'description' => 'Modification - Student - Test',
        ]);

        $this->actingAs($direction)->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('Journal d activite')
            ->assertSee('Modification');

        $this->actingAs($secretariat)->get(route('activity-logs.index'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . '-activity-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
