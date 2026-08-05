@extends('layouts.app', [
    'title' => 'Pièces manquantes - Lycée Privé Pagnidibsom',
    'active' => 'reports',
    'pageTitle' => 'Pièces manquantes',
    'pageSubtitle' => 'Contrôle des dossiers administratifs pour ' . ($academicYear?->name ?? 'l’année active'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('reports.incomplete-students', ['school_class_id' => $schoolClass?->id]) }}">Données incomplètes</a>
    <a class="btn btn-subtle" href="{{ route('reports.class-list', ['school_class_id' => $schoolClass?->id]) }}">Liste élèves</a>
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('reports.payment-situation', ['school_class_id' => $schoolClass?->id]) }}">Situation paiements</a>
    @endcan
    <a class="btn btn-subtle" href="{{ route('reports.missing-documents.export', request()->query()) }}" data-download-feedback="Téléchargement Excel des pièces manquantes lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
    <a class="btn btn-primary" href="{{ route('reports.missing-documents.pdf', request()->query()) }}">PDF</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Sélection</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('reports.missing-documents') }}">
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
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Élèves suivis</span>
            <strong>{{ $summary['students'] }}</strong>
        </div>
        <div class="stat">
            <span>Dossiers complets</span>
            <strong>{{ $summary['complete'] }}</strong>
        </div>
        <div class="stat">
            <span>Dossiers incomplets</span>
            <strong>{{ $summary['incomplete'] }}</strong>
        </div>
        <div class="stat">
            <span>Pièces manquantes</span>
            <strong>{{ $summary['missing_documents'] }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <div>
                <h2>{{ $schoolClass?->name ?? 'Toutes les classes' }}</h2>
                <p style="margin:4px 0 0;color:var(--muted)">
                    Base contrôlee : {{ implode(', ', $requiredDocuments) }}.
                </p>
            </div>
            <span class="badge">{{ $rows->count() }} ligne(s)</span>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Aucun élève ne correspond à cette sélection.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:980px">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Statut dossier</th>
                            <th>Pièces manquantes</th>
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
                                <td>
                                    <span class="badge {{ $row['is_complete'] ? '' : 'badge-warning' }}">
                                        {{ $row['is_complete'] ? 'Complet' : 'Incomplet' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($row['is_complete'])
                                        <span style="color:var(--muted)">Aucune pièce manquante</span>
                                    @else
                                        <div class="page-actions" style="justify-content:flex-start">
                                            @foreach ($row['missing_documents'] as $document)
                                                <span class="badge badge-warning">{{ $document['label'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($student)
                                        <a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Voir dossier</a>
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
