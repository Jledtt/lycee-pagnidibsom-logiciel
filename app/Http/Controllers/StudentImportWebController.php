<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Services\CsvExportService;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentImportWebController extends Controller
{
    private const SESSION_KEY = 'student_import_preview';

    public function create(): View
    {
        return view('students.import', [
            'academicYear' => $this->activeAcademicYear(),
            'preview' => session()->get(self::SESSION_KEY),
        ]);
    }

    public function template(CsvExportService $csvExport, StudentImportService $studentImport)
    {
        return $csvExport->download(
            'modele-import-eleves.csv',
            $studentImport->templateHeaders(),
            $studentImport->templateRows(),
        );
    }

    public function preview(Request $request, StudentImportService $studentImport): RedirectResponse
    {
        $data = $request->validate([
            'students_file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
        ]);

        $preview = $studentImport->preview($data['students_file'], $this->activeAcademicYear());
        session()->put(self::SESSION_KEY, $preview);

        return redirect()
            ->route('students.import')
            ->with('success', 'Fichier analyse. Verifie les lignes avant de lancer l import.');
    }

    public function store(Request $request, StudentImportService $studentImport): RedirectResponse
    {
        $preview = session(self::SESSION_KEY);

        if (! $preview) {
            return redirect()
                ->route('students.import')
                ->withErrors(['students_file' => 'Importe d abord un fichier pour afficher la previsualisation.']);
        }

        $result = $studentImport->import($preview, $this->activeAcademicYear(), $request->user()?->id);
        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('students.index')
            ->with('success', $result['created'] . ' eleve(s) importe(s). ' . $result['skipped'] . ' doublon(s) ignore(s).');
    }

    public function destroy(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('students.import')
            ->with('success', 'Previsualisation annulee.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }
}
