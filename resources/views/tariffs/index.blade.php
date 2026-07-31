@extends('layouts.app', [
    'title' => 'Tarifs scolaires - Lycée Privé Pagnidibsom',
    'active' => 'tariffs',
    'pageTitle' => 'Tarifs scolaires',
    'pageSubtitle' => 'Montants modifiables par classe pour ' . ($academicYear?->name ?? 'l\'année active'),
])

@section('page_actions')
    <form
        method="POST"
        action="{{ route('tariffs.defaults') }}"
        data-confirm
        data-confirm-title="Initialiser les tarifs officiels"
        data-confirm-object="Toutes les classes actives — {{ $academicYear?->name ?? 'Année active' }}"
        data-confirm-message="Les lignes officielles seront créées ou mises à jour avec les montants de l’affiche. Les autres lignes personnalisées seront conservées."
        data-confirm-action="Initialiser les tarifs"
        data-confirm-tone="primary"
        data-prevent-double-submit
    >
        @csrf
        <button class="btn btn-subtle" type="submit">Initialiser affiche</button>
    </form>
@endsection

@section('content')
    <section class="summary-row">
        <div class="stat">
            <span>Classes actives</span>
            <strong>{{ $classes->count() }}</strong>
        </div>
        <div class="stat">
            <span>Total attendu estime</span>
            <strong class="money">{{ number_format($totalExpected, 0, ',', ' ') }} FCFA</strong>
        </div>
        <div class="stat">
            <span>Année scolaire</span>
            <strong>{{ $academicYear?->name ?? '-' }}</strong>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Tarifs par classe</h2>
            <span class="badge">Modifiable a tout moment</span>
        </div>

        @if ($classes->isEmpty())
            <div class="empty">Aucune classe active. Crée d’abord les classes, puis configure les tarifs.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Effectif</th>
                        <th>Lignes</th>
                        <th>Total par élève</th>
                        <th>Total classe</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($classes as $class)
                        <tr>
                            <td><strong>{{ $class->name }}</strong></td>
                            <td>{{ $class->level?->name ?? '-' }}</td>
                            <td>{{ $class->enrollments_count }}</td>
                            <td>{{ $class->tariff_lines_count }}</td>
                            <td class="money">{{ number_format($class->tariff_total, 0, ',', ' ') }} FCFA</td>
                            <td class="money">{{ number_format($class->tariff_total * $class->enrollments_count, 0, ',', ' ') }} FCFA</td>
                            <td><a class="btn btn-subtle" href="{{ route('tariffs.edit', $class) }}">Modifier</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Tarifs de l’affiche</h2>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Groupe</th>
                    <th>Classes</th>
                    <th>Inscription</th>
                    <th>Novembre</th>
                    <th>Février</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>General</td><td>6e, 5e, 4e</td><td>50 000</td><td>25 000</td><td>25 000</td><td>100 000</td></tr>
                <tr><td>General</td><td>3e, 2nde A/C, 1re A/D</td><td>60 000</td><td>25 000</td><td>25 000</td><td>110 000</td></tr>
                <tr><td>Technique</td><td>BEP1 Genie civil / Electrotechnique</td><td>120 000</td><td>40 000</td><td>40 000</td><td>200 000</td></tr>
                <tr><td>Primaire</td><td>CP1</td><td>50 000</td><td>20 000</td><td>20 000</td><td>90 000</td></tr>
            </tbody>
        </table>
    </section>
@endsection
