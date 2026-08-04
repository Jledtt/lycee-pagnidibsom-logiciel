<?php

namespace App\Http\Controllers;

use App\Http\Requests\Timetable\UpdateTeacherAvailabilityRequest;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilitySchedule;
use App\Models\User;
use App\Services\TeacherAvailabilityService;
use App\Services\TimetableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherAvailabilityWebController extends Controller
{
    public function __construct(
        private readonly TimetableTemplateService $templates,
        private readonly TeacherAvailabilityService $availabilities,
    ) {}

    public function index(Request $request): View
    {
        $academicYear = $this->requireActiveAcademicYear();
        $canEditAll = $request->user()->can('timetables.manage');
        $canViewAll = $canEditAll || $request->user()->can('teachers.manage');
        $teachers = $canViewAll
            ? User::query()->role('enseignant')->where('status', 'active')->orderBy('name')->get()
            : User::query()->whereKey($request->user()->id)->where('status', 'active')->get();

        if (! $canViewAll) {
            abort_unless($request->user()->hasRole('enseignant'), 403);
        }

        $teacher = $teachers->firstWhere('id', $request->integer('teacher_id')) ?? $teachers->first();
        $periods = collect($this->templates->ensurePeriods($academicYear))
            ->filter(fn (array $period): bool => $period['is_active'] && ! $period['is_break'])
            ->values();
        $schedule = $teacher
            ? TeacherAvailabilitySchedule::query()
                ->with('availabilities')
                ->where('academic_year_id', $academicYear->id)
                ->where('teacher_id', $teacher->id)
                ->first()
            : null;
        $availabilityBySlot = $schedule?->availabilities
            ->keyBy(fn (TeacherAvailability $availability): string => $availability->timetable_period_id.'|'.$availability->day_of_week)
            ?? collect();
        $assignments = $teacher
            ? ClassSubject::query()
                ->with(['schoolClass', 'subject'])
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->orderBy('school_class_id')
                ->get()
            : collect();
        $conflicts = $teacher
            ? $this->availabilities->conflictsFor($academicYear, $teacher, $schedule)
            : collect();

        return view('timetables.availabilities', [
            'academicYear' => $academicYear,
            'assignments' => $assignments,
            'availabilityBySlot' => $availabilityBySlot,
            'availabilityLabels' => TeacherAvailability::labels(),
            'canEdit' => $teacher && ($canEditAll || ($request->user()->is($teacher) && $schedule?->status !== TeacherAvailabilitySchedule::STATUS_VALIDATED)),
            'canEditAll' => $canEditAll,
            'canViewAll' => $canViewAll,
            'conflicts' => $conflicts,
            'days' => $this->templates->days(),
            'periods' => $periods,
            'schedule' => $schedule,
            'scheduleLabels' => TeacherAvailabilitySchedule::labels(),
            'teacher' => $teacher,
            'teachers' => $teachers,
        ]);
    }

    public function update(
        UpdateTeacherAvailabilityRequest $request,
        User $teacher,
    ): RedirectResponse {
        abort_unless($teacher->hasRole('enseignant'), 404);
        $academicYear = $this->requireActiveAcademicYear();
        $canEditAll = $request->user()->can('timetables.manage');
        $isOwnSchedule = $request->user()->is($teacher) && $request->user()->hasRole('enseignant');

        abort_unless($canEditAll || $isOwnSchedule, 403);

        $schedule = TeacherAvailabilitySchedule::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $teacher->id)
            ->first();

        abort_if(
            ! $canEditAll && $schedule?->status === TeacherAvailabilitySchedule::STATUS_VALIDATED,
            403,
            'Cette fiche a déjà été validée par l’administration.',
        );

        if (! $canEditAll && $request->validated('workflow_status') === TeacherAvailabilitySchedule::STATUS_VALIDATED) {
            throw ValidationException::withMessages([
                'workflow_status' => 'Seule l’administration peut valider définitivement une disponibilité.',
            ]);
        }

        $updated = $this->availabilities->update(
            $academicYear,
            $teacher,
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('timetables.availabilities', ['teacher_id' => $teacher->id])
            ->with('success', 'Disponibilités enregistrées avec le statut « '.TeacherAvailabilitySchedule::labels()[$updated->status].' ».');
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
    }
}
