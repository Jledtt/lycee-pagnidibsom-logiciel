@extends('layouts.app', [
    'title' => 'Absences - Lycée Privé Pagnidibsom',
    'active' => 'attendance',
    'pageTitle' => 'Absences',
    'pageSubtitle' => 'Pointage par classe, absences et retards',
])

@section('page_actions')
    @if ($schoolClass)
        @can('attendance.reports')
            <a class="btn btn-subtle" href="{{ route('attendance.export', ['school_class_id' => $schoolClass->id, 'date' => $date->toDateString()]) }}" data-download-feedback="Téléchargement Excel des absences lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
            <a class="btn btn-subtle" href="{{ $selectedSession ? route('attendance.sessions.pdf', $selectedSession) : route('attendance.pdf', ['school_class_id' => $schoolClass->id, 'date' => $date->toDateString()]) }}">PDF</a>
        @endcan
    @endif
@endsection

@section('content')
    @php($statusLabels = ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Retard', 'excused' => 'Justifie'])

    <section class="panel">
        <div class="panel-head">
            <h2>Appel du jour</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('attendance.index') }}">
            <select name="school_class_id" required>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($schoolClass?->id === $class->id)>
                        {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                    </option>
                @endforeach
            </select>
            <input name="date" type="date" value="{{ $date->toDateString() }}">
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>

        @if ($schoolClass)
            <form method="POST" action="{{ route('attendance.sessions.store') }}" style="margin-top:12px">
                @csrf
                <input type="hidden" name="school_class_id" value="{{ $schoolClass->id }}">
                <input type="hidden" name="session_date" value="{{ $date->toDateString() }}">
                <button class="btn btn-primary" type="submit">
                    {{ $selectedSession ? 'Continuer le pointage' : 'Faire l appel' }}
                </button>
            </form>
        @endif
    </section>

    <section class="summary-row" style="margin-top:16px">
        <div class="stat">
            <span>Absents</span>
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

    <section class="grid two-col" style="margin-top:16px">
        <div class="panel">
            <div class="panel-head">
                <h2>Pointages du {{ $date->format('d/m/Y') }}</h2>
                <span class="badge">{{ $sessions->count() }} classe(s)</span>
            </div>

            @if ($sessions->isEmpty())
                <div class="empty">Aucun pointage créé pour cette date.</div>
            @else
                <div class="subject-list-scroll">
                <table class="table" style="min-width:680px">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Presents</th>
                            <th>Absents</th>
                            <th>Retards</th>
                                <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td><strong>{{ $session->schoolClass?->name }}</strong></td>
                                <td>{{ $session->records->where('status', 'present')->count() }}</td>
                                <td>{{ $session->records->where('status', 'absent')->count() }}</td>
                                <td>{{ $session->records->where('status', 'late')->count() }}</td>
                                <td>
                                    <div class="searchbar">
                                        <a class="btn btn-subtle" href="{{ route('attendance.sessions.edit', $session) }}">Voir</a>
                                        @can('attendance.reports')
                                            <a class="btn btn-subtle" href="{{ route('attendance.sessions.pdf', $session) }}">PDF</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Derniers incidents</h2>
                <span class="badge">{{ $recentRecords->count() }} ligne(s)</span>
            </div>

            @if ($recentRecords->isEmpty())
                <div class="empty">Aucune absence ou retard enregistré pour le moment.</div>
            @else
                <div class="subject-list-scroll">
                <table class="table" style="min-width:760px">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRecords as $record)
                            <tr>
                                <td>
                                    <strong>{{ $record->student?->full_name }}</strong><br>
                                    <span class="badge">{{ $record->student?->matricule }}</span>
                                </td>
                                <td>{{ $record->session?->schoolClass?->name ?? '-' }}</td>
                                <td>{{ $record->session?->session_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $record->status === 'present' ? '' : 'badge-warning' }}">
                                        {{ $statusLabels[$record->status] ?? $record->status }}
                                    </span>
                                </td>
                                <td>
                                    <div class="searchbar">
                                        @if ($record->student)
                                            @can('attendance.view')
                                                <a class="btn btn-subtle" href="{{ route('attendance.students.history', $record->student) }}">Historique</a>
                                            @endcan
                                        @endif
                                    @can('attendance.update')
                                        <button
                                            class="btn btn-subtle"
                                            type="button"
                                            data-dialog-open="attendance-record-dialog"
                                            data-attendance-student="{{ $record->student?->full_name }}"
                                            data-attendance-date="{{ $record->session?->session_date?->format('d/m/Y') }}"
                                            data-attendance-status="{{ $statusLabels[$record->status] ?? $record->status }}"
                                            data-attendance-reason="{{ $record->reason }}"
                                            data-attendance-justify-url="{{ route('attendance.records.justify', $record) }}"
                                            data-attendance-clear-url="{{ route('attendance.records.clear', $record) }}"
                                        >Traiter</button>
                                    @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('dialogs')
    @can('attendance.update')
        @include('attendance.partials.record-dialog', ['records' => $recentRecords])
    @endcan
@endpush
