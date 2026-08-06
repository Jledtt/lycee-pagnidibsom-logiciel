@extends('layouts.app', [
    'title' => 'Planification automatique - Lycée Privé Pagnidibsom',
    'active' => 'timetables',
    'pageTitle' => 'Planification automatique',
    'pageSubtitle' => 'Importer, contrôler puis générer les emplois du temps sans écraser les grilles actives',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.index') }}">Retour aux grilles</a>
    <a class="btn btn-subtle" href="{{ route('timetables.availabilities') }}">Disponibilités</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <ol class="planning-steps" aria-label="Étapes de planification">
        <li class="planning-step"><span>1</span><strong>Disponibilités</strong><small>Importer et contrôler</small></li>
        <li class="planning-step"><span>2</span><strong>Proposition</strong><small>Résoudre les contraintes</small></li>
        <li class="planning-step"><span>3</span><strong>Application</strong><small>Créer des brouillons</small></li>
    </ol>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head planning-import-head">
            <div>
                <h2>1. Importer les disponibilités</h2>
                <p class="panel-subtitle">Formats acceptés : CSV, Excel, PDF et Word. Utilise le modèle pour éviter les ambiguïtés.</p>
            </div>
            <a class="btn btn-subtle" href="{{ route('timetables.planning.template') }}">Télécharger le modèle CSV</a>
        </div>

        <form class="planning-import" method="POST" action="{{ route('timetables.planning.import.preview') }}" enctype="multipart/form-data">
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
                    <a class="btn btn-primary" href="{{ route('timetables.planning.import.review') }}">Continuer la révision</a>
                </div>
            </div>
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <div>
                <h2>2. Générer une proposition</h2>
                <p class="panel-subtitle">Choisis une classe pour un essai ciblé, ou toutes les classes pour une planification globale. Les classes avec un emploi du temps actif sont protégées et restent inchangées.</p>
            </div>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        <form method="GET" action="{{ route('timetables.planning') }}" class="planning-import" style="margin-bottom:16px">
            <div class="field">
                <label for="planning_school_class_id">Classe à planifier</label>
                <select id="planning_school_class_id" name="school_class_id">
                    <option value="">Toutes les classes sans grille active</option>
                    @foreach ($classes as $classOption)
                        <option value="{{ $classOption->id }}" @selected($selectedClass?->id === $classOption->id)>{{ $classOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-subtle" type="submit">Vérifier cette sélection</button>
        </form>

        <div class="planning-metrics">
            <div><span>Classes</span><strong>{{ $readiness['counts']['classes'] }}</strong></div>
            <div><span>Matières</span><strong>{{ $readiness['counts']['assignments'] }}</strong></div>
            <div><span>Professeurs</span><strong>{{ $readiness['counts']['teachers'] }}</strong></div>
            <div><span>Heures à placer</span><strong>{{ $readiness['counts']['requested_slots'] }}</strong></div>
        </div>

        @if ($readiness['blockers'])
            <details class="planning-diagnostics planning-diagnostics--error" @if (count($readiness['blockers']) <= 5) open @endif>
                <summary>
                    <strong>Points bloquants</strong>
                    <span>Corrections requises : {{ count($readiness['blockers']) }}</span>
                </summary>
                <ul>@foreach ($readiness['blockers'] as $message)<li>{{ $message }}</li>@endforeach</ul>
            </details>
        @endif
        @if ($readiness['warnings'])
            <div class="planning-diagnostics">
                <strong>Informations</strong>
                <ul>@foreach ($readiness['warnings'] as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('timetables.planning.generate') }}">
            @csrf
            @if ($selectedClass)
                <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
            @endif
            <button class="btn btn-primary" type="submit" @disabled($readiness['blockers'] !== [])>Générer la proposition</button>
        </form>
    </section>

    @if ($run)
        @php
            $statusLabel = match ($run->solver_status) {
                'OPTIMAL' => 'Solution optimale',
                'FEASIBLE' => 'Solution réalisable',
                'INFEASIBLE' => 'Aucune solution possible',
                'INVALID_SOLUTION' => 'Proposition rejetée',
                'NOT_READY' => 'Configuration incomplète',
                'ERROR' => 'Moteur indisponible',
                default => $run->solver_status ?? 'En attente',
            };
        @endphp
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <div>
                    <h2>3. Proposition n° {{ $run->id }}</h2>
                    <p class="panel-subtitle">Demandée le {{ $run->created_at->format('d/m/Y à H:i') }} par {{ $run->requester?->name ?? 'utilisateur supprimé' }}</p>
                </div>
                <span class="badge {{ $run->canBeApplied() ? '' : 'badge-warning' }}">{{ $statusLabel }}</span>
            </div>

            @if ($run->diagnostics['blockers'] ?? [])
                <div class="planning-diagnostics planning-diagnostics--error">
                    <strong>Aucune donnée n’a été modifiée</strong>
                    <ul>@foreach ($run->diagnostics['blockers'] as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif

            @foreach ($gridPreview as $classGrid)
                <details class="planning-class" @if ($loop->first) open @endif>
                    <summary>
                        <strong>{{ $classGrid['class']->name }}</strong>
                        <span>Voir la grille proposée</span>
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
                <form method="POST" action="{{ route('timetables.planning.apply', $run) }}" style="margin-top:16px"
                    data-confirm
                    data-confirm-title="Appliquer la proposition"
                    data-confirm-object="{{ $readiness['counts']['classes'] }} {{ $readiness['counts']['classes'] === 1 ? 'classe' : 'classes' }} — {{ $academicYear->name }}"
                    data-confirm-message="Les grilles non actives seront remplacées par ces brouillons automatiques. Les emplois du temps actifs resteront inchangés."
                    data-confirm-action="Appliquer les brouillons"
                    data-confirm-tone="primary">
                    @csrf
                    <button class="btn btn-primary" type="submit">Appliquer en brouillon</button>
                </form>
            @elseif ($run->status === \App\Models\TimetableGenerationRun::STATUS_APPLIED)
                <div class="notice">Proposition appliquée le {{ $run->applied_at?->format('d/m/Y à H:i') }} par {{ $run->appliedBy?->name ?? 'utilisateur supprimé' }}.</div>
            @endif
        </section>
    @endif
@endsection
