@extends('layouts.app', [
    'title' => 'Modifier eleve - Lycee Prive Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Modifier le dossier',
    'pageSubtitle' => $student->full_name . ' - ' . $student->matricule,
])

@section('content')
    <form method="POST" action="{{ route('students.update', $student) }}">
        @csrf
        @method('PUT')
        @include('students._form', ['submitLabel' => 'Mettre a jour'])
    </form>
@endsection
