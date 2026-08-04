<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\TimetablePeriod;
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
                        'subject_name' => $isTargetCell ? 'Mathématiques' : $entry->subject_name,
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
            'subject_name' => 'Mathématiques',
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
            'principal_teacher' => 'Aminata Test (Français); Paul Exemple (Mathématiques)',
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
                'subject_name' => 'Français',
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('timetables.index', ['school_class_id' => $schoolClass->id]))
            ->assertOk()
            ->assertSee('7h00-7h55', false)
            ->assertSee('EPS')
            ->assertSee('Français')
            ->assertSee('class="timetable-overview"', false)
            ->assertSee('Équipe pédagogique')
            ->assertSee('2 professeurs')
            ->assertSeeInOrder(['Aminata Test (Français)', 'Paul Exemple (Mathématiques)']);

        $this->assertSame(1, substr_count($response->getContent(), '<strong>7h00-7h55</strong>'));
    }

    public function test_timetable_pdf_uses_school_header_and_clean_wording(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('5e B');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $timetable = Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'title' => 'Emploi du temps final',
            'status' => 'active',
            'principal_teacher' => 'Équipe de direction',
            'created_by' => $user->id,
        ]);

        $entry = $timetable->entries()->create([
            'sort_order' => 1,
            'period_label' => '7h00-7h55',
            'starts_at' => '07:00',
            'ends_at' => '07:55',
            'day_of_week' => 'monday',
            'subject_name' => 'Mathématiques',
            'teacher_name' => 'BADO Constant',
            'is_break' => false,
        ]);

        $html = view('timetables.pdf', [
            'timetable' => $timetable->load(['academicYear', 'schoolClass.level']),
            'school' => SchoolSetting::query()->first(),
            'days' => ['monday' => 'Lundi'],
            'grid' => [[
                'period_label' => '7h00-7h55',
                'is_break' => false,
                'days' => ['monday' => $entry],
            ]],
        ])->render();

        $this->assertStringContainsString('Professeur principal / équipe pédagogique', $html);
        $this->assertStringContainsString('Bâtir l&#039;excellence', $html);
        $this->assertStringContainsString('Mathématiques', $html);
    }

    public function test_timetable_keeps_afternoon_periods_visible_when_empty(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('5e A');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $timetable = Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'title' => 'Emploi incomplet',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $timetable->entries()->create([
            'sort_order' => 1,
            'period_label' => '7h00-7h55',
            'starts_at' => '07:00',
            'ends_at' => '07:55',
            'day_of_week' => 'monday',
            'subject_name' => 'EPS',
        ]);

        $this->actingAs($user)
            ->get(route('timetables.index', ['school_class_id' => $schoolClass->id]))
            ->assertOk()
            ->assertSee('15h00-16h00')
            ->assertSee('16h00-17h00');
    }

    public function test_timetable_periods_are_initialized_and_can_update_existing_grids(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('Classe créneaux');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($user)
            ->post(route('timetables.store'), [
                'school_class_id' => $schoolClass->id,
                'title' => 'Grille configurable',
            ])
            ->assertRedirect();

        $periods = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(9, $periods);

        $payload = $periods->map(fn (TimetablePeriod $period): array => [
            'id' => $period->id,
            'sort_order' => $period->sort_order,
            'label' => $period->sort_order === 1 ? '07h00-07h55' : $period->label,
            'starts_at' => $period->starts_at ? substr((string) $period->starts_at, 0, 5) : null,
            'ends_at' => $period->ends_at ? substr((string) $period->ends_at, 0, 5) : null,
            'is_break' => $period->is_break ? 1 : 0,
            'is_active' => $period->is_active ? 1 : 0,
        ])->all();
        $payload[] = [
            'id' => null,
            'sort_order' => 10,
            'label' => '17h00-18h00',
            'starts_at' => '17:00',
            'ends_at' => '18:00',
            'is_break' => 0,
            'is_active' => 1,
        ];

        $this->actingAs($user)
            ->put(route('timetables.periods.update'), ['periods' => $payload])
            ->assertRedirect(route('timetables.periods'));

        $this->assertDatabaseHas('timetable_periods', [
            'id' => $periods->first()->id,
            'label' => '07h00-07h55',
        ]);
        $this->assertDatabaseHas('timetable_entries', [
            'timetable_period_id' => $periods->first()->id,
            'period_label' => '07h00-07h55',
        ]);
        $newPeriod = TimetablePeriod::query()->where('label', '17h00-18h00')->firstOrFail();
        $this->assertSame(6, $newPeriod->entries()->count());
    }

    public function test_timetable_periods_reject_overlapping_courses(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('Classe chevauchement');
        $this->actingAs($user)->post(route('timetables.store'), [
            'school_class_id' => $schoolClass->id,
            'title' => 'Grille chevauchement',
        ])->assertRedirect();

        $periods = TimetablePeriod::query()->orderBy('sort_order')->get();
        $payload = $periods->map(function (TimetablePeriod $period): array {
            return [
                'id' => $period->id,
                'sort_order' => $period->sort_order,
                'label' => $period->label,
                'starts_at' => $period->sort_order === 2 ? '07:30' : ($period->starts_at ? substr((string) $period->starts_at, 0, 5) : null),
                'ends_at' => $period->ends_at ? substr((string) $period->ends_at, 0, 5) : null,
                'is_break' => $period->is_break ? 1 : 0,
                'is_active' => $period->is_active ? 1 : 0,
            ];
        })->all();

        $this->actingAs($user)
            ->from(route('timetables.periods'))
            ->put(route('timetables.periods.update'), ['periods' => $payload])
            ->assertRedirect(route('timetables.periods'))
            ->assertSessionHasErrors('periods');
    }

    public function test_timetable_entry_uses_the_real_class_subject_and_teacher(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $schoolClass = $this->schoolClass('Classe affectée');
        $subject = Subject::query()->firstOrCreate(
            ['name' => 'Algorithmique'],
            ['code' => 'ALGO', 'status' => 'active'],
        );
        $assignment = ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('timetables.store'), [
                'school_class_id' => $schoolClass->id,
                'title' => 'Grille structurée',
            ])
            ->assertRedirect();

        $timetable = Timetable::query()->where('school_class_id', $schoolClass->id)->with('entries')->firstOrFail();
        $target = $timetable->entries->first(fn ($entry) => ! $entry->is_break && $entry->day_of_week === 'monday');

        $payload = $timetable->entries
            ->sortBy([['sort_order', 'asc'], ['day_of_week', 'asc']])
            ->values()
            ->map(fn ($entry): array => $this->entryPayload(
                $entry,
                $entry->is($target) ? $assignment->id : null,
            ))
            ->all();

        $this->actingAs($user)
            ->put(route('timetables.update', $timetable), [
                'title' => $timetable->title,
                'status' => 'draft',
                'entries' => $payload,
            ])
            ->assertRedirect(route('timetables.edit', $timetable));

        $this->assertDatabaseHas('timetable_entries', [
            'timetable_id' => $timetable->id,
            'timetable_period_id' => $target->timetable_period_id,
            'day_of_week' => 'monday',
            'class_subject_id' => $assignment->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'subject_name' => 'Algorithmique',
            'teacher_name' => $teacher->name,
            'source' => 'manual',
        ]);
    }

    public function test_timetable_rejects_an_assignment_from_another_class(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $schoolClass = $this->schoolClass('Classe protégée');
        $otherClass = $this->schoolClass('Autre classe');
        $subject = Subject::query()->firstOrCreate(
            ['name' => 'Robotique'],
            ['code' => 'ROBO', 'status' => 'active'],
        );
        $otherAssignment = ClassSubject::query()->create([
            'school_class_id' => $otherClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 1,
            'weekly_hours' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('timetables.store'), [
            'school_class_id' => $schoolClass->id,
            'title' => 'Grille protégée',
        ]);

        $timetable = Timetable::query()->where('school_class_id', $schoolClass->id)->with('entries')->firstOrFail();
        $entries = $timetable->entries->sortBy([['sort_order', 'asc'], ['day_of_week', 'asc']])->values();
        $payload = $entries
            ->map(fn ($entry, int $index): array => $this->entryPayload($entry, $index === 0 ? $otherAssignment->id : null))
            ->all();

        $this->actingAs($user)
            ->from(route('timetables.edit', $timetable))
            ->put(route('timetables.update', $timetable), [
                'title' => $timetable->title,
                'status' => 'draft',
                'entries' => $payload,
            ])
            ->assertRedirect(route('timetables.edit', $timetable))
            ->assertSessionHasErrors('entries.0.class_subject_id');
    }

    public function test_timetable_rejects_a_teacher_booked_in_another_class(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $firstClass = $this->schoolClass('Première classe conflit');
        $secondClass = $this->schoolClass('Seconde classe conflit');
        $subject = Subject::query()->firstOrCreate(
            ['name' => 'Programmation'],
            ['code' => 'PROG', 'status' => 'active'],
        );
        $firstAssignment = ClassSubject::query()->create([
            'school_class_id' => $firstClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 4,
            'is_active' => true,
        ]);
        $secondAssignment = ClassSubject::query()->create([
            'school_class_id' => $secondClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 4,
            'is_active' => true,
        ]);

        foreach ([$firstClass, $secondClass] as $schoolClass) {
            $this->actingAs($user)->post(route('timetables.store'), [
                'school_class_id' => $schoolClass->id,
                'title' => 'Grille '.$schoolClass->name,
            ])->assertRedirect();
        }

        $firstTimetable = Timetable::query()->where('school_class_id', $firstClass->id)->with('entries')->firstOrFail();
        $secondTimetable = Timetable::query()->where('school_class_id', $secondClass->id)->with('entries')->firstOrFail();
        $firstTarget = $firstTimetable->entries->first(fn ($entry) => ! $entry->is_break && $entry->day_of_week === 'monday');
        $secondTarget = $secondTimetable->entries->first(fn ($entry) => ! $entry->is_break && $entry->day_of_week === 'monday');

        $firstPayload = $firstTimetable->entries
            ->sortBy([['sort_order', 'asc'], ['day_of_week', 'asc']])
            ->values()
            ->map(fn ($entry): array => $this->entryPayload($entry, $entry->is($firstTarget) ? $firstAssignment->id : null))
            ->all();
        $this->actingAs($user)->put(route('timetables.update', $firstTimetable), [
            'title' => $firstTimetable->title,
            'status' => 'draft',
            'entries' => $firstPayload,
        ])->assertSessionHasNoErrors();

        $secondPayload = $secondTimetable->entries
            ->sortBy([['sort_order', 'asc'], ['day_of_week', 'asc']])
            ->values()
            ->map(fn ($entry): array => $this->entryPayload($entry, $entry->is($secondTarget) ? $secondAssignment->id : null))
            ->all();

        $this->actingAs($user)
            ->from(route('timetables.edit', $secondTimetable))
            ->put(route('timetables.update', $secondTimetable), [
                'title' => 'Titre qui ne doit pas être enregistré',
                'status' => 'active',
                'entries' => $secondPayload,
            ])
            ->assertRedirect(route('timetables.edit', $secondTimetable))
            ->assertSessionHasErrors('entries');

        $this->assertDatabaseHas('timetables', [
            'id' => $secondTimetable->id,
            'title' => $secondTimetable->title,
            'status' => 'draft',
        ]);
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

    private function entryPayload($entry, ?int $classSubjectId = null): array
    {
        return [
            'timetable_period_id' => $entry->timetable_period_id,
            'sort_order' => $entry->sort_order,
            'period_label' => $entry->period_label,
            'starts_at' => $entry->starts_at ? substr((string) $entry->starts_at, 0, 5) : null,
            'ends_at' => $entry->ends_at ? substr((string) $entry->ends_at, 0, 5) : null,
            'day_of_week' => $entry->day_of_week,
            'class_subject_id' => $classSubjectId,
            'subject_name' => $entry->subject_name,
            'teacher_name' => $entry->teacher_name,
            'room' => $entry->room,
            'is_break' => $entry->is_break ? 1 : 0,
        ];
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
