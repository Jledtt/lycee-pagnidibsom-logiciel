@extends('layouts.app', [
    'title' => 'Fiche d’émargement - Lycée Privé Pagnidibsom',
    'active' => 'teacher-attendance-sheets',
    'pageTitle' => 'Fiche d’émargement des professeurs',
    'pageSubtitle' => 'Document de contrôle journalier des heures de cours effectuées',
])

@section('content')
    <section class="grid two-col">
        <form class="panel" method="GET" action="{{ route('teacher-attendance-sheets.pdf') }}">
            <div class="panel-head">
                <h2>Générer une fiche</h2>
                <span class="badge">{{ $academicYear?->name }}</span>
            </div>

            <div class="field">
                <label for="teacher_name">Professeur</label>
                <select id="teacher_name" name="teacher_name">
                    <option value="">Fiche vierge / tous professeurs</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher }}" @selected(old('teacher_name', $filters['teacher_name'] ?? '') === $teacher)>{{ $teacher }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid two-col">
                <div class="field">
                    <label for="start_date">Début</label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $filters['start_date']) }}" required>
                </div>
                <div class="field">
                    <label for="end_date">Fin</label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $filters['end_date']) }}" aria-describedby="attendance-period-help @error('end_date') attendance-period-error @enderror" required>
                    <small id="attendance-period-help">La fiche peut couvrir au maximum 31 jours.</small>
                    @error('end_date')
                        <p class="error" id="attendance-period-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" data-download-feedback="Téléchargement de la fiche d’émargement lancé.">Télécharger PDF</button>
            </div>
        </form>

        <div class="panel">
            <div class="panel-head">
                <h2>Principe</h2>
            </div>
            <div class="detail-item">
                <span>Vie scolaire</span>
                <strong>Le logiciel génère la fiche, puis le professeur passe à la vie scolaire après le cours pour signer sur papier.</strong>
            </div>
            <div class="detail-item">
                <span>Contrôle</span>
                <strong>Sans signature sur la fiche imprimée, l’administration peut considérer que le cours n’a pas été effectué.</strong>
            </div>
            <div class="detail-item">
                <span>Source</span>
                <strong>Les cases restent vierges. La vie scolaire imprime la fiche, le professeur renseigne les heures effectuees et signe sur papier.</strong>
            </div>
        </div>
    </section>
@endsection
