@extends('layouts.app', [
    'title' => 'Professeurs - Lycée Privé Pagnidibsom',
    'active' => 'teachers',
    'pageTitle' => 'Professeurs',
    'pageSubtitle' => 'Dossiers, affectations, émargements, documents et honoraires',
])

@section('page_actions')
    @can('teacher_attendance.view')
        <a class="btn btn-subtle" href="{{ route('teacher-work-sessions.index') }}">Émargements</a>
    @endcan
    @can('teacher_fees.view')
        <a class="btn btn-subtle" href="{{ route('teacher-fees.index') }}">Honoraires</a>
    @endcan
    @can('users.manage')
        <a class="btn btn-primary" href="{{ route('staff.create', ['role' => 'enseignant']) }}">Nouveau professeur</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <form class="searchbar" method="GET" action="{{ route('teachers.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, matricule, spécialité ou téléphone">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Actifs</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactifs</option>
                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspendus</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('teachers.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Équipe enseignante</h2>
            <span class="badge">{{ $teachers->total() }} professeur(s)</span>
        </div>

        @if ($teachers->isEmpty())
            <div class="empty">Aucun professeur trouvé. Crée un compte avec le rôle Enseignant.</div>
        @else
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Professeur</th>
                            <th>Spécialité</th>
                            <th>Taux horaire</th>
                            <th>Retenue</th>
                            <th>Documents</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $teacher)
                            <tr>
                                <td>
                                    <strong>{{ $teacher->name }}</strong><br>
                                    <span style="color:var(--muted)">{{ $teacher->teacherProfile?->employee_number ?: $teacher->phone }}</span>
                                </td>
                                <td>{{ $teacher->teacherProfile?->specialty ?: '-' }}</td>
                                <td>{{ number_format((float) ($teacher->teacherProfile?->default_hourly_rate ?? 0), 0, ',', ' ') }} FCFA</td>
                                <td>{{ number_format((float) ($teacher->teacherProfile?->withholding_tax_rate ?? 2), 2, ',', ' ') }} %</td>
                                <td>{{ $teacher->teacher_documents_count }}</td>
                                <td><span class="badge">{{ $teacher->status }}</span></td>
                                <td><a class="btn btn-subtle" href="{{ route('teachers.show', $teacher) }}">Ouvrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination">{{ $teachers->links() }}</div>
        @endif
    </section>
@endsection
