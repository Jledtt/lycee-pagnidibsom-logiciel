@extends('layouts.app', [
    'title' => 'Documents - Lycee Prive Pagnidibsom',
    'active' => 'certificates',
    'pageTitle' => 'Documents',
    'pageSubtitle' => 'Certificats et documents administratifs des eleves',
])

@section('page_actions')
    <a class="btn btn-primary" href="{{ route('certificates.create') }}">Generer un certificat</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Recherche</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('certificates.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, prenom ou matricule">
            <select name="type">
                <option value="">Tous les types</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-subtle" type="submit">Filtrer</button>
            <a class="btn btn-subtle" href="{{ route('certificates.index') }}">Reinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Certificats generes</h2>
            <span class="badge">{{ $documents->total() }} document(s)</span>
        </div>

        @if ($documents->isEmpty())
            <div class="empty">Aucun certificat genere pour le moment.</div>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Eleve</th>
                        <th>Type</th>
                        <th>Annee</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        <tr>
                            <td>{{ $document->received_at?->format('d/m/Y') ?? $document->created_at->format('d/m/Y') }}</td>
                            <td>
                                <strong>{{ $document->student->full_name }}</strong><br>
                                <span class="badge">{{ $document->student->matricule }}</span>
                            </td>
                            <td>{{ $types[$document->document_type] ?? $document->name }}</td>
                            <td>{{ $document->academicYear?->name ?? '-' }}</td>
                            <td><a class="btn btn-subtle" href="{{ route('certificates.show', $document) }}">Voir</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="pagination">
                {{ $documents->links() }}
            </div>
        @endif
    </section>
@endsection
