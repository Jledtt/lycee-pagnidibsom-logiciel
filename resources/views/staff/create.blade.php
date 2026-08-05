@extends('layouts.app', [
    'title' => 'Nouvel utilisateur - Lycée Privé Pagnidibsom',
    'active' => 'staff',
    'pageTitle' => 'Nouvel utilisateur',
    'pageSubtitle' => 'Création d’un compte pour le personnel de l’établissement',
])

@section('content')
    <form method="POST" action="{{ route('staff.store') }}">
        @csrf

        @include('staff._form', ['submitLabel' => 'Créer le compte'])
    </form>
@endsection
