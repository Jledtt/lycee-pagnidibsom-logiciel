<?php

namespace Tests\Feature;

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

        $this->assertSame(6, SchoolClass::query()->count());
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
    }

    private function subjectCountForClass(string $className): int
    {
        $schoolClass = SchoolClass::query()->where('name', $className)->firstOrFail();

        return ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('is_active', true)
            ->count();
    }
}
