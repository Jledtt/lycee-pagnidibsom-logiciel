@extends('layouts.app', [
    'title' => 'Saisie notes examen - Lycee Prive Pagnidibsom',
    'active' => 'mock-exams',
    'pageTitle' => 'Saisie des notes',
    'pageSubtitle' => $exam->name . ' - ' . ($subject->subject?->name ?? 'Matiere'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('mock-exams.index', ['mock_exam_id' => $exam->id]) }}">Retour examens</a>
    @can('mock_exams.print')
        <a class="btn btn-primary" href="{{ route('mock-exams.subjects.scores.pdf', [$exam, $subject]) }}" data-download-feedback="Telechargement de la feuille de notes lance.">PDF saisie</a>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="summary-row">
        <div class="stat">
            <span>Session</span>
            <strong>{{ $exam->name }}</strong>
        </div>
        <div class="stat">
            <span>Matiere</span>
            <strong>{{ $subject->subject?->name }}</strong>
        </div>
        <div class="stat">
            <span>Note sur</span>
            <strong>{{ number_format($subject->max_score, 0, ',', ' ') }}</strong>
        </div>
        <div class="stat">
            <span>Coefficient</span>
            <strong>{{ number_format($subject->coefficient, 2, ',', ' ') }}</strong>
        </div>
    </section>

    @if ($exam->is_locked)
        <p class="notice" style="margin-top:16px">Session verrouillee : seul l'administrateur peut encore corriger les notes.</p>
    @endif

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Notes par anonymat / PV</h2>
            <span class="badge">{{ $candidates->count() }} candidat(s)</span>
        </div>

        <form method="POST" action="{{ route('mock-exams.subjects.scores.update', [$exam, $subject]) }}">
            @csrf
            @method('PUT')

            <div class="subject-list-scroll">
                <table class="table" style="min-width:960px">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Anonymat</th>
                            <th>PV / Matricule</th>
                            <th>Eleve</th>
                            <th>Classe</th>
                            <th>Note</th>
                            <th>Absent</th>
                            <th>Observation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($candidates as $candidate)
                            @php($score = $scores->get($candidate->id))
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><span class="badge">{{ $candidate->anonymous_code ?: '-' }}</span></td>
                                <td>{{ $candidate->student?->matricule }}</td>
                                <td><strong>{{ $candidate->student?->full_name }}</strong></td>
                                <td>{{ $candidate->schoolClass?->name }}</td>
                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        max="{{ (float) $subject->max_score }}"
                                        step="0.01"
                                        name="scores[{{ $candidate->id }}][score]"
                                        value="{{ old('scores.'.$candidate->id.'.score', $score?->score) }}"
                                        style="width:110px"
                                        @disabled(! $canEditExam)
                                    >
                                </td>
                                <td>
                                    <input type="hidden" name="scores[{{ $candidate->id }}][is_absent]" value="0">
                                    <input type="checkbox" name="scores[{{ $candidate->id }}][is_absent]" value="1" @checked(old('scores.'.$candidate->id.'.is_absent', $score?->is_absent) == 1) @disabled(! $canEditExam)>
                                </td>
                                <td>
                                    <input
                                        name="scores[{{ $candidate->id }}][observation]"
                                        value="{{ old('scores.'.$candidate->id.'.observation', $score?->observation) }}"
                                        placeholder="Observation"
                                        @disabled(! $canEditExam)
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Aucun candidat. Synchronise d'abord les candidats de la session.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('mock_exams.manage')
                <div class="form-actions" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit" @disabled(! $canEditExam)>Enregistrer les notes</button>
                </div>
            @endcan
        </form>
    </section>
@endsection
