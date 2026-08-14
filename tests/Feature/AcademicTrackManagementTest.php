<?php

namespace Tests\Feature;

use App\Models\AcademicTrack;
use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicTrackManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administration_direction_and_secretariat_can_manage_tracks(): void
    {
        $this->seed(DatabaseSeeder::class);
        $track = AcademicTrack::query()->where('code', 'A')->firstOrFail();

        foreach (['admin', 'direction', 'secretariat'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('academic-tracks.index'))->assertOk();
            $this->actingAs($user)->get(route('academic-tracks.create'))->assertOk();
            $this->actingAs($user)->get(route('academic-tracks.edit', $track))->assertOk();
        }

        foreach (['comptable', 'enseignant', 'surveillant'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('academic-tracks.index'))->assertForbidden();
            $this->actingAs($user)->get(route('academic-tracks.edit', $track))->assertForbidden();
            $this->actingAs($user)->put(route('academic-tracks.update', $track), [
                'name' => 'Modification interdite',
                'code' => 'INTERDIT',
                'kind' => 'serie',
                'status' => 'active',
            ])->assertForbidden();
        }
    }

    public function test_secretariat_can_create_a_normalized_and_audited_track(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');

        $response = $this->actingAs($secretary)->post(route('academic-tracks.store'), [
            'name' => '  Génie   électrique ',
            'code' => ' elec ',
            'kind' => 'filiere',
            'description' => 'Filière technique.',
            'status' => 'active',
        ]);

        $track = AcademicTrack::query()->where('code', 'ELEC')->firstOrFail();

        $response->assertRedirect(route('academic-tracks.edit', $track));
        $this->assertSame('Génie électrique', $track->name);
        $this->assertSame('filiere', $track->kind);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'auditable_type' => AcademicTrack::class,
            'auditable_id' => (string) $track->id,
            'user_id' => $secretary->id,
        ]);
    }

    public function test_active_track_can_be_assigned_to_a_class_and_displayed(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');
        $track = AcademicTrack::query()->where('code', 'A')->firstOrFail();
        $class = $this->schoolClass('1re Sans Série', '1SS');

        $this->actingAs($secretary)->put(route('classes.update', $class), [
            'name' => $class->name,
            'code' => $class->code,
            'level_id' => $class->level_id,
            'academic_track_id' => $track->id,
            'capacity' => 60,
            'status' => 'active',
        ])->assertRedirect(route('classes.show', $class));

        $this->assertSame($track->id, $class->fresh()->academic_track_id);
        $this->actingAs($secretary)
            ->get(route('classes.show', $class))
            ->assertOk()
            ->assertSee('Série A');
    }

    public function test_inactive_track_cannot_be_newly_assigned_but_existing_link_is_preserved(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretary = $this->userWithRole('secretariat');
        $track = AcademicTrack::query()->where('code', 'A')->firstOrFail();
        $existingClass = $this->schoolClass('2nde A Historique', '2AH', $track);
        $newClass = $this->schoolClass('1re Sans Série', '1SS');

        $this->actingAs($secretary)->put(route('academic-tracks.update', $track), [
            'name' => $track->name,
            'code' => $track->code,
            'kind' => $track->kind,
            'status' => 'inactive',
        ])->assertRedirect();

        $this->assertDatabaseHas('school_classes', [
            'id' => $existingClass->id,
            'academic_track_id' => $track->id,
        ]);

        $this->actingAs($secretary)->put(route('classes.update', $newClass), [
            'name' => $newClass->name,
            'code' => $newClass->code,
            'level_id' => $newClass->level_id,
            'academic_track_id' => $track->id,
            'status' => 'active',
        ])->assertSessionHasErrors('academic_track_id');

        $this->assertNull($newClass->fresh()->academic_track_id);
        $this->assertFalse(Route::has('academic-tracks.destroy'));
    }

    public function test_track_migration_is_reversible_and_backfills_only_exact_legacy_classes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $exactA = $this->schoolClass('2nde A', 'LEGACY-A');
        $exactC = $this->schoolClass('Classe libre', '2NDC');
        $ambiguous = $this->schoolClass('2nde D', 'LEGACY-D');
        $migration = require database_path('migrations/2026_08_14_030000_create_academic_tracks.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('academic_tracks'));
        $this->assertFalse(Schema::hasColumn('school_classes', 'academic_track_id'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('academic_tracks'));
        $this->assertTrue(Schema::hasColumn('school_classes', 'academic_track_id'));

        $trackA = AcademicTrack::query()->where('code', 'A')->firstOrFail();
        $trackC = AcademicTrack::query()->where('code', 'C')->firstOrFail();
        $this->assertSame($trackA->id, SchoolClass::query()->findOrFail($exactA->id)->academic_track_id);
        $this->assertSame($trackC->id, SchoolClass::query()->findOrFail($exactC->id)->academic_track_id);
        $this->assertNull(SchoolClass::query()->findOrFail($ambiguous->id)->academic_track_id);
    }

    private function schoolClass(string $name, string $code, ?AcademicTrack $track = null): SchoolClass
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->where('name', '2nde')->firstOrFail();

        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'academic_track_id' => $track?->id,
            'name' => $name,
            'code' => $code,
            'capacity' => 60,
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-track-'.str()->lower(str()->random(6)),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
