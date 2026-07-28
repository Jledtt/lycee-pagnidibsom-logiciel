@extends('layouts.app', [
    'title' => 'Import notes - Lycée Privé Pagnidibsom',
    'active' => 'grades',
    'pageTitle' => 'Import des notes',
    'pageSubtitle' => $assessment->schoolClass->name . ' - ' . $assessment->subject->name . ' - ' . $assessment->title,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('grades.index', ['school_class_id' => $assessment->school_class_id, 'term_id' => $assessment->term_id, 'assessment_id' => $assessment->id]) }}">Retour notes</a>
    <a class="btn btn-subtle" href="{{ route('grades.import.template', $assessment) }}" data-download-feedback="Modele de notes telecharge. Ouvre-le dans Excel puis complete la colonne Note.">Modele Excel</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($assessment->is_locked)
        <p class="error">Cette evaluation est verrouillee. Deverrouille-la avant d importer des notes.</p>
    @endif

    <section class="summary-row">
        <div class="stat">
            <span>Classe</span>
            <strong>{{ $assessment->schoolClass->name }}</strong>
        </div>
        <div class="stat">
            <span>Matière</span>
            <strong>{{ $assessment->subject->name }}</strong>
        </div>
        <div class="stat">
            <span>Note sur</span>
            <strong>{{ number_format((float) $assessment->max_score, 0, ',', ' ') }}</strong>
        </div>
    </section>

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>1. Preparer le fichier</h2>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Format</span>
                    <strong>CSV, XLSX ou PDF texte</strong>
                </div>
                <div class="detail-item">
                    <span>Colonnes obligatoires</span>
                    <strong>Matricule, note</strong>
                </div>
                <div class="detail-item">
                    <span>Mise à jour</span>
                    <strong>Les notes existantes seront remplacees</strong>
                </div>
            </div>

            <p class="notice" style="margin-top:16px">
                Utilise le modele Excel pour garder les matricules exacts. Pour un PDF, il doit être un PDF texte exporte depuis Excel, Word ou un logiciel scolaire.
            </p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>2. Charger les notes</h2>
            </div>

            <form method="POST" action="{{ route('grades.import.preview', $assessment) }}" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label>Fichier des notes</label>
                    <input type="file" name="grades_file" accept=".csv,.txt,.xlsx,.pdf" required @disabled($assessment->is_locked)>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" @disabled($assessment->is_locked)>Analyser le fichier</button>
                </div>
            </form>
        </div>
    </section>

    @if ($preview)
        @php($summary = $preview['summary'])
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Lignes trouvées</span>
                <strong>{{ $summary['total'] }}</strong>
            </div>
            <div class="stat">
                <span>Valides</span>
                <strong>{{ $summary['valid'] }}</strong>
            </div>
            <div class="stat">
                <span>Erreurs / mises à jour</span>
                <strong>{{ $summary['invalid'] }} / {{ $summary['updates'] }}</strong>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>3. Prévisualisation</h2>
                <span class="badge">{{ $summary['valid'] }} importable(s)</span>
            </div>

            @if (empty($preview['rows']))
                <div class="empty">Aucune ligne de note trouvée dans le fichier.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:860px">
                        <thead>
                            <tr>
                                <th>Ligne</th>
                                <th>Matricule</th>
                                <th>Élève</th>
                                <th>Note</th>
                                <th>Statut note</th>
                                <th>Commentaire</th>
                                <th>Statut</th>
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['rows'] as $row)
                                @php($data = $row['data'])
                                <tr>
                                    <td>{{ $row['line'] }}</td>
                                    <td>{{ $row['matricule'] ?: '-' }}</td>
                                    <td><strong>{{ $row['student_label'] ?: '-' }}</strong></td>
                                    <td>{{ is_null($data['score']) ? '-' : number_format((float) $data['score'], 2, ',', ' ') }}</td>
                                    <td>{{ $data['status_label'] ?? ($data['is_absent'] ? 'Absent' : 'Note saisie') }}</td>
                                    <td>{{ $data['comment'] ?: '-' }}</td>
                                    <td>
                                        <span class="badge {{ $row['status'] === 'valid' ? '' : 'badge-danger' }}">
                                            {{ $row['status'] === 'valid' ? ($row['will_update'] ? 'Mise à jour' : 'Valide') : 'Erreur' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if (! empty($row['errors']))
                                            <strong>Erreurs</strong>
                                            <div>{{ implode(' ', $row['errors']) }}</div>
                                        @endif
                                        @if (! empty($row['warnings']))
                                            <strong>A noter</strong>
                                            <div>{{ implode(' ', $row['warnings']) }}</div>
                                        @endif
                                        @if (empty($row['errors']) && empty($row['warnings']))
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="form-actions">
                <form method="POST" action="{{ route('grades.import.destroy', $assessment) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-subtle" type="submit">Annuler</button>
                </form>

                <form method="POST" action="{{ route('grades.import.store', $assessment) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($summary['valid'] === 0 || $assessment->is_locked)>
                        Importer les notes valides
                    </button>
                </form>
            </div>
        </section>
    @endif
@endsection
