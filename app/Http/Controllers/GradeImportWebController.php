<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\GradeImportService;
use App\Services\XlsxExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeImportWebController extends Controller
{
    private const SESSION_PREFIX = 'grade_import_preview_';

    public function create(Assessment $assessment): View
    {
        $assessment->load(['academicYear', 'term', 'schoolClass.level', 'subject', 'assessmentType']);

        return view('grades.import', [
            'academicYear' => $assessment->academicYear,
            'assessment' => $assessment,
            'preview' => session()->get($this->sessionKey($assessment)),
        ]);
    }

    public function template(Assessment $assessment, XlsxExportService $xlsxExport, GradeImportService $gradeImport)
    {
        $assessment->load(['schoolClass', 'subject']);

        return $xlsxExport->download(
            'modele-notes-' . str($assessment->schoolClass->name . '-' . $assessment->subject->name . '-' . $assessment->title)->slug() . '.xlsx',
            $gradeImport->templateHeaders(),
            $gradeImport->templateRows($assessment),
            'Notes',
        );
    }

    public function preview(Request $request, Assessment $assessment, GradeImportService $gradeImport): RedirectResponse
    {
        abort_if($assessment->is_locked, 403, 'Cette evaluation est verrouillee.');

        $data = $request->validate([
            'grades_file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,pdf'],
        ]);

        $preview = $gradeImport->preview($data['grades_file'], $assessment);
        session()->put($this->sessionKey($assessment), $preview);

        return redirect()
            ->route('grades.import', $assessment)
            ->with('success', 'Fichier analysé. Vérifie les notes avant de lancer l’import.');
    }

    public function store(Request $request, Assessment $assessment, GradeImportService $gradeImport): RedirectResponse
    {
        abort_if($assessment->is_locked, 403, 'Cette evaluation est verrouillee.');

        $preview = session()->get($this->sessionKey($assessment));

        if (! $preview || (int) ($preview['assessment_id'] ?? 0) !== $assessment->id) {
            return redirect()
                ->route('grades.import', $assessment)
                ->withErrors(['grades_file' => 'Importe d abord un fichier pour afficher la previsualisation.']);
        }

        $result = $gradeImport->import($preview, $assessment, $request->user()?->id);
        session()->forget($this->sessionKey($assessment));

        return redirect()
            ->route('grades.index', [
                'school_class_id' => $assessment->school_class_id,
                'term_id' => $assessment->term_id,
                'assessment_id' => $assessment->id,
            ])
            ->with('success', $result['created'] . ' note(s) créée(s), ' . $result['updated'] . ' note(s) mise(s) à jour.');
    }

    public function destroy(Assessment $assessment): RedirectResponse
    {
        session()->forget($this->sessionKey($assessment));

        return redirect()
            ->route('grades.import', $assessment)
            ->with('success', 'Prévisualisation annulée.');
    }

    private function sessionKey(Assessment $assessment): string
    {
        return self::SESSION_PREFIX . $assessment->id;
    }
}
