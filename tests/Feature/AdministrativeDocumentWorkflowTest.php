<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentExitAuthorization;
use App\Models\Timetable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrativeDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_generate_exit_authorization_and_download_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        [$student] = $this->enrolledStudent($user);

        $this->actingAs($user)
            ->get(route('exit-authorizations.create', ['student_id' => $student->id]))
            ->assertOk()
            ->assertSee('Nouvelle autorisation')
            ->assertSee($student->matricule);

        $response = $this->actingAs($user)
            ->post(route('exit-authorizations.store'), [
                'student_id' => $student->id,
                'document_date' => '2026-07-21',
                'departure_at' => '2026-07-21T10:30',
                'return_at' => '2026-07-21T15:00',
                'subject_name' => 'Cours de la journée',
                'destination' => 'Centre de sante',
                'reason' => 'Maladie',
                'notes' => 'Autorisation remise a la vie scolaire.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_exit_authorizations', [
            'student_id' => $student->id,
            'reason' => 'Maladie',
            'destination' => 'Centre de sante',
        ]);

        $authorization = StudentExitAuthorization::query()->firstOrFail();
        $this->assertSame($student->id, $authorization->student_id);

        $this->actingAs($user)
            ->get(route('exit-authorizations.pdf', $authorization))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = view('exit-authorizations.pdf', [
            'authorization' => $authorization->load(['student', 'schoolClass']),
            'school' => SchoolSetting::query()->first(),
        ])->render();

        $this->assertStringContainsString('AUTORISATION D’ENTRÉE ET DE SORTIE', $html);
        $this->assertStringContainsString('Matière concernée', $html);
        $this->assertStringContainsString('Bâtir l&#039;excellence', $html);
    }

    public function test_teacher_attendance_sheet_is_prefilled_from_timetable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        [, $schoolClass, $academicYear] = $this->enrolledStudent($user);
        $this->timetable($user, $schoolClass, $academicYear);

        $this->actingAs($user)
            ->get(route('teacher-attendance-sheets.index'))
            ->assertOk()
            ->assertSee('Fiche d’émargement')
            ->assertSee('KEREGUE Sompeguea');

        $this->actingAs($user)
            ->get(route('teacher-attendance-sheets.pdf', [
                'teacher_name' => 'KEREGUE Sompeguea',
                'start_date' => '2026-07-20',
                'end_date' => '2026-07-25',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->from(route('teacher-attendance-sheets.index'))
            ->get(route('teacher-attendance-sheets.pdf', [
                'teacher_name' => 'KEREGUE Sompeguea',
                'start_date' => '2026-08-25',
                'end_date' => '2026-10-15',
            ]))
            ->assertRedirect(route('teacher-attendance-sheets.index'))
            ->assertSessionHasErrors([
                'end_date' => 'La période ne doit pas dépasser 31 jours.',
            ])
            ->assertSessionHasInput('teacher_name', 'KEREGUE Sompeguea')
            ->assertSessionHasInput('start_date', '2026-08-25')
            ->assertSessionHasInput('end_date', '2026-10-15');
    }

    public function test_exit_authorization_pdf_uses_clean_client_wording(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        [$student, $schoolClass, $academicYear] = $this->enrolledStudent($user);

        $authorization = StudentExitAuthorization::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'document_date' => '2026-07-21',
            'departure_at' => '2026-07-21 10:30:00',
            'return_at' => '2026-07-21 15:00:00',
            'subject_name' => 'Cours de la journée',
            'destination' => 'Centre de santé',
            'reason' => 'Maladie',
            'notes' => 'Autorisation remise à la vie scolaire.',
            'created_by' => $user->id,
        ]);

        $html = view('exit-authorizations.pdf', [
            'authorization' => $authorization->load(['student', 'schoolClass']),
            'school' => SchoolSetting::query()->first(),
        ])->render();

        $this->assertStringContainsString('AUTORISATION D’ENTRÉE ET DE SORTIE', $html);
        $this->assertStringContainsString('Matière concernée', $html);
        $this->assertStringContainsString('Bâtir l&#039;excellence', $html);
    }

    public function test_certificates_use_client_wording_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        [$student, , $academicYear] = $this->enrolledStudent($user);

        $certificate = StudentDocument::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'Certificat de scolarité - '.$student->full_name,
            'document_type' => 'school_certificate',
            'document_number' => 'CERT-TEST-001',
            'status' => 'received',
            'received_at' => '2026-07-21',
        ]);

        $this->actingAs($user)
            ->get(route('certificates.pdf', $certificate))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $student->load('guardians');
        $enrollment = Enrollment::query()
            ->with('schoolClass.level')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->firstOrFail();

        $html = view('certificates.certificate-pdf', [
            'certificate' => $certificate->load('academicYear'),
            'typeLabel' => 'Certificat de scolarité',
            'student' => $student,
            'enrollment' => $enrollment,
            'school' => SchoolSetting::query()->first(),
            'fatherGuardian' => $student->guardians->firstWhere('pivot.relationship', 'father'),
            'motherGuardian' => $student->guardians->firstWhere('pivot.relationship', 'mother'),
            'summary' => ['expected' => null, 'paid' => 0, 'balance' => null],
            'principalName' => 'Yamdaogo TINTILA',
        ])->render();

        $this->assertStringContainsString('Je soussigné(e)', $html);
        $this->assertStringContainsString('fille de', $html);
        $this->assertStringContainsString('présent certificat', $html);
        $this->assertStringContainsString('N° certificat', $html);
    }

    public function test_admin_can_save_accountant_name_for_official_documents(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('admin');
        $settings = SchoolSetting::query()->firstOrFail();
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($user)
            ->put(route('settings.update'), [
                'school_name' => $settings->school_name,
                'short_name' => $settings->short_name,
                'address' => $settings->address,
                'phone' => $settings->phone,
                'email' => $settings->email,
                'website' => $settings->website,
                'currency' => $settings->currency,
                'motto' => $settings->motto,
                'country' => $settings->country,
                'national_motto' => $settings->national_motto,
                'city' => $settings->city,
                'postal_box' => $settings->postal_box,
                'principal_name' => 'Yamdaogo TINTILA',
                'principal_title' => 'Le Proviseur',
                'accountant_name' => 'Mika COMPTABLE',
                'active_academic_year_id' => $academicYear->id,
            ])
            ->assertRedirect(route('settings.edit'));

        $this->assertDatabaseHas('school_settings', [
            'id' => $settings->id,
            'accountant_name' => 'Mika COMPTABLE',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-admin-doc-test-'.uniqid(),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function enrolledStudent(User $user): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrCreate(
            ['name' => '5e A'],
            [
                'cycle' => 'Premier cycle',
                'position' => 2,
            ],
        );
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'code' => '5EA',
            'capacity' => 60,
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'matricule' => 'LPP-TEST-'.uniqid(),
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'birth_date' => '2011-05-20',
            'birth_place' => 'Ouagadougou',
            'status' => 'active',
        ]);
        $father = Guardian::query()->create([
            'first_name' => 'Moussa',
            'last_name' => 'Ouedraogo',
            'phone_primary' => '70000000',
            'status' => 'active',
        ]);
        $mother = Guardian::query()->create([
            'first_name' => 'Aminata',
            'last_name' => 'Sawadogo',
            'phone_primary' => '76000000',
            'status' => 'active',
        ]);

        $student->guardians()->attach($father->id, ['relationship' => 'father', 'is_primary' => true]);
        $student->guardians()->attach($mother->id, ['relationship' => 'mother']);

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => '2026-07-21',
            'type' => 'new',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        return [$student, $schoolClass, $academicYear];
    }

    private function timetable(User $user, SchoolClass $schoolClass, AcademicYear $academicYear): Timetable
    {
        $timetable = Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'title' => 'Emploi test',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $timetable->entries()->createMany([
            [
                'sort_order' => 1,
                'period_label' => '7h-8h',
                'starts_at' => '07:00',
                'ends_at' => '08:00',
                'day_of_week' => 'monday',
                'subject_name' => 'Histoire-Géographie',
                'teacher_name' => 'KEREGUE Sompeguea',
            ],
            [
                'sort_order' => 2,
                'period_label' => '15h-16h',
                'starts_at' => '15:00',
                'ends_at' => '16:00',
                'day_of_week' => 'wednesday',
                'subject_name' => 'Français',
                'teacher_name' => 'KEREGUE Sompeguea',
            ],
        ]);

        return $timetable;
    }
}
