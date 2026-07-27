<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_upload_and_download_student_document(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $student = $this->student();

        $file = UploadedFile::fake()->create('acte.pdf', 128, 'application/pdf');

        $this->actingAs($user)
            ->post(route('students.documents.store', $student), [
                'name' => 'Acte de naissance',
                'document_type' => 'birth_certificate',
                'status' => 'received',
                'received_at' => '2026-07-18',
                'document_file' => $file,
            ])
            ->assertRedirect(route('students.show', $student));

        $document = StudentDocument::query()->firstOrFail();

        $this->assertSame($student->id, $document->student_id);
        $this->assertSame('birth_certificate', $document->document_type);
        $this->assertStringStartsWith('media:', (string) $document->file_path);
        $this->assertDatabaseHas('media', [
            'model_type' => Student::class,
            'model_id' => $student->id,
            'collection_name' => 'birth_certificate',
            'name' => 'Acte de naissance',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'auditable_type' => StudentDocument::class,
            'auditable_id' => (string) $document->id,
            'action' => 'created',
        ]);

        $this->actingAs($user)
            ->get(route('student-documents.download', $document))
            ->assertOk()
            ->assertDownload('lpp-2026-0001-acte-de-naissance.pdf');
    }

    public function test_secretariat_can_upload_student_photo(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $student = $this->student();

        $this->actingAs($user)
            ->post(route('students.documents.store', $student), [
                'name' => 'Photo élève',
                'document_type' => 'photo',
                'status' => 'received',
                'received_at' => '2026-07-18',
                'document_file' => UploadedFile::fake()->image('photo.jpg'),
            ])
            ->assertRedirect(route('students.show', $student));

        $document = StudentDocument::query()->where('document_type', 'photo')->firstOrFail();

        $this->assertStringStartsWith('media:', (string) $document->file_path);
        $this->assertDatabaseHas('media', [
            'model_type' => Student::class,
            'model_id' => $student->id,
            'collection_name' => 'student_photo',
            'name' => 'Photo élève',
        ]);
        $this->assertNotNull($student->fresh()->photo_path);
    }

    public function test_secretariat_can_mark_missing_document_without_file(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $student = $this->student();

        $this->actingAs($user)
            ->post(route('students.documents.store', $student), [
                'name' => 'Ancien bulletin',
                'document_type' => 'previous_report_card',
                'status' => 'missing',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('student_documents', [
            'student_id' => $student->id,
            'name' => 'Ancien bulletin',
            'document_type' => 'previous_report_card',
            'status' => 'missing',
            'file_path' => null,
        ]);
    }

    public function test_document_file_is_deleted_when_document_is_removed(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $student = $this->student();
        $path = UploadedFile::fake()
            ->image('photo.jpg')
            ->store('students/'.$student->id.'/documents', 'public');

        $document = StudentDocument::query()->create([
            'student_id' => $student->id,
            'name' => 'Photo',
            'document_type' => 'photo',
            'file_path' => $path,
            'status' => 'received',
            'received_at' => now()->toDateString(),
        ]);

        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->delete(route('students.documents.destroy', [$student, $document]))
            ->assertRedirect(route('students.show', $student));

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('student_documents', [
            'id' => $document->id,
        ]);
    }

    public function test_direction_cannot_upload_student_document(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('direction');
        $student = $this->student();

        $this->actingAs($user)
            ->post(route('students.documents.store', $student), [
                'name' => 'Acte de naissance',
                'document_type' => 'birth_certificate',
                'status' => 'received',
                'document_file' => UploadedFile::fake()->create('acte.pdf', 128, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    private function student(): Student
    {
        return Student::query()->create([
            'matricule' => 'LPP-2026-0001',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => 'female',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-student-document-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
