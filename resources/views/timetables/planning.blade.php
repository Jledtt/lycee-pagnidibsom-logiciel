@extends('layouts.app', [
    'title' => 'Assistant emploi du temps - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Assistant emploi du temps',
    'pageSubtitle' => 'Choisis une classe, corrige ce qui manque, puis crée un essai de grille',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.index') }}">Retour aux grilles</a>
    <a class="btn btn-subtle" href="{{ route('timetables.planning.blockers', ['school_class_id' => $selectedClass?->id]) }}">Voir ce qui manque</a>
    <a class="btn btn-subtle" href="{{ route('timetables.availabilities') }}">Disponibilités</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <ol class="planning-steps" aria-label="Étapes de l’assistant emploi du temps" data-tour-target="timetable-overview">
        <li class="planning-step"><span>1</span><strong>Choisir la classe</strong><small>Travaille classe par classe au début</small></li>
        <li class="planning-step"><span>2</span><strong>Compléter les infos</strong><small>Professeurs, matières et heures</small></li>
        <li class="planning-step"><span>3</span><strong>Créer un essai</strong><small>La grille reste en brouillon</small></li>
    </ol>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head planning-import-head">
            <div>
                <h2>1. Ajouter les disponibilités des professeurs</h2>
                <p class="panel-subtitle">Importe un fichier ou ouvre la page Disponibilités pour cocher les créneaux professeur par professeur.</p>
            </div>
            <div class="page-actions" data-tour-target="timetable-availability-actions">
                <a class="btn btn-subtle" href="{{ route('timetables.availabilities') }}">Ouvrir Disponibilités</a>
                <a class="btn btn-subtle" href="{{ route('timetables.planning.template') }}">Modèle CSV</a>
            </div>
        </div>

        <form class="planning-import" method="POST" action="{{ route('timetables.planning.import.preview') }}" enctype="multipart/form-data" data-tour-target="timetable-import">
            @csrf
            <div class="field">
                <label for="availability_file">Fichier des professeurs</label>
                <input id="availability_file" type="file" name="availability_file" accept=".csv,.txt,.xlsx,.pdf,.docx" required>
            </div>
            <button class="btn btn-primary" type="submit">Analyser le fichier</button>
        </form>

        @if ($importPreview)
            <div class="planning-preview-head">
                <div>
                    <strong>{{ $importPreview['filename'] }}</strong>
                    <span>Valides : {{ $importPreview['summary']['valid'] }} · À corriger : {{ $importPreview['summary']['invalid'] }} · Ignorées : {{ $importPreview['summary']['ignored'] ?? 0 }}</span>
                </div>
                <div class="page-actions">
                    <form method="POST" action="{{ route('timetables.planning.import.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-subtle" type="submit">Retirer l’aperçu</button>
                    </form>
                    <a class="btn btn-primary" href="{{ route('timetables.planning.import.review') }}">Revoir l’import</a>
                </div>
            </div>
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <div>
                <h2>2. Préparer l’essai de grille</h2>
                <p class="panel-subtitle">Commence par une seule classe pour vérifier le résultat facilement. Les grilles déjà actives restent protégées.</p>
            </div>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        <form method="GET" action="{{ route('timetables.planning') }}" class="planning-import" style="margin-bottom:16px" data-tour-target="timetable-class-selection">
            <div class="field">
                <label for="planning_school_class_id">Classe à tester</label>
                <select id="planning_school_class_id" name="school_class_id">
                    <option value="">Toutes les classes prêtes</option>
                    @foreach ($classes as $classOption)
                        <option value="{{ $classOption->id }}" @selected($selectedClass?->id === $classOption->id)>{{ $classOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-subtle" type="submit">Vérifier la classe</button>
        </form>

        <div class="planning-metrics">
            <div><span>Classes</span><strong>{{ $readiness['counts']['classes'] }}</strong></div>
            <div><span>Matières</span><strong>{{ $readiness['counts']['assignments'] }}</strong></div>
            <div><span>Professeurs</span><strong>{{ $readiness['counts']['teachers'] }}</strong></div>
            <div><span>Heures à placer</span><strong>{{ $readiness['counts']['requested_slots'] }}</strong></div>
        </div>

        @if ($readiness['blockers'])
            <div class="planning-simple-alert" data-tour-target="timetable-blockers">
                <div>
                    <strong>Il manque encore des informations.</strong>
                    <span>{{ count($readiness['blockers']) }} {{ count($readiness['blockers']) === 1 ? 'correction est nécessaire' : 'corrections sont nécessaires' }} avant de créer un essai.</span>
                </div>
                <a class="btn btn-subtle" href="{{ route('timetables.planning.blockers', ['school_class_id' => $selectedClass?->id]) }}">Voir ce qui manque</a>
            </div>
        @else
            <div class="planning-simple-ready" data-tour-target="timetable-blockers">
                <strong>Cette sélection est prête.</strong>
                <span>Tu peux créer un essai de grille. Rien ne sera publié automatiquement.</span>
            </div>
        @endif

        @if ($readiness['warnings'])
            <div class="planning-diagnostics">
                <strong>Informations</strong>
                <ul>@foreach ($readiness['warnings'] as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('timetables.planning.generate') }}" data-tour-target="timetable-generate">
            @csrf
            @if ($selectedClass)
                <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
            @endif
            <button class="btn btn-primary" type="submit" @disabled($readiness['blockers'] !== [])>Créer un essai de grille</button>
        </form>
    </section>

    @if ($run)
        @php
            $statusLabel = match ($run->solver_status) {
                'OPTIMAL' => 'Solution optimale',
                'FEASIBLE' => 'Solution réalisable',
                'INFEASIBLE' => 'Aucune solution possible',
                'INVALID_SOLUTION' => 'Essai rejeté',
                'NOT_READY' => 'Configuration incomplète',
                'ERROR' => 'Moteur indisponible',
                default => $run->solver_status ?? 'En attente',
            };
        @endphp
        <section class="panel" style="margin-top:16px" data-tour-target="timetable-result">
            <div class="panel-head">
                <div>
                    <h2>3. Essai de grille n° {{ $run->id }}</h2>
                    <p class="panel-subtitle">Créé le {{ $run->created_at->format('d/m/Y à H:i') }} par {{ $run->requester?->name ?? 'utilisateur supprimé' }}</p>
                </div>
                <span class="badge {{ $run->canBeApplied() ? '' : 'badge-warning' }}">{{ $statusLabel }}</span>
            </div>

            @if ($run->diagnostics['blockers'] ?? [])
                <div class="planning-simple-alert">
                    <div>
                        <strong>Aucune grille n’a été modifiée.</strong>
                        <span>{{ count($run->diagnostics['blockers']) }} {{ count($run->diagnostics['blockers']) === 1 ? 'problème doit' : 'problèmes doivent' }} être corrigé{{ count($run->diagnostics['blockers']) === 1 ? '' : 's' }}.</span>
                    </div>
                    <a class="btn btn-subtle" href="{{ route('timetables.planning.blockers', ['school_class_id' => $selectedClass?->id]) }}">Voir ce qui manque</a>
                </div>
            @endif

            @foreach ($gridPreview as $classGrid)
                <details class="planning-class" @if ($loop->first) open @endif>
                    <summary>
                        <strong>{{ $classGrid['class']->name }}</strong>
                        <span>Voir l’essai proposé</span>
                    </summary>
                    <div class="subject-list-scroll">
                        <table class="table timetable-table" style="min-width:1080px">
                            <thead><tr><th>Horaire</th>@foreach ($days as $dayLabel)<th>{{ $dayLabel }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($classGrid['rows'] as $row)
                                    <tr>
                                        <td><strong>{{ $row['period']->label }}</strong></td>
                                        @foreach (array_keys($days) as $day)
                                            @php($assignment = $row['days'][$day])
                                            <td>
                                                @if ($row['period']->is_break)
                                                    <strong>{{ $row['period']->label }}</strong>
                                                @elseif ($assignment)
                                                    <strong>{{ $assignment->subject?->name }}</strong><br>
                                                    <small>{{ $assignment->teacher?->name }}</small>
                                                @else
                                                    <span class="planning-empty-slot">Libre</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach

            @if ($run->canBeApplied())
                @php($singleClassRun = count($run->input_snapshot['target_class_ids'] ?? []) === 1)
                <form method="POST" action="{{ route('timetables.planning.apply', $run) }}" style="margin-top:16px"
                    data-confirm
                    data-confirm-title="Utiliser cet essai"
                    data-confirm-object="{{ $readiness['counts']['classes'] }} {{ $readiness['counts']['classes'] === 1 ? 'classe' : 'classes' }} — {{ $academicYear->name }}"
                    data-confirm-message="Les grilles non actives seront remplacées par ces brouillons automatiques. Les emplois du temps actifs resteront inchangés."
                    data-confirm-action="{{ $singleClassRun ? 'Utiliser et modifier' : 'Utiliser les brouillons' }}"
                    data-confirm-tone="primary">
                    @csrf
                    <button class="btn btn-primary" type="submit">
                        {{ $singleClassRun ? 'Utiliser puis modifier la grille' : 'Utiliser ces brouillons' }}
                    </button>
                </form>
            @elseif ($run->status === \App\Models\TimetableGenerationRun::STATUS_APPLIED)
                <div class="notice">Essai appliqué le {{ $run->applied_at?->format('d/m/Y à H:i') }} par {{ $run->appliedBy?->name ?? 'utilisateur supprimé' }}.</div>
                @if ($appliedTimetables->isNotEmpty())
                    <div class="form-actions" style="margin-top:12px">
                        @foreach ($appliedTimetables as $timetable)
                            <a class="btn btn-primary" href="{{ route('timetables.edit', $timetable) }}">
                                Modifier la grille {{ $timetable->schoolClass->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </section>
    @endif
@endsection
