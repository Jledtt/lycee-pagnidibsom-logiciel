<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\AcademicYearActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicYearWebController extends Controller
{
    public function __construct(
        private readonly AcademicYearActivationService $activationService,
    ) {}

    public function index(): View
    {
        $years = AcademicYear::query()
            ->with(['terms' => fn ($query) => $query->orderBy('position')])
            ->withCount(['classes', 'enrollments'])
            ->orderByDesc('starts_at')
            ->get();

        return view('academic-years.index', [
            'years' => $years,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('academic_years', 'name')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'create_default_terms' => ['nullable', 'boolean'],
        ]);

        if ($this->datesOverlap($data['starts_at'], $data['ends_at'])) {
            return back()
                ->withErrors(['starts_at' => 'Cette période chevauche une année scolaire existante.'])
                ->withInput();
        }

        DB::transaction(function () use ($data): void {
            $year = AcademicYear::query()->create([
                'name' => $data['name'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'is_active' => false,
                'status' => 'planned',
            ]);

            if (! empty($data['create_default_terms'])) {
                foreach (['Trimestre 1', 'Trimestre 2', 'Trimestre 3'] as $index => $name) {
                    $year->terms()->create([
                        'name' => $name,
                        'type' => 'trimestre',
                        'position' => $index + 1,
                    ]);
                }
            }
        });

        return redirect()
            ->route('academic-years.index')
            ->with('success', 'Année scolaire créée.');
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('academic_years', 'name')->ignore($academicYear->id)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['planned', 'active', 'closed', 'archived'])],
        ]);

        if ($academicYear->is_active && $data['status'] !== 'active') {
            return redirect()
                ->route('academic-years.index')
                ->withErrors(['status' => 'L’année active doit garder le statut actif. Active une autre année avant de la fermer.']);
        }

        if (! $academicYear->is_active && $data['status'] === 'active') {
            return redirect()
                ->route('academic-years.index')
                ->withErrors(['status' => 'Utilise le bouton « Activer » pour rendre cette année active.']);
        }

        if ($this->datesOverlap($data['starts_at'], $data['ends_at'], $academicYear)) {
            return back()
                ->withErrors(['starts_at' => 'Cette période chevauche une autre année scolaire.'])
                ->withInput();
        }

        DB::transaction(function () use ($academicYear, $data): void {
            $lockedYear = AcademicYear::query()
                ->lockForUpdate()
                ->findOrFail($academicYear->id);

            $lockedYear->update($data);
        });

        return redirect()
            ->route('academic-years.index')
            ->with('success', 'Année scolaire mise à jour.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        $this->activationService->activate($academicYear->id);

        return redirect()
            ->route('academic-years.index')
            ->with('success', $academicYear->name.' est maintenant l’année active.');
    }

    private function datesOverlap(string $startsAt, string $endsAt, ?AcademicYear $except = null): bool
    {
        return AcademicYear::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->whereDate('starts_at', '<=', $endsAt)
            ->whereDate('ends_at', '>=', $startsAt)
            ->exists();
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['trimestre', 'semestre', 'autre'])],
            'position' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('terms', 'position')->where('academic_year_id', $request->integer('academic_year_id')),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        Term::create($data);

        return redirect()
            ->route('academic-years.index')
            ->with('success', 'Période ajoutée.');
    }

    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['trimestre', 'semestre', 'autre'])],
            'position' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('terms', 'position')
                    ->where('academic_year_id', $term->academic_year_id)
                    ->ignore($term->id),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_closed' => ['required', 'boolean'],
        ]);

        $term->update($data);

        return redirect()
            ->route('academic-years.index')
            ->with('success', 'Période mise à jour.');
    }
}
