@extends('layouts.app', [
    'title' => 'Pointage - Lycée Privé Pagnidibsom',
    'active' => 'attendance',
    'pageTitle' => 'Pointage ' . $session->schoolClass->name,
    'pageSubtitle' => 'Appel du ' . $session->session_date->format('d/m/Y'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('attendance.index', ['school_class_id' => $session->school_class_id, 'date' => $session->session_date->toDateString()]) }}">Retour</a>
    @can('attendance.reports')
        <a class="btn btn-subtle" href="{{ route('attendance.sessions.pdf', $session) }}">PDF</a>
    @endcan
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Présents</span>
            <strong>{{ $summary['present'] }}</strong>
        </div>
        <div class="stat">
            <span>Absents</span>
            <strong>{{ $summary['absent'] }}</strong>
        </div>
        <div class="stat">
            <span>Retards</span>
            <strong>{{ $summary['late'] }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Liste des élèves</h2>
            <span class="badge">{{ $rows->count() }} élève(s)</span>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Aucun élève actif dans cette classe.</div>
        @else
            <form method="POST" action="{{ route('attendance.sessions.update', $session) }}">
                @csrf
                @method('PUT')

                <div class="form-actions" style="margin-bottom:12px">
                    <button class="btn btn-subtle" type="button" data-mark-all-present>Marquer tous présents</button>
                    <button class="btn btn-primary" type="submit">Enregistrer le pointage</button>
                </div>

                <div class="subject-list-scroll">
                <table class="table" style="min-width:980px">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Statut</th>
                            <th>Minutes retard</th>
                            <th>Motif / observation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($student = $row['student'])
                            @php($record = $row['record'])
                            @php($status = old("records.$loop->index.status", $record?->status ?? 'present'))
                            <tr>
                                <td>
                                    <strong>{{ $student->full_name }}</strong><br>
                                    <span class="badge">{{ $student->matricule }}</span>
                                    <input type="hidden" name="records[{{ $loop->index }}][student_id]" value="{{ $student->id }}">
                                </td>
                                <td>
                                    <select name="records[{{ $loop->index }}][status]" data-attendance-status>
                                        <option value="present" @selected($status === 'present')>Présent</option>
                                        <option value="absent" @selected($status === 'absent')>Absent</option>
                                        <option value="late" @selected($status === 'late')>Retard</option>
                                        <option value="excused" @selected($status === 'excused')>Absence justifiée</option>
                                    </select>
                                </td>
                                <td>
                                    <input name="records[{{ $loop->index }}][minutes_late]" type="number" min="0" max="600" value="{{ old("records.$loop->index.minutes_late", $record?->minutes_late) }}" placeholder="Ex: 15">
                                </td>
                                <td>
                                    <input name="records[{{ $loop->index }}][reason]" value="{{ old("records.$loop->index.reason", $record?->reason) }}" placeholder="Motif ou observation">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div class="form-actions" style="margin-top:14px">
                    <a class="btn btn-subtle" href="{{ route('attendance.index', ['school_class_id' => $session->school_class_id, 'date' => $session->session_date->toDateString()]) }}">Annuler</a>
                    <button class="btn btn-primary" type="submit">Enregistrer le pointage</button>
                </div>
            </form>
        @endif
    </section>

    <script>
        document.querySelector('[data-mark-all-present]')?.addEventListener('click', () => {
            document.querySelectorAll('[data-attendance-status]').forEach((select) => {
                select.value = 'present';
            });
        });
    </script>
@endsection
