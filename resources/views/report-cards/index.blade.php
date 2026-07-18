@extends('layouts.app', [
    'title' => 'Bulletins - Lycee Prive Pagnidibsom',
    'active' => 'report-cards',
    'pageTitle' => 'Bulletins',
    'pageSubtitle' => 'Generation des moyennes, rangs et bulletins imprimables',
])

@section('page_actions')
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
            <h2>Selection</h2>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        @if ($classes->isEmpty() || $terms->isEmpty())
            <div class="empty">Il faut au moins une classe active et un trimestre pour generer les bulletins.</div>
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
                <span>Eleves</span>
                <strong>{{ $students->count() }}</strong>
            </div>
            <div class="stat">
                <span>Bulletins generes</span>
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
                @can('report_cards.generate')
                    <form method="POST" action="{{ route('report-cards.generate') }}">
                        @csrf
                        <input type="hidden" name="school_class_id" value="{{ $selectedClass->id }}">
                        <input type="hidden" name="term_id" value="{{ $selectedTerm->id }}">
                        <button class="btn btn-primary" type="submit">Generer / recalculer</button>
                    </form>
                @endcan
            </div>

            @if ($students->isEmpty())
                <div class="empty">Aucun eleve actif dans cette classe.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:820px">
                        <thead>
                            <tr>
                                <th>Eleve</th>
                                <th>Moyenne</th>
                                <th>Rang</th>
                                <th>Statut</th>
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
                                        {{ $reportCard?->rank ? $reportCard->rank . ' / ' . $reportCard->class_size : '-' }}
                                    </td>
                                    <td>
                                        @if (! $reportCard)
                                            <span class="badge badge-warning">A generer</span>
                                        @elseif ($reportCard->general_average === null)
                                            <span class="badge badge-warning">Non note</span>
                                        @else
                                            <span class="badge">Pret</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($reportCard)
                                            @can('report_cards.print')
                                                <a class="btn btn-primary" href="{{ route('report-cards.pdf', $reportCard) }}">PDF</a>
                                            @endcan
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
