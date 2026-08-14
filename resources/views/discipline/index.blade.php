@extends('layouts.app', [
    'title' => 'Discipline - Lycée Privé Pagnidibsom',
    'active' => 'discipline',
    'pageTitle' => 'Discipline',
    'pageSubtitle' => 'Incidents, mesures prises et historique de l’année active',
])

@php
    $typeLabels = ['observation' => 'Observation', 'warning' => 'Avertissement', 'sanction' => 'Sanction'];
    $statusLabels = ['active' => 'En cours', 'resolved' => 'Résolu', 'cancelled' => 'Annulé'];
@endphp

@section('page_actions')
    @can('discipline.manage')
        <a class="btn btn-primary" href="{{ route('discipline.create') }}">Nouvel incident</a>
    @endcan
@endsection

@section('content')
    @if (! $academicYear)
        <div class="error">Aucune année scolaire active. Les incidents ne peuvent pas être affichés ni enregistrés.</div>
    @endif

    <section class="panel">
        <form class="searchbar" method="GET" action="{{ route('discipline.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Élève, matricule ou objet">
            <select name="school_class_id">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((int) ($filters['school_class_id'] ?? 0) === $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
            <select name="type">
                <option value="">Toutes les natures</option>
                @foreach ($typeLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                @foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
            @if (! empty($filters['student_id']))
                <input type="hidden" name="student_id" value="{{ $filters['student_id'] }}">
            @endif
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('discipline.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Registre disciplinaire</h2>
            <span class="badge">{{ $records->total() }} incident(s)</span>
        </div>

        @if ($records->isEmpty())
            <div class="empty">Aucun incident ne correspond aux filtres sélectionnés.</div>
        @else
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th>Date</th><th>Élève</th><th>Classe</th><th>Nature</th><th>Objet</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>{{ $record->record_date?->format('d/m/Y') }}</td>
                                <td><strong>{{ $record->student?->full_name }}</strong><br><span style="color:var(--muted)">{{ $record->student?->matricule }}</span></td>
                                <td>{{ $record->schoolClass?->name ?? '-' }}</td>
                                <td>{{ $typeLabels[$record->type] ?? $record->type }}</td>
                                <td>{{ $record->title }}</td>
                                <td><span class="badge {{ $record->status === 'cancelled' ? 'badge-warning' : '' }}">{{ $statusLabels[$record->status] ?? $record->status }}</span></td>
                                <td><a class="btn btn-subtle" href="{{ route('discipline.show', $record) }}">Ouvrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $records->links() }}</div>
        @endif
    </section>
@endsection
