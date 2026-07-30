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
        <a class="btn btn-primary" href="{{ route('teacher-fees.index') }}">Honoraires</a>
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

    @can('teacher_attendance.manage')
        <section class="panel" style="margin-top:16px">
            <div class="panel-head"><h2>Enregistrer des heures effectuées</h2><span class="badge">{{ $academicYear?->name }}</span></div>
            <form method="POST" action="{{ route('teacher-work-sessions.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field"><label>Professeur</label><select name="teacher_id" required><option value="">Choisir</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((int) old('teacher_id', $filters['teacher_id']) === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Date du cours</label><input type="date" name="session_date" value="{{ old('session_date', now()->toDateString()) }}" required></div>
                    <div class="field"><label>Classe</label><select name="school_class_id" required><option value="">Choisir</option>@foreach ($classes as $class)<option value="{{ $class->id }}" @selected((int) old('school_class_id') === $class->id)>{{ $class->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Matière</label><select name="subject_id" required><option value="">Choisir</option>@foreach ($subjects as $subject)<option value="{{ $subject->id }}" @selected((int) old('subject_id') === $subject->id)>{{ $subject->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Début du cours</label><input type="time" name="starts_at" value="{{ old('starts_at') }}" required></div>
                    <div class="field"><label>Fin du cours</label><input type="time" name="ends_at" value="{{ old('ends_at') }}" required></div>
                    <div class="field"><label>Nombre d’heures effectuées</label><input type="number" min="0.25" max="250" step="0.25" name="hours_worked" value="{{ old('hours_worked', 1) }}" required></div>
                    <div class="field"><label>Taux horaire exceptionnel</label><input type="number" min="0" step="1" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="Sinon taux du dossier"></div>
                    <div class="field"><label>Statut</label><select name="status"><option value="draft">À vérifier</option><option value="validated">Validé</option></select></div>
                    <div class="field"><label>Signature papier contrôlée</label><label class="check"><input type="checkbox" name="teacher_signed" value="1" @checked(old('teacher_signed'))> Oui</label></div>
                    <div class="field wide"><label>Observation</label><textarea name="notes">{{ old('notes') }}</textarea></div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Enregistrer les heures</button></div>
            </form>
        </section>
    @endcan

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
                                        <div class="form-actions">
                                            @if ($session->status === 'draft')
                                                <form method="POST" action="{{ route('teacher-work-sessions.validate', $session) }}">@csrf @method('PUT')<button class="btn btn-subtle" type="submit">Valider</button></form>
                                            @endif
                                            @unless ($session->feeLine)
                                                <form method="POST" action="{{ route('teacher-work-sessions.destroy', $session) }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Supprimer</button></form>
                                            @endunless
                                        </div>
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
