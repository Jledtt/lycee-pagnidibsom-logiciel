<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Level;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Services\ReportCardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCardOfficialDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_absence_hours_only_count_unjustified_absences_during_the_term(): void
    {
        [$academicYear, $term, $schoolClass, $student] = $this->context();
        $term->update(['starts_at' => '2026-10-01', 'ends_at' => '2026-12-20']);
        $term->refresh();

        $included = $this->attendanceSession($academicYear, $schoolClass, '2026-10-15');
        $justified = $this->attendanceSession($academicYear, $schoolClass, '2026-11-10');
        $outside = $this->attendanceSession($academicYear, $schoolClass, '2027-01-10');

        AttendanceRecord::query()->create([
            'attendance_session_id' => $included->id,
            'student_id' => $student->id,
            'status' => 'absent',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $justified->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'justified_at' => '2026-11-11 08:00:00',
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $outside->id,
            'student_id' => $student->id,
            'status' => 'absent',
        ]);

        $this->assertSame(1.0, app(ReportCardService::class)->absenceHoursFor($student->id, $term));
    }

    public function test_ranked_and_unranked_class_sizes_are_stored_at_generation_time(): void
    {
        [$academicYear, $term, $schoolClass, $firstStudent] = $this->context();
        $secondStudent = $this->enrollStudent($academicYear, $schoolClass, 'LPP-OFFICIEL-2');
        $thirdStudent = $this->enrollStudent($academicYear, $schoolClass, 'LPP-OFFICIEL-3');
        $subject = Subject::query()->where('code', 'FR')->firstOrFail();
        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 1,
            'is_active' => true,
        ]);
        $assessment = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => AssessmentType::query()->where('name', AssessmentType::NAME_DEVOIR)->firstOrFail()->id,
            'title' => 'Octobre - Français',
            'max_score' => 20,
            'assessment_date' => $term->starts_at?->toDateString() ?? now()->toDateString(),
        ]);

        foreach ([$firstStudent, $secondStudent] as $student) {
            Grade::query()->create([
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'score' => 14,
                'status' => Grade::STATUS_GRADED,
                'is_absent' => false,
            ]);
        }

        app(ReportCardService::class)->generateForClass($schoolClass, $term);
        $cards = ReportCard::query()->where('school_class_id', $schoolClass->id)->get();

        $this->assertCount(3, $cards);
        $this->assertSame([2], $cards->pluck('class_size_ranked')->unique()->values()->all());
        $this->assertSame([1], $cards->pluck('class_size_unranked')->unique()->values()->all());
        $this->assertNull($cards->firstWhere('student_id', $thirdStudent->id)?->rank);

        $this->enrollStudent($academicYear, $schoolClass, 'LPP-OFFICIEL-4');
        $firstCard = $cards->firstWhere('student_id', $firstStudent->id)?->fresh();
        $this->assertSame(2, $firstCard?->class_size_ranked);
        $this->assertSame(1, $firstCard?->class_size_unranked);
    }

    public function test_manual_bulletin_fields_are_preserved_when_regenerated(): void
    {
        [, $term, $schoolClass, $student] = $this->context();
        $service = app(ReportCardService::class);
        $service->generateForClass($schoolClass, $term);
        $card = ReportCard::query()->where('student_id', $student->id)->firstOrFail();
        $card->update([
            'conduct' => 'Très bonne',
            'distinction' => ReportCard::DISTINCTION_WARNING_WORK,
            'decision' => 'Décision de la direction',
            'principal_observation' => 'Observation conservée',
        ]);

        $service->generateForClass($schoolClass, $term);
        $card->refresh();

        $this->assertSame('Très bonne', $card->conduct);
        $this->assertSame(ReportCard::DISTINCTION_WARNING_WORK, $card->distinction);
        $this->assertSame('Décision de la direction', $card->decision);
        $this->assertSame('Observation conservée', $card->principal_observation);
    }

    public function test_distinction_suggestions_follow_the_official_thresholds(): void
    {
        $service = app(ReportCardService::class);

        $this->assertSame(ReportCard::DISTINCTION_HIGH_HONORS_CONGRATULATIONS, $service->suggestedDistinction(16));
        $this->assertSame(ReportCard::DISTINCTION_HIGH_HONORS_ENCOURAGEMENT, $service->suggestedDistinction(14));
        $this->assertSame(ReportCard::DISTINCTION_HONOR_ROLL, $service->suggestedDistinction(12));
        $this->assertNull($service->suggestedDistinction(11.99));
        $this->assertNull($service->suggestedDistinction(null));
    }

    private function context(): array
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $term = $academicYear->terms()->orderBy('position')->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => 'Classe bulletin officiel',
            'code' => 'BUL-OFFICIEL',
            'status' => 'active',
        ]);

        return [
            $academicYear,
            $term,
            $schoolClass,
            $this->enrollStudent($academicYear, $schoolClass, 'LPP-OFFICIEL-1'),
        ];
    }

    private function enrollStudent(AcademicYear $academicYear, SchoolClass $schoolClass, string $matricule): Student
    {
        $student = Student::query()->create([
            'matricule' => $matricule,
            'first_name' => 'Awa',
            'last_name' => 'Kaboré',
            'status' => 'active',
        ]);
        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'type' => 'new',
        ]);

        return $student;
    }

    private function attendanceSession(
        AcademicYear $academicYear,
        SchoolClass $schoolClass,
        string $date,
    ): AttendanceSession {
        return AttendanceSession::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'session_date' => $date,
        ]);
    }
}
