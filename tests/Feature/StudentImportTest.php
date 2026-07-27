<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_download_student_import_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $response = $this->actingAs($user)->get(route('students.import.template'));
        $sheetXml = $this->sheetXml($response->getContent());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Nom', $sheetXml);
        $this->assertStringContainsString('Prénom', $sheetXml);
        $this->assertStringContainsString('Date naissance', $sheetXml);
        $this->assertStringContainsString('Ouedraogo', $sheetXml);
        $this->assertStringContainsString('Awa', $sheetXml);
    }

    public function test_secretariat_can_preview_and_import_students_csv(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'code' => '5A',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->createWithContent('eleves.csv', implode("\n", [
            'Nom;Prénom;Sexe;Date naissance;Lieu naissance;Classe souhaitee;Pere nom;Pere prenom;Pere telephone',
            'Ouedraogo;Awa;Fille;15/09/2012;Ouagadougou;5e A;Ouedraogo;Adama;71000000',
        ]));

        $this->actingAs($user)
            ->post(route('students.import.preview'), ['students_file' => $file])
            ->assertRedirect(route('students.import'));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('students.import'))
            ->assertOk()
            ->assertSee('Awa Ouedraogo')
            ->assertSee('Classe trouvée')
            ->assertSee('Valide');

        $this->actingAs($user)
            ->post(route('students.import.store'))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', [
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'desired_class' => '5e A',
        ]);
        $this->assertDatabaseHas('guardians', [
            'first_name' => 'Adama',
            'last_name' => 'Ouedraogo',
            'phone_primary' => '71000000',
        ]);

        $student = Student::query()->where('first_name', 'Awa')->firstOrFail();
        $guardian = Guardian::query()->where('phone_primary', '71000000')->firstOrFail();

        $this->assertTrue($student->guardians()->whereKey($guardian->id)->exists());
        $this->assertDatabaseHas('enrollments', [
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'status' => 'active',
        ]);
    }

    public function test_duplicate_students_are_previewed_and_skipped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        Student::query()->create([
            'matricule' => 'LPP-2026-0099',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'birth_date' => '2012-09-15',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->createWithContent('eleves.csv', implode("\n", [
            'Nom;Prénom;Sexe;Date naissance',
            'Ouedraogo;Awa;Fille;15/09/2012',
        ]));

        $this->actingAs($user)
            ->post(route('students.import.preview'), ['students_file' => $file])
            ->assertRedirect(route('students.import'));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('students.import'))
            ->assertOk()
            ->assertSee('Doublon')
            ->assertSee('Élève déjà présent');

        $this->actingAs($user)
            ->post(route('students.import.store'))
            ->assertRedirect(route('students.index'));

        $this->assertSame(1, Student::query()->where('first_name', 'Awa')->where('last_name', 'Ouedraogo')->count());
    }

    public function test_secretariat_can_preview_and_import_text_pdf_students(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '6e A',
            'code' => '6A',
            'status' => 'active',
        ]);

        $pdfContent = Pdf::loadHTML(
            '<pre>Nom;Prénom;Sexe;Date naissance;Lieu naissance;Classe souhaitee
Kabre;Issa;Garcon;20/05/2011;Ouagadougou;6e A</pre>'
        )->output();

        $file = UploadedFile::fake()->createWithContent('eleves.pdf', $pdfContent);

        $this->actingAs($user)
            ->post(route('students.import.preview'), ['students_file' => $file])
            ->assertRedirect(route('students.import'));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('students.import'))
            ->assertOk()
            ->assertSee('Issa Kabre')
            ->assertSee('Valide');

        $this->actingAs($user)
            ->post(route('students.import.store'))
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', [
            'first_name' => 'Issa',
            'last_name' => 'Kabre',
            'gender' => 'male',
            'desired_class' => '6e A',
        ]);
    }

    public function test_comptable_cannot_import_students(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');

        $this->actingAs($user)->get(route('students.import'))->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-student-import-test',
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
