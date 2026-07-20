@extends('layouts.app', [
    'title' => 'Examens blancs - Lycee Prive Pagnidibsom',
    'active' => 'mock-exams',
    'pageTitle' => 'Examens blancs',
    'pageSubtitle' => 'Simulation BEPC blanc aujourd hui, BAC blanc quand la Terminale ouvrira',
])

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Nouvelle session</h2>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        <form class="form-grid" method="POST" action="{{ route('mock-exams.store') }}">
            @csrf

            <div class="field">
                <label>Nom</label>
                <input name="name" value="{{ old('name', 'BEPC Blanc N 1') }}" required>
            </div>

            <div class="field">
                <label>Type</label>
                <select name="exam_type" required>
                    <option value="bepc_blanc" @selected(old('exam_type') === 'bepc_blanc')>BEPC blanc</option>
                    <option value="bac_blanc" @selected(old('exam_type') === 'bac_blanc')>BAC blanc</option>
                </select>
            </div>

            <div class="field">
                <label>Date debut</label>
                <input type="date" name="starts_on" value="{{ old('starts_on') }}">
            </div>

            <div class="field">
                <label>Date fin</label>
                <input type="date" name="ends_on" value="{{ old('ends_on') }}">
            </div>

            <div class="field wide">
                <label>Classes concernees</label>
                <div class="subject-list-scroll">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;min-width:620px">
                        @foreach ($classes as $class)
                            <label class="detail-item check" style="margin:0;align-items:center">
                                <input type="checkbox" name="school_class_ids[]" value="{{ $class->id }}" @checked(in_array($class->id, old('school_class_ids', $suggestedClassIds), true))>
                                <span style="margin:0;text-transform:none;font-size:14px">{{ $class->name }}{{ $class->level ? ' - '.$class->level->name : '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <p style="margin:8px 0 0;color:var(--muted)">Conseil : selectionne les 3e pour le BEPC blanc. Plus tard, selectionne les Terminales pour le BAC blanc.</p>
            </div>

            <div class="field wide">
                <label>Notes internes</label>
                <textarea name="notes" placeholder="Ex: simulation interne, ne compte pas dans la moyenne trimestrielle">{{ old('notes') }}</textarea>
            </div>

            <div class="form-actions wide">
                <button class="btn btn-primary" type="submit">Creer la session</button>
            </div>
        </form>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Sessions creees</h2>
                <span class="badge">{{ $exams->count() }} session(s)</span>
            </div>

            @if ($exams->isEmpty())
                <div class="empty">Aucune session d'examen blanc pour le moment.</div>
            @else
                <div class="ledger-list">
                    @foreach ($exams as $exam)
                        <a class="ledger-item" href="{{ route('mock-exams.index', ['mock_exam_id' => $exam->id]) }}">
                            <div class="ledger-summary" style="grid-template-columns:minmax(220px,1.4fr) minmax(140px,.7fr) minmax(140px,.7fr) minmax(130px,.7fr)">
                                <div class="ledger-person">
                                    <strong>{{ $exam->name }}</strong>
                                    <span>{{ $exam->exam_type_label }} - {{ $exam->status_label }}</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong>{{ $exam->candidates_count }}</strong>
                                    <span>Candidats</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong>{{ $exam->subjects_count }}</strong>
                                    <span>Matieres</span>
                                </div>
                                <div class="ledger-metric">
                                    <strong>{{ $exam->classes->pluck('name')->join(', ') ?: '-' }}</strong>
                                    <span>Classes</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>{{ $selectedExam ? $selectedExam->name : 'Session selectionnee' }}</h2>
                @if ($selectedExam)
                    <span class="badge">{{ $selectedExam->exam_type_label }}</span>
                @endif
            </div>

            @if (! $selectedExam)
                <div class="empty">Cree ou selectionne une session.</div>
            @else
                <div class="summary-row">
                    <div class="stat">
                        <span>Candidats</span>
                        <strong>{{ $selectedExam->candidates->count() }}</strong>
                    </div>
                    <div class="stat">
                        <span>Matieres</span>
                        <strong>{{ $selectedExam->subjects->count() }}</strong>
                    </div>
                    <div class="stat">
                        <span>Classes</span>
                        <strong>{{ $selectedExam->classes->count() }}</strong>
                    </div>
                </div>

                <div class="page-actions" style="margin-top:16px">
                    <form method="POST" action="{{ route('mock-exams.candidates.sync', $selectedExam) }}">
                        @csrf
                        <button class="btn btn-subtle" type="submit">Synchroniser candidats</button>
                    </form>

                    <form class="inline-form" method="POST" action="{{ route('mock-exams.anonymity.generate', $selectedExam) }}">
                        @csrf
                        <div class="field" style="min-width:90px">
                            <label>Prefixe</label>
                            <input name="prefix" value="X">
                        </div>
                        <button class="btn btn-subtle" type="submit">Generer anonymats</button>
                    </form>

                    <form class="inline-form" method="POST" action="{{ route('mock-exams.rooms.distribute', $selectedExam) }}">
                        @csrf
                        <div class="field" style="min-width:90px">
                            <label>Salles</label>
                            <input type="number" name="room_count" min="1" max="30" value="2">
                        </div>
                        <button class="btn btn-subtle" type="submit">Repartir</button>
                    </form>
                </div>

                <div class="page-actions" style="margin-top:16px">
                    <a class="btn btn-primary" href="{{ route('mock-exams.candidates.pdf', $selectedExam) }}" data-download-feedback="Telechargement de la liste des candidats lance.">PDF candidats</a>
                    <a class="btn btn-subtle" href="{{ route('mock-exams.rooms.pdf', $selectedExam) }}" data-download-feedback="Telechargement de la repartition par salle lance.">PDF salles</a>
                    <a class="btn btn-subtle" href="{{ route('mock-exams.anonymity.pdf', $selectedExam) }}" data-download-feedback="Telechargement de la liste des anonymats lance.">PDF anonymats</a>
                </div>

                @if ($selectedExam->notes)
                    <p class="notice" style="margin-top:16px">{{ $selectedExam->notes }}</p>
                @endif
            @endif
        </div>
    </section>

    @if ($selectedExam)
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Candidats</h2>
                <span class="badge">{{ $selectedExam->candidates->count() }} ligne(s)</span>
            </div>

            <div class="subject-list-scroll">
                <table class="table" style="min-width:900px">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Anonymat</th>
                            <th>Matricule</th>
                            <th>Eleve</th>
                            <th>Classe</th>
                            <th>Salle</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedExam->candidates->sortBy([['schoolClass.name', 'asc'], ['student.last_name', 'asc']]) as $candidate)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><span class="badge">{{ $candidate->anonymous_code ?: '-' }}</span></td>
                                <td>{{ $candidate->student?->matricule }}</td>
                                <td><strong>{{ $candidate->student?->full_name }}</strong></td>
                                <td>{{ $candidate->schoolClass?->name }}</td>
                                <td>{{ $candidate->room_name ?: '-' }}</td>
                                <td><span class="badge">{{ $candidate->status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">Aucun candidat. Clique sur synchroniser candidats.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Matieres de la session</h2>
                <span class="badge">{{ $selectedExam->subjects->count() }} matiere(s)</span>
            </div>

            <div class="subject-list-scroll">
                <table class="table" style="min-width:760px">
                    <thead>
                        <tr>
                            <th>Ordre</th>
                            <th>Matiere</th>
                            <th>Partie</th>
                            <th>Note sur</th>
                            <th>Coefficient</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedExam->subjects->sortBy('position') as $subject)
                            <tr>
                                <td>{{ $subject->position }}</td>
                                <td><strong>{{ $subject->subject?->name }}</strong></td>
                                <td>{{ $subject->exam_part_label }}</td>
                                <td>{{ number_format($subject->max_score, 0, ',', ' ') }}</td>
                                <td>{{ number_format($subject->coefficient, 2, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">Aucune matiere. Les matieres actives des classes seront reprises automatiquement a la creation.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
