<?php

namespace App\Http\Controllers;

use App\Models\AcademicTrack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicTrackWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AcademicTrack::class);

        $tracks = AcademicTrack::query()
            ->withCount('schoolClasses')
            ->when($request->string('search')->trim()->toString(), function (Builder $query, string $search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->string('kind')->toString(), fn (Builder $query, string $kind) => $query->where('kind', $kind))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByRaw("status = 'inactive'")
            ->orderBy('kind')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('academic-tracks.index', [
            'academicYear' => null,
            'filters' => $request->only(['search', 'kind', 'status']),
            'tracks' => $tracks,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AcademicTrack::class);

        return view('academic-tracks.create', [
            'academicYear' => null,
            'academicTrack' => new AcademicTrack(['kind' => 'serie', 'status' => 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AcademicTrack::class);
        $academicTrack = AcademicTrack::query()->create($this->validated($request));

        return redirect()
            ->route('academic-tracks.edit', $academicTrack)
            ->with('success', 'Série ou filière créée. Elle peut maintenant être affectée aux classes.');
    }

    public function edit(AcademicTrack $academicTrack): View
    {
        $this->authorize('update', $academicTrack);
        $academicTrack->load(['schoolClasses' => fn ($query) => $query->with(['academicYear', 'level'])->orderBy('name')]);

        return view('academic-tracks.edit', [
            'academicYear' => null,
            'academicTrack' => $academicTrack,
        ]);
    }

    public function update(Request $request, AcademicTrack $academicTrack): RedirectResponse
    {
        $this->authorize('update', $academicTrack);
        $academicTrack->update($this->validated($request, $academicTrack));

        return redirect()
            ->route('academic-tracks.edit', $academicTrack)
            ->with('success', 'Série ou filière mise à jour.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AcademicTrack $academicTrack = null): array
    {
        $request->merge([
            'name' => Str::of((string) $request->input('name'))->squish()->toString(),
            'code' => Str::upper(Str::of((string) $request->input('code'))->squish()->toString()),
        ]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:190',
                Rule::unique('academic_tracks', 'name')
                    ->where('kind', $request->string('kind')->toString())
                    ->ignore($academicTrack?->id),
            ],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('academic_tracks', 'code')->ignore($academicTrack?->id)],
            'kind' => ['required', Rule::in(['serie', 'filiere'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
