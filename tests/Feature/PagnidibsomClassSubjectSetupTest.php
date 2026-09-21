<?php

namespace Tests\Feature;

use App\Models\AcademicTrack;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagnidibsomClassSubjectSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_classes_and_subject_assignments_from_school_plan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('lpp:setup-classes-subjects')
            ->assertExitCode(0);

        $this->assertDatabaseHas('school_classes', ['name' => '6e', 'code' => '6E']);
        $this->assertDatabaseHas('school_classes', ['name' => '5e', 'code' => '5E']);
        $this->assertDatabaseHas('school_classes', ['name' => '4e', 'code' => '4E']);
        $this->assertDatabaseHas('school_classes', ['name' => '3e', 'code' => '3E']);
        $this->assertDatabaseHas('school_classes', ['name' => '2nde A', 'code' => '2NDA']);
        $this->assertDatabaseHas('school_classes', ['name' => '2nde C', 'code' => '2NDC']);
        $this->assertDatabaseHas('school_classes', ['name' => '1ère A', 'code' => '1REA']);
        $this->assertDatabaseHas('school_classes', ['name' => '1ère D', 'code' => '1RED']);

        $trackA = AcademicTrack::query()->where('code', 'A')->firstOrFail();
        $trackC = AcademicTrack::query()->where('code', 'C')->firstOrFail();
        $trackD = AcademicTrack::query()->where('code', 'D')->firstOrFail();
        $this->assertSame($trackA->id, SchoolClass::query()->where('name', '2nde A')->value('academic_track_id'));
        $this->assertSame($trackC->id, SchoolClass::query()->where('name', '2nde C')->value('academic_track_id'));
        $this->assertSame($trackA->id, SchoolClass::query()->where('name', '1ère A')->value('academic_track_id'));
        $this->assertSame($trackD->id, SchoolClass::query()->where('name', '1ère D')->value('academic_track_id'));
        $this->assertNull(SchoolClass::query()->where('name', '6e')->value('academic_track_id'));

        $this->assertSame(8, SchoolClass::query()->count());
        $this->assertSame(10, Subject::query()->whereIn('code', [
            'FR',
            'MATH',
            'ANG',
            'SVT',
            'HG',
            'EPS',
            'ECM',
            'PC',
            'ALL',
            'PHILO',
        ])->count());

        $this->assertSame(7, $this->subjectCountForClass('6e'));
        $this->assertSame(7, $this->subjectCountForClass('5e'));
        $this->assertSame(8, $this->subjectCountForClass('4e'));
        $this->assertSame(8, $this->subjectCountForClass('3e'));
        $this->assertSame(8, $this->subjectCountForClass('2nde A'));
        $this->assertSame(9, $this->subjectCountForClass('2nde C'));
        $this->assertSame(8, $this->subjectCountForClass('1ère A'));
        $this->assertSame(8, $this->subjectCountForClass('1ère D'));

        $this->assertSame(16.0, $this->coefficientTotalForClass('6e'));
        $this->assertSame(18.0, $this->coefficientTotalForClass('4e'));
        $this->assertSame(24.0, $this->coefficientTotalForClass('2nde A'));
        $this->assertSame(28.0, $this->coefficientTotalForClass('2nde C'));
        $this->assertSame(25.0, $this->coefficientTotalForClass('1ère A'));
        $this->assertSame(25.0, $this->coefficientTotalForClass('1ère D'));

        $this->assertSame(6.0, $this->coefficientForClassSubject('2nde C', 'MATH'));
        $this->assertSame(6.0, $this->coefficientForClassSubject('2nde C', 'PC'));
        $this->assertSame(5.0, $this->coefficientForClassSubject('2nde A', 'FR'));
        $this->assertSame(5.0, $this->coefficientForClassSubject('1ère D', 'PC'));
        $this->assertDatabaseMissing('class_subjects', [
            'school_class_id' => SchoolClass::query()->where('name', '1ère D')->value('id'),
            'subject_id' => Subject::query()->where('code', 'PHILO')->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_command_assigns_available_teachers_and_deactivates_old_subjects(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('lpp:setup-classes-subjects')->assertExitCode(0);

        $physicsTeacher = User::factory()->create([
            'name' => 'NANA Drissa',
            'status' => 'active',
        ]);
        $physicsTeacher->assignRole('enseignant');

        $civicsTeacher = User::factory()->create([
            'name' => 'GNEBGA Bissore Jérôme',
            'status' => 'active',
        ]);
        $civicsTeacher->assignRole('enseignant');

        $technology = Subject::query()->where('code', 'TIC')->firstOrFail();
        $fifthGrade = SchoolClass::query()->where('name', '5e')->firstOrFail();
        ClassSubject::query()->create([
            'school_class_id' => $fifthGrade->id,
            'subject_id' => $technology->id,
            'teacher_id' => $civicsTeacher->id,
            'coefficient' => 2,
            'is_active' => true,
        ]);

        $this->artisan('lpp:setup-classes-subjects')->assertExitCode(0);

        $this->assertDatabaseHas('class_subjects', [
            'school_class_id' => SchoolClass::query()->where('name', '3e')->value('id'),
            'subject_id' => Subject::query()->where('code', 'PC')->value('id'),
            'teacher_id' => $physicsTeacher->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('class_subjects', [
            'school_class_id' => SchoolClass::query()->where('name', '3e')->value('id'),
            'subject_id' => Subject::query()->where('code', 'ECM')->value('id'),
            'teacher_id' => $civicsTeacher->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('class_subjects', [
            'school_class_id' => $fifthGrade->id,
            'subject_id' => $technology->id,
            'teacher_id' => null,
            'is_active' => false,
        ]);
    }

    private function subjectCountForClass(string $className): int
    {
        $schoolClass = SchoolClass::query()->where('name', $className)->firstOrFail();

        return ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->count();
    }

    private function coefficientTotalForClass(string $className): float
    {
        $schoolClass = SchoolClass::query()->where('name', $className)->firstOrFail();

        return (float) ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->sum('coefficient');
    }

    private function coefficientForClassSubject(string $className, string $subjectCode): float
    {
        $schoolClass = SchoolClass::query()->where('name', $className)->firstOrFail();
        $subject = Subject::query()->where('code', $subjectCode)->firstOrFail();

        return (float) ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $subject->id)
            ->where('is_active', true)
            ->value('coefficient');
    }
}
