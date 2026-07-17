@extends('layouts.app', [
    'title' => 'Nouvel eleve - Lycee Prive Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Nouvel eleve',
    'pageSubtitle' => 'Creation du dossier administratif et du contact tuteur',
])

@section('content')
    <form method="POST" action="{{ route('students.store') }}">
        @csrf
        @include('students._form', ['submitLabel' => 'Enregistrer le dossier'])
    </form>
@endsection
