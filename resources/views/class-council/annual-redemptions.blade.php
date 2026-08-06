@extends('layouts.app', [
    'title' => 'Rachats conseil - Lycée Privé Pagnidibsom',
    'active' => 'report-cards',
    'pageTitle' => 'Rachats conseil',
    'pageSubtitle' => 'Élèves proches de 10/20 pouvant être rachetés après le conseil',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('class-council.index', ['school_class_id' => $selectedClass?->id]) }}">Conseil de classe</a>
    @if ($selectedClass)
        @can('report_cards.print')
            <a class="btn btn-primary" href="{{ route('class-council.annual-redemptions-pdf', ['school_class_id' => $selectedClass->id, 'threshold' => $threshold]) }}" data-download-feedback="Téléchargement de la liste des rachats lancé.">PDF rachats</a>
        @endcan
    @endif
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Sélection</h2>
            <span class="badge">{{ $academicYear->name }}</span>
        </div>

        <form class="searchbar" method="GET" action="{{ route('class-council.annual-redemptions') }}">
            <select name="school_class_id">
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>
                        {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                    </option>
                @endforeach
            </select>

            <input type="number" name="threshold" min="0" max="9.99" step="0.01" value="{{ number_format($threshold, 2, '.', '') }}" placeholder="Seuil ex: 9.85" style="max-width:170px">
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    @if ($selectedClass)
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Classe</span>
                <strong>{{ $selectedClass->name }}</strong>
            </div>
            <div class="stat">
                <span>Seuil rachat</span>
                <strong>{{ number_format($threshold, 2, ',', ' ') }} / 20</strong>
            </div>
            <div class="stat">
                <span>Eligibles</span>
                <strong>{{ $eligibleRows->count() }}</strong>
            </div>
            <div class="stat">
                <span>Trimestres</span>
                <strong>{{ $terms->count() }}</strong>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <div>
                    <h2>Élèves eligibles au rachat</h2>
                    <p style="margin:4px 0 0;color:var(--muted)">Le conseil peut relevér ces élèves a 10/20 pour passage en classe superieure.</p>
                </div>
                <span class="badge">{{ $eligibleRows->count() }} ligne(s)</span>
            </div>

            <div class="subject-list-scroll">
                <table class="table" style="min-width:980px">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Élève</th>
                            @foreach ($terms as $term)
                                <th>{{ $term->name }}</th>
                            @endforeach
                            <th>Moyenne annuelle</th>
                            <th>Après rachat</th>
                            <th>Décision proposée</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($eligibleRows as $row)
                            <tr>
                                <td>{{ $row['rank_label'] ?: '-' }}</td>
                                <td>
                                    <strong>{{ $row['student']->full_name }}</strong><br>
                                    <span class="badge">{{ $row['student']->matricule }}</span>
                                </td>
                                @foreach ($terms as $term)
                                    @php($average = $row['term_averages']->get($term->id))
                                    <td>{{ $average === null ? '-' : number_format($average, 2, ',', ' ') }}</td>
                                @endforeach
                                <td><strong>{{ number_format($row['annual_average'], 2, ',', ' ') }}</strong></td>
                                <td><strong>{{ number_format($row['redeemed_average'], 2, ',', ' ') }}</strong></td>
                                <td><span class="badge">Racheté - passe</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 5 + $terms->count() }}">Aucun élève eligible avec ce seuil.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
