@extends('layouts.app', [
    'title' => 'Modifier l’incident - Lycée Privé Pagnidibsom',
    'active' => 'discipline',
    'pageTitle' => 'Modifier l’incident',
    'pageSubtitle' => $record->student?->full_name . ' · ' . $record->record_date?->format('d/m/Y'),
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('discipline.show', $record) }}">Annuler</a>
@endsection

@section('content')
    <section class="panel">
        <div class="panel-head"><h2>Informations de l’incident</h2></div>
        <form method="POST" action="{{ route('discipline.update', $record) }}" data-prevent-double-submit>
            @csrf
            @method('PUT')
            @include('discipline._form')
            <div class="form-actions">
                <a class="btn btn-subtle" href="{{ route('discipline.show', $record) }}">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer les modifications</button>
            </div>
        </form>
    </section>
@endsection
