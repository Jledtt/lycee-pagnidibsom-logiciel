@extends('layouts.app', [
    'title' => 'Modifier emploi du temps - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Modifier emploi du temps',
    'pageSubtitle' => $timetable->schoolClass->name . ' - ' . $timetable->academicYear->name,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.index', ['school_class_id' => $timetable->school_class_id]) }}">Retour</a>
    <a class="btn btn-subtle" href="{{ route('timetables.review', $timetable) }}">Reviser</a>
    @can('timetables.print')
        <a class="btn btn-primary" href="{{ route('timetables.pdf', $timetable) }}" data-download-feedback="Téléchargement PDF de l’emploi du temps lancé.">PDF</a>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('timetables.update', $timetable) }}">
        @csrf
        @method('PUT')

        <section class="panel">
            <div class="panel-head">
                <h2>Informations</h2>
                <span class="badge">{{ $timetable->schoolClass->name }}</span>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label>Titre</label>
                    <input name="title" value="{{ old('title', $timetable->title) }}" required>
                </div>
                <div class="field">
                    <label>Statut</label>
                    <select name="status">
                        <option value="draft" @selected(old('status', $timetable->status) === 'draft')>Brouillon</option>
                        <option value="archived" @selected(old('status', $timetable->status) === 'archived')>Archive</option>
                    </select>
                </div>
                <div class="field wide">
                    <label>Professeur principal / équipe pédagogique</label>
                    <textarea name="principal_teacher" rows="2">{{ old('principal_teacher', $timetable->principal_teacher) }}</textarea>
                </div>
                <div class="field wide">
                    <label>Notes internes</label>
                    <textarea name="notes" rows="2">{{ old('notes', $timetable->notes) }}</textarea>
                </div>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Grille hebdomadaire</h2>
                <span class="badge">Double saisie possible: matière + professeur</span>
            </div>

            <datalist id="subject-options">
                @foreach ($subjectOptions as $subject)
                    <option value="{{ $subject }}"></option>
                @endforeach
                <option value="Devoir"></option>
                <option value="EC"></option>
                <option value="HG"></option>
                <option value="PC"></option>
                <option value="Maths"></option>
            </datalist>

            <div class="subject-list-scroll">
                <table class="table timetable-edit-table" style="min-width:1180px">
                    <thead>
                        <tr>
                            <th>Horaire</th>
                            @foreach ($days as $dayLabel)
                                <th>{{ $dayLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php($entryIndex = 0)
                        @foreach ($grid as $row)
                            @if ($row['is_break'])
                                <tr>
                                    <td>
                                        <strong>{{ $row['period_label'] }}</strong>
                                        @foreach (array_keys($days) as $dayKey)
                                            @php($entry = $row['days'][$dayKey] ?? null)
                                            <input type="hidden" name="entries[{{ $entryIndex }}][entry_id]" value="{{ $entry?->id }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][sort_order]" value="{{ $row['sort_order'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][timetable_period_id]" value="{{ $row['id'] ?? '' }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][period_label]" value="{{ $row['period_label'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][starts_at]" value="">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][ends_at]" value="">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][day_of_week]" value="{{ $dayKey }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][class_subject_id]" value="">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][subject_name]" value="{{ $row['period_label'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][teacher_name]" value="">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][room]" value="">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][is_break]" value="1">
                                            @php($entryIndex++)
                                        @endforeach
                                    </td>
                                    <td colspan="{{ count($days) }}" style="text-align:center;font-weight:800;color:#85600f;background:#faf3df">{{ $row['period_label'] }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td>
                                        <strong>{{ $row['period_label'] }}</strong>
                                        <div style="display:grid;gap:6px;margin-top:8px">
                                            <input name="periods[{{ $row['sort_order'] }}][starts_at]" value="{{ $row['starts_at'] }}" disabled>
                                            <input name="periods[{{ $row['sort_order'] }}][ends_at]" value="{{ $row['ends_at'] }}" disabled>
                                        </div>
                                    </td>
                                    @foreach (array_keys($days) as $dayKey)
                                        @php($entry = $row['days'][$dayKey] ?? null)
                                        <td>
                                            <input type="hidden" name="entries[{{ $entryIndex }}][entry_id]" value="{{ $entry?->id }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][sort_order]" value="{{ $row['sort_order'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][timetable_period_id]" value="{{ $row['id'] ?? '' }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][period_label]" value="{{ $row['period_label'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][starts_at]" value="{{ $row['starts_at'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][ends_at]" value="{{ $row['ends_at'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][day_of_week]" value="{{ $dayKey }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][is_break]" value="0">

                                            <div class="timetable-cell-fields {{ $entry?->is_locked ? 'timetable-cell-fields--locked' : '' }}" style="display:grid;gap:6px">
                                                @if ($entry?->is_locked)
                                                    <span class="badge">Verrouillé</span>
                                                @elseif ($entry?->source === 'automatic')
                                                    <span class="badge badge-warning">Automatique</span>
                                                @endif
                                                <select name="entries[{{ $entryIndex }}][class_subject_id]" data-timetable-assignment aria-label="Affectation pédagogique">
                                                    <option value="">Activité ou ancien libellé</option>
                                                    @foreach ($classSubjects as $classSubject)
                                                        <option
                                                            value="{{ $classSubject->id }}"
                                                            data-subject="{{ $classSubject->subject?->name }}"
                                                            data-teacher="{{ $classSubject->teacher?->name }}"
                                                            @selected((int) old('entries.' . $entryIndex . '.class_subject_id', $entry?->class_subject_id) === $classSubject->id)
                                                        >
                                                            {{ $classSubject->subject?->name }}{{ $classSubject->teacher ? ' - ' . $classSubject->teacher->name : ' - professeur non affecté' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="timetable-legacy-fields" style="display:grid;gap:6px">
                                                    <input data-timetable-subject name="entries[{{ $entryIndex }}][subject_name]" list="subject-options" value="{{ old('entries.' . $entryIndex . '.subject_name', $entry?->subject_name) }}" placeholder="Devoir ou activité libre">
                                                    <input data-timetable-teacher name="entries[{{ $entryIndex }}][teacher_name]" value="{{ old('entries.' . $entryIndex . '.teacher_name', $entry?->teacher_name) }}" placeholder="Professeur (ancien planning)">
                                                </div>
                                                <input name="entries[{{ $entryIndex }}][room]" value="{{ old('entries.' . $entryIndex . '.room', $entry?->room) }}" placeholder="Salle">
                                            </div>
                                        </td>
                                        @php($entryIndex++)
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-actions" style="margin-top:16px">
                <button class="btn btn-primary" type="submit">Enregistrer l’emploi du temps</button>
            </div>
        </section>
    </form>
@endsection
