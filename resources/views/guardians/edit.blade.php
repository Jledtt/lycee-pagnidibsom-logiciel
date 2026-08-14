@extends('layouts.app', [
    'title' => 'Modifier ' . $guardian->full_name,
    'active' => 'guardians',
    'pageTitle' => 'Modifier le responsable',
    'pageSubtitle' => $guardian->full_name,
])

@section('page_actions')
    <a class="btn btn-subtle" href="{{ route('guardians.show', $guardian) }}">Retour à la fiche</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('guardians.update', $guardian) }}" data-prevent-double-submit>
        @csrf
        @method('PUT')
        @include('guardians._form')

        <div class="form-actions">
            <a class="btn btn-subtle" href="{{ route('guardians.show', $guardian) }}">Annuler</a>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </div>
    </form>
@endsection
