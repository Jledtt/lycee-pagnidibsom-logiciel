<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\SchoolSetting;
use App\Models\TimetableEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeacherAttendanceSheetWebController extends Controller
{
    private const PERIODS = [
        '07' => '7h-8h',
        '08' => '8h-9h',
        '09' => '9h-10h',
        '10' => '10h-11h',
        '11' => '11h-12h',
        '15' => '15h-16h',
        '16' => '16h-17h',
    ];

    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        return view('teacher-attendance-sheets.index', [
            'academicYear' => $academicYear,
            'filters' => $this->defaultFilters($request),
            'teachers' => $this->teacherNames($academicYear),
        ]);
    }

    public function pdf(Request $request)
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $this->validatedFilters($request);
        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->startOfDay();
        $teacherName = $filters['teacher_name'] ?? null;
        $rows = $this->rows($academicYear, $start, $end, $teacherName);
        $filename = 'fiche-emargement-' . Str::slug(($teacherName ?: 'professeurs') . '-' . $start->format('Y-m-d') . '-' . $end->format('Y-m-d')) . '.pdf';

        return Pdf::loadView('teacher-attendance-sheets.pdf', [
            'academicYear' => $academicYear,
            'end' => $end,
            'periods' => self::PERIODS,
            'rows' => $rows,
            'school' => SchoolSetting::query()->first(),
            'start' => $start,
            'teacherName' => $teacherName,
        ])
            ->setPaper('a4', 'landscape')
            ->stream($filename);
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function defaultFilters(Request $request): array
    {
        $start = $request->date('start_date') ?: now()->startOfWeek(Carbon::MONDAY);
        $end = $request->date('end_date') ?: $start->copy()->endOfWeek(Carbon::SATURDAY);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'teacher_name' => $request->string('teacher_name')->toString(),
        ];
    }

    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'teacher_name' => ['nullable', 'string', 'max:160'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($start->diffInDays($end) > 31) {
            abort(422, 'La periode ne doit pas depasser 31 jours.');
        }

        return $data;
    }

    private function teacherNames(?AcademicYear $academicYear): Collection
    {
        return TimetableEntry::query()
            ->whereHas('timetable', fn ($query) => $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id)))
            ->whereNotNull('teacher_name')
            ->pluck('teacher_name')
            ->map(fn (?string $name) => trim((string) $name))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function rows(?AcademicYear $academicYear, Carbon $start, Carbon $end, ?string $teacherName): Collection
    {
        $entries = TimetableEntry::query()
            ->with('timetable.schoolClass')
            ->whereHas('timetable', fn ($query) => $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id)))
            ->when($teacherName, fn ($query) => $query->where('teacher_name', $teacherName))
            ->whereNotNull('subject_name')
            ->get();

        $byDayAndHour = $entries->groupBy(fn (TimetableEntry $entry) => $entry->day_of_week . '-' . $this->hourKey($entry));
        $rows = collect();
        $date = $start->copy();

        while ($date->lte($end)) {
            $cells = [];

            foreach (self::PERIODS as $hour => $label) {
                $dayKey = $this->dayKey($date);
                $cells[$label] = $byDayAndHour->get($dayKey . '-' . $hour, collect())
                    ->map(fn (TimetableEntry $entry) => trim(($entry->timetable?->schoolClass?->name ?? '') . ' ' . ($entry->subject_name ?? '')))
                    ->filter()
                    ->unique()
                    ->join(' / ');
            }

            $rows->push([
                'date' => $date->copy(),
                'cells' => $cells,
                'hours' => collect($cells)->filter()->count(),
            ]);

            $date->addDay();
        }

        return $rows;
    }

    private function hourKey(TimetableEntry $entry): string
    {
        if ($entry->starts_at) {
            return substr((string) $entry->starts_at, 0, 2);
        }

        if (preg_match('/^(\d{1,2})h/', $entry->period_label, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        return '';
    }

    private function dayKey(Carbon $date): string
    {
        return [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ][$date->dayOfWeekIso];
    }
}
