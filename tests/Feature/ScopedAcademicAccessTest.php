<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentExitAuthorization;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScopedAcademicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_only_sees_students_from_assigned_classes(): void
    {
        $context = $this->academicContext();

        $this->actingAs($context['teacher'])
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee($context['assignedStudent']->matricule)
            ->assertDontSee($context['otherStudent']->matricule)
            ->assertDontSee('Tuteur confidentiel');

        $this->actingAs($context['teacher'])
            ->get(route('students.show', $context['assignedStudent']))
            ->assertOk()
            ->assertSee('Informations utiles')
            ->assertDontSee('Sante strictement confidentielle')
            ->assertDontSee('Adresse strictement confidentielle');

        $this->actingAs($context['teacher'])
            ->get(route('students.show', $context['otherStudent']))
            ->assertNotFound();
    }

    public function test_accounting_gets_identity_and_finance_without_full_student_record(): void
    {
        $context = $this->academicContext();
        $accountant = $this->userWithRole('comptable');

        $this->actingAs($accountant)
            ->get(route('students.show', $context['assignedStudent']))
            ->assertOk()
            ->assertSee('Informations utiles')
            ->assertSee('Situation financière')
            ->assertDontSee('Sante strictement confidentielle')
            ->assertDontSee('Adresse strictement confidentielle');
    }

    public function test_confidential_student_documents_are_limited_to_authorized_staff(): void
    {
        $context = $this->academicContext();

        Storage::fake('documents');
        Storage::disk('documents')->put('students/confidential.pdf', 'document-confidentiel');

        $document = StudentDocument::query()->create([
            'student_id' => $context['assignedStudent']->id,
            'academic_year_id' => $context['academicYear']->id,
            'name' => 'Certificat medical confidentiel',
            'document_type' => 'medical_certificate',
            'file_path' => 'students/confidential.pdf',
            'status' => 'received',
            'received_at' => now()->toDateString(),
        ]);

        $this->actingAs($context['teacher'])
            ->get(route('student-documents.show', $document))
            ->assertNotFound();

        $this->actingAs($this->userWithRole('comptable'))
            ->get(route('student-documents.download', $document))
            ->assertNotFound();

        $this->actingAs($this->userWithRole('secretariat'))
            ->get(route('student-documents.show', $document))
            ->assertOk();
    }

    public function test_teacher_only_accesses_assigned_assessments_and_subjects(): void
    {
        $context = $this->academicContext();

        $indexResponse = $this->actingAs($context['teacher'])
            ->get(route('grades.index'));
        $indexResponse
            ->assertOk()
            ->assertSee($context['assignedClass']->name)
            ->assertDontSee($context['otherClass']->name)
            ->assertSee($context['assignedAssessment']->title)
            ->assertDontSee($context['otherAssessment']->title);

        $this->actingAs($context['teacher'])
            ->get(route('grades.index', ['school_class_id' => $context['otherClass']->id]))
            ->assertNotFound();

        $this->actingAs($context['teacher'])
            ->get(route('grades.index', [
                'school_class_id' => $context['assignedClass']->id,
                'term_id' => $context['term']->id,
                'assessment_id' => $context['otherAssessment']->id,
            ]))
            ->assertNotFound();

        $templateResponse = $this->actingAs($context['teacher'])
            ->get(route('grades.import.template', $context['assignedAssessment']));
        $templateResponse->assertOk();

        $forbiddenTemplateResponse = $this->actingAs($context['teacher'])
            ->get(route('grades.import.template', $context['otherAssessment']));
        $forbiddenTemplateResponse->assertNotFound();

        $forbiddenCreateResponse = $this->actingAs($context['teacher'])
            ->post(route('grades.assessments.store'), [
                'school_class_id' => $context['otherClass']->id,
                'term_id' => $context['term']->id,
                'subject_id' => $context['otherSubject']->id,
                'assessment_type_id' => $context['assessmentType']->id,
                'title' => 'Evaluation interdite',
                'max_score' => 20,
            ]);
        $forbiddenCreateResponse->assertNotFound();
    }

    public function test_teacher_attendance_access_is_limited_to_assigned_classes(): void
    {
        $context = $this->academicContext();

        $this->actingAs($context['teacher'])
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee($context['assignedClass']->name)
            ->assertDontSee($context['otherClass']->name);

        $this->actingAs($context['teacher'])
            ->get(route('attendance.sessions.edit', $context['assignedSession']))
            ->assertOk();

        $this->actingAs($context['teacher'])
            ->get(route('attendance.sessions.edit', $context['otherSession']))
            ->assertNotFound();

        $this->actingAs($context['teacher'])
            ->get(route('attendance.index', ['school_class_id' => $context['otherClass']->id]))
            ->assertNotFound();

        $this->actingAs($context['teacher'])
            ->post(route('attendance.sessions.store'), [
                'school_class_id' => $context['otherClass']->id,
                'session_date' => now()->toDateString(),
            ])
            ->assertNotFound();

        $this->actingAs($this->userWithRole('surveillant'))
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee($context['assignedClass']->name)
            ->assertSee($context['otherClass']->name);
    }

    public function test_teacher_exit_authorizations_are_limited_to_assigned_classes(): void
    {
        $context = $this->academicContext();
        $assignedAuthorization = $this->exitAuthorization(
            $context['academicYear'],
            $context['assignedClass'],
            $context['assignedStudent'],
            $context['teacher'],
            'Sortie classe autorisée',
        );
        $otherAuthorization = $this->exitAuthorization(
            $context['academicYear'],
            $context['otherClass'],
            $context['otherStudent'],
            $context['otherTeacher'],
            'Sortie classe interdite',
        );

        $this->actingAs($context['teacher'])
            ->get(route('exit-authorizations.index'))
            ->assertOk()
            ->assertSee($assignedAuthorization->reason)
            ->assertDontSee($otherAuthorization->reason);

        $this->actingAs($context['teacher'])
            ->get(route('exit-authorizations.show', $assignedAuthorization))
            ->assertOk();

        $this->actingAs($context['teacher'])
            ->get(route('exit-authorizations.show', $otherAuthorization))
            ->assertNotFound();

        $this->actingAs($context['teacher'])
            ->get(route('exit-authorizations.create', ['student_id' => $context['otherStudent']->id]))
            ->assertNotFound();

        $this->actingAs($context['teacher'])
            ->post(route('exit-authorizations.store'), [
                'student_id' => $context['otherStudent']->id,
                'document_date' => now()->toDateString(),
                'reason' => 'Tentative hors périmètre',
            ])
            ->assertNotFound();

        $this->actingAs($this->userWithRole('surveillant'))
            ->get(route('exit-authorizations.index'))
            ->assertOk()
            ->assertSee($assignedAuthorization->reason)
            ->assertSee($otherAuthorization->reason);
    }

    public function test_sanctum_student_endpoints_apply_scope_and_safe_fields(): void
    {
        $context = $this->academicContext();

        Sanctum::actingAs($context['teacher']);

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonFragment(['matricule' => $context['assignedStudent']->matricule])
            ->assertJsonMissing(['matricule' => $context['otherStudent']->matricule])
            ->assertJsonMissing(['health_notes' => 'Sante strictement confidentielle'])
            ->assertJsonMissing(['address' => 'Adresse strictement confidentielle']);

        $this->getJson('/api/students/'.$context['assignedStudent']->id)
            ->assertOk()
            ->assertJsonPath('matricule', $context['assignedStudent']->matricule)
            ->assertJsonMissingPath('health_notes')
            ->assertJsonMissingPath('address')
            ->assertJsonMissingPath('guardians')
            ->assertJsonMissingPath('payments');

        $this->getJson('/api/students/'.$context['otherStudent']->id)
            ->assertNotFound();
    }

    public function test_sanctum_dashboard_hides_finance_from_teacher(): void
    {
        $context = $this->academicContext();
        $context['otherSession']->records()->update(['status' => 'absent']);

        Sanctum::actingAs($context['teacher']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('students_count', 1)
            ->assertJsonMissingPath('payments_total')
            ->assertJsonMissingPath('enrollments_count')
            ->assertJsonPath('absences_today', 0);
    }

    private function academicContext(): array
    {
        $this->seed(DatabaseSeeder::class);

        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $subjects = Subject::query()->orderBy('id')->take(2)->get();
        $assignedSubject = $subjects->firstOrFail();
        $otherSubject = $subjects->get(1) ?? Subject::query()->create([
            'name' => 'Matiere hors perimetre',
            'code' => 'HORS',
            'status' => 'active',
        ]);
        $term = $academicYear->terms()->orderBy('position')->firstOrFail();
        $assessmentType = AssessmentType::query()->firstOrFail();
        $teacher = $this->userWithRole('enseignant');
        $otherTeacher = $this->userWithRole('enseignant');

        $assignedClass = $this->schoolClass($academicYear, $level, '3e Scope A', '3S-A');
        $otherClass = $this->schoolClass($academicYear, $level, '3e Scope B', '3S-B');

        ClassSubject::query()->create([
            'school_class_id' => $assignedClass->id,
            'subject_id' => $assignedSubject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 4,
            'is_active' => true,
        ]);
        ClassSubject::query()->create([
            'school_class_id' => $otherClass->id,
            'subject_id' => $otherSubject->id,
            'teacher_id' => $otherTeacher->id,
            'coefficient' => 2,
            'weekly_hours' => 4,
            'is_active' => true,
        ]);

        $assignedStudent = $this->student('LPP-SCOPE-001', 'Awa', 'Affectee');
        $otherStudent = $this->student('LPP-SCOPE-002', 'Moussa', 'Hors Classe');
        $this->enroll($academicYear, $assignedClass, $assignedStudent);
        $this->enroll($academicYear, $otherClass, $otherStudent);

        $assignedAssessment = $this->assessment(
            $academicYear,
            $term->id,
            $assignedClass,
            $assignedSubject,
            $assessmentType,
            $teacher,
            'Evaluation classe affectee',
        );
        $otherAssessment = $this->assessment(
            $academicYear,
            $term->id,
            $otherClass,
            $otherSubject,
            $assessmentType,
            $otherTeacher,
            'Evaluation classe interdite',
        );

        $assignedSession = $this->attendanceSession($academicYear, $assignedClass, $teacher, $assignedStudent);
        $otherSession = $this->attendanceSession($academicYear, $otherClass, $otherTeacher, $otherStudent);

        return compact(
            'academicYear',
            'term',
            'assessmentType',
            'teacher',
            'otherTeacher',
            'assignedClass',
            'otherClass',
            'assignedSubject',
            'otherSubject',
            'assignedStudent',
            'otherStudent',
            'assignedAssessment',
            'otherAssessment',
            'assignedSession',
            'otherSession',
        );
    }

    private function schoolClass(AcademicYear $year, Level $level, string $name, string $code): SchoolClass
    {
        return SchoolClass::query()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'name' => $name,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    private function student(string $matricule, string $firstName, string $lastName): Student
    {
        return Student::query()->create([
            'matricule' => $matricule,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'female',
            'address' => 'Adresse strictement confidentielle',
            'health_notes' => 'Sante strictement confidentielle',
            'status' => 'active',
        ]);
    }

    private function enroll(AcademicYear $year, SchoolClass $class, Student $student): void
    {
        Enrollment::query()->create([
            'academic_year_id' => $year->id,
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'type' => 'new',
        ]);
    }

    private function assessment(
        AcademicYear $year,
        int $termId,
        SchoolClass $class,
        Subject $subject,
        AssessmentType $type,
        User $teacher,
        string $title,
    ): Assessment {
        return Assessment::query()->create([
            'academic_year_id' => $year->id,
            'term_id' => $termId,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $type->id,
            'teacher_id' => $teacher->id,
            'title' => $title,
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
        ]);
    }

    private function attendanceSession(
        AcademicYear $year,
        SchoolClass $class,
        User $teacher,
        Student $student,
    ): AttendanceSession {
        $session = AttendanceSession::query()->create([
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'session_date' => now()->toDateString(),
            'created_by' => $teacher->id,
        ]);

        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        return $session;
    }

    private function exitAuthorization(
        AcademicYear $year,
        SchoolClass $class,
        Student $student,
        User $creator,
        string $reason,
    ): StudentExitAuthorization {
        return StudentExitAuthorization::query()->create([
            'academic_year_id' => $year->id,
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'document_date' => now()->toDateString(),
            'reason' => $reason,
            'created_by' => $creator->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-'.str()->random(8),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
