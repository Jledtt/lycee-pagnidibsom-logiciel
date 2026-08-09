@extends('layouts.app', [
    'title' => 'Réviser l’emploi du temps - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Révision de l’emploi du temps',
    'pageSubtitle' => $timetable->schoolClass->name . ' - ' . $timetable->academicYear->name,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.index', ['school_class_id' => $timetable->school_class_id]) }}">Retour</a>
    @if ($timetable->status !== 'active')
        <a class="btn btn-primary" href="{{ route('timetables.edit', $timetable) }}">Modifier la grille</a>
    @endif
    @can('timetables.print')
        <a class="btn btn-subtle" href="{{ route('timetables.pdf', $timetable) }}" data-download-feedback="Téléchargement du PDF lancé.">PDF</a>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel timetable-review-overview">
        <div class="panel-head">
            <div>
                <h2>{{ $timetable->title }}</h2>
                <p class="muted">Contrôle les volumes, les conflits et les cours à conserver avant publication.</p>
            </div>
            <span class="badge {{ $timetable->status === 'active' ? '' : 'badge-warning' }}">
                {{ $timetable->status === 'active' ? 'Publié' : ($timetable->status === 'archived' ? 'Archivé' : 'Brouillon') }}
            </span>
        </div>

        <div class="planning-metrics timetable-review-metrics">
            <div><span>Cours placés</span><strong>{{ $audit['metrics']['courses'] }}</strong></div>
            <div><span>Créneaux attendus</span><strong>{{ $audit['metrics']['expected'] }}</strong></div>
            <div><span>Génération automatique</span><strong>{{ $audit['metrics']['automatic'] }}</strong></div>
            <div><span>Corrections manuelles</span><strong>{{ $audit['metrics']['manual'] }}</strong></div>
            <div><span>Cours verrouillés</span><strong>{{ $audit['metrics']['locked'] }}</strong></div>
            <div><span>Salles utilisées</span><strong>{{ $audit['metrics']['rooms'] }}</strong></div>
        </div>

        @if ($timetable->status === 'active')
            <div class="planning-diagnostics">
                <strong>Grille publiée</strong>
                <p>
                    Publication du {{ $timetable->published_at?->format('d/m/Y à H:i') ?? 'jour non renseigné' }}
                    @if ($timetable->publisher) par {{ $timetable->publisher->name }} @endif.
                    Les cours sont proteges contre les modifications accidentelles.
                </p>
            </div>
        @endif

        @if ($audit['blockers'])
            <div class="planning-diagnostics planning-diagnostics--error" role="alert">
                <strong>Publication impossible pour le moment</strong>
                <ul>@foreach ($audit['blockers'] as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($audit['warnings'])
            <div class="planning-diagnostics">
                <strong>Points à vérifier</strong>
                <ul>@foreach ($audit['warnings'] as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="timetable-review-actions">
            @if ($timetable->status === 'draft')
                <form method="POST" action="{{ route('timetables.locks.update', $timetable) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="locked" value="1">
                    <button class="btn btn-subtle" type="submit" @disabled($audit['metrics']['courses'] === 0)>Tout verrouiller</button>
                </form>
                <form method="POST" action="{{ route('timetables.locks.update', $timetable) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="locked" value="0">
                    <button class="btn btn-subtle" type="submit" @disabled($audit['metrics']['courses'] === 0)>Tout déverrouiller</button>
                </form>
                <form
                    method="POST"
                    action="{{ route('timetables.publish', $timetable) }}"
                    data-confirm
                    data-confirm-title="Publier l’emploi du temps"
                    data-confirm-object="{{ $timetable->schoolClass->name }} - {{ $timetable->academicYear->name }}"
                    data-confirm-message="Tous les cours seront verrouillés. Les corrections resteront possibles après un retour explicite en brouillon."
                    data-confirm-action="Publier la grille"
                    data-confirm-tone="primary">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled(! $audit['can_publish'])>Publier</button>
                </form>
            @elseif ($timetable->status === 'active')
                <form
                    method="POST"
                    action="{{ route('timetables.reopen', $timetable) }}"
                    data-confirm
                    data-confirm-title="Repasser en brouillon"
                    data-confirm-object="{{ $timetable->schoolClass->name }} - {{ $timetable->academicYear->name }}"
                    data-confirm-message="La grille ne sera plus considérée comme publiée. Les cours resteront verrouillés jusqu’à leur déverrouillage volontaire."
                    data-confirm-action="Repasser en brouillon">
                    @csrf
                    <button class="btn btn-subtle" type="submit">Repasser en brouillon</button>
                </form>
            @endif
        </div>
    </section>

    @if ($audit['coverage']->isNotEmpty())
        <section class="panel timetable-review-section">
            <div class="panel-head">
                <h2>Volumes horaires par matière</h2>
                <span class="badge">{{ $audit['coverage']->where('complete', true)->count() }}/{{ $audit['coverage']->count() }} conformes</span>
            </div>
            <div class="subject-list-scroll">
                <table class="table timetable-coverage-table">
                    <thead><tr><th>Matière</th><th>Professeur</th><th>Attendu</th><th>Placé</th><th>Contrôle</th></tr></thead>
                    <tbody>
                        @foreach ($audit['coverage'] as $item)
                            <tr>
                                <td><strong>{{ $item['assignment']->subject?->name ?? 'Matière' }}</strong></td>
                                <td>{{ $item['assignment']->teacher?->name ?? 'Non affecté' }}</td>
                                <td>{{ $item['expected'] }}</td>
                                <td>{{ $item['placed'] }}</td>
                                <td><span class="badge {{ $item['complete'] ? '' : 'badge-warning' }}">{{ $item['complete'] ? 'Conforme' : 'À corriger' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="panel timetable-review-section">
        <div class="panel-head">
            <h2>Grille visuelle</h2>
            <div class="timetable-review-legend" aria-label="Légende">
                <span><i class="review-dot review-dot--automatic"></i>Automatique</span>
                <span><i class="review-dot review-dot--manual"></i>Manuel</span>
                <span><i class="review-dot review-dot--locked"></i>Verrouillé</span>
            </div>
        </div>

        <div class="subject-list-scroll">
            <table class="table timetable-review-grid">
                <thead>
                    <tr><th>Horaire</th>@foreach ($days as $label)<th>{{ $label }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @foreach ($grid as $row)
                        @if ($row['is_break'])
                            <tr class="timetable-review-break"><th>{{ $row['period_label'] }}</th><td colspan="{{ count($days) }}">{{ $row['period_label'] }}</td></tr>
                        @else
                            <tr>
                                <th>{{ $row['period_label'] }}</th>
                                @foreach (array_keys($days) as $dayKey)
                                    @php($entry = $row['days']->get($dayKey))
                                    <td>
                                        @if ($entry && (filled($entry->class_subject_id) || filled($entry->subject_name)))
                                            <article class="review-course review-course--{{ $entry->source }} {{ $entry->is_locked ? 'review-course--locked' : '' }}">
                                                <strong>{{ $entry->subject_name }}</strong>
                                                <span>{{ $entry->teacher_name ?: 'Professeur non renseigné' }}</span>
                                                @if ($entry->room)<small>Salle {{ $entry->room }}</small>@endif
                                                @if ($timetable->status === 'draft')
                                                    <form method="POST" action="{{ route('timetables.entries.lock', [$timetable, $entry]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="locked" value="{{ $entry->is_locked ? 0 : 1 }}">
                                                        <button class="review-lock-button" type="submit">{{ $entry->is_locked ? 'Déverrouiller' : 'Verrouiller' }}</button>
                                                    </form>
                                                @elseif ($entry->is_locked)
                                                    <small>Verrouillé</small>
                                                @endif
                                            </article>
                                        @else
                                            <span class="planning-empty-slot">Libre</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
