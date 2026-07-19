@extends('layouts.app', [
    'title' => 'Liste des eleves par classe - Lycee Prive Pagnidibsom',
    'active' => 'reports',
    'pageTitle' => 'Liste des eleves par classe',
    'pageSubtitle' => 'Rapport imprimable par classe pour ' . ($academicYear?->name ?? 'l annee active'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('reports.missing-documents', ['school_class_id' => $schoolClass?->id]) }}">Pieces manquantes</a>
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('reports.payment-situation', ['school_class_id' => $schoolClass?->id]) }}">Situation paiements</a>
        <a class="btn btn-subtle" href="{{ route('reports.installments', ['school_class_id' => $schoolClass?->id]) }}">Tranches</a>
    @endcan
    @if ($schoolClass)
        <a class="btn btn-subtle" href="{{ route('reports.class-list.export', ['school_class_id' => $schoolClass->id]) }}" data-download-feedback="Telechargement Excel de la liste de classe lance. Regarde l'icone de telechargement du navigateur.">Excel</a>
        <a class="btn btn-primary" href="{{ route('reports.class-list.pdf', ['school_class_id' => $schoolClass->id]) }}">PDF</a>
    @endif
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Selection</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('reports.class-list') }}">
            <select name="school_class_id" required>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($schoolClass?->id === $class->id)>
                        {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                    </option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    @if (! $schoolClass)
        <section class="panel" style="margin-top:16px">
            <div class="empty">Aucune classe active disponible pour l'annee scolaire active.</div>
        </section>
    @else
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Classe</span>
                <strong>{{ $schoolClass->name }}</strong>
            </div>
            <div class="stat">
                <span>Effectif</span>
                <strong>{{ $summary['total'] }}</strong>
            </div>
            <div class="stat">
                <span>Filles / Garcons</span>
                <strong>{{ $summary['girls'] }} / {{ $summary['boys'] }}</strong>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Eleves inscrits</h2>
                <span class="badge">{{ $summary['total'] }} eleve(s)</span>
            </div>

            @if ($schoolClass->enrollments->isEmpty())
                <div class="empty">Aucun eleve actif inscrit dans cette classe.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Matricule</th>
                            <th>Nom et prenom(s)</th>
                            <th>Sexe</th>
                            <th>Naissance</th>
                            <th>Tuteur</th>
                            <th>Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schoolClass->enrollments as $enrollment)
                            @php($student = $enrollment->student)
                            @php($guardian = $student?->guardians->first())
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $student?->matricule }}</td>
                                <td><strong>{{ $student?->full_name }}</strong></td>
                                <td>{{ $student?->gender_label ?? 'Non renseigne' }}</td>
                                <td>{{ $student?->birth_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $guardian?->full_name ?? '-' }}</td>
                                <td>{{ $guardian?->phone_primary ?? $student?->home_phone ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
@endsection
