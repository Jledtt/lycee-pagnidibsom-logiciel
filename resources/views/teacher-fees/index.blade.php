@extends('layouts.app', [
    'title' => 'Honoraires professeurs',
    'active' => 'teacher-fees',
    'pageTitle' => 'Honoraires des professeurs',
    'pageSubtitle' => 'Préparation, validation et paiement des heures de cours',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('teachers.index') }}">Professeurs</a>
    <a class="btn btn-subtle" href="{{ route('teacher-work-sessions.index') }}">Émargements</a>
@endsection

@section('content')
    @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif

    <section class="panel">
        <div class="panel-head"><h2>Rechercher les honoraires</h2></div>
        <form class="searchbar" method="GET" action="{{ route('teacher-fees.index') }}">
            <input type="month" name="month" value="{{ $filters['month'] ?? '' }}">
            <select name="teacher_id"><option value="">Tous les professeurs</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((int) ($filters['teacher_id'] ?? 0) === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select>
            <select name="status"><option value="">Tous les statuts</option>@foreach (['draft' => 'Brouillon', 'approved' => 'Validé', 'paid' => 'Payé', 'cancelled' => 'Annulé'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
        </form>
    </section>

    @can('teacher_fees.manage')
        <section class="panel" style="margin-top:16px">
            <div class="panel-head"><h2>Préparer un ordre de paiement</h2><span class="badge">Heures validées uniquement</span></div>
            <form class="searchbar" method="GET" action="{{ route('teacher-fees.create') }}">
                <select name="teacher_id" required><option value="">Choisir un professeur</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select>
                <input type="month" name="month" value="{{ now()->format('Y-m') }}" required>
                <button class="btn btn-primary" type="submit">Préparer les honoraires</button>
            </form>
        </section>
    @endcan

    <section class="panel" style="margin-top:16px">
        <div class="panel-head"><h2>Ordres de paiement</h2><span class="badge">{{ $statements->total() }}</span></div>
        @if ($statements->isEmpty())
            <div class="empty">Aucun ordre de paiement trouvé.</div>
        @else
            <div style="overflow-x:auto">
                <table class="table">
                    <thead><tr><th>Référence</th><th>Période</th><th>Professeur</th><th>Brut</th><th>Retenues</th><th>Net</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($statements as $statement)
                            <tr>
                                <td><strong>{{ $statement->reference }}</strong></td>
                                <td>{{ $statement->period_month->translatedFormat('F Y') }}</td>
                                <td>{{ $statement->teacher?->name }}</td>
                                <td>{{ number_format((float) $statement->gross_amount, 0, ',', ' ') }} FCFA</td>
                                <td>{{ number_format((float) ($statement->withholding_tax_amount + $statement->advance_amount + $statement->other_deduction_amount), 0, ',', ' ') }} FCFA</td>
                                <td><strong>{{ number_format((float) $statement->net_amount, 0, ',', ' ') }} FCFA</strong></td>
                                <td><span class="badge">{{ $statement->status }}</span></td>
                                <td><a class="btn btn-subtle" href="{{ route('teacher-fees.show', $statement) }}">Ouvrir</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $statements->links() }}</div>
        @endif
    </section>
@endsection
