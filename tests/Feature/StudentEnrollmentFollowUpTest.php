<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEnrollmentFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_creation_offers_the_expected_follow_up_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');

        $response = $this->actingAs($user)->post(route('students.store'), [
            'first_name' => 'Nadia',
            'last_name' => 'Suivi',
            'gender' => 'female',
        ]);

        $student = Student::query()->where('last_name', 'Suivi')->firstOrFail();

        $response
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHas('student_created', true);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="student-created-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false)
            ->assertSee('Inscrire maintenant')
            ->assertSee(route('enrollments.create', ['student_id' => $student->id]), false)
            ->assertSee('Ajouter des documents')
            ->assertSee('Voir le dossier')
            ->assertSee('id="student-document-dialog"', false);
    }

    public function test_enrollment_prefill_and_creation_offer_the_expected_follow_up_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        $student = Student::query()->create([
            'matricule' => 'LPP-FOLLOW-001',
            'first_name' => 'Issa',
            'last_name' => 'Parcours',
            'status' => 'active',
        ]);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e Suivi',
            'code' => '5SUIVI',
            'status' => 'active',
        ]);
        $feeType = FeeType::query()->create([
            'name' => 'Inscription suivi',
            'code' => 'INS-SUIVI',
            'is_required' => true,
            'status' => 'active',
        ]);
        FeeSchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'fee_type_id' => $feeType->id,
            'amount' => 50000,
            'period' => 'Inscription',
        ]);

        $this->actingAs($user)
            ->get(route('enrollments.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('value="'.$student->id.'" selected', false);

        $response = $this->actingAs($user)->post(route('enrollments.store'), [
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => '2026-09-15',
            'type' => 'new',
            'status' => 'active',
        ]);

        $enrollment = Enrollment::query()->where('student_id', $student->id)->firstOrFail();

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('enrollment_created', true);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="enrollment-created-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false)
            ->assertSee('Premier paiement')
            ->assertSee('Fiche d’inscription')
            ->assertSee('Carte scolaire')
            ->assertSee('Voir la classe')
            ->assertSee('id="enrollment-payment-dialog"', false)
            ->assertSee('id="enrollment-summary-drawer"', false)
            ->assertSee('id="enrollment-financial-drawer"', false);

        $this->actingAs($user)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('id="student-enrollment-drawer"', false)
            ->assertSee('id="student-financial-drawer"', false);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-student-follow-up-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
