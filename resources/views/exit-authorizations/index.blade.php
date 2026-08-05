@extends('layouts.app', [
    'title' => 'Autorisations - Lycée Privé Pagnidibsom',
    'active' => 'exit-authorizations',
    'pageTitle' => 'Autorisations d’entrée et de sortie',
    'pageSubtitle' => 'Documents remis aux élèves autorisés à quitter ou à rejoindre l’établissement',
])

@section('page_actions')
    <a class="btn btn-primary" href="{{ route('exit-authorizations.create') }}">Nouvelle autorisation</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <h2>Rechercher</h2>
        </div>

        <form class="searchbar" method="GET" action="{{ route('exit-authorizations.index') }}">
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, prénom ou matricule">
            <button class="btn btn-subtle" type="submit">Afficher</button>
            <a class="btn btn-subtle" href="{{ route('exit-authorizations.index') }}">Réinitialiser</a>
        </form>
    </section>

    <section class="panel" style="margin-top:16px">
        <div class="panel-head">
            <h2>Historique</h2>
            <span class="badge">{{ $authorizations->total() }} ligne(s)</span>
        </div>

        @if ($authorizations->isEmpty())
            <div class="empty">Aucune autorisation générée pour le moment.</div>
        @else
            <div class="subject-list-scroll">
                <table class="table" style="min-width:900px">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Motif</th>
                            <th>Sortie</th>
                            <th>Retour</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($authorizations as $authorization)
                            <tr>
                                <td>{{ $authorization->document_date?->format('d/m/Y') }}</td>
                                <td>
                                    <strong>{{ $authorization->student?->full_name }}</strong><br>
                                    <span class="badge">{{ $authorization->student?->matricule }}</span>
                                </td>
                                <td>{{ $authorization->schoolClass?->name ?? '-' }}</td>
                                <td>{{ $authorization->reason }}</td>
                                <td>{{ $authorization->departure_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>{{ $authorization->return_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>
                                    <div class="searchbar">
                                        <a class="btn btn-subtle" href="{{ route('exit-authorizations.show', $authorization) }}">Voir</a>
                                        <a class="btn btn-subtle" href="{{ route('exit-authorizations.pdf', $authorization) }}" data-download-feedback="Téléchargement de l’autorisation lancé.">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $authorizations->links() }}
        @endif
    </section>
@endsection
