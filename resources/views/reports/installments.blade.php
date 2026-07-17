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
            <button class="btn btn-subtle" type="submit">Afficher</button>
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
                <strong>{{ $summary['up_to_date'] }}</strong>
                <span>Tranches a jour</span>
            </div>
            <div class="module">
                <strong>{{ $summary['partial'] }}</strong>
                <span>Tranches partielles</span>
            </div>
            <div class="module">
                <strong>{{ $summary['unpaid'] }}</strong>
                <span>Tranches impayees</span>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>{{ $schoolClass->name }}</h2>
                <span class="badge">{{ $rows->count() }} ligne(s)</span>
            </div>

            @if ($rows->isEmpty())
                <div class="empty">Aucune tranche configuree pour cette classe.</div>
            @else
                <table class="table">
                    <thead>
                        <tr>
                            <th>Eleve</th>
                            <th>Tranche</th>
                            <th>Frais</th>
                            <th>Attendu</th>
                            <th>Paye</th>
                            <th>Reste</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['student']?->full_name }}</strong><br>
                                    <span class="badge">{{ $row['student']?->matricule }}</span>
                                </td>
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
            @endif
        </section>
    @endif
@endsection
