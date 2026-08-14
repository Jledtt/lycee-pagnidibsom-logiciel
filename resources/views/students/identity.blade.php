@extends('layouts.app', [
    'title' => $student->full_name . ' - Lycée Privé Pagnidibsom',
    'active' => 'students',
    'pageTitle' => $student->full_name,
    'pageSubtitle' => 'Matricule ' . $student->matricule,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('students.index') }}">Retour</a>
    @can('payments.view')
        <a class="btn btn-subtle" href="{{ route('payments.students.statement', $student) }}">Situation financière</a>
    @endcan
    @can('payments.create')
        <a class="btn btn-primary" href="{{ route('payments.create', ['student_id' => $student->id]) }}">Encaisser</a>
    @endcan
    @can('attendance.view')
        <a class="btn btn-subtle" href="{{ route('attendance.students.history', $student) }}">Assiduité</a>
    @endcan
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Informations utiles</p>
                <h2>Identité scolaire</h2>
            </div>
            <span class="badge">{{ $student->status }}</span>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span>Matricule</span>
                <strong>{{ $student->matricule }}</strong>
            </div>
            <div class="detail-item">
                <span>Élève</span>
                <strong>{{ $student->full_name }}</strong>
            </div>
            <div class="detail-item">
                <span>Sexe</span>
                <strong>{{ $student->gender_label }}</strong>
            </div>
            <div class="detail-item">
                <span>Classe actuelle</span>
                <strong>{{ $currentEnrollment?->schoolClass?->name ?? 'Non inscrit' }}</strong>
            </div>
            <div class="detail-item">
                <span>Année scolaire</span>
                <strong>{{ $academicYear?->name ?? '-' }}</strong>
            </div>
        </div>
    </section>

    @can('payments.view')
        @if ($financialSummary)
            <section class="panel" style="margin-top:16px">
                <div class="panel-head">
                    <h2>Situation financière</h2>
                </div>
                <div class="summary-row">
                    <div class="stat"><span>Total attendu</span><strong>{{ is_null($financialSummary['expected']) ? 'À configurer' : number_format($financialSummary['expected'], 0, ',', ' ').' FCFA' }}</strong></div>
                    <div class="stat"><span>Total payé</span><strong>{{ number_format($financialSummary['paid'], 0, ',', ' ') }} FCFA</strong></div>
                    <div class="stat"><span>Reste à payer</span><strong>{{ is_null($financialSummary['balance']) ? 'À configurer' : number_format($financialSummary['balance'], 0, ',', ' ').' FCFA' }}</strong></div>
                </div>
            </section>
        @endif
    @endcan
@endsection
