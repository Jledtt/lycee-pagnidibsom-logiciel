@extends('layouts.app', [
    'title' => 'Impayés - Lycée Privé Pagnidibsom',
    'active' => 'payments',
    'pageTitle' => 'Impayés',
    'pageSubtitle' => 'Élèves avec reste à payer ou frais non configurés',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('payments.index') }}">Paiements</a>
    @can('payments.reports')
        <a class="btn btn-subtle" href="{{ route('payments.unpaid.export') }}" data-download-feedback="Téléchargement Excel des impayés lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('payments.create') }}">Nouveau paiement</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Liste des impayés</h2>
            <span class="badge">{{ $rows->count() }} élève(s)</span>
        </div>

        @if ($rows->isEmpty())
            <div class="empty">Aucun impayé detecte pour les frais configurés.</div>
        @else
            <div class="subject-list-scroll">
            <table class="table" style="min-width:920px">
                <thead>
                    <tr>
                        <th>Élève</th>
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
                            <td class="money">{{ is_null($summary['expected']) ? 'À configurer' : number_format($summary['expected'], 0, ',', ' ') . ' FCFA' }}</td>
                            <td class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} FCFA</td>
                            <td class="money">{{ is_null($summary['balance']) ? 'À configurer' : number_format($summary['balance'], 0, ',', ' ') . ' FCFA' }}</td>
                            <td>
                                @can('payments.create')
                                    <a class="btn btn-subtle" href="{{ route('payments.create', ['student_id' => $enrollment->student_id, 'amount' => is_null($summary['balance']) ? null : (int) $summary['balance']]) }}">Payer</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </section>
@endsection
