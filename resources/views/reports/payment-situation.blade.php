@extends('layouts.app', [
    'title' => 'Situation des paiements par classe - Lycée Privé Pagnidibsom',
    'active' => 'reports',
    'pageTitle' => 'Situation des paiements',
    'pageSubtitle' => 'Suivi financier par classe pour ' . ($academicYear?->name ?? 'l’année active'),
])

@section('page_actions')
    @can('students.export')
        <a class="btn btn-subtle" href="{{ route('reports.class-list', ['school_class_id' => $schoolClass?->id]) }}">Liste élèves</a>
        <a class="btn btn-subtle" href="{{ route('reports.missing-documents', ['school_class_id' => $schoolClass?->id]) }}">Pièces manquantes</a>
    @endcan
    <a class="btn btn-subtle" href="{{ route('reports.installments', ['school_class_id' => $schoolClass?->id]) }}">Tranches</a>
    @if ($schoolClass)
        <a class="btn btn-subtle" href="{{ route('reports.payment-situation.export', ['school_class_id' => $schoolClass->id]) }}" data-download-feedback="Téléchargement Excel de la situation des paiements lancé. Regarde l’icône de téléchargement du navigateur.">Excel</a>
        <a class="btn btn-primary" href="{{ route('reports.payment-situation.pdf', ['school_class_id' => $schoolClass->id]) }}">PDF</a>
    @endif
@endsection

@section('content')
    @php($currency = $schoolSettings?->currency ?? 'FCFA')
    @php($statusOptions = [
        'paid' => 'A jour',
        'partial' => 'Partiel',
        'unpaid' => 'Impayé',
        'unconfigured' => 'Tarif à configurer',
    ])

    <section class="panel">
        <div class="panel-head">
            <h2>Sélection</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('reports.payment-situation') }}">
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
                @foreach ($statusOptions as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Afficher</button>
        </form>
    </section>

    @if (! $schoolClass)
        <section class="panel" style="margin-top:16px">
            <div class="empty">Aucune classe active disponible pour l’année scolaire active.</div>
        </section>
    @else
        <section class="summary-row" style="margin-top:16px">
            <div class="stat">
                <span>Total attendu</span>
                <strong class="money">{{ is_null($summary['expected']) ? 'À configurer' : number_format($summary['expected'], 0, ',', ' ') . ' ' . $currency }}</strong>
            </div>
            <div class="stat">
                <span>Total paye</span>
                <strong class="money">{{ number_format($summary['paid'], 0, ',', ' ') }} {{ $currency }}</strong>
            </div>
            <div class="stat">
                <span>Reste à payer</span>
                <strong class="money">{{ is_null($summary['balance']) ? 'À configurer' : number_format($summary['balance'], 0, ',', ' ') . ' ' . $currency }}</strong>
            </div>
        </section>

        <section class="grid modules" style="margin-top:16px">
            <div class="module">
                <strong>{{ $summary['up_to_date'] }}</strong>
                <span>Élèves à jour</span>
            </div>
            <div class="module">
                <strong>{{ $summary['partial'] }}</strong>
                <span>Paiements partiels</span>
            </div>
            <div class="module">
                <strong>{{ $summary['unpaid'] }}</strong>
                <span>Élèves impayés</span>
            </div>
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>{{ $schoolClass->name }}</h2>
                <span class="badge">{{ $rows->count() }} élève(s)</span>
            </div>

            @if ($rows->isEmpty())
                <div class="empty">Aucun élève actif inscrit dans cette classe.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:1120px">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Élève</th>
                                <th>Contact</th>
                                <th>Attendu</th>
                                <th>Paye</th>
                                <th>Reste</th>
                                <th>Progression</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <strong>{{ $row['student']?->full_name }}</strong><br>
                                        <span class="badge">{{ $row['student']?->matricule }}</span>
                                    </td>
                                    <td>{{ $row['contact'] ?: '-' }}</td>
                                    <td class="money">{{ is_null($row['expected']) ? '-' : number_format($row['expected'], 0, ',', ' ') . ' ' . $currency }}</td>
                                    <td class="money">{{ number_format($row['paid'], 0, ',', ' ') }} {{ $currency }}</td>
                                    <td class="money">{{ is_null($row['balance']) ? '-' : number_format($row['balance'], 0, ',', ' ') . ' ' . $currency }}</td>
                                    <td>{{ $row['progress'] }}%</td>
                                    <td><span class="badge {{ $row['status']['class'] }}">{{ $row['status']['label'] }}</span></td>
                                    <td>
                                        @if ($row['student'])
                                            <div class="page-actions" style="justify-content:flex-start">
                                                <a class="btn btn-subtle" href="{{ route('payments.students.statement', $row['student']) }}">Situation</a>
                                                @can('payments.create')
                                                    @if (! is_null($row['balance']) && $row['balance'] > 0)
                                                        <a class="btn btn-primary" href="{{ route('payments.create', ['student_id' => $row['student']->id, 'amount' => (int) $row['balance']]) }}">Payer</a>
                                                    @endif
                                                @endcan
                                            </div>
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
