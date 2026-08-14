@extends('layouts.app', [
    'title' => 'Nouvel incident disciplinaire - Lycée Privé Pagnidibsom',
    'active' => 'discipline',
    'pageTitle' => 'Nouvel incident disciplinaire',
    'pageSubtitle' => 'Enregistrer des faits concernant un élève inscrit',
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('discipline.index') }}">Annuler</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Informations de l’incident</h2>
                <p style="margin:4px 0 0;color:var(--muted)">Année {{ $academicYear->name }}. La classe est déterminée automatiquement à partir de l’inscription active.</p>
            </div>
        </div>

        @if ($students->isEmpty())
            <div class="empty">Aucun élève ne possède une inscription active pour cette année scolaire.</div>
        @else
            <form method="POST" action="{{ route('discipline.store') }}" data-prevent-double-submit>
                @csrf
                @include('discipline._form')
                <div class="form-actions">
                    <a class="btn btn-subtle" href="{{ route('discipline.index') }}">Annuler</a>
                    <button class="btn btn-primary" type="submit">Enregistrer l’incident</button>
                </div>
            </form>
        @endif
    </section>
@endsection
