@extends('layouts.app', [
    'title' => 'Nouvelle série ou filière - Lycée Privé Pagnidibsom',
    'active' => 'academic-tracks',
    'pageTitle' => 'Nouvelle série ou filière',
    'pageSubtitle' => 'Créer une option pédagogique réutilisable dans les classes',
])

@section('content')
    <form method="POST" action="{{ route('academic-tracks.store') }}" data-prevent-double-submit>
        @csrf
        @include('academic-tracks._form', ['submitLabel' => 'Créer'])
    </form>
@endsection
