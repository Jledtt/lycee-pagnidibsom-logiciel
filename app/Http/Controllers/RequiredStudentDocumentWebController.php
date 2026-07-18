<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\RequiredStudentDocument;
use App\Models\SchoolClass;
use App\Services\RequiredStudentDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequiredStudentDocumentWebController extends Controller
{
    public function index(RequiredStudentDocumentService $requiredDocuments): View
    {
        return view('settings.required-documents', [
            'academicYear' => $this->activeAcademicYear(),
            'classes' => $this->classes(),
            'cycles' => $this->cycles(),
            'documentTypes' => $requiredDocuments->availableDocumentTypes(),
            'requiredDocuments' => RequiredStudentDocument::query()
                ->with('schoolClass.level')
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RequiredStudentDocument::query()->create($this->validatedData($request));

        return redirect()
            ->route('settings.required-documents.index')
            ->with('success', 'Piece obligatoire ajoutee.');
    }

    public function update(Request $request, RequiredStudentDocument $requiredDocument): RedirectResponse
    {
        $requiredDocument->update($this->validatedData($request, $requiredDocument));

        return redirect()
            ->route('settings.required-documents.index')
            ->with('success', 'Piece obligatoire mise a jour.');
    }

    public function destroy(RequiredStudentDocument $requiredDocument): RedirectResponse
    {
        $requiredDocument->delete();

        return redirect()
            ->route('settings.required-documents.index')
            ->with('success', 'Piece obligatoire supprimee.');
    }

    private function validatedData(Request $request, ?RequiredStudentDocument $requiredDocument = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'scope' => ['required', Rule::in(['all', 'cycle', 'class'])],
            'level_cycle' => ['nullable', 'string', 'max:255'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        if ($data['scope'] === 'cycle' && blank($data['level_cycle'])) {
            throw ValidationException::withMessages(['level_cycle' => 'Choisis le cycle concerne.']);
        }

        if ($data['scope'] === 'class' && blank($data['school_class_id'])) {
            throw ValidationException::withMessages(['school_class_id' => 'Choisis la classe concernee.']);
        }

        if ($data['scope'] !== 'cycle') {
            $data['level_cycle'] = null;
        }

        if ($data['scope'] !== 'class') {
            $data['school_class_id'] = null;
        }

        $data['document_type'] = $this->normalizeDocumentType($data['document_type'] ?: $data['name']);

        $duplicate = RequiredStudentDocument::query()
            ->where('document_type', $data['document_type'])
            ->where('scope', $data['scope'])
            ->where(function ($query) use ($data) {
                $data['level_cycle']
                    ? $query->where('level_cycle', $data['level_cycle'])
                    : $query->whereNull('level_cycle');
            })
            ->where(function ($query) use ($data) {
                $data['school_class_id']
                    ? $query->where('school_class_id', $data['school_class_id'])
                    : $query->whereNull('school_class_id');
            })
            ->when($requiredDocument, fn ($query) => $query->whereKeyNot($requiredDocument->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['document_type' => 'Cette piece existe deja pour cette portee.']);
        }

        return $data;
    }

    private function normalizeDocumentType(string $value): string
    {
        return str_replace('-', '_', Str::slug($value, '_'));
    }

    private function classes()
    {
        return SchoolClass::query()
            ->with('level')
            ->when($this->activeAcademicYear(), fn ($query, AcademicYear $academicYear) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function cycles()
    {
        return Level::query()
            ->whereNotNull('cycle')
            ->orderBy('position')
            ->pluck('cycle')
            ->unique()
            ->values();
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
