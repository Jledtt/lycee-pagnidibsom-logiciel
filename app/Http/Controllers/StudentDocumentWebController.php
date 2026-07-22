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

        $filePath = null;

        if ($request->hasFile('document_file')) {
            $filePath = $request
                ->file('document_file')
                ->store('students/' . $student->id . '/documents', 'public');
        }

        StudentDocument::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->activeAcademicYear()?->id,
            'name' => $data['name'],
            'document_type' => $data['document_type'],
            'file_path' => $filePath,
            'status' => $data['status'],
            'received_at' => $data['status'] === 'missing'
                ? null
                : ($data['received_at'] ?? ($data['status'] === 'received' ? now()->toDateString() : null)),
        ]);

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Document ajouté au dossier élève.');
    }

    public function show(StudentDocument $studentDocument): BinaryFileResponse
    {
        abort_if(blank($studentDocument->file_path), 404, 'Fichier introuvable.');
        abort_unless(Storage::disk('public')->exists($studentDocument->file_path), 404, 'Fichier introuvable.');

        return response()->file(Storage::disk('public')->path($studentDocument->file_path));
    }

    public function download(StudentDocument $studentDocument): BinaryFileResponse
    {
        abort_if(blank($studentDocument->file_path), 404, 'Fichier introuvable.');
        abort_unless(Storage::disk('public')->exists($studentDocument->file_path), 404, 'Fichier introuvable.');

        $extension = pathinfo($studentDocument->file_path, PATHINFO_EXTENSION);
        $filename = Str::slug($studentDocument->student?->matricule . '-' . $studentDocument->name) . '.' . $extension;

        return response()->download(Storage::disk('public')->path($studentDocument->file_path), $filename);
    }

    public function destroy(Student $student, StudentDocument $studentDocument): RedirectResponse
    {
        abort_unless($studentDocument->student_id === $student->id, 404);

        if ($studentDocument->file_path && Storage::disk('public')->exists($studentDocument->file_path)) {
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
}
