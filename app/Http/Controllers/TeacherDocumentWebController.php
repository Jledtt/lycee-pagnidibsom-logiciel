<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\TeacherDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeacherDocumentWebController extends Controller
{
    public function store(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->hasRole('enseignant'), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(['CNIB', 'Passeport', 'Contrat', 'Diplôme', 'Attestation', 'RIB', 'Autre'])],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'document_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);
        $file = $request->file('document_file');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('teacher-documents/'.$teacher->id, $filename, 'local');

        TeacherDocument::query()->create([
            ...$data,
            'teacher_id' => $teacher->id,
            'academic_year_id' => AcademicYear::query()->where('is_active', true)->value('id'),
            'file_path' => $path,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document ajouté au dossier professeur.');
    }

    public function download(Request $request, TeacherDocument $teacherDocument): BinaryFileResponse
    {
        abort_unless(
            $request->user()->can('teacher_documents.manage') || $request->user()->id === $teacherDocument->teacher_id,
            403,
        );
        abort_unless(Storage::disk('local')->exists($teacherDocument->file_path), 404, 'Fichier introuvable.');
        $extension = pathinfo($teacherDocument->file_path, PATHINFO_EXTENSION);

        return response()->download(
            Storage::disk('local')->path($teacherDocument->file_path),
            Str::slug($teacherDocument->teacher?->name.'-'.$teacherDocument->name).'.'.$extension,
        );
    }

    public function destroy(TeacherDocument $teacherDocument): RedirectResponse
    {
        Storage::disk('local')->delete($teacherDocument->file_path);
        $teacherDocument->delete();

        return back()->with('success', 'Document supprimé.');
    }
}
