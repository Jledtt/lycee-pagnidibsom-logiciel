<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Dossier professeur - {{ $teacher->name }}</title>
    <style>@page { margin: 24px 28px; }</style>
    @include('pdf.partials.standard-styles')
    <style>
        body { font-size: 10px; }
        .section-title { margin: 14px 0 5px; padding-bottom: 3px; border-bottom: 1px solid #555; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .info td { width: 50%; padding: 5px 7px; }
        .documents th, .documents td { padding: 4px 6px; }
        .signatures { width: 100%; margin-top: 40px; text-align: center; }
        .signatures td { width: 50%; }
    </style>
</head>
<body>
    @include('pdf.partials.school-header', [
        'school' => $school,
        'logoSize' => 64,
        'marginBottom' => 8,
        'rightLines' => ['Année scolaire : '.$academicYear?->name, 'Dossier enseignant'],
        'rightWidth' => 190,
        'rightSize' => 10,
        'schoolNameSize' => 16,
        'schoolInfoSize' => 9,
    ])

    <div class="document-title">Fiche administrative du professeur</div>
    <div class="section-title">Identité et coordonnées</div>
    <table class="data-grid info">
        <tr><td><strong>Nom complet :</strong> {{ $teacher->name }}</td><td><strong>Matricule :</strong> {{ $teacher->teacherProfile?->employee_number ?: '-' }}</td></tr>
        <tr><td><strong>Téléphone :</strong> {{ $teacher->phone ?: '-' }}</td><td><strong>E-mail :</strong> {{ $teacher->email }}</td></tr>
        <tr><td><strong>Spécialité :</strong> {{ $teacher->teacherProfile?->specialty ?: '-' }}</td><td><strong>Contrat :</strong> {{ $teacher->teacherProfile?->contract_type ?: '-' }}</td></tr>
        <tr><td><strong>Pièce :</strong> {{ $teacher->teacherProfile?->identity_document_type ?: '-' }} {{ $teacher->teacherProfile?->identity_document_number }}</td><td><strong>Embauché(e) le :</strong> {{ $teacher->teacherProfile?->hired_at?->format('d/m/Y') ?: '-' }}</td></tr>
        <tr><td colspan="2"><strong>Adresse :</strong> {{ $teacher->teacherProfile?->address ?: '-' }}</td></tr>
    </table>

    <div class="section-title">Affectations pédagogiques</div>
    <table class="data-grid documents">
        <thead><tr><th>Classe</th><th>Matière</th><th>Coefficient</th></tr></thead>
        <tbody>
            @forelse ($assignments as $assignment)
                <tr><td>{{ $assignment->schoolClass?->name }}</td><td>{{ $assignment->subject?->name }}</td><td>{{ $assignment->coefficient }}</td></tr>
            @empty
                <tr><td colspan="3" class="center">Aucune affectation pour l’année active.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Documents enregistrés</div>
    <table class="data-grid documents">
        <thead><tr><th>Document</th><th>Type</th><th>Numéro</th><th>Expiration</th></tr></thead>
        <tbody>
            @forelse ($teacher->teacherDocuments as $document)
                <tr><td>{{ $document->name }}</td><td>{{ $document->document_type }}</td><td>{{ $document->document_number ?: '-' }}</td><td>{{ $document->expires_at?->format('d/m/Y') ?: '-' }}</td></tr>
            @empty
                <tr><td colspan="4" class="center">Aucun document enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures"><tr><td>Le professeur<br><br><br>Signature</td><td>La direction<br><br><br>Signature et cachet</td></tr></table>
</body>
</html>
