@extends('layouts.app', [
    'title' => 'Notes - Lycee Prive Pagnidibsom',
    'active' => 'grades',
    'pageTitle' => 'Notes',
    'pageSubtitle' => 'Creation des evaluations et saisie des notes',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('subjects.index', ['school_class_id' => $selectedClass?->id]) }}">Matieres</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Selection</h2>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        @if ($classes->isEmpty() || $terms->isEmpty())
            <div class="empty">Il faut au moins une classe active et un trimestre pour saisir les notes.</div>
        @else
            <form class="searchbar" method="GET" action="{{ route('grades.index') }}">
                <select name="school_class_id">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>
                            {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                        </option>
                    @endforeach
                </select>

                <select name="term_id">
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected($selectedTerm?->id === $term->id)>{{ $term->name }}</option>
                    @endforeach
                </select>

                <button class="btn btn-subtle" type="submit">Afficher</button>
            </form>
        @endif
    </section>

    @if ($selectedClass && $selectedTerm)
        <section class="grid stats" style="margin-top:16px">
            <div class="stat">
                <span>Classe</span>
                <strong>{{ $selectedClass->name }}</strong>
            </div>
            <div class="stat">
                <span>Trimestre</span>
                <strong>{{ $selectedTerm->name }}</strong>
            </div>
            <div class="stat">
                <span>Eleves</span>
                <strong>{{ $students->count() }}</strong>
            </div>
            <div class="stat">
                <span>Matieres actives</span>
                <strong>{{ $classSubjects->count() }}</strong>
            </div>
            <div class="stat">
                <span>Evaluations</span>
                <strong>{{ $assessments->count() }}</strong>
            </div>
        </section>

        <section class="grid two-col">
            <div class="panel">
                <div class="panel-head">
                    <h2>Nouvelle evaluation</h2>
                </div>

                @if ($classSubjects->isEmpty())
                    <div class="empty">Aucune matiere active pour cette classe. Ajoute d'abord les matieres et coefficients.</div>
                @else
                    <form class="form-grid" method="POST" action="{{ route('grades.assessments.store') }}">
                        @csrf
                        <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                        <input type="hidden" name="term_id" value="{{ $selectedTerm->id }}">

                        <div class="field">
                            <label>Matiere</label>
                            <select name="subject_id" required>
                                @foreach ($classSubjects as $classSubject)
                                    <option value="{{ $classSubject->subject_id }}">{{ $classSubject->subject->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label>Type</label>
                            <select name="assessment_type_id" required>
                                @foreach ($assessmentTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }} x{{ number_format($type->weight, 0, ',', ' ') }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field wide">
                            <label>Titre</label>
                            <input name="title" placeholder="Ex: Devoir 1" required>
                        </div>

                        <div class="field">
                            <label>Note sur</label>
                            <input type="number" name="max_score" min="1" max="100" step="0.25" value="20" required>
                        </div>

                        <div class="field">
                            <label>Date</label>
                            <input type="date" name="assessment_date" value="{{ now()->toDateString() }}">
                        </div>

                        <div class="form-actions wide">
                            <button class="btn btn-primary" type="submit">Creer l'evaluation</button>
                        </div>
                    </form>
                @endif

                <div class="panel" style="margin-top:16px">
                    <div class="panel-head">
                        <h2>Evaluations</h2>
                        <span class="badge">{{ $assessments->count() }} ligne(s)</span>
                    </div>

                    @if ($assessments->isEmpty())
                        <div class="empty">Aucune evaluation pour cette classe et ce trimestre.</div>
                    @else
                        <div class="subject-list-scroll">
                            <div class="subject-list-inner ledger-list">
                                @foreach ($assessments as $assessment)
                                    <div class="ledger-item">
                                        <div class="ledger-summary" style="grid-template-columns:minmax(220px,1.3fr) minmax(120px,.6fr) minmax(130px,.6fr) minmax(260px,1fr)">
                                            <div class="ledger-person">
                                                <strong>{{ $assessment->title }}</strong>
                                                <span>{{ $assessment->subject->name }} - {{ $assessment->assessmentType->name }}</span>
                                                <span class="badge {{ $assessment->is_locked ? 'badge-warning' : '' }}" style="margin-top:6px">
                                                    {{ $assessment->is_locked ? 'Verrouillee' : 'Ouverte' }}
                                                </span>
                                            </div>
                                            <div class="ledger-metric">
                                                <strong>{{ number_format($assessment->max_score, 0, ',', ' ') }}</strong>
                                                <span>Note sur</span>
                                            </div>
                                            <div class="ledger-metric">
                                                <strong>{{ $assessment->grades_count }}</strong>
                                                <span>Notes</span>
                                            </div>
                                            <div class="page-actions" style="justify-content:flex-end">
                                                <a class="btn btn-subtle" href="{{ route('grades.index', ['school_class_id' => $selectedClass->id, 'term_id' => $selectedTerm->id, 'assessment_id' => $assessment->id]) }}">Saisir</a>
                                                @can('grades.update')
                                                    <a class="btn btn-subtle" href="{{ route('grades.import', $assessment) }}">Importer</a>
                                                @endcan
                                                <a class="btn btn-subtle" href="{{ route('grades.assessments.export', $assessment) }}" data-download-feedback="Telechargement Excel des notes lance. Regarde l'icone de telechargement du navigateur.">Excel</a>
                                                <a class="btn btn-primary" href="{{ route('grades.assessments.pdf', $assessment) }}">PDF</a>
                                                @if ($assessment->is_locked)
                                                    @can('grades.unlock')
                                                        <button class="btn btn-subtle" type="submit" form="unlock-assessment-{{ $assessment->id }}">Deverrouiller</button>
                                                    @endcan
                                                @else
                                                    @can('grades.lock')
                                                        <button class="btn btn-subtle" type="submit" form="lock-assessment-{{ $assessment->id }}">Verrouiller</button>
                                                    @endcan
                                                    <button class="btn btn-danger" type="submit" form="delete-assessment-{{ $assessment->id }}">Supprimer</button>
                                                @endif
                                            </div>
                                        </div>
                                        <form id="lock-assessment-{{ $assessment->id }}" method="POST" action="{{ route('grades.assessments.lock', $assessment) }}">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                        <form id="unlock-assessment-{{ $assessment->id }}" method="POST" action="{{ route('grades.assessments.unlock', $assessment) }}">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                        <form id="delete-assessment-{{ $assessment->id }}" method="POST" action="{{ route('grades.assessments.destroy', $assessment) }}">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Saisie des notes</h2>
                    @if ($selectedAssessment)
                        <span class="badge">{{ $selectedAssessment->subject->name }}</span>
                    @endif
                </div>

                @if (! $selectedAssessment)
                    <div class="empty">Choisis ou cree une evaluation pour saisir les notes.</div>
                @elseif ($students->isEmpty())
                    <div class="empty">Aucun eleve actif dans cette classe.</div>
                @else
                    @if ($selectedTerm->is_closed || $selectedAssessment->is_locked)
                        <p class="notice">
                            @if ($selectedAssessment->is_locked)
                                Cette evaluation est verrouillee. Les notes sont en lecture seule.
                            @else
                                Ce trimestre est marque ferme, mais les notes restent modifiables.
                            @endif
                        </p>
                    @endif

                    <form method="POST" action="{{ route('grades.assessments.grades.update', $selectedAssessment) }}">
                        @csrf
                        @method('PUT')

                        <div class="subject-list-scroll">
                            <table class="table" style="min-width:720px">
                                <thead>
                                    <tr>
                                        <th>Eleve</th>
                                        <th>Note / {{ number_format($selectedAssessment->max_score, 0, ',', ' ') }}</th>
                                        <th>Absent</th>
                                        <th>Commentaire</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        @php($grade = $gradesByStudent->get($student->id))
                                        <tr>
                                            <td>
                                                <strong>{{ $student->full_name }}</strong><br>
                                                <span class="badge">{{ $student->matricule }}</span>
                                            </td>
                                            <td>
                                                <input type="number" name="grades[{{ $student->id }}][score]" min="0" max="{{ $selectedAssessment->max_score }}" step="0.25" value="{{ old('grades.' . $student->id . '.score', $grade?->score) }}" @disabled($selectedAssessment->is_locked)>
                                            </td>
                                            <td>
                                                <label class="check">
                                                    <input type="checkbox" name="grades[{{ $student->id }}][is_absent]" value="1" @checked(old('grades.' . $student->id . '.is_absent', $grade?->is_absent)) @disabled($selectedAssessment->is_locked)>
                                                    Oui
                                                </label>
                                            </td>
                                            <td>
                                                <input name="grades[{{ $student->id }}][comment]" value="{{ old('grades.' . $student->id . '.comment', $grade?->comment) }}" placeholder="Facultatif" @disabled($selectedAssessment->is_locked)>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @unless ($selectedAssessment->is_locked)
                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit">Enregistrer les notes</button>
                            </div>
                        @endunless
                    </form>
                @endif
            </div>
        </section>
    @endif
@endsection
