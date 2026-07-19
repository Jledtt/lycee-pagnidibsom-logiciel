@extends('layouts.app', [
    'title' => 'Donnees eleves incompletes - Lycee Prive Pagnidibsom',
    'active' => 'reports',
    'pageTitle' => 'Donnees eleves incompletes',
    'pageSubtitle' => 'Controle des informations a completer pour ' . ($academicYear?->name ?? 'l annee active'),
])

@php
    $issueOptions = [
        'gender' => 'Sexe non renseigne',
        'birth_date' => 'Date de naissance',
        'contact' => 'Contact parent/tuteur',
        'photo' => 'Photo',
        'documents' => 'Pieces obligatoires',
    ];
@endphp

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('reports.class-list', ['school_class_id' => $schoolClass?->id]) }}">Liste eleves</a>
    <a class="btn btn-subtle" href="{{ route('reports.missing-documents', ['school_class_id' => $schoolClass?->id]) }}">Pieces manquantes</a>
    <a class="btn btn-primary" href="{{ route('reports.incomplete-students.export', request()->query()) }}" data-download-feedback="Telechargement Excel des donnees incompletes lance. Regarde l'icone de telechargement du navigateur.">Excel</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Selection</h2>
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
            <span>Eleves suivis</span>
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
            <span>Pieces obligatoires</span>
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
                    Les manques sont detectes a partir de la fiche eleve, des contacts parents, de la photo et des pieces obligatoires.
                </p>
            </div>
            <span class="badge">{{ $rows->count() }} ligne(s)</span>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Aucun eleve ne correspond a cette selection.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:1120px">
                    <thead>
                        <tr>
                            <th>Eleve</th>
                            <th>Classe</th>
                            <th>Sexe</th>
                            <th>Naissance</th>
                            <th>Contact</th>
                            <th>Photo</th>
                            <th>A completer</th>
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
                                <td>{{ $student?->gender_label ?? 'Non renseigne' }}</td>
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
