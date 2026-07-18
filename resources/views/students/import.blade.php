@extends('layouts.app', [
    'title' => 'Import eleves - Lycee Prive Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Import Excel/PDF des eleves',
    'pageSubtitle' => 'Ajouter plusieurs dossiers eleves a partir d un fichier CSV, XLSX ou PDF texte',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('students.index') }}">Retour eleves</a>
    <a class="btn btn-subtle" href="{{ route('students.import.template') }}" data-download-feedback="Modele d import telecharge. Ouvre-le dans Excel puis complete les lignes.">Modele Excel</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="grid two-col">
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
                    <strong>Nom, prenom, sexe</strong>
                </div>
                <div class="detail-item">
                    <span>Doublons</span>
                    <strong>Nom + prenom + naissance</strong>
                </div>
            </div>

            <p class="notice" style="margin-top:16px">
                Les PDF scannes ou pris en photo demandent une etape OCR. Pour l instant, l import PDF fonctionne avec les PDF texte exportes depuis Excel, Word ou un logiciel scolaire.
            </p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>2. Charger le fichier</h2>
            </div>

            <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="field">
                    <label>Fichier des eleves</label>
                    <input type="file" name="students_file" accept=".csv,.txt,.xlsx,.pdf" required>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Analyser le fichier</button>
                </div>
            </form>
        </div>
    </section>

    @if ($preview)
        @php($summary = $preview['summary'])
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Lignes trouvees</span>
                <strong>{{ $summary['total'] }}</strong>
            </div>
            <div class="stat">
                <span>Valides</span>
                <strong>{{ $summary['valid'] }}</strong>
            </div>
            <div class="stat">
                <span>Erreurs / doublons</span>
                <strong>{{ $summary['invalid'] }} / {{ $summary['duplicates'] }}</strong>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>3. Previsualisation</h2>
                <span class="badge">{{ $summary['valid'] }} importable(s)</span>
            </div>

            @if (empty($preview['rows']))
                <div class="empty">Aucune ligne eleve trouvee dans le fichier.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:880px">
                        <thead>
                            <tr>
                                <th>Ligne</th>
                                <th>Eleve</th>
                                <th>Sexe</th>
                                <th>Naissance</th>
                                <th>Classe</th>
                                <th>Tuteur</th>
                                <th>Statut</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['rows'] as $row)
                                @php($data = $row['data'])
                                <tr>
                                    <td>{{ $row['line'] }}</td>
                                    <td>
                                        <strong>{{ $row['display_name'] ?: '-' }}</strong><br>
                                        <span>{{ $data['home_phone'] ?? '' }}</span>
                                    </td>
                                    <td>{{ $data['gender'] === 'female' ? 'Fille' : ($data['gender'] === 'male' ? 'Garcon' : '-') }}</td>
                                    <td>{{ $data['birth_date'] ?: '-' }}</td>
                                    <td>
                                        {{ $row['class_label'] ?: '-' }}
                                        @if (! empty($data['school_class_id']))
                                            <br><span class="badge">Classe trouvee</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ trim(($data['father_first_name'] ?? '') . ' ' . ($data['father_last_name'] ?? '')) ?: '-' }}<br>
                                        <span>{{ $data['father_phone_primary'] ?? '' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $row['status'] === 'valid' ? '' : ($row['status'] === 'duplicate' ? 'badge-warning' : 'badge-danger') }}">
                                            {{ $row['status'] === 'valid' ? 'Valide' : ($row['status'] === 'duplicate' ? 'Doublon' : 'Erreur') }}
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
                <form method="POST" action="{{ route('students.import.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-subtle" type="submit">Annuler</button>
                </form>

                <form method="POST" action="{{ route('students.import.store') }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($summary['valid'] === 0)>
                        Importer les lignes valides
                    </button>
                </form>
            </div>
        </section>
    @endif
@endsection
