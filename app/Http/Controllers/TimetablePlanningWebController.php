<?php

namespace App\Http\Controllers;

use App\Http\Requests\Timetable\ReviewTeacherAvailabilityImportRequest;
use App\Models\AcademicYear;
use App\Models\TeacherAvailability;
use App\Models\TimetableGenerationRun;
use App\Services\TeacherAvailabilityImportService;
use App\Services\TimetableGenerationService;
use App\Services\TimetableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetablePlanningWebController extends Controller
{
    private const IMPORT_SESSION_KEY = 'timetables.availability_import_preview';

    public function index(
        Request $request,
        TimetableGenerationService $generation,
        TimetableTemplateService $templates,
    ): View {
        $academicYear = $this->requireActiveAcademicYear();
        $run = TimetableGenerationRun::query()
            ->with(['requester', 'appliedBy'])
            ->where('academic_year_id', $academicYear->id)
            ->when($request->integer('run'), fn ($query) => $query->whereKey($request->integer('run')))
            ->latest()
            ->first();

        return view('timetables.planning', [
            'academicYear' => $academicYear,
            'days' => $templates->days(),
            'gridPreview' => $run ? $generation->previewGrid($run) : [],
            'importPreview' => $request->session()->get(self::IMPORT_SESSION_KEY),
            'readiness' => $generation->readiness($academicYear),
            'run' => $run,
        ]);
    }

    public function template(TeacherAvailabilityImportService $importer): StreamedResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        return Response::streamDownload(function () use ($importer, $academicYear): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $importer->templateHeaders(), ';');
            foreach ($importer->templateRows($academicYear) as $row) {
                fputcsv($output, $row, ';');
            }
            fclose($output);
        }, 'modele-disponibilites-professeurs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function previewImport(Request $request, TeacherAvailabilityImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'availability_file' => ['required', 'file', 'max:5120', 'extensions:csv,txt,xlsx,pdf,docx'],
        ]);
        $academicYear = $this->requireActiveAcademicYear();
        $request->session()->put(
            self::IMPORT_SESSION_KEY,
            $importer->preview($data['availability_file'], $academicYear),
        );

        return redirect()->route('timetables.planning.import.review');
    }

    public function reviewImport(
        Request $request,
        TeacherAvailabilityImportService $importer,
        TimetableTemplateService $templates,
    ): View|RedirectResponse {
        $academicYear = $this->requireActiveAcademicYear();
        $preview = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (! $this->isCurrentPreview($preview, $academicYear)) {
            $request->session()->forget(self::IMPORT_SESSION_KEY);

            return redirect()
                ->route('timetables.planning')
                ->with('warning', 'L’analyse a expiré. Sélectionne de nouveau le document.');
        }

        return view('timetables.import-review', [
            'academicYear' => $academicYear,
            'availabilityLabels' => TeacherAvailability::labels(),
            'days' => $templates->days(),
            'preview' => $preview,
            'teachers' => $importer->reviewTeachers(),
        ]);
    }

    public function reviseImport(
        ReviewTeacherAvailabilityImportRequest $request,
        TeacherAvailabilityImportService $importer,
    ): RedirectResponse {
        $academicYear = $this->requireActiveAcademicYear();
        $preview = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (! $this->isCurrentPreview($preview, $academicYear)) {
            $request->session()->forget(self::IMPORT_SESSION_KEY);

            return redirect()
                ->route('timetables.planning')
                ->with('warning', 'L’analyse a expiré. Sélectionne de nouveau le document.');
        }

        $revised = $importer->revise($preview, $request->validated('rows'), $academicYear);
        $request->session()->put(self::IMPORT_SESSION_KEY, $revised);

        return redirect()
            ->route('timetables.planning.import.review')
            ->with(
                $revised['summary']['invalid'] > 0 ? 'warning' : 'success',
                $revised['summary']['invalid'] > 0
                    ? 'Certaines lignes conservées doivent encore être corrigées.'
                    : 'Toutes les lignes conservées sont prêtes à être importées.',
            );
    }

    public function applyImport(Request $request, TeacherAvailabilityImportService $importer): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $preview = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (! $this->isCurrentPreview($preview, $academicYear)) {
            $request->session()->forget(self::IMPORT_SESSION_KEY);
            throw ValidationException::withMessages([
                'availability_file' => 'L’analyse a expiré. Analyse de nouveau le fichier avant l’import.',
            ]);
        }
        $preview = $importer->revalidate($preview, $academicYear);
        $request->session()->put(self::IMPORT_SESSION_KEY, $preview);
        if ((int) ($preview['summary']['valid'] ?? 0) < 1 || (int) ($preview['summary']['invalid'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'availability_file' => 'Corrige ou ignore toutes les lignes invalides avant l’import.',
            ]);
        }

        $result = $importer->import($preview, $academicYear, $request->user());
        $request->session()->forget(self::IMPORT_SESSION_KEY);

        $teacherLabel = $result['teachers'] === 1 ? 'fiche de disponibilité validée' : 'fiches de disponibilité validées';
        $rowLabel = $result['rows'] === 1 ? 'ligne' : 'lignes';

        return redirect()
            ->route('timetables.planning')
            ->with('success', $result['teachers'].' '.$teacherLabel.' à partir de '.$result['rows'].' '.$rowLabel.'.');
    }

    public function clearImport(Request $request): RedirectResponse
    {
        $request->session()->forget(self::IMPORT_SESSION_KEY);

        return redirect()->route('timetables.planning');
    }

    public function generate(Request $request, TimetableGenerationService $generation): RedirectResponse
    {
        $run = $generation->generate($this->requireActiveAcademicYear(), $request->user());

        return redirect()
            ->route('timetables.planning', ['run' => $run->id])
            ->with(
                $run->canBeApplied() ? 'success' : 'warning',
                $run->canBeApplied()
                    ? 'Proposition générée. Vérifie les grilles avant de les appliquer.'
                    : 'Aucune grille n’a été modifiée. Consulte les points à corriger.',
            );
    }

    public function apply(
        Request $request,
        TimetableGenerationRun $timetableGenerationRun,
        TimetableGenerationService $generation,
    ): RedirectResponse {
        $academicYear = $this->requireActiveAcademicYear();
        abort_unless($timetableGenerationRun->academic_year_id === $academicYear->id, 404);

        $generation->apply($timetableGenerationRun, $request->user());

        return redirect()
            ->route('timetables.planning', ['run' => $timetableGenerationRun->id])
            ->with('success', 'La proposition a été appliquée en brouillon. Les emplois du temps actifs ont été conservés.');
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }

    private function isCurrentPreview(mixed $preview, AcademicYear $academicYear): bool
    {
        return is_array($preview)
            && (int) ($preview['academic_year_id'] ?? 0) === $academicYear->id
            && (int) ($preview['expires_at'] ?? 0) >= now()->timestamp;
    }
}
