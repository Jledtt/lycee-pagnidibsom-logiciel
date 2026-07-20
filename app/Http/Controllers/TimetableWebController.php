<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TimetableWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);
        $entries = $this->entries($schoolClass, $academicYear);

        return view('timetables.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'dayLabels' => TimetableEntry::dayLabels(),
            'entries' => $entries,
            'grid' => $this->grid($entries),
            'schoolClass' => $schoolClass,
            'subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(),
            'templateAvailable' => $schoolClass ? $this->templateForClass($schoolClass) !== [] : false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $this->validatedEntry($request);

        try {
            TimetableEntry::query()->create([
                ...$data,
                'academic_year_id' => $academicYear->id,
            ]);
        } catch (QueryException) {
            return $this->backToIndex($data['school_class_id'])
                ->withErrors(['starts_at' => 'Un creneau existe deja pour cette classe, ce jour et cette heure.']);
        }

        return $this->backToIndex($data['school_class_id'])
            ->with('success', 'Creneau ajoute a l emploi du temps.');
    }

    public function update(Request $request, TimetableEntry $timetableEntry): RedirectResponse
    {
        $data = $this->validatedEntry($request);

        try {
            $timetableEntry->update($data);
        } catch (QueryException) {
            return $this->backToIndex($data['school_class_id'])
                ->withErrors(['starts_at' => 'Un autre creneau existe deja pour cette classe, ce jour et cette heure.']);
        }

        return $this->backToIndex($data['school_class_id'])
            ->with('success', 'Creneau modifie.');
    }

    public function destroy(TimetableEntry $timetableEntry): RedirectResponse
    {
        $schoolClassId = $timetableEntry->school_class_id;
        $timetableEntry->delete();

        return $this->backToIndex($schoolClassId)
            ->with('success', 'Creneau retire.');
    }

    public function applyTemplate(Request $request): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
        ]);
        $schoolClass = SchoolClass::query()->findOrFail($data['school_class_id']);
        $template = $this->templateForClass($schoolClass);

        if ($template === []) {
            return $this->backToIndex($schoolClass->id)
                ->withErrors(['school_class_id' => 'Aucun modele disponible pour cette classe.']);
        }

        foreach ($template as $entry) {
            TimetableEntry::query()->updateOrCreate(
                [
                    'school_class_id' => $schoolClass->id,
                    'day_of_week' => $entry['day_of_week'],
                    'starts_at' => $entry['starts_at'],
                    'ends_at' => $entry['ends_at'],
                ],
                [
                    'academic_year_id' => $academicYear->id,
                    'subject_label' => $entry['subject_label'],
                    'teacher_name' => $entry['teacher_name'] ?? null,
                    'room' => null,
                    'notes' => null,
                ],
            );
        }

        return $this->backToIndex($schoolClass->id)
            ->with('success', count($template).' creneau(x) appliques depuis l exemple Word.');
    }

    public function pdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $classes = $this->classes($academicYear);
        $schoolClass = $this->selectedClass($request, $classes);

        abort_if(! $schoolClass, 404, 'Classe introuvable.');

        $entries = $this->entries($schoolClass, $academicYear);
        $filename = 'emploi-du-temps-'.Str::slug($schoolClass->name.'-'.($academicYear?->name ?? 'annee')).'.pdf';

        return Pdf::loadView('timetables.pdf', [
            'academicYear' => $academicYear,
            'dayLabels' => TimetableEntry::dayLabels(),
            'entries' => $entries,
            'grid' => $this->grid($entries),
            'school' => SchoolSetting::query()->first(),
            'schoolClass' => $schoolClass,
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function validatedEntry(Request $request): array
    {
        return $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(TimetableEntry::dayLabels()))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'subject_label' => ['required', 'string', 'max:120'],
            'teacher_name' => ['nullable', 'string', 'max:160'],
            'room' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }

    private function classes(?AcademicYear $academicYear): Collection
    {
        return SchoolClass::query()
            ->with('level')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function selectedClass(Request $request, Collection $classes): ?SchoolClass
    {
        if ($classes->isEmpty()) {
            return null;
        }

        return $classes->firstWhere('id', $request->integer('school_class_id')) ?? $classes->first();
    }

    private function entries(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        return TimetableEntry::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('starts_at')
            ->orderBy('day_of_week')
            ->get();
    }

    private function grid(Collection $entries): array
    {
        $slots = $entries
            ->map(fn (TimetableEntry $entry) => [
                'starts_at' => substr((string) $entry->starts_at, 0, 5),
                'ends_at' => substr((string) $entry->ends_at, 0, 5),
                'label' => $entry->time_label,
            ])
            ->unique(fn (array $slot) => $slot['starts_at'].'-'.$slot['ends_at'])
            ->sortBy('starts_at')
            ->values();

        return $slots->map(function (array $slot) use ($entries) {
            return [
                'slot' => $slot,
                'days' => collect(TimetableEntry::dayLabels())
                    ->mapWithKeys(fn ($label, $day) => [
                        $day => $entries->first(fn (TimetableEntry $entry) => (int) $entry->day_of_week === (int) $day
                            && substr((string) $entry->starts_at, 0, 5) === $slot['starts_at']
                            && substr((string) $entry->ends_at, 0, 5) === $slot['ends_at']),
                    ])
                    ->all(),
            ];
        })->all();
    }

    private function backToIndex(?int $schoolClassId): RedirectResponse
    {
        return redirect()->route('timetables.index', array_filter([
            'school_class_id' => $schoolClassId,
        ]));
    }

    private function templateForClass(SchoolClass $schoolClass): array
    {
        $key = $this->classTemplateKey($schoolClass->name);
        $templates = $this->templates();

        return $templates[$key] ?? [];
    }

    private function classTemplateKey(string $className): string
    {
        $name = Str::lower(Str::ascii($className));

        return match (true) {
            str_contains($name, '6') => '6eme',
            str_contains($name, '5') => '5eme',
            str_contains($name, '4') => '4eme',
            str_contains($name, '3') => '3eme',
            str_contains($name, '2nde') && str_contains($name, 'c') => '2nde c',
            str_contains($name, '2nde') && str_contains($name, 'a') => '2nde a',
            default => '',
        };
    }

    private function templates(): array
    {
        return [
            '6eme' => $this->templateRows([
                '07:00-07:55' => ['EC', 'Francais', 'Francais', 'SVT', 'HG', ''],
                '07:55-08:50' => ['EC', 'Francais', 'Anglais', 'Francais', 'SVT', 'HG'],
                '08:50-09:45' => ['Maths', 'Anglais', 'Devoir', 'EPS', '', ''],
                '10:10-11:05' => ['HG', 'Maths', 'Maths', 'Devoir', 'Francais', ''],
                '11:05-12:00' => ['HG', 'SVT', 'Maths', 'Devoir', 'Francais', ''],
                '15:00-16:00' => ['Anglais', 'EPS', 'Anglais', '', '', ''],
                '16:00-17:00' => ['Maths', 'EPS', 'Anglais', '', '', ''],
            ], [
                'Francais' => 'ZONGO Florence',
                'Anglais' => 'KIEMA Philomene',
                'HG' => 'KOMBASSERE Salifou',
                'Maths' => 'BADO Constant',
                'EC' => 'KEREGUE',
                'SVT' => 'KABORE Aichatou',
                'EPS' => 'MEDA Mathurin',
            ]),
            '5eme' => $this->templateRows([
                '07:00-07:55' => ['Maths', 'HG', 'Maths', 'HG', 'Francais', 'Maths'],
                '07:55-08:50' => ['EPS', 'HG', 'Maths', 'HG', 'Francais', 'Maths'],
                '08:50-09:45' => ['Francais', 'EC', 'SVT', 'EC', '', 'Francais'],
                '10:10-11:05' => ['SVT', 'Devoir', 'SVT', 'Anglais', 'Francais', ''],
                '11:05-12:00' => ['Devoir', 'Anglais', 'Anglais', '', '', ''],
                '15:00-16:00' => ['Anglais', 'EPS', '', '', '', ''],
                '16:00-17:00' => ['Anglais', 'EPS', '', '', '', ''],
            ], [
                'Francais' => 'BADIEL Philippe',
                'Anglais' => 'DEMBELE/SAWADOGO Bibata',
                'HG' => 'KEREGUE Sompeguea',
                'Maths' => 'DIANDA Halidou',
                'EC' => 'KEREGUE Sompeguea',
                'SVT' => 'KABORE/KABRE Aichatou',
                'EPS' => 'NIKIEMA Zakaria',
            ]),
            '4eme' => $this->templateRows([
                '07:00-07:55' => ['EPS', 'PC', 'Anglais', 'PC', 'Maths', 'EPS'],
                '07:55-08:50' => ['EPS', 'PC', 'PC', '', 'Maths', ''],
                '08:50-09:45' => ['SVT', 'Francais', 'Maths', '', '', ''],
                '10:10-11:05' => ['Anglais', 'HG', 'Maths', 'HG', 'SVT', 'EC'],
                '11:05-12:00' => ['Anglais', 'HG', 'Maths', 'HG', 'SVT', 'EC'],
                '15:00-16:00' => ['Francais', 'Francais', '', '', '', ''],
                '16:00-17:00' => ['Francais', 'Francais', '', '', '', ''],
            ], [
                'Francais' => 'ZONGO Florence',
                'Anglais' => 'KIEMA Philomene',
                'HG' => 'DAH Sami Sie',
                'Maths' => 'KAMANA Payaki',
                'EC' => 'KOMBASSERE Salifou',
                'SVT' => 'KOIBA Issaka',
                'EPS' => 'OUEDRAOGO Hilaire',
            ]),
            '3eme' => $this->templateRows([
                '07:00-07:55' => ['SVT', 'Maths', 'Francais', 'Anglais', 'SVT', 'Francais'],
                '07:55-08:50' => ['SVT', 'Maths', 'Francais', 'Anglais', 'SVT', 'Francais'],
                '08:50-09:45' => ['EPS', 'Anglais', 'Devoir', 'Maths', '', ''],
                '10:10-11:05' => ['PC', 'Devoir', 'Devoir', 'PC', 'Francais', 'Maths'],
                '11:05-12:00' => ['PC', 'Devoir', 'PC', 'Francais', 'Maths', ''],
                '15:00-16:00' => ['HG', 'EPS', 'HG', '', '', ''],
                '16:00-17:00' => ['HG', 'EPS', 'HG', '', '', ''],
            ], [
                'Francais' => 'SONG-NABA Belko Leon',
                'Anglais' => 'TINTILA Yamdaogo',
                'HG' => 'KOMBASSERE Salifou',
                'Maths' => 'BADO Constant',
                'SVT' => 'KOIBA Issaka',
                'EPS' => 'OUEDRAOGO Hilaire',
            ]),
            '2nde c' => $this->templateRows([
                '07:00-07:55' => ['Francais', 'EC', 'HG', 'Maths', 'EPS', 'EPS'],
                '07:55-08:50' => ['Francais', 'EC', 'HG', 'Maths', 'Francais', ''],
                '08:50-09:45' => ['HG', 'Maths', 'SVT', 'Philo', 'SVT', 'Francais'],
                '10:10-11:05' => ['PC', 'Maths', 'SVT', 'Philo', 'PC', 'Maths'],
                '11:05-12:00' => ['PC', 'Anglais', 'PC', '', 'Maths', ''],
                '15:00-16:00' => ['PC', 'Anglais', '', '', '', ''],
                '16:00-17:00' => ['PC', 'Anglais', '', '', '', ''],
            ], [
                'Francais' => 'BADIEL Philippe',
                'Anglais' => 'DEMBELE/SAWADOGO Bibata',
                'HG' => 'KEREGUE Sompeguea',
                'Maths' => 'KAMANA Payaki',
                'SVT' => 'KABORE/KABRE Aichatou',
                'EPS' => 'NIKIEMA Zakaria',
                'EC' => 'DAH Sam Sie',
            ]),
            '2nde a' => $this->templateRows([
                '07:00-07:55' => ['EPS', 'EC', 'Francais', 'HG', 'Maths', ''],
                '07:55-08:50' => ['Philo', 'EPS', 'Francais', 'HG', 'Maths', ''],
                '08:50-09:45' => ['Philo', 'HG', 'Francais', 'Maths', 'Francais', ''],
                '10:10-11:05' => ['Allemand', 'HG', 'Francais', 'Anglais', 'Allemand', 'Allemand'],
                '11:05-12:00' => ['Allemand', 'Anglais', 'Anglais', 'Allemand', 'Allemand', ''],
            ], [
                'Francais' => 'SONG-NABA Belko Leon',
                'Anglais' => 'TINTILA Yamdaogo',
                'EC' => 'DAH Sam Sie',
                'Maths' => 'KAMANA Payaki',
                'HG' => 'KEREGUE Sompeguea',
                'Philo' => 'MORE Tolfanrson',
                'Allemand' => 'M. SAWDOGO',
            ]),
        ];
    }

    private function templateRows(array $slots, array $teachers): array
    {
        $rows = [];

        foreach ($slots as $time => $subjects) {
            [$startsAt, $endsAt] = explode('-', $time);

            foreach ($subjects as $index => $subject) {
                if (blank($subject)) {
                    continue;
                }

                $rows[] = [
                    'day_of_week' => $index + 1,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'subject_label' => $subject,
                    'teacher_name' => $teachers[$subject] ?? null,
                ];
            }
        }

        return $rows;
    }
}
