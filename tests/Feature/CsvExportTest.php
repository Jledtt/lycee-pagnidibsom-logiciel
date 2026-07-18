<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_export_students_csv(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        Student::query()->create([
            'matricule' => 'LPP-TEST-001',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('students.export'));
        $content = $response->streamedContent();

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Matricule;Nom;Prenom', $content);
        $this->assertStringContainsString('LPP-TEST-001', $content);
    }

    public function test_secretariat_cannot_export_payments_csv(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $this->actingAs($user)->get(route('payments.export'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . '-csv-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
