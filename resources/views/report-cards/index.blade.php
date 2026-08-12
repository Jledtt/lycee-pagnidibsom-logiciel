@extends('layouts.app', [
    'title' => 'Bulletins - Lycée Privé Pagnidibsom',
    'active' => 'report-cards',
    'pageTitle' => 'Bulletins',
    'pageSubtitle' => 'Génération des moyennes, rangs et bulletins imprimables',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('class-council.index', ['school_class_id' => $selectedClass?->id, 'term_id' => $selectedTerm?->id]) }}">Conseil de classe</a>
    <a class="btn btn-subtle" href="{{ route('grades.index', ['school_class_id' => $selectedClass?->id, 'term_id' => $selectedTerm?->id]) }}">Notes</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Sélection</h2>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        @if ($classes->isEmpty() || $terms->isEmpty())
            <div class="empty">Il faut au moins une classe active et un trimestre pour générer les bulletins.</div>
        @else
            <form class="searchbar" method="GET" action="{{ route('report-cards.index') }}">
                <select name="school_class_id">
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>
                            {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                        </option>
                    @endforeach
                </select>

                <select name="term_id">
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected($selectedTerm?->id === $term->id)>{{ $term->name }}</option>
                    @endforeach
                </select>

                @if ($termPeriods->isNotEmpty())
                    <select name="term_period_id">
                        @foreach ($termPeriods as $period)
                            <option value="{{ $period->id }}" @selected($selectedTermPeriod?->id === $period->id)>{{ $period->name }}</option>
                        @endforeach
                    </select>
                @endif

                <button class="btn btn-subtle" type="submit">Afficher</button>
            </form>
        @endif
    </section>

    @if ($selectedClass && $selectedTerm)
        <section class="grid stats" style="margin-top:16px">
            <div class="stat">
                <span>Classe</span>
                <strong>{{ $selectedClass->name }}</strong>
            </div>
            <div class="stat">
                <span>Trimestre</span>
                <strong>{{ $selectedTerm->name }}</strong>
            </div>
            <div class="stat">
                <span>Période</span>
                <strong>{{ $selectedTermPeriod?->name ?? '-' }}</strong>
            </div>
            <div class="stat">
                <span>Élèves</span>
                <strong>{{ $students->count() }}</strong>
            </div>
            <div class="stat">
                <span>Bulletins générés</span>
                <strong>{{ $reportCards->count() }}</strong>
            </div>
            <div class="stat">
                <span>Moyennes disponibles</span>
                <strong>{{ $reportCards->filter(fn ($card) => $card->general_average !== null)->count() }}</strong>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>{{ $selectedClass->name }} - {{ $selectedTerm->name }}</h2>
                <div class="page-actions">
                    @can('report_cards.generate')
                        <form method="POST" action="{{ route('report-cards.generate') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                            <input type="hidden" name="term_id" value="{{ $selectedTerm->id }}">
                            <button class="btn btn-primary" type="submit">Générer / recalculer</button>
                        </form>
                    @endcan
                    @can('report_cards.print')
                        <a class="btn btn-subtle" href="{{ route('report-cards.class-pdf', ['school_class_id' => $selectedClass->id, 'term_id' => $selectedTerm->id]) }}">PDF classe</a>
                        @if ($selectedTermPeriod)
                            <a class="btn btn-subtle" href="{{ route('report-cards.period-class-pdf', ['school_class_id' => $selectedClass->id, 'term_id' => $selectedTerm->id, 'term_period_id' => $selectedTermPeriod->id]) }}" data-download-feedback="Téléchargement du relevé mensuel lancé.">PDF {{ $selectedTermPeriod->name }}</a>
                        @endif
                        <a class="btn btn-subtle" href="{{ route('report-cards.class-export', ['school_class_id' => $selectedClass->id, 'term_id' => $selectedTerm->id]) }}" data-download-feedback="Téléchargement Excel des bulletins lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
                    @endcan
                </div>
            </div>

            @if ($students->isEmpty())
                <div class="empty">Aucun élève actif dans cette classe.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:1040px">
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Moyenne</th>
                                <th>Rang</th>
                                <th>Statut</th>
                                <th>Appreciation generale</th>
                                <th>Décision</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php($reportCard = $reportCards->get($student->id))
                                <tr>
                                    <td>
                                        <strong>{{ $student->full_name }}</strong><br>
                                        <span class="badge">{{ $student->matricule }}</span>
                                    </td>
                                    <td>
                                        {{ $reportCard?->general_average === null ? '-' : number_format($reportCard->general_average, 2, ',', ' ') . ' / 20' }}
                                    </td>
                                    <td>
                                        {{ $reportCard?->rank_label ? $reportCard->rank_label . ' / ' . $reportCard->class_size : '-' }}
                                    </td>
                                    <td>
                                        @if (! $reportCard)
                                            <span class="badge badge-warning">A générer</span>
                                        @elseif ($reportCard->general_average === null)
                                            <span class="badge badge-warning">Non note</span>
                                        @elseif ($reportCard->status === 'validated')
                                            <span class="badge">Validé</span>
                                        @elseif ($reportCard->status === 'published')
                                            <span class="badge">Publié</span>
                                        @else
                                            <span class="badge">Brouillon</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($reportCard)
                                            <strong>{{ $reportCard->appreciation ?: '-' }}</strong>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($reportCard)
                                            @can('report_cards.validate')
                                                <form id="update-report-card-{{ $reportCard->id }}" method="POST" action="{{ route('report-cards.update', $reportCard) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <select name="decision">
                                                        @foreach (['Admis', 'Tableau d honneur', 'Encouragements', 'A deliberer', 'Redoublement propose', 'Exclu'] as $decision)
                                                            <option value="{{ $decision }}" @selected(($reportCard->decision ?: 'A deliberer') === $decision)>{{ $decision }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select name="distinction" style="margin-top:8px" aria-label="Distinction ou sanction">
                                                        <option value="">Aucune distinction ou sanction</option>
                                                        @foreach (\App\Models\ReportCard::distinctions() as $distinction)
                                                            <option value="{{ $distinction }}" @selected($reportCard->distinction === $distinction)>{{ $distinction }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input
                                                        name="conduct"
                                                        value="{{ $reportCard->conduct }}"
                                                        placeholder="Conduite"
                                                        aria-label="Conduite"
                                                        style="margin-top:8px"
                                                    >
                                                    <textarea name="principal_observation" rows="2" placeholder="Observation administrative" style="margin-top:8px">{{ $reportCard->principal_observation }}</textarea>
                                                </form>
                                            @else
                                                <strong>{{ $reportCard->decision ?: '-' }}</strong>
                                            @endcan
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($reportCard)
                                            <div class="page-actions">
                                                @can('report_cards.validate')
                                                    <div class="inline-form">
                                                        <div class="field">
                                                            <label>Statut</label>
                                                            <select name="status" form="update-report-card-{{ $reportCard->id }}">
                                                                <option value="draft" @selected($reportCard->status === 'draft')>Brouillon</option>
                                                                <option value="validated" @selected($reportCard->status === 'validated')>Validé</option>
                                                                <option value="published" @selected($reportCard->status === 'published')>Publié</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-subtle" type="submit" form="update-report-card-{{ $reportCard->id }}">Sauvegarder</button>
                                                @endcan
                                            @can('report_cards.print')
                                                <a class="btn btn-primary" href="{{ route('report-cards.pdf', $reportCard) }}">PDF</a>
                                            @endcan
                                            </div>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection
