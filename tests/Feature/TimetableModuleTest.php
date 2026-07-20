<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\TimetableEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_create_timetable_entry(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $class = $this->schoolClass('5e A');

        $this->actingAs($user)
            ->post(route('timetables.store'), [
                'school_class_id' => $class->id,
                'day_of_week' => 1,
                'starts_at' => '07:00',
                'ends_at' => '07:55',
                'subject_label' => 'Francais',
                'teacher_name' => 'BADIEL Philippe',
                'room' => 'Salle 1',
            ])
            ->assertRedirect(route('timetables.index', ['school_class_id' => $class->id]));

        $this->assertDatabaseHas('timetable_entries', [
            'school_class_id' => $class->id,
            'day_of_week' => 1,
            'subject_label' => 'Francais',
            'teacher_name' => 'BADIEL Philippe',
        ]);
    }

    public function test_word_example_template_can_be_applied_to_matching_class(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $class = $this->schoolClass('6eme');

        $this->actingAs($user)
            ->post(route('timetables.defaults'), ['school_class_id' => $class->id])
            ->assertRedirect(route('timetables.index', ['school_class_id' => $class->id]));

        $this->assertDatabaseHas('timetable_entries', [
            'school_class_id' => $class->id,
            'day_of_week' => 1,
            'starts_at' => '07:00',
            'ends_at' => '07:55',
            'subject_label' => 'EC',
        ]);

        $this->assertGreaterThan(10, TimetableEntry::query()->where('school_class_id', $class->id)->count());
    }

    public function test_teacher_can_view_but_cannot_modify_timetable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('enseignant');
        $class = $this->schoolClass('4eme');

        $this->actingAs($user)
            ->get(route('timetables.index', ['school_class_id' => $class->id]))
            ->assertOk()
            ->assertSee('Emplois du temps');

        $this->actingAs($user)
            ->post(route('timetables.store'), [
                'school_class_id' => $class->id,
                'day_of_week' => 1,
                'starts_at' => '07:00',
                'ends_at' => '07:55',
                'subject_label' => 'Maths',
            ])
            ->assertForbidden();
    }

    public function test_direction_can_download_timetable_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('direction');
        $class = $this->schoolClass('3eme');

        TimetableEntry::query()->create([
            'academic_year_id' => $class->academic_year_id,
            'school_class_id' => $class->id,
            'day_of_week' => 1,
            'starts_at' => '07:00',
            'ends_at' => '07:55',
            'subject_label' => 'SVT',
        ]);

        $this->actingAs($user)
            ->get(route('timetables.pdf', ['school_class_id' => $class->id]))
            ->assertOk();
    }

    private function schoolClass(string $name): SchoolClass
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => $name,
            'code' => 'EDT-'.str_replace(' ', '-', strtoupper($name)),
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-timetable-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
