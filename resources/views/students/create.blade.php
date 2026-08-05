@extends('layouts.app', [
    'title' => 'Nouvel élève - Lycée Privé Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Nouvel élève',
    'pageSubtitle' => 'Création du dossier administratif et du contact du tuteur',
])

@section('content')
    <form method="POST" action="{{ route('students.store') }}">
        @csrf
        @include('students._form', ['submitLabel' => 'Enregistrer le dossier'])
    </form>
@endsection
