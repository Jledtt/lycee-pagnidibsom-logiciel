@extends('layouts.app', [
    'title' => 'Autorisation - Lycée Privé Pagnidibsom',
    'active' => 'exit-authorizations',
    'pageTitle' => 'Autorisation entree / sortie',
    'pageSubtitle' => $authorization->student?->full_name,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('exit-authorizations.index') }}">Retour</a>
    <a class="btn btn-primary" href="{{ route('exit-authorizations.pdf', $authorization) }}" data-download-feedback="Téléchargement de l’autorisation lancé.">PDF</a>
@endsection

@section('content')
    <section class="grid two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Élève</h2>
                <span class="badge">{{ $authorization->schoolClass?->name ?? '-' }}</span>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><span>Nom</span><strong>{{ $authorization->student?->full_name }}</strong></div>
                <div class="detail-item"><span>Matricule</span><strong>{{ $authorization->student?->matricule }}</strong></div>
                <div class="detail-item"><span>Date document</span><strong>{{ $authorization->document_date?->format('d/m/Y') }}</strong></div>
                <div class="detail-item"><span>Créé par</span><strong>{{ $authorization->creator?->name ?? '-' }}</strong></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Sortie</h2>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><span>Sortie</span><strong>{{ $authorization->departure_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                <div class="detail-item"><span>Retour</span><strong>{{ $authorization->return_at?->format('d/m/Y H:i') ?? '-' }}</strong></div>
                <div class="detail-item"><span>Matière concernee</span><strong>{{ $authorization->subject_name ?: '-' }}</strong></div>
                <div class="detail-item"><span>Lieu</span><strong>{{ $authorization->destination ?: '-' }}</strong></div>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Motif</h2>
        </div>
        <p><strong>{{ $authorization->reason }}</strong></p>
        @if ($authorization->notes)
            <p style="color:var(--muted)">{{ $authorization->notes }}</p>
        @endif
    </section>
@endsection
