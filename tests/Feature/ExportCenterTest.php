<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_open_import_export_center_without_financial_blocks(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$year, $class] = $this->schoolContext();
        $user = $this->userWithRole('secretariat');

        $this->actingAs($user)
            ->get(route('exports.index', [
                'academic_year_id' => $year->id,
                'school_class_id' => $class->id,
            ]))
            ->assertOk()
            ->assertSee('Imports / Exports')
            ->assertSee('Élèves par classe')
            ->assertDontSee('Paiements encaissés')
            ->assertDontSee('Honoraires professeurs');
    }

    public function test_secretariat_can_download_students_from_export_center(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$year, $class] = $this->schoolContext();

        $student = Student::query()->create([
            'matricule' => 'LPP-EXPORT-001',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Ouagadougou',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $year->id,
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'enrollment_date' => '2026-10-01',
            'type' => 'new',
            'status' => 'active',
        ]);

        $user = $this->userWithRole('secretariat');

        $response = $this->actingAs($user)->get(route('exports.students', [
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheetXml = $this->sheetXml($response->getContent());
        $this->assertStringContainsString('Matricule', $sheetXml);
        $this->assertStringContainsString('LPP-EXPORT-001', $sheetXml);
    }

    public function test_secretariat_cannot_download_payment_export_from_export_center(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $this->actingAs($user)->get(route('exports.payments'))->assertForbidden();
    }

    private function schoolContext(): array
    {
        $year = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->firstOrCreate(
                ['name' => '2026-2027'],
                [
                    'starts_at' => '2026-09-01',
                    'ends_at' => '2027-07-31',
                    'is_active' => true,
                    'status' => 'active',
                ]
            );

        $level = Level::query()->firstOrCreate(
            ['name' => '3e'],
            [
                'cycle' => 'Secondaire',
                'position' => 3,
            ]
        );

        $class = SchoolClass::query()->firstOrCreate(
            [
                'academic_year_id' => $year->id,
                'name' => '3e',
            ],
            [
                'level_id' => $level->id,
                'code' => '3E',
                'capacity' => 60,
                'status' => 'active',
            ]
        );

        return [$year, $class];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-export-center-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function sheetXml(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, $content);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();
        @unlink($path);

        return $xml;
    }
}
