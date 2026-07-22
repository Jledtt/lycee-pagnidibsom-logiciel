@extends('layouts.app', [
    'title' => $typeLabel . ' - Lycée Privé Pagnidibsom',
    'active' => 'certificates',
    'pageTitle' => $typeLabel,
    'pageSubtitle' => $certificate->student->full_name . ' - ' . $certificate->student->matricule,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('certificates.index') }}">Retour</a>
    <a class="btn btn-subtle" href="{{ route('students.show', $certificate->student) }}">Dossier élève</a>
    <a class="btn btn-primary" href="{{ route('certificates.pdf', $certificate) }}">PDF</a>
@endsection

@section('content')
    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Document</h2>
                <span class="badge">{{ $certificate->academicYear?->name ?? '-' }}</span>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span>Type</span>
                    <strong>{{ $typeLabel }}</strong>
                </div>
                <div class="detail-item">
                    <span>Date</span>
                    <strong>{{ $certificate->received_at?->format('d/m/Y') ?? $certificate->created_at->format('d/m/Y') }}</strong>
                </div>
                <div class="detail-item">
                    <span>No certificat</span>
                    <strong>{{ $certificate->document_number ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Classe</span>
                    <strong>{{ $enrollment?->schoolClass?->name ?? '-' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Total paye</span>
                    <strong>{{ number_format($summary['paid'], 0, ',', ' ') }} FCFA</strong>
                </div>
                <div class="detail-item">
                    <span>Reste à payer</span>
                    <strong>{{ is_null($summary['balance']) ? 'Frais à configurer' : number_format($summary['balance'], 0, ',', ' ') . ' FCFA' }}</strong>
                </div>
                <div class="detail-item">
                    <span>Statut</span>
                    <strong>{{ $certificate->status }}</strong>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Actions</h2>
            </div>

            <div class="grid" style="grid-template-columns:1fr">
                <a class="btn btn-primary" href="{{ route('certificates.pdf', $certificate) }}">Télécharger / imprimer le PDF</a>
                <a class="btn btn-subtle" href="{{ route('certificates.create', ['student_id' => $certificate->student_id]) }}">Générer un autre certificat</a>
                <a class="btn btn-subtle" href="{{ route('students.registration-sheet.pdf', $certificate->student) }}">Fiche d’inscription PDF</a>
            </div>

            @if ($certificate->document_type === 'no_debt_certificate' && is_null($summary['balance']))
                <p class="notice" style="margin-top:16px">Les frais officiels par classe ne sont pas encore configurés. Le certificat de non redevance se base donc sur les paiements enregistrés.</p>
            @endif
        </div>
    </section>
@endsection
