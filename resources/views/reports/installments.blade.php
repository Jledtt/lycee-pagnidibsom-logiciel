@extends('layouts.app', [
    'title' => 'Tranches de paiement - Lycee Prive Pagnidibsom',
    'active' => 'reports',
    'pageTitle' => 'Tranches de paiement',
    'pageSubtitle' => 'Suivi des tranches payees et restantes par classe',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('reports.payment-situation', ['school_class_id' => $schoolClass?->id]) }}">Situation globale</a>
    @if ($schoolClass)
        <a class="btn btn-primary" href="{{ route('reports.installments.pdf', ['school_class_id' => $schoolClass->id]) }}">PDF</a>
    @endif
@endsection

@section('content')
    @php($currency = $schoolSettings?->currency ?? 'FCFA')

    <section class="panel">
        <div class="panel-head">
            <h2>Selection</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('reports.installments') }}">
            <select name="school_class_id" required>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected($schoolClass?->id === $class->id)>
                        {{ $class->name }}{{ $class->level ? ' - ' . $class->level->name : '' }}
                    </option>
                @endforeach
            </select>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom ou matricule">
            <select name="status">
                <option value="">Tous les statuts</option>
                <option value="unpaid" @selected(($filters['status'] ?? '') === 'unpaid')>Impayes</option>
                <option value="partial" @selected(($filters['status'] ?? '') === 'partial')>Partiels</option>
                <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>A jour</option>
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('reports.installments', ['school_class_id' => $schoolClass?->id]) }}">Reinitialiser</a>
        </form>
    </section>

    @if (! $schoolClass)
        <section class="panel" style="margin-top:16px">
            <div class="empty">Aucune classe active disponible pour l'annee scolaire active.</div>
        </section>
    @else
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Total attendu</span>
                <strong class="money">{{ number_format($summary['expected'], 0, ',', ' ') }} {{ $currency }}</strong>
            </div>
            <div class="stat">
                <span>Total paye</span>
                <strong class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} {{ $currency }}</strong>
            </div>
            <div class="stat">
                <span>Reste</span>
                <strong class="money">{{ number_format($summary['balance'], 0, ',', ' ') }} {{ $currency }}</strong>
            </div>
        </section>

        <section class="grid modules" style="margin-top:16px">
            <div class="module">
                <strong>{{ $studentSummary['total'] }}</strong>
                <span>Eleves suivis</span>
            </div>
            <div class="module">
                <strong>{{ $studentSummary['partial'] }}</strong>
                <span>Eleves avec paiement partiel</span>
            </div>
            <div class="module">
                <strong>{{ $studentSummary['unpaid'] }}</strong>
                <span>Eleves impayes</span>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>{{ $schoolClass->name }}</h2>
                <span class="badge">{{ $studentRows->count() }} eleve(s) affiche(s)</span>
            </div>

            @if ($rows->isEmpty())
                <div class="empty">Aucune tranche configuree pour cette classe.</div>
            @elseif ($studentRows->isEmpty())
                <div class="empty">Aucun eleve ne correspond aux filtres choisis.</div>
            @else
                <div class="ledger-list">
                    @foreach ($studentRows as $studentRow)
                        @php($student = $studentRow['student'])
                        @php($detailRows = $studentRow['unpaid_rows']->isEmpty() ? $studentRow['rows'] : $studentRow['unpaid_rows'])

                        <details class="ledger-item">
                            <summary class="ledger-summary">
                                <span class="ledger-person">
                                    <strong>{{ $student->full_name }}</strong>
                                    <span>{{ $student->matricule }}</span>
                                </span>

                                <span class="ledger-metric">
                                    <strong class="money">{{ number_format($studentRow['balance'], 0, ',', ' ') }} {{ $currency }}</strong>
                                    <span>Reste a payer</span>
                                </span>

                                <span class="ledger-metric">
                                    <strong class="money">{{ number_format($studentRow['paid'], 0, ',', ' ') }} {{ $currency }}</strong>
                                    <span>Deja paye</span>
                                </span>

                                <span class="ledger-progress">
                                    <span class="meter" style="--value: {{ $studentRow['progress'] }}%">
                                        <span></span>
                                    </span>
                                    <span class="badge">{{ $studentRow['progress'] }}%</span>
                                </span>

                                <span class="badge {{ $studentRow['status']['class'] }}">{{ $studentRow['status']['label'] }}</span>

                                <span class="btn btn-subtle ledger-toggle">Voir les tranches</span>
                            </summary>

                            <div class="ledger-detail">
                                <div class="ledger-detail-head">
                                    <div>
                                        <h3>{{ $studentRow['unpaid_rows']->isEmpty() ? 'Toutes les tranches' : 'Tranches a regler' }}</h3>
                                        <span class="badge">{{ $studentRow['unpaid_count'] }} impaye(s)</span>
                                    </div>
                                    @can('payments.create')
                                        <a class="btn btn-primary" href="{{ route('payments.create', ['student_id' => $student->id]) }}">Enregistrer un paiement</a>
                                    @endcan
                                </div>

                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Tranche</th>
                                            <th>Frais</th>
                                            <th>Attendu</th>
                                            <th>Paye</th>
                                            <th>Reste</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($detailRows as $row)
                                            <tr>
                                                <td>{{ $row['schedule']->period ?: '-' }}</td>
                                                <td>{{ $row['schedule']->feeType?->name ?? '-' }}</td>
                                                <td class="money">{{ number_format($row['expected'], 0, ',', ' ') }} {{ $currency }}</td>
                                                <td class="money">{{ number_format($row['paid'], 0, ',', ' ') }} {{ $currency }}</td>
                                                <td class="money">{{ number_format($row['balance'], 0, ',', ' ') }} {{ $currency }}</td>
                                                <td><span class="badge {{ $row['status']['class'] }}">{{ $row['status']['label'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
@endsection
