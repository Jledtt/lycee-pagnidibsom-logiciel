<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Timetable;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_create_edit_and_download_timetable(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('6e');

        $this->actingAs($user)
            ->get(route('timetables.index', ['school_class_id' => $schoolClass->id]))
            ->assertOk()
            ->assertSee('Emplois du temps')
            ->assertSee('exemple 2025-2026');

        $this->actingAs($user)
            ->post(route('timetables.example'), [
                'school_class_id' => $schoolClass->id,
            ])
            ->assertRedirect();

        $timetable = Timetable::query()
            ->where('school_class_id', $schoolClass->id)
            ->with('entries')
            ->firstOrFail();

        $this->assertSame('Emploi du temps provisoire', $timetable->title);
        $this->assertCount(54, $timetable->entries);
        $this->assertDatabaseHas('timetable_entries', [
            'timetable_id' => $timetable->id,
            'day_of_week' => 'monday',
            'period_label' => '7h00-7h55',
            'subject_name' => 'EC',
        ]);

        $payload = [
            'title' => 'Emploi du temps final',
            'principal_teacher' => 'Professeur Principal Test',
            'notes' => 'Version validee',
            'status' => 'active',
            'entries' => $timetable->entries
                ->sortBy([['sort_order', 'asc']])
                ->values()
                ->map(function ($entry) {
                    $isTargetCell = $entry->day_of_week === 'monday' && $entry->period_label === '7h00-7h55';

                    return [
                        'sort_order' => $entry->sort_order,
                        'period_label' => $entry->period_label,
                        'starts_at' => $entry->starts_at ? substr((string) $entry->starts_at, 0, 5) : null,
                        'ends_at' => $entry->ends_at ? substr((string) $entry->ends_at, 0, 5) : null,
                        'day_of_week' => $entry->day_of_week,
                        'subject_name' => $isTargetCell ? 'Mathematiques' : $entry->subject_name,
                        'teacher_name' => $isTargetCell ? 'BADO Constant' : $entry->teacher_name,
                        'room' => $isTargetCell ? 'Salle 1' : $entry->room,
                        'is_break' => $entry->is_break ? 1 : 0,
                    ];
                })
                ->all(),
        ];

        $this->actingAs($user)
            ->put(route('timetables.update', $timetable), $payload)
            ->assertRedirect(route('timetables.edit', $timetable));

        $this->assertDatabaseHas('timetables', [
            'id' => $timetable->id,
            'title' => 'Emploi du temps final',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('timetable_entries', [
            'timetable_id' => $timetable->id,
            'day_of_week' => 'monday',
            'period_label' => '7h00-7h55',
            'subject_name' => 'Mathematiques',
            'teacher_name' => 'BADO Constant',
            'room' => 'Salle 1',
        ]);

        $this->actingAs($user)
            ->get(route('timetables.pdf', $timetable))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_timetable_groups_same_period_on_one_row(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('5e A');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

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
                'period_label' => '7h00-7h55',
                'starts_at' => '07:00',
                'ends_at' => '07:55',
                'day_of_week' => 'monday',
                'subject_name' => 'EPS',
            ],
            [
                'sort_order' => 2,
                'period_label' => '7h00-7h55',
                'starts_at' => '07:00',
                'ends_at' => '07:55',
                'day_of_week' => 'tuesday',
                'subject_name' => 'Francais',
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('timetables.index', ['school_class_id' => $schoolClass->id]))
            ->assertOk()
            ->assertSee('7h00-7h55', false)
            ->assertSee('EPS')
            ->assertSee('Francais');

        $this->assertSame(1, substr_count($response->getContent(), '<strong>7h00-7h55</strong>'));
    }

    public function test_comptable_cannot_open_timetable_module(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');

        $this->actingAs($user)
            ->get(route('timetables.index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-timetable-test-'.uniqid(),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function schoolClass(string $name): SchoolClass
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrCreate(
            ['name' => $name],
            [
                'cycle' => 'Premier cycle',
                'position' => 1,
            ],
        );

        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => $name,
            'code' => strtoupper($name),
            'capacity' => 60,
            'status' => 'active',
        ]);
    }
}
