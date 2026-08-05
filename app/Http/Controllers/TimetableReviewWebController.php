<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Services\AuditTrailService;
use App\Services\TimetableReviewService;
use App\Services\TimetableTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableReviewWebController extends Controller
{
    public function __construct(
        private readonly TimetableReviewService $reviews,
        private readonly TimetableTemplateService $templates,
        private readonly AuditTrailService $auditTrail,
    ) {}

    public function show(Timetable $timetable): View
    {
        $timetable->load(['academicYear', 'schoolClass.level', 'entries', 'publisher']);

        return view('timetables.review', [
            'timetable' => $timetable,
            'days' => $this->templates->days(),
            'grid' => $this->grid($timetable),
            'audit' => $this->reviews->audit($timetable),
        ]);
    }

    public function lock(Request $request, Timetable $timetable, TimetableEntry $entry): RedirectResponse
    {
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        $this->reviews->setLock($timetable, $entry, (bool) $data['locked']);

        return back()->with('success', $data['locked'] ? 'Créneau verrouillé.' : 'Créneau déverrouillé.');
    }

    public function lockAll(Request $request, Timetable $timetable): RedirectResponse
    {
        $data = $request->validate(['locked' => ['required', 'boolean']]);
        $count = $this->reviews->setAllLocks($timetable, (bool) $data['locked']);

        return back()->with('success', $count.' '.($count === 1 ? 'créneau mis à jour.' : 'créneaux mis à jour.'));
    }

    public function publish(Request $request, Timetable $timetable): RedirectResponse
    {
        $old = $timetable->only(['status', 'published_at', 'published_by']);
        $this->reviews->publish($timetable, $request->user());
        $this->auditTrail->record('published', $timetable, $old, $timetable->fresh()->only(['status', 'published_at', 'published_by']));

        return redirect()->route('timetables.review', $timetable)
            ->with('success', 'Emploi du temps publié. Tous les cours sont maintenant verrouillés.');
    }

    public function reopen(Timetable $timetable): RedirectResponse
    {
        $old = $timetable->only(['status', 'published_at', 'published_by']);
        $this->reviews->reopen($timetable);
        $this->auditTrail->record('reopened', $timetable, $old, $timetable->fresh()->only(['status', 'published_at', 'published_by']));

        return redirect()->route('timetables.review', $timetable)
            ->with('success', 'Emploi du temps repassé en brouillon. Déverrouille uniquement les cours à corriger.');
    }

    private function grid(Timetable $timetable): array
    {
        return $timetable->entries
            ->sortBy([['sort_order', 'asc'], ['day_of_week', 'asc']])
            ->groupBy('period_label')
            ->map(function ($entries): array {
                $first = $entries->first();

                return [
                    'period_label' => $first->period_label,
                    'sort_order' => $first->sort_order,
                    'is_break' => $first->is_break,
                    'days' => $entries->keyBy('day_of_week'),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }
}
