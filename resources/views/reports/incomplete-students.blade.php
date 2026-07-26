@extends('layouts.app', [
    'title' => 'Données élèves incomplètes - Lycée Privé Pagnidibsom',
    'active' => 'reports',
    'pageTitle' => 'Données élèves incomplètes',
    'pageSubtitle' => 'Contrôle des informations à compléter pour ' . ($academicYear?->name ?? 'l’année active'),
])

@php
    $issueOptions = [
        'gender' => 'Sexe non renseigné',
        'birth_date' => 'Date de naissance',
        'contact' => 'Contact parent/tuteur',
        'photo' => 'Photo',
        'documents' => 'Pièces obligatoires',
    ];
@endphp

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('reports.class-list', ['school_class_id' => $schoolClass?->id]) }}">Liste élèves</a>
    <a class="btn btn-subtle" href="{{ route('reports.missing-documents', ['school_class_id' => $schoolClass?->id]) }}">Pièces manquantes</a>
    <a class="btn btn-primary" href="{{ route('reports.incomplete-students.export', request()->query()) }}" data-download-feedback="Téléchargement Excel des données incomplètes lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Sélection</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('reports.incomplete-students') }}">
            <select name="school_class_id">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($schoolClass?->id === $class->id)>
                        {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                    </option>
                @endforeach
            </select>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou matricule">
            <select name="status">
                <option value="">Tous les dossiers</option>
                <option value="incomplete" @selected(($filters['status'] ?? '') === 'incomplete')>Incomplets</option>
                <option value="complete" @selected(($filters['status'] ?? '') === 'complete')>Complets</option>
            </select>
            <select name="issue">
                <option value="">Tout type de manque</option>
                @foreach ($issueOptions as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['issue'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Élèves suivis</span>
            <strong>{{ $summary['students'] }}</strong>
        </div>
        <div class="stat">
            <span>Dossiers incomplets</span>
            <strong>{{ $summary['incomplete'] }}</strong>
        </div>
        <div class="stat">
            <span>Sexe absent</span>
            <strong>{{ $summary['missing_gender'] }}</strong>
        </div>
        <div class="stat">
            <span>Naissance absente</span>
            <strong>{{ $summary['missing_birth_date'] }}</strong>
        </div>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Contact absent</span>
            <strong>{{ $summary['missing_contact'] }}</strong>
        </div>
        <div class="stat">
            <span>Photo absente</span>
            <strong>{{ $summary['missing_photo'] }}</strong>
        </div>
        <div class="stat">
            <span>Pièces obligatoires</span>
            <strong>{{ $summary['missing_documents'] }}</strong>
        </div>
        <div class="stat">
            <span>Dossiers complets</span>
            <strong>{{ $summary['complete'] }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <div>
                <h2>{{ $schoolClass?->name ?? 'Toutes les classes' }}</h2>
                <p style="margin:4px 0 0;color:var(--muted)">
                    Les manques sont détectés à partir de la fiche élève, des contacts parents, de la photo et des pièces obligatoires.
                </p>
            </div>
            <span class="badge">{{ $rows->count() }} ligne(s)</span>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Aucun élève ne correspond à cette sélection.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:1120px">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Sexe</th>
                            <th>Naissance</th>
                            <th>Contact</th>
                            <th>Photo</th>
                            <th>À compléter</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($student = $row['student'])
                            <tr>
                                <td>
                                    <strong>{{ $student?->full_name }}</strong><br>
                                    <span class="badge">{{ $student?->matricule }}</span>
                                </td>
                                <td>{{ $row['class']?->name ?? '-' }}</td>
                                <td>{{ $student?->gender_label ?? 'Non renseigné' }}</td>
                                <td>{{ $student?->birth_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $row['has_contact'] ? '' : 'badge-warning' }}">
                                        {{ $row['has_contact'] ? 'Oui' : 'Non' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $row['has_photo'] ? '' : 'badge-warning' }}">
                                        {{ $row['has_photo'] ? 'Oui' : 'Non' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($row['is_complete'])
                                        <span style="color:var(--muted)">Dossier complet</span>
                                    @else
                                        <div class="page-actions" style="justify-content:flex-start">
                                            @foreach ($row['issues'] as $issue)
                                                <span class="badge badge-warning">{{ $issue['label'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($student)
                                        <div class="page-actions" style="justify-content:flex-start">
                                            <a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Voir</a>
                                            @can('students.update')
                                                <a class="btn btn-primary" href="{{ route('students.edit', $student) }}">Modifier</a>
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
