@extends('layouts.app', [
    'title' => 'Impayes - Lycee Prive Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Impayes',
    'pageSubtitle' => 'Eleves avec reste a payer ou frais non configures',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Paiements</a>
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('payments.unpaid.export') }}">Excel</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('payments.create') }}">Nouveau paiement</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Liste des impayes</h2>
            <span class="badge">{{ $rows->count() }} eleve(s)</span>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Aucun impaye detecte pour les frais configures.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Eleve</th>
                        <th>Classe</th>
                        <th>Attendu</th>
                        <th>Paye</th>
                        <th>Reste</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($summary = $row['summary'])
                        @php($enrollment = $row['enrollment'])
                        <tr>
                            <td>
                                <strong>{{ $enrollment->student->full_name }}</strong><br>
                                <span class="badge">{{ $enrollment->student->matricule }}</span>
                            </td>
                            <td>{{ $enrollment->schoolClass?->name ?? '-' }}</td>
                            <td class="money">{{ is_null($summary['expected']) ? 'A configurer' : number_format($summary['expected'], 0, ',', ' ') . ' FCFA' }}</td>
                            <td class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} FCFA</td>
                            <td class="money">{{ is_null($summary['balance']) ? 'A configurer' : number_format($summary['balance'], 0, ',', ' ') . ' FCFA' }}</td>
                            <td>
                                @can('payments.create')
                                    <a class="btn btn-subtle" href="{{ route('payments.create', ['student_id' => $enrollment->student_id]) }}">Payer</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
