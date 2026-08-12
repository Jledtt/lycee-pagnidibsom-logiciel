<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Rules\ValidClassSubjectCoefficient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassSubjectCoefficientIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('coefficientProvider')]
    public function test_coefficient_grid(float $coefficient, bool $expected): void
    {
        $validator = Validator::make(
            ['coefficient' => $coefficient],
            ['coefficient' => ['required', new ValidClassSubjectCoefficient]],
        );

        $this->assertSame($expected, $validator->passes());
    }

    public function test_invalid_coefficient_is_rejected_at_entry(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('admin');
        [$schoolClass, $subject] = $this->classAndSubject();

        $this->actingAs($user)
            ->post(route('subjects.class-subjects.store'), [
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'coefficient' => 1.04,
            ])
            ->assertSessionHasErrors([
                'coefficient' => ValidClassSubjectCoefficient::MESSAGE,
            ]);

        $this->assertDatabaseMissing('class_subjects', [
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_audit_command_reports_anomalies_without_modifying_them(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$schoolClass, $subject] = $this->classAndSubject();
        $assignment = ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 1.04,
            'status' => 'active',
        ]);

        $this->artisan('lpp:audit-coefficients')
            ->expectsOutputToContain($schoolClass->name)
            ->expectsOutputToContain('1 coefficient(s) à corriger manuellement')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(1.04, (float) $assignment->fresh()->coefficient);
    }

    public static function coefficientProvider(): array
    {
        return [
            'minimum' => [0.5, true],
            'entier' => [1.0, true],
            'demi-point' => [1.5, true],
            'maximum' => [10.0, true],
            'pas invalide' => [1.04, false],
            'sous le minimum' => [0.0, false],
            'au-dessus du maximum' => [10.5, false],
        ];
    }

    private function classAndSubject(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => 'Classe audit coefficients',
            'code' => 'COEFF-AUDIT',
            'status' => 'active',
        ]);
        $subject = Subject::query()->create([
            'name' => 'Matière audit coefficients',
            'code' => 'COEFF-AUDIT',
            'status' => 'active',
        ]);

        return [$schoolClass, $subject];
    }
}
