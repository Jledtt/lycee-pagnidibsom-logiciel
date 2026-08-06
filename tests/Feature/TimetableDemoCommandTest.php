<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\TeacherAvailabilitySchedule;
use App\Models\Timetable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TimetableDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_prepares_demo_teachers_and_applies_a_draft_for_one_class(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame('text', Schema::getColumnType('timetables', 'principal_teacher'));
        config()->set('services.timetable_solver.python', PHP_BINARY);
        config()->set('services.timetable_solver.script', base_path('tests/Fixtures/timetable_solver_stub.php'));
        $this->assertSame(0, Artisan::call('lpp:setup-classes-subjects'), Artisan::output());

        $exitCode = Artisan::call('lpp:prepare-timetable-demo', [
            '--class' => '3E',
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('code', '3E')
            ->firstOrFail();
        $assignments = $schoolClass->classSubjects()->where('is_active', true)->get();
        $teacherIds = $assignments->pluck('teacher_id')->filter()->unique();

        $this->assertCount(10, $assignments);
        $this->assertCount(10, $teacherIds);
        $this->assertSame(29.0, (float) $assignments->sum('weekly_hours'));
        $this->assertSame(10, User::query()->where('username', 'like', 'demo.edt.'.$schoolClass->id.'.%')->count());
        $this->assertSame(10, TeacherAvailabilitySchedule::query()
            ->where('academic_year_id', $academicYear->id)
            ->whereIn('teacher_id', $teacherIds)
            ->where('status', TeacherAvailabilitySchedule::STATUS_VALIDATED)
            ->count());

        $timetable = Timetable::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('school_class_id', $schoolClass->id)
            ->firstOrFail();
        $this->assertSame('draft', $timetable->status);
        $this->assertSame('Exemple automatique - 3e', $timetable->title);
        $this->assertSame(29, $timetable->entries()->where('source', 'automatic')->count());
    }
}
