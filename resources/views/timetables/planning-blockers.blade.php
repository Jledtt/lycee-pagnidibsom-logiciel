@extends('layouts.app', [
    'title' => 'Ce qui manque - Emploi du temps',
    'active' => 'timetables',
    'pageTitle' => 'Ce qui manque',
    'pageSubtitle' => 'Liste simple des corrections à faire avant de créer un essai de grille',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('timetables.planning', ['school_class_id' => $selectedClass?->id]) }}">Retour à l’assistant</a>
    <a class="btn btn-subtle" href="{{ route('timetables.availabilities') }}">Disponibilités</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Checklist de correction</h2>
                <p class="panel-subtitle">
                    @if ($selectedClass)
                        Classe contrôlée : {{ $selectedClass->name }} · Année {{ $academicYear->name }}.
                    @else
                        Contrôle de toutes les classes actives sans grille active · Année {{ $academicYear->name }}.
                    @endif
                </p>
            </div>
            <span class="badge {{ count($readiness['blockers']) > 0 ? 'badge-warning' : '' }}">
                {{ count($readiness['blockers']) }} {{ count($readiness['blockers']) === 1 ? 'chose à corriger' : 'choses à corriger' }}
            </span>
        </div>

        <form method="GET" action="{{ route('timetables.planning.blockers') }}" class="planning-import" style="margin-bottom:16px">
            <div class="field">
                <label for="blocker_school_class_id">Classe à vérifier</label>
                <select id="blocker_school_class_id" name="school_class_id">
                    <option value="">Toutes les classes prêtes</option>
                    @foreach ($classes as $classOption)
                        <option value="{{ $classOption->id }}" @selected($selectedClass?->id === $classOption->id)>{{ $classOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-subtle" type="submit">Actualiser</button>
        </form>

        <div class="planning-metrics">
            <div><span>Classes</span><strong>{{ $readiness['counts']['classes'] }}</strong></div>
            <div><span>Matières</span><strong>{{ $readiness['counts']['assignments'] }}</strong></div>
            <div><span>Professeurs</span><strong>{{ $readiness['counts']['teachers'] }}</strong></div>
            <div><span>Heures à placer</span><strong>{{ $readiness['counts']['requested_slots'] }}</strong></div>
        </div>

        @if ($readiness['blockers'] === [])
            <div class="planning-diagnostic-success">
                <strong>Tout est prêt.</strong>
                <span>Aucune correction n’est nécessaire. Tu peux revenir à l’assistant et créer un essai de grille.</span>
                <a class="btn btn-primary" href="{{ route('timetables.planning', ['school_class_id' => $selectedClass?->id]) }}">Créer un essai</a>
            </div>
        @else
            <div class="planning-blocker-guide">
                <strong>Corrige ces points dans l’ordre.</strong>
                <span>Après correction, clique sur Actualiser. Quand la liste est vide, l’assistant pourra créer un essai de grille.</span>
            </div>

            <div class="planning-blocker-groups">
                @foreach ($blockerGroups as $group)
                    <section class="planning-blocker-card">
                        <div class="planning-blocker-card__head">
                            <div>
                                <h3>{{ $group['title'] }}</h3>
                                <p>{{ $group['description'] }}</p>
                            </div>
                            <span>{{ count($group['messages']) }}</span>
                        </div>
                        <ul class="planning-checklist">
                            @foreach ($group['messages'] as $message)
                                <li><span aria-hidden="true"></span>{{ $message }}</li>
                            @endforeach
                        </ul>
                        <div class="planning-blocker-card__action">
                            <strong>À faire</strong>
                            <span>{{ $group['action'] }}</span>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        @if ($warningGroups !== [])
            <div class="planning-blocker-groups planning-blocker-groups--secondary">
                @foreach ($warningGroups as $group)
                    <section class="planning-blocker-card planning-blocker-card--info">
                        <div class="planning-blocker-card__head">
                            <div>
                                <h3>Information utile</h3>
                                <p>{{ $group['description'] }}</p>
                            </div>
                            <span>{{ count($group['messages']) }}</span>
                        </div>
                        <ul class="planning-checklist">
                            @foreach ($group['messages'] as $message)
                                <li><span aria-hidden="true"></span>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
@endsection
