<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_routes_apply_the_validated_role_matrix(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = $this->student('LPP-API-MATRIX');

        foreach (['admin', 'direction', 'secretariat', 'comptable', 'enseignant', 'surveillant'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));

            $this->getJson('/api/students')->assertOk();

            $this->postJson('/api/students', [])->assertStatus(
                in_array($role, ['admin', 'direction', 'secretariat'], true) ? 422 : 403
            );

            $this->patchJson('/api/students/'.$student->id, [])->assertStatus(
                in_array($role, ['admin', 'direction', 'secretariat'], true) ? 200 : 403
            );

            $this->getJson('/api/enrollments')->assertStatus(
                in_array($role, ['admin', 'direction', 'secretariat'], true) ? 200 : 403
            );

            $this->postJson('/api/enrollments', [])->assertStatus(
                in_array($role, ['admin', 'direction', 'secretariat'], true) ? 422 : 403
            );

            $this->getJson('/api/payments')->assertStatus(
                in_array($role, ['admin', 'direction', 'comptable'], true) ? 200 : 403
            );

            $this->postJson('/api/payments', [])->assertStatus(
                in_array($role, ['admin', 'direction', 'comptable'], true) ? 422 : 403
            );
        }
    }

    public function test_unauthenticated_requests_cannot_reach_protected_api_routes(): void
    {
        $this->getJson('/api/students')->assertUnauthorized();
        $this->getJson('/api/enrollments')->assertUnauthorized();
        $this->getJson('/api/payments')->assertUnauthorized();
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_accounting_payment_payload_contains_identity_but_no_confidential_student_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $accountant = $this->userWithRole('comptable');
        $student = $this->student('LPP-API-FINANCE');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        Payment::query()->create([
            'receipt_number' => 'REC-API-0001',
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'paid_at' => now(),
            'amount' => 25000,
            'payment_method' => 'cash',
            'status' => 'valid',
            'received_by' => $accountant->id,
        ]);

        Sanctum::actingAs($accountant);

        $this->getJson('/api/payments')
            ->assertOk()
            ->assertJsonPath('data.0.student.matricule', $student->matricule)
            ->assertJsonPath('data.0.student.full_name', $student->full_name)
            ->assertJsonMissingPath('data.0.student.address')
            ->assertJsonMissingPath('data.0.student.health_notes')
            ->assertJsonMissingPath('data.0.student.health_conditions')
            ->assertJsonMissingPath('data.0.student.emergency_contact_phone');

        $this->getJson('/api/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('matricule', $student->matricule)
            ->assertJsonMissingPath('address')
            ->assertJsonMissingPath('health_notes')
            ->assertJsonMissingPath('guardians')
            ->assertJsonMissingPath('payments');
    }

    private function student(string $matricule): Student
    {
        return Student::query()->create([
            'matricule' => $matricule,
            'first_name' => 'Awa',
            'last_name' => 'Sawadogo',
            'gender' => 'female',
            'address' => 'Adresse confidentielle',
            'health_notes' => 'Données médicales confidentielles',
            'health_conditions' => ['asthme'],
            'emergency_contact_phone' => '70000000',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-api-matrix-'.str()->lower(str()->random(6)),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
