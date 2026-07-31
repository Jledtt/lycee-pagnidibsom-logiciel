<?php

namespace App\Http\Controllers;

use App\Http\Requests\Student\StoreStudentDocumentRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentDocumentWebController extends Controller
{
    public function store(StoreStudentDocumentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();

        $document = StudentDocument::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeAcademicYear()?->id,
            'name' => $data['name'],
            'document_type' => $data['document_type'],
            'file_path' => null,
            'status' => $data['status'],
            'received_at' => $data['status'] === 'missing'
                ? null
                : ($data['received_at'] ?? ($data['status'] === 'received' ? now()->toDateString() : null)),
        ]);

        if ($request->hasFile('document_file')) {
            $media = $student
                ->addMediaFromRequest('document_file')
                ->usingName($data['name'])
                ->withCustomProperties([
                    'student_document_id' => $document->id,
                    'document_type' => $data['document_type'],
                    'status' => $data['status'],
                ])
                ->toMediaCollection($this->mediaCollectionFor($data['document_type']));

            $document->forceFill([
                'file_path' => 'media:'.$media->id,
            ])->save();

            if ($data['document_type'] === 'photo') {
                $student->forceFill([
                    'photo_path' => 'media:'.$media->id,
                ])->save();
            }
        }

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Document ajouté au dossier élève.');
    }

    public function show(StudentDocument $studentDocument): BinaryFileResponse
    {
        abort_if(blank($studentDocument->file_path), 404, 'Fichier introuvable.');

        if ($media = $this->mediaFromDocument($studentDocument)) {
            abort_unless(file_exists($media->getPath()), 404, 'Fichier introuvable.');

            return response()->file($media->getPath());
        }

        $disk = $this->diskContaining($studentDocument->file_path);
        abort_unless($disk, 404, 'Fichier introuvable.');

        return response()->file(Storage::disk($disk)->path($studentDocument->file_path));
    }

    public function download(StudentDocument $studentDocument): BinaryFileResponse
    {
        abort_if(blank($studentDocument->file_path), 404, 'Fichier introuvable.');

        if ($media = $this->mediaFromDocument($studentDocument)) {
            abort_unless(file_exists($media->getPath()), 404, 'Fichier introuvable.');

            $filename = Str::slug($studentDocument->student?->matricule.'-'.$studentDocument->name)
                .'.'
                .$media->extension;

            return response()->download($media->getPath(), $filename);
        }

        $disk = $this->diskContaining($studentDocument->file_path);
        abort_unless($disk, 404, 'Fichier introuvable.');

        $extension = pathinfo($studentDocument->file_path, PATHINFO_EXTENSION);
        $filename = Str::slug($studentDocument->student?->matricule.'-'.$studentDocument->name).'.'.$extension;

        return response()->download(Storage::disk($disk)->path($studentDocument->file_path), $filename);
    }

    public function destroy(Student $student, StudentDocument $studentDocument): RedirectResponse
    {
        abort_unless($studentDocument->student_id === $student->id, 404);

        if ($media = $this->mediaFromDocument($studentDocument)) {
            $media->delete();
        } elseif ($disk = $this->diskContaining($studentDocument->file_path)) {
            Storage::disk($disk)->delete($studentDocument->file_path);
        }

        $studentDocument->delete();

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Document supprimé du dossier élève.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function mediaCollectionFor(string $documentType): string
    {
        return match ($documentType) {
            'photo' => 'student_photo',
            'birth_certificate' => 'birth_certificate',
            'medical_certificate' => 'medical_certificate',
            'previous_school_record',
            'previous_report_card' => 'previous_school_record',
            default => 'scanned_documents',
        };
    }

    private function mediaFromDocument(StudentDocument $studentDocument): ?Media
    {
        if (! Str::startsWith((string) $studentDocument->file_path, 'media:')) {
            return null;
        }

        $mediaId = (int) Str::after($studentDocument->file_path, 'media:');

        if ($mediaId <= 0) {
            return null;
        }

        return Media::query()
            ->whereKey($mediaId)
            ->where('model_type', Student::class)
            ->where('model_id', $studentDocument->student_id)
            ->first();
    }

    private function diskContaining(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        foreach (['documents', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }
}
