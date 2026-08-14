<?php

namespace Tests\Feature;

use App\Models\AcademicTrack;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
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

        $trackA = AcademicTrack::query()->where('code', 'A')->firstOrFail();
        $trackC = AcademicTrack::query()->where('code', 'C')->firstOrFail();
        $this->assertSame($trackA->id, SchoolClass::query()->where('name', '2nde A')->value('academic_track_id'));
        $this->assertSame($trackC->id, SchoolClass::query()->where('name', '2nde C')->value('academic_track_id'));
        $this->assertNull(SchoolClass::query()->where('name', '6e')->value('academic_track_id'));

        $this->assertSame(6, SchoolClass::query()->count());
        $this->assertSame(11, Subject::query()->whereIn('code', [
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
            'TIC',
        ])->count());

        $this->assertSame(8, $this->subjectCountForClass('6e'));
        $this->assertSame(8, $this->subjectCountForClass('5e'));
        $this->assertSame(10, $this->subjectCountForClass('4e'));
        $this->assertSame(10, $this->subjectCountForClass('3e'));
        $this->assertSame(11, $this->subjectCountForClass('2nde A'));
        $this->assertSame(10, $this->subjectCountForClass('2nde C'));

        $this->assertSame(18.0, $this->coefficientTotalForClass('6e'));
        $this->assertSame(22.0, $this->coefficientTotalForClass('4e'));
        $this->assertSame(30.0, $this->coefficientTotalForClass('2nde A'));
        $this->assertSame(30.0, $this->coefficientTotalForClass('2nde C'));

        $this->assertSame(6.0, $this->coefficientForClassSubject('2nde C', 'MATH'));
        $this->assertSame(6.0, $this->coefficientForClassSubject('2nde C', 'PC'));
        $this->assertSame(5.0, $this->coefficientForClassSubject('2nde A', 'FR'));
        $this->assertSame(2.0, $this->coefficientForClassSubject('3e', 'TIC'));
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
