@extends('layouts.app', [
    'title' => 'Conseil de classe - Lycée Privé Pagnidibsom',
    'active' => 'report-cards',
    'pageTitle' => 'Conseil de classe',
    'pageSubtitle' => 'Classement, statistiques, PV et verrouillage des notes',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('report-cards.index', ['school_class_id' => $selectedClass?->id, 'term_id' => $selectedTerm?->id]) }}">Bulletins</a>
    <a class="btn btn-subtle" href="{{ route('grades.index', ['school_class_id' => $selectedClass?->id, 'term_id' => $selectedTerm?->id]) }}">Notes</a>
    <a class="btn btn-subtle" href="{{ route('class-council.annual-redemptions', ['school_class_id' => $selectedClass?->id]) }}">Rachats conseil</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <div class="panel-head">
            <h2>Sélection</h2>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        @if ($classes->isEmpty() || $terms->isEmpty())
            <div class="empty">Il faut au moins une classe active et un trimestre.</div>
        @else
            <form class="searchbar" method="GET" action="{{ route('class-council.index') }}">
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

                <button class="btn btn-subtle" type="submit">Afficher</button>
            </form>
        @endif
    </section>

    @if ($selectedClass && $selectedTerm)
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Moyenne classe</span>
                <strong>{{ $summary['class_average'] === null ? '-' : number_format($summary['class_average'], 2, ',', ' ') . ' / 20' }}</strong>
            </div>
            <div class="stat">
                <span>Meilleure moyenne</span>
                <strong>{{ $summary['best']?->general_average === null ? '-' : number_format($summary['best']->general_average, 2, ',', ' ') }}</strong>
            </div>
            <div class="stat">
                <span>Plus faible moyenne</span>
                <strong>{{ $summary['weakest']?->general_average === null ? '-' : number_format($summary['weakest']->general_average, 2, ',', ' ') }}</strong>
            </div>
            <div class="stat">
                <span>Bulletins valides</span>
                <strong>{{ $summary['validated'] }} / {{ $summary['students'] }}</strong>
            </div>
        </section>

        <section class="grid modules" style="margin-top:16px">
            <div class="module">
                <strong>{{ $summary['best']?->student?->full_name ?? '-' }}</strong>
                <span>Premier de la classe</span>
            </div>
            <div class="module">
                <strong>{{ $summary['admitted'] }}</strong>
                <span>Décision admis</span>
            </div>
            <div class="module">
                <strong>{{ $summary['deliberation'] }}</strong>
                <span>A deliberer</span>
            </div>
            <div class="module">
                <strong>{{ $lockSummary['locked'] }} / {{ $lockSummary['total'] }}</strong>
                <span>{{ $lockSummary['is_locked'] ? 'Conseil verrouille' : ($lockSummary['is_partial'] ? 'Verrouillage partiel' : 'Non verrouille') }}</span>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <div>
                    <h2>{{ $selectedClass->name }} - {{ $selectedTerm->name }}</h2>
                    <p style="margin:4px 0 0;color:var(--muted)">Le PV recalcule les bulletins avant impression.</p>
                </div>
                <div class="page-actions">
                    @can('report_cards.generate')
                        <form method="POST" action="{{ route('report-cards.generate') }}">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                            <input type="hidden" name="term_id" value="{{ $selectedTerm->id }}">
                            <button class="btn btn-subtle" type="submit">Générer / recalculer</button>
                        </form>
                    @endcan

                    @can('report_cards.print')
                        <a class="btn btn-subtle" href="{{ route('class-council.pv-pdf', ['school_class_id' => $selectedClass->id, 'term_id' => $selectedTerm->id]) }}">PV PDF</a>
                    @endcan

                    @can('report_cards.validate')
                        @if (! $lockSummary['is_locked'])
                            <form method="POST" action="{{ route('class-council.lock') }}" onsubmit="return confirm('Verrouiller toutes les evaluations de ce trimestre ?')">
                                @csrf
                                <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                                <input type="hidden" name="term_id" value="{{ $selectedTerm->id }}">
                                <button class="btn btn-primary" type="submit">Verrouiller conseil</button>
                            </form>
                        @endif
                    @endcan

                    @can('grades.unlock')
                        @if ($lockSummary['locked'] > 0)
                            <form method="POST" action="{{ route('class-council.unlock') }}" onsubmit="return confirm('Déverrouiller les évaluations pour correction admin ?')">
                                @csrf
                                <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                                <input type="hidden" name="term_id" value="{{ $selectedTerm->id }}">
                                <button class="btn btn-danger" type="submit">Déverrouiller</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            @if ($reportCards->isEmpty())
                <div class="empty">Aucun bulletin généré. Lance d’abord “Générer / recalculer”.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:1040px">
                        <thead>
                            <tr>
                                <th>Rang</th>
                                <th>Élève</th>
                                <th>Moyenne</th>
                                <th>Appreciation</th>
                                <th>Décision</th>
                                <th>Statut</th>
                                <th>Documents</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reportCards as $reportCard)
                                <tr>
                                    <td><strong>{{ $reportCard->rank ? $reportCard->rank . 'e' : '-' }}</strong></td>
                                    <td>
                                        <strong>{{ $reportCard->student?->full_name }}</strong><br>
                                        <span class="badge">{{ $reportCard->student?->matricule }}</span>
                                    </td>
                                    <td>{{ $reportCard->general_average === null ? '-' : number_format($reportCard->general_average, 2, ',', ' ') . ' / 20' }}</td>
                                    <td>{{ $reportCard->appreciation ?: '-' }}</td>
                                    <td>{{ $reportCard->decision ?: '-' }}</td>
                                    <td>
                                        <span class="badge {{ $reportCard->status === 'draft' ? 'badge-warning' : '' }}">
                                            {{ ['draft' => 'Brouillon', 'validated' => 'Valide', 'published' => 'Publie'][$reportCard->status] ?? $reportCard->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="page-actions" style="justify-content:flex-start">
                                            @can('report_cards.print')
                                                <a class="btn btn-subtle" href="{{ route('report-cards.transcript-pdf', $reportCard) }}">Rélève</a>
                                                <a class="btn btn-primary" href="{{ route('report-cards.pdf', $reportCard) }}">Bulletin</a>
                                            @endcan
                                        </div>
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
