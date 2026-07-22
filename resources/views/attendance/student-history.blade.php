@extends('layouts.app', [
    'title' => 'Assiduite - ' . $student->full_name,
    'active' => 'attendance',
    'pageTitle' => 'Assiduite de ' . $student->full_name,
    'pageSubtitle' => 'Historique mensuel des absences, retards et justifications',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('students.show', $student) }}">Fiche élève</a>
    @can('attendance.reports')
        <a class="btn btn-primary" href="{{ route('attendance.students.history.pdf', ['student' => $student->id, 'month' => $month]) }}">PDF</a>
    @endcan
@endsection

@section('content')
    @php($statusLabels = ['absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Justifie'])

    <section class="panel">
        <div class="panel-head">
            <h2>Filtre</h2>
            <span class="badge">{{ $academicYear?->name ?? 'Aucune année active' }}</span>
        </div>

        <form class="searchbar" method="GET" action="{{ route('attendance.students.history', $student) }}">
            <input name="month" type="month" value="{{ $month }}">
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Absences</span>
            <strong>{{ $summary['absent'] }}</strong>
        </div>
        <div class="stat">
            <span>Retards</span>
            <strong>{{ $summary['late'] }}</strong>
        </div>
        <div class="stat">
            <span>Justifies</span>
            <strong>{{ $summary['excused'] }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Historique du mois</h2>
            <span class="badge">{{ $records->count() }} ligne(s)</span>
        </div>

        @if ($records->isEmpty())
            <div class="empty">Aucune absence ou retard pour cette période.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:920px">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Retard</th>
                            <th>Motif / observation</th>
                            <th>Justification</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>{{ $record->session?->session_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $record->session?->schoolClass?->name ?? '-' }}</td>
                                <td><span class="badge badge-warning">{{ $statusLabels[$record->status] ?? $record->status }}</span></td>
                                <td>{{ $record->status === 'late' ? (($record->minutes_late ?? 0) . ' min') : '-' }}</td>
                                <td>{{ $record->reason ?: '-' }}</td>
                                <td>
                                    @if ($record->justified_at)
                                        {{ $record->justified_at->format('d/m/Y') }}
                                        @if ($record->justifiedBy)
                                            <br><span class="muted">{{ $record->justifiedBy->name }}</span>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @can('attendance.update')
                                        <form method="POST" action="{{ route('attendance.records.clear', $record) }}" onsubmit="return confirm('Supprimer cette absence ou ce retard ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger" type="submit">Supprimer</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
