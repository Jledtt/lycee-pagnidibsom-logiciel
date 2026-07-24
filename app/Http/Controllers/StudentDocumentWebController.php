<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\RequiredStudentDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentDocumentWebController extends Controller
{
    public function store(Request $request, Student $student, RequiredStudentDocumentService $requiredDocuments): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in(array_keys($requiredDocuments->availableDocumentTypes()))],
            'status' => ['required', 'in:received,missing,expired'],
            'received_at' => ['nullable', 'date'],
            'document_file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        if (($data['status'] ?? null) !== 'missing' && ! $request->hasFile('document_file')) {
            return back()
                ->withErrors(['document_file' => 'Ajoute un fichier PDF ou image, ou marque le document comme manquant.'])
                ->withInput();
        }

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
                'file_path' => 'media:' . $media->id,
            ])->save();

            if ($data['document_type'] === 'photo') {
                $student->forceFill([
                    'photo_path' => $media->getUrl(),
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

        abort_unless(Storage::disk('public')->exists($studentDocument->file_path), 404, 'Fichier introuvable.');

        return response()->file(Storage::disk('public')->path($studentDocument->file_path));
    }

    public function download(StudentDocument $studentDocument): BinaryFileResponse
    {
        abort_if(blank($studentDocument->file_path), 404, 'Fichier introuvable.');

        if ($media = $this->mediaFromDocument($studentDocument)) {
            abort_unless(file_exists($media->getPath()), 404, 'Fichier introuvable.');

            $filename = Str::slug($studentDocument->student?->matricule . '-' . $studentDocument->name)
                . '.'
                . $media->extension;

            return response()->download($media->getPath(), $filename);
        }

        abort_unless(Storage::disk('public')->exists($studentDocument->file_path), 404, 'Fichier introuvable.');

        $extension = pathinfo($studentDocument->file_path, PATHINFO_EXTENSION);
        $filename = Str::slug($studentDocument->student?->matricule . '-' . $studentDocument->name) . '.' . $extension;

        return response()->download(Storage::disk('public')->path($studentDocument->file_path), $filename);
    }

    public function destroy(Student $student, StudentDocument $studentDocument): RedirectResponse
    {
        abort_unless($studentDocument->student_id === $student->id, 404);

        if ($media = $this->mediaFromDocument($studentDocument)) {
            $media->delete();
        } elseif ($studentDocument->file_path && Storage::disk('public')->exists($studentDocument->file_path)) {
            Storage::disk('public')->delete($studentDocument->file_path);
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

        return Media::query()->whereKey($mediaId)->first();
    }
}
