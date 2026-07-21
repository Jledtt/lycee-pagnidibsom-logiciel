@extends('layouts.app', [
    'title' => 'Fiche d emargement - Lycee Prive Pagnidibsom',
    'active' => 'teacher-attendance-sheets',
    'pageTitle' => 'Fiche d emargement des professeurs',
    'pageSubtitle' => 'Document de controle journalier des heures de cours effectuees',
])

@section('content')
    <section class="grid two-col">
        <form class="panel" method="GET" action="{{ route('teacher-attendance-sheets.pdf') }}">
            <div class="panel-head">
                <h2>Generer une fiche</h2>
                <span class="badge">{{ $academicYear?->name }}</span>
            </div>

            <div class="field">
                <label for="teacher_name">Professeur</label>
                <select id="teacher_name" name="teacher_name">
                    <option value="">Fiche vierge / tous professeurs</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher }}" @selected(($filters['teacher_name'] ?? '') === $teacher)>{{ $teacher }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid two-col">
                <div class="field">
                    <label for="start_date">Debut</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] }}" required>
                </div>
                <div class="field">
                    <label for="end_date">Fin</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] }}" required>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" data-download-feedback="Telechargement de la fiche d emargement lance.">Telecharger PDF</button>
            </div>
        </form>

        <div class="panel">
            <div class="panel-head">
                <h2>Principe</h2>
            </div>
            <div class="detail-item">
                <span>Vie scolaire</span>
                <strong>Le professeur passe a la vie scolaire apres le cours pour signer.</strong>
            </div>
            <div class="detail-item">
                <span>Controle</span>
                <strong>Sans emargement, l administration peut considerer que le cours n a pas ete effectue.</strong>
            </div>
            <div class="detail-item">
                <span>Source</span>
                <strong>Les cases sont pre-remplies depuis les emplois du temps lorsque le professeur est choisi.</strong>
            </div>
        </div>
    </section>
@endsection
