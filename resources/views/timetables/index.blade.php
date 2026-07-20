@extends('layouts.app', [
    'title' => 'Emplois du temps - Lycee Prive Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Emplois du temps',
    'pageSubtitle' => 'Creation, modification et impression par classe',
])

@section('page_actions')
    @if ($schoolClass)
        @can('timetables.print')
            <a class="btn btn-subtle" href="{{ route('timetables.pdf', ['school_class_id' => $schoolClass->id]) }}">PDF</a>
        @endcan
    @endif
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Classe</h2>
            @if ($schoolClass)
                <span class="badge">{{ $schoolClass->name }}</span>
            @endif
        </div>

        @if ($classes->isEmpty())
            <div class="empty">Aucune classe active pour l'annee scolaire.</div>
        @else
            <form class="searchbar" method="GET" action="{{ route('timetables.index') }}">
                <select name="school_class_id">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($schoolClass?->id === $class->id)>
                            {{ $class->name }}{{ $class->level ? ' - '.$class->level->name : '' }}
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-subtle" type="submit">Afficher</button>
            </form>
        @endif
    </section>

    @if ($schoolClass)
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Creneaux</span>
                <strong>{{ $entries->count() }}</strong>
            </div>
            <div class="stat">
                <span>Jours</span>
                <strong>{{ $entries->pluck('day_of_week')->unique()->count() }}</strong>
            </div>
            <div class="stat">
                <span>Annee scolaire</span>
                <strong>{{ $academicYear?->name ?? '-' }}</strong>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <div>
                    <h2>Grille hebdomadaire</h2>
                    <p style="margin:4px 0 0;color:var(--muted)">Les cellules vides correspondent aux heures non programmees.</p>
                </div>
                @can('timetables.manage')
                    @if ($templateAvailable)
                        <form method="POST" action="{{ route('timetables.defaults') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $schoolClass->id }}">
                            <button class="btn btn-subtle" type="submit">Appliquer l'exemple Word</button>
                        </form>
                    @endif
                @endcan
            </div>

            @if ($entries->isEmpty())
                <div class="empty">Aucun creneau saisi pour cette classe.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:1080px">
                        <thead>
                            <tr>
                                <th>Horaire</th>
                                @foreach ($dayLabels as $day)
                                    <th>{{ $day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid as $row)
                                <tr>
                                    <td><strong>{{ $row['slot']['label'] }}</strong></td>
                                    @foreach ($dayLabels as $dayNumber => $day)
                                        @php($entry = $row['days'][$dayNumber] ?? null)
                                        <td>
                                            @if ($entry)
                                                <strong>{{ $entry->subject_label }}</strong><br>
                                                <span style="color:var(--muted)">{{ $entry->teacher_name ?: '-' }}</span>
                                            @else
                                                <span style="color:var(--muted)">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @can('timetables.manage')
            <section class="panel" style="margin-top:16px">
                <div class="panel-head">
                    <h2>Ajouter un creneau</h2>
                </div>

                <form class="form-grid" method="POST" action="{{ route('timetables.store') }}">
                    @csrf
                    <input type="hidden" name="school_class_id" value="{{ $schoolClass->id }}">

                    <div class="field">
                        <label>Jour</label>
                        <select name="day_of_week" required>
                            @foreach ($dayLabels as $dayNumber => $day)
                                <option value="{{ $dayNumber }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Debut</label>
                        <input type="time" name="starts_at" value="07:00" required>
                    </div>
                    <div class="field">
                        <label>Fin</label>
                        <input type="time" name="ends_at" value="07:55" required>
                    </div>
                    <div class="field">
                        <label>Matiere / activite</label>
                        <input name="subject_label" list="subject-options" placeholder="Ex: Francais" required>
                    </div>
                    <div class="field">
                        <label>Professeur</label>
                        <input name="teacher_name" placeholder="Nom du professeur">
                    </div>
                    <div class="field">
                        <label>Salle</label>
                        <input name="room" placeholder="Optionnel">
                    </div>
                    <div class="field wide">
                        <label>Observation</label>
                        <input name="notes" placeholder="Optionnel">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Ajouter</button>
                    </div>
                </form>

                <datalist id="subject-options">
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->name }}"></option>
                    @endforeach
                    <option value="Devoir"></option>
                    <option value="Recreation"></option>
                    <option value="Soir"></option>
                </datalist>
            </section>
        @endcan

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Creneaux detailles</h2>
                <span class="badge">{{ $entries->count() }} ligne(s)</span>
            </div>

            @if ($entries->isEmpty())
                <div class="empty">Aucun creneau a modifier.</div>
            @else
                <div class="ledger-list">
                    @foreach ($entries->sortBy(['day_of_week', 'starts_at']) as $entry)
                        <details class="ledger-item">
                            <summary class="ledger-summary" style="grid-template-columns:minmax(120px,.7fr) minmax(130px,.7fr) minmax(190px,1fr) minmax(170px,1fr)">
                                <div class="ledger-person">
                                    <strong>{{ $entry->day_label }}</strong>
                                    <span>{{ $entry->time_label }}</span>
                                </div>
                                <span class="badge">{{ $entry->subject_label }}</span>
                                <span>{{ $entry->teacher_name ?: '-' }}</span>
                                <span>{{ $entry->room ?: '-' }}</span>
                            </summary>
                            <div class="ledger-detail">
                                @can('timetables.manage')
                                    <form class="form-grid" method="POST" action="{{ route('timetables.update', $entry) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="school_class_id" value="{{ $schoolClass->id }}">

                                        <div class="field">
                                            <label>Jour</label>
                                            <select name="day_of_week" required>
                                                @foreach ($dayLabels as $dayNumber => $day)
                                                    <option value="{{ $dayNumber }}" @selected((int) $entry->day_of_week === (int) $dayNumber)>{{ $day }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Debut</label>
                                            <input type="time" name="starts_at" value="{{ substr((string) $entry->starts_at, 0, 5) }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Fin</label>
                                            <input type="time" name="ends_at" value="{{ substr((string) $entry->ends_at, 0, 5) }}" required>
                                        </div>
                                        <div class="field">
                                            <label>Matiere / activite</label>
                                            <input name="subject_label" value="{{ $entry->subject_label }}" list="subject-options" required>
                                        </div>
                                        <div class="field">
                                            <label>Professeur</label>
                                            <input name="teacher_name" value="{{ $entry->teacher_name }}">
                                        </div>
                                        <div class="field">
                                            <label>Salle</label>
                                            <input name="room" value="{{ $entry->room }}">
                                        </div>
                                        <div class="field wide">
                                            <label>Observation</label>
                                            <input name="notes" value="{{ $entry->notes }}">
                                        </div>
                                        <div class="form-actions">
                                            <button class="btn btn-primary" type="submit">Modifier</button>
                                            <button class="btn btn-danger" type="submit" form="delete-timetable-{{ $entry->id }}">Supprimer</button>
                                        </div>
                                    </form>
                                    <form id="delete-timetable-{{ $entry->id }}" method="POST" action="{{ route('timetables.destroy', $entry) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @else
                                    <div class="empty">Ton role permet de consulter cet emploi du temps, pas de le modifier.</div>
                                @endcan
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
@endsection
