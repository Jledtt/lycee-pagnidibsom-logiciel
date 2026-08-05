@extends('layouts.app', [
    'title' => 'Disponibilités des professeurs - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Disponibilités des professeurs',
    'pageSubtitle' => 'Créneaux utilisables pour préparer les emplois du temps de ' . $academicYear->name,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.index') }}">Retour aux emplois du temps</a>
    @can('timetables.manage')
        <a class="btn btn-subtle" href="{{ route('timetables.periods') }}">Configurer les créneaux</a>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @if (! $teacher)
        <section class="panel">
            <div class="empty">Aucun professeur actif n’est disponible pour cette année scolaire.</div>
        </section>
    @else
        @if ($canViewAll)
            <section class="panel availability-teacher-picker">
                <form class="searchbar" method="GET" action="{{ route('timetables.availabilities') }}">
                    <div class="field" style="margin:0;min-width:min(360px,100%)">
                        <label for="teacher_id">Professeur</label>
                        <select id="teacher_id" name="teacher_id">
                            @foreach ($teachers as $teacherOption)
                                <option value="{{ $teacherOption->id }}" @selected($teacherOption->is($teacher))>{{ $teacherOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-subtle" type="submit">Afficher</button>
                </form>
            </section>
        @endif

        <section class="availability-overview" aria-labelledby="availability-teacher-name">
            <div class="availability-overview__identity">
                <span class="availability-overview__eyebrow">Fiche hebdomadaire</span>
                <h2 id="availability-teacher-name">{{ $teacher->name }}</h2>
                <p>{{ $assignments->count() }} {{ $assignments->count() === 1 ? 'affectation pédagogique' : 'affectations pédagogiques' }} pour {{ $academicYear->name }}</p>
            </div>
            <div class="availability-overview__status">
                <span>État de la fiche</span>
                <strong class="availability-workflow availability-workflow--{{ $schedule?->status ?? 'draft' }}">
                    {{ $scheduleLabels[$schedule?->status ?? 'draft'] }}
                </strong>
                @if ($schedule?->updated_at)
                    <small>Modifiée le {{ $schedule->updated_at->format('d/m/Y à H:i') }}</small>
                @else
                    <small>Pas encore enregistrée</small>
                @endif
            </div>
        </section>

        @if ($assignments->isNotEmpty())
            <section class="availability-assignments" aria-label="Affectations pédagogiques">
                @foreach ($assignments as $assignment)
                    <span>{{ $assignment->schoolClass?->name }} · {{ $assignment->subject?->name }}{{ $assignment->weekly_hours ? ' · ' . number_format((float) $assignment->weekly_hours, 2, ',', ' ') . ' h/sem.' : '' }}</span>
                @endforeach
            </section>
        @endif

        @if ($conflicts->isNotEmpty())
            <div class="error" style="margin-top:16px">
                {{ $conflicts->count() === 1 ? 'Un cours déjà placé ne correspond' : $conflicts->count().' cours déjà placés ne correspondent' }} pas à cette disponibilité. La fiche doit rester en brouillon jusqu’à {{ $conflicts->count() === 1 ? 'sa correction' : 'leur correction' }}.
            </div>
        @endif

        <form method="POST" action="{{ route('timetables.availabilities.update', $teacher) }}" data-availability-form>
            @csrf
            @method('PUT')

            <section class="panel availability-panel" style="margin-top:16px">
                <div class="availability-toolbar">
                    <div>
                        <h2>Semaine habituelle</h2>
                        <p class="muted">Clique sur une case pour passer de « Indisponible » à « Disponible », puis « Préféré ».</p>
                    </div>
                    @if ($canEdit)
                        <div class="availability-bulk-actions" aria-label="Actions rapides">
                            <button class="btn btn-subtle" type="button" data-availability-all="available">Tout disponible</button>
                            <button class="btn btn-subtle" type="button" data-availability-all="unavailable">Tout indisponible</button>
                        </div>
                    @endif
                </div>

                <div class="availability-summary" aria-live="polite">
                    <div><span class="availability-dot availability-dot--available"></span><strong data-availability-count="available">0</strong><span>disponibles</span></div>
                    <div><span class="availability-dot availability-dot--preferred"></span><strong data-availability-count="preferred">0</strong><span>préférés</span></div>
                    <div><span class="availability-dot availability-dot--unavailable"></span><strong data-availability-count="unavailable">0</strong><span>indisponibles</span></div>
                </div>

                <div class="subject-list-scroll availability-grid-scroll">
                    <table class="table availability-grid">
                        <thead>
                            <tr>
                                <th>Horaire</th>
                                @foreach ($days as $dayKey => $dayLabel)
                                    <th>
                                        <span>{{ $dayLabel }}</span>
                                        @if ($canEdit)
                                            <button type="button" data-availability-day="{{ $dayKey }}" title="Rendre tout le {{ strtolower($dayLabel) }} disponible">Tout dispo</button>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $period)
                                <tr>
                                    <th scope="row">
                                        <strong>{{ $period['label'] }}</strong>
                                        @if ($period['starts_at'] && $period['ends_at'])
                                            <small>{{ $period['starts_at'] }}–{{ $period['ends_at'] }}</small>
                                        @endif
                                    </th>
                                    @foreach ($days as $dayKey => $dayLabel)
                                        @php
                                            $slotKey = $period['id'] . '|' . $dayKey;
                                            $slotStatus = old(
                                                'slots.' . $period['id'] . '.' . $dayKey,
                                                $availabilityBySlot->get($slotKey)?->status ?? 'unavailable'
                                            );
                                        @endphp
                                        <td>
                                            <input
                                                type="hidden"
                                                name="slots[{{ $period['id'] }}][{{ $dayKey }}]"
                                                value="{{ $slotStatus }}"
                                                data-availability-input
                                                data-day="{{ $dayKey }}"
                                            >
                                            <button
                                                class="availability-slot availability-slot--{{ $slotStatus }}"
                                                type="button"
                                                data-availability-toggle
                                                data-status="{{ $slotStatus }}"
                                                aria-label="{{ $dayLabel }}, {{ $period['label'] }} : {{ $availabilityLabels[$slotStatus] }}"
                                                @disabled(! $canEdit)
                                            >
                                                <span class="availability-dot availability-dot--{{ $slotStatus }}"></span>
                                                <span data-availability-label>{{ $availabilityLabels[$slotStatus] }}</span>
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="availability-legend">
                    <span><i class="availability-dot availability-dot--unavailable"></i>Ne pas programmer</span>
                    <span><i class="availability-dot availability-dot--available"></i>Peut être programmé</span>
                    <span><i class="availability-dot availability-dot--preferred"></i>À privilégier</span>
                </div>
            </section>

            <section class="panel" style="margin-top:16px">
                <div class="field">
                    <label for="availability_notes">Précisions pour l’administration</label>
                    <textarea id="availability_notes" name="notes" rows="3" @disabled(! $canEdit) placeholder="Ex. : indisponible un samedi sur deux, contrainte de transport…">{{ old('notes', $schedule?->notes) }}</textarea>
                </div>

                @if ($canEdit)
                    <div class="availability-submit-actions">
                        <button class="btn btn-subtle" type="submit" name="workflow_status" value="draft">Enregistrer en brouillon</button>
                        <button class="btn btn-primary" type="submit" name="workflow_status" value="submitted">Enregistrer et transmettre</button>
                        @if ($canEditAll)
                            <button class="btn btn-primary" type="submit" name="workflow_status" value="validated">Valider pour la planification</button>
                        @endif
                    </div>
                @else
                    <div class="notice">Cette fiche est en lecture seule. Une fiche validée doit être rouverte par l’administration pour être modifiée.</div>
                @endif
            </section>
        </form>

    @endif
@endsection
