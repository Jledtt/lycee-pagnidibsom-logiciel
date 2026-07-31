@extends('layouts.app', [
    'title' => 'Émargements professeurs',
    'active' => 'teacher-work-sessions',
    'pageTitle' => 'Émargements des professeurs',
    'pageSubtitle' => 'Heures de cours réellement effectuées et validées',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('teachers.index') }}">Professeurs</a>
    <a class="btn btn-subtle" href="{{ route('teacher-attendance-sheets.index') }}">Fiche papier</a>
    @can('teacher_fees.view')
        <a class="btn btn-subtle" href="{{ route('teacher-fees.index') }}">Honoraires</a>
    @endcan
    @can('teacher_attendance.manage')
        <button class="btn btn-primary" type="button" data-dialog-open="teacher-work-session-form-dialog">Ajouter des heures</button>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <form class="searchbar" method="GET" action="{{ route('teacher-work-sessions.index') }}">
            <input type="month" name="month" value="{{ $filters['month'] }}">
            <select name="teacher_id">
                <option value="">Tous les professeurs</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((int) ($filters['teacher_id'] ?? 0) === $teacher->id)>{{ $teacher->name }}</option>
                @endforeach
            </select>
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="draft" @selected($filters['status'] === 'draft')>Brouillons</option>
                <option value="validated" @selected($filters['status'] === 'validated')>Validés</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>Annulés</option>
            </select>
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Heures du mois</h2>
            <span class="badge">{{ number_format((float) $sessions->getCollection()->sum('hours_worked'), 2, ',', ' ') }} h sur cette page</span>
        </div>
        @if ($sessions->isEmpty())
            <div class="empty">Aucune heure enregistrée pour cette période.</div>
        @else
            <div style="overflow-x:auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Professeur</th><th>Classe / matière</th><th>Horaire</th><th>Heures</th><th>Signature</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($sessions as $session)
                            <tr>
                                <td>{{ $session->session_date->format('d/m/Y') }}</td>
                                <td><strong>{{ $session->teacher?->name }}</strong></td>
                                <td>{{ $session->schoolClass?->name }}<br><span style="color:var(--muted)">{{ $session->subject?->name }}</span></td>
                                <td>{{ $session->starts_at ? substr($session->starts_at, 0, 5) : '-' }} – {{ $session->ends_at ? substr($session->ends_at, 0, 5) : '-' }}</td>
                                <td><strong>{{ number_format((float) $session->hours_worked, 2, ',', ' ') }} h</strong></td>
                                <td>{{ $session->teacher_signed_at ? 'Contrôlée' : 'Non contrôlée' }}</td>
                                <td><span class="badge">{{ $session->status }}</span></td>
                                <td>
                                    @can('teacher_attendance.manage')
                                        @unless ($session->feeLine)
                                            <button
                                                class="btn btn-subtle"
                                                type="button"
                                                data-dialog-open="teacher-work-session-action-dialog"
                                                data-session-teacher="{{ $session->teacher?->name }}"
                                                data-session-course="{{ $session->subject?->name }} - {{ $session->schoolClass?->name }}"
                                                data-session-time="{{ $session->session_date->format('d/m/Y') }} · {{ $session->starts_at ? substr($session->starts_at, 0, 5) : '-' }}–{{ $session->ends_at ? substr($session->ends_at, 0, 5) : '-' }}"
                                                data-session-hours="{{ number_format((float) $session->hours_worked, 2, ',', ' ') }} h"
                                                data-session-validate-url="{{ $session->status === 'draft' ? route('teacher-work-sessions.validate', $session) : '' }}"
                                                data-session-delete-url="{{ route('teacher-work-sessions.destroy', $session) }}"
                                            >Gérer</button>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $sessions->links() }}</div>
        @endif
    </section>
@endsection

@push('dialogs')
    @can('teacher_attendance.manage')
        @include('teacher-work-sessions.partials.form-dialog')
        @include('teacher-work-sessions.partials.action-dialog')
    @endcan
@endpush
