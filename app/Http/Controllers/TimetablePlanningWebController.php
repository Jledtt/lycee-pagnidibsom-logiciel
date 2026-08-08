<?php

namespace App\Http\Controllers;

use App\Http\Requests\Timetable\ReviewTeacherAvailabilityImportRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\TeacherAvailability;
use App\Models\TimetableGenerationRun;
use App\Services\TeacherAvailabilityImportService;
use App\Services\TimetableGenerationService;
use App\Services\TimetableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
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
        $classes = $academicYear->classes()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $selectedClassId = $request->integer('school_class_id');
        $selectedClass = $selectedClassId
            ? SchoolClass::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active')
                ->find($selectedClassId)
            : null;
        $run = TimetableGenerationRun::query()
            ->with(['requester', 'appliedBy'])
            ->where('academic_year_id', $academicYear->id)
            ->when($request->integer('run'), fn ($query) => $query->whereKey($request->integer('run')))
            ->latest()
            ->first();

        return view('timetables.planning', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'days' => $templates->days(),
            'gridPreview' => $run ? $generation->previewGrid($run) : [],
            'importPreview' => $request->session()->get(self::IMPORT_SESSION_KEY),
            'readiness' => $generation->readiness($academicYear, $selectedClass ? [$selectedClass->id] : []),
            'run' => $run,
            'selectedClass' => $selectedClass,
        ]);
    }

    public function blockers(Request $request, TimetableGenerationService $generation): View
    {
        $academicYear = $this->requireActiveAcademicYear();
        $classes = $academicYear->classes()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $selectedClassId = $request->integer('school_class_id');
        $selectedClass = $selectedClassId
            ? SchoolClass::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('status', 'active')
                ->find($selectedClassId)
            : null;
        $readiness = $generation->readiness($academicYear, $selectedClass ? [$selectedClass->id] : []);

        return view('timetables.planning-blockers', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'readiness' => $readiness,
            'selectedClass' => $selectedClass,
            'blockerGroups' => $this->groupPlanningMessages($readiness['blockers']),
            'warningGroups' => $this->groupPlanningMessages($readiness['warnings']),
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
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validate([
            'school_class_id' => [
                'nullable',
                'integer',
                Rule::exists('school_classes', 'id')
                    ->where('academic_year_id', $academicYear->id)
                    ->where('status', 'active'),
            ],
        ]);
        $classIds = filled($data['school_class_id'] ?? null) ? [(int) $data['school_class_id']] : [];
        $run = $generation->generate($academicYear, $request->user(), $classIds);

        return redirect()
            ->route('timetables.planning', [
                'run' => $run->id,
                'school_class_id' => $data['school_class_id'] ?? null,
            ])
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
            ->route('timetables.planning', [
                'run' => $timetableGenerationRun->id,
                'school_class_id' => count($timetableGenerationRun->input_snapshot['target_class_ids'] ?? []) === 1
                    ? $timetableGenerationRun->input_snapshot['target_class_ids'][0]
                    : null,
            ])
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

    /**
     * @param  array<int, string>  $messages
     * @return array<string, array{title: string, description: string, action: string, messages: array<int, string>}>
     */
    private function groupPlanningMessages(array $messages): array
    {
        $groups = [
            'teacher' => [
                'title' => 'Professeurs et disponibilités',
                'description' => 'Un professeur manque, ses disponibilités ne sont pas validées ou elles ne couvrent pas ses heures.',
                'action' => 'Ouvre Disponibilités, complète le professeur concerné, puis valide la fiche.',
                'messages' => [],
            ],
            'hours' => [
                'title' => 'Volumes horaires',
                'description' => 'Une matière n’a pas assez d’heures, trop d’heures ou un volume impossible à placer.',
                'action' => 'Corrige les heures hebdomadaires dans Matières et coefficients pour la classe.',
                'messages' => [],
            ],
            'class' => [
                'title' => 'Classes et matières',
                'description' => 'Une classe active n’a pas encore de matières exploitables pour la génération.',
                'action' => 'Vérifie la classe, les matières actives et les affectations de l’année scolaire.',
                'messages' => [],
            ],
            'schedule' => [
                'title' => 'Créneaux et grilles existantes',
                'description' => 'Les périodes de cours, les cours verrouillés ou les grilles actives empêchent la génération.',
                'action' => 'Corrige les créneaux, rouvre la grille si nécessaire ou cible une autre classe.',
                'messages' => [],
            ],
            'system' => [
                'title' => 'Moteur de génération',
                'description' => 'La configuration est lisible, mais le moteur n’a pas pu produire une proposition utilisable.',
                'action' => 'Réessaie après correction des données, puis consulte les journaux si le moteur reste indisponible.',
                'messages' => [],
            ],
        ];

        foreach ($messages as $message) {
            $key = match (true) {
                str_contains($message, 'professeur'), str_contains($message, 'disponibilit') => 'teacher',
                str_contains($message, 'volume horaire'), str_contains($message, 'heure(s)'), str_contains($message, 'capacit') => 'hours',
                str_contains($message, 'mati'), str_contains($message, 'classe active') => 'class',
                str_contains($message, 'cr'), str_contains($message, 'verrouill'), str_contains($message, 'grille active'), str_contains($message, 'emploi du temps actif') => 'schedule',
                default => 'system',
            };

            $groups[$key]['messages'][] = $message;
        }

        return array_filter($groups, fn (array $group): bool => $group['messages'] !== []);
    }
}
