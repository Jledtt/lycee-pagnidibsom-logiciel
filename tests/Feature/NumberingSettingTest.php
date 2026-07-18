<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\NumberingSetting;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use App\Services\MatriculeGeneratorService;
use App\Services\OfficialNumberService;
use App\Services\ReceiptNumberService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_numbering_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('settings.numbering.index'))
            ->assertOk()
            ->assertSee('Parametres de numerotation')
            ->assertSee('{NUMBER}');

        $payload = $this->numberingPayload([
            OfficialNumberService::STUDENT_MATRICULE => [
                'prefix' => 'LPAG',
                'format' => '{PREFIX}-{YY}-{NUMBER}',
                'padding' => 3,
                'next_number' => 12,
            ],
        ]);

        $this->actingAs($admin)
            ->put(route('settings.numbering.update'), ['settings' => $payload])
            ->assertRedirect(route('settings.numbering.index'));

        $this->assertDatabaseHas('numbering_settings', [
            'type' => OfficialNumberService::STUDENT_MATRICULE,
            'prefix' => 'LPAG',
            'format' => '{PREFIX}-{YY}-{NUMBER}',
            'padding' => 3,
            'next_number' => 12,
        ]);
    }

    public function test_secretariat_cannot_manage_numbering_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = $this->userWithRole('secretariat');

        $this->actingAs($secretariat)
            ->get(route('settings.numbering.index'))
            ->assertForbidden();
    }

    public function test_configured_formats_are_used_for_matricules_and_receipts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        NumberingSetting::query()
            ->where('type', OfficialNumberService::STUDENT_MATRICULE)
            ->update([
                'prefix' => 'LPAG',
                'format' => '{PREFIX}-{YY}-{NUMBER}',
                'padding' => 3,
                'next_number' => 12,
            ]);

        NumberingSetting::query()
            ->where('type', OfficialNumberService::PAYMENT_RECEIPT)
            ->update([
                'prefix' => 'PAY',
                'format' => '{PREFIX}-{YEAR}-{NUMBER}',
                'padding' => 2,
                'next_number' => 7,
            ]);

        $matricule = app(MatriculeGeneratorService::class)->generate($academicYear);
        $receipt = app(ReceiptNumberService::class)->generate();

        $this->assertSame('LPAG-26-012', $matricule);
        $this->assertSame('PAY-2026-07', $receipt);
        $this->assertDatabaseHas('numbering_settings', [
            'type' => OfficialNumberService::STUDENT_MATRICULE,
            'next_number' => 13,
        ]);
        $this->assertDatabaseHas('numbering_settings', [
            'type' => OfficialNumberService::PAYMENT_RECEIPT,
            'next_number' => 8,
        ]);
    }

    public function test_certificate_gets_configured_official_number(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');
        [$academicYear, $student] = $this->enrolledStudent();

        NumberingSetting::query()
            ->where('type', OfficialNumberService::STUDENT_CERTIFICATE)
            ->update([
                'prefix' => 'CEF',
                'format' => '{PREFIX}-{YEAR}-{NUMBER}',
                'padding' => 3,
                'next_number' => 5,
            ]);

        $this->actingAs($admin)
            ->post(route('certificates.store'), [
                'student_id' => $student->id,
                'document_type' => 'school_certificate',
                'received_at' => '2026-07-18',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('student_documents', [
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'document_type' => 'school_certificate',
            'document_number' => 'CEF-2026-005',
        ]);
    }

    private function numberingPayload(array $overrides = []): array
    {
        return NumberingSetting::query()
            ->orderBy('id')
            ->get()
            ->map(function (NumberingSetting $setting) use ($overrides) {
                $values = array_merge([
                    'prefix' => $setting->prefix,
                    'format' => $setting->format,
                    'padding' => $setting->padding,
                    'next_number' => $setting->next_number,
                    'status' => $setting->status,
                ], $overrides[$setting->type] ?? []);

                return [
                    'id' => $setting->id,
                    ...$values,
                ];
            })
            ->values()
            ->all();
    }

    private function enrolledStudent(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'code' => '5A-NUM-TEST',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-2026-9901',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'birth_date' => '2010-03-12',
            'birth_place' => 'Ouagadougou',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'enrollment_date' => '2026-07-18',
            'type' => 'new',
            'status' => 'active',
        ]);

        return [$academicYear, $student];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . '-numbering-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
