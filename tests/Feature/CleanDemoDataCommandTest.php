<?php

namespace Tests\Feature;

use App\Models\ClassSubject;
use App\Models\NumberingSetting;
use App\Models\Student;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CleanDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_lists_demo_data_without_deleting_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = $this->demoStudent();

        $this->assertSame(0, Artisan::call('lpp:clean-demo-data'));
        $this->assertStringContainsString('Aucune donnée n’a été supprimée', Artisan::output());
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_unknown_student_blocks_forced_cleanup(): void
    {
        $this->seed(DatabaseSeeder::class);
        $demoStudent = $this->demoStudent();
        $realStudent = Student::query()->create([
            'matricule' => 'LPP-2026-9001',
            'first_name' => 'Élève',
            'last_name' => 'Réel',
            'status' => 'active',
        ]);

        $this->assertSame(1, Artisan::call('lpp:clean-demo-data', ['--force' => true]));
        $this->assertStringContainsString('Nettoyage bloqué', Artisan::output());
        $this->assertDatabaseHas('students', ['id' => $demoStudent->id]);
        $this->assertDatabaseHas('students', ['id' => $realStudent->id]);
    }

    public function test_forced_cleanup_removes_demo_records_and_preserves_configuration(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, Artisan::call('lpp:setup-classes-subjects'), Artisan::output());
        $this->demoStudent();
        $teacher = User::query()->create([
            'username' => 'demo.edt.1.1',
            'name' => 'Professeur démo - Français',
            'email' => 'demo-edt-1-1@example.invalid',
            'password' => Hash::make('mot-de-passe-temporaire'),
            'status' => 'active',
        ]);
        $teacher->assignRole('enseignant');
        TeacherProfile::query()->create([
            'user_id' => $teacher->id,
            'employee_number' => 'DEMO-EDT-1',
            'specialty' => 'Français',
        ]);
        ClassSubject::query()->firstOrFail()->update(['teacher_id' => $teacher->id]);
        NumberingSetting::query()->update(['next_number' => 42]);

        $preservedUsers = User::query()->where('username', 'not like', 'demo.edt.%')->count();
        $preservedClasses = DB::table('school_classes')->count();

        $this->assertSame(0, Artisan::call('lpp:clean-demo-data', ['--force' => true]), Artisan::output());

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('class_subjects', ['teacher_id' => null]);
        $this->assertSame($preservedUsers, User::query()->count());
        $this->assertSame($preservedClasses, DB::table('school_classes')->count());
        $this->assertTrue(NumberingSetting::query()->get()->every(
            fn (NumberingSetting $setting): bool => $setting->next_number === 1,
        ));
    }

    private function demoStudent(): Student
    {
        return Student::query()->create([
            'matricule' => 'LPP-DEMO-3E-01',
            'first_name' => 'Aïcha',
            'last_name' => 'Démo',
            'status' => 'active',
        ]);
    }
}
