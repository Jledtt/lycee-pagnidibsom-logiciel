@extends('layouts.app', [
    'title' => 'Modifier élève - Lycée Privé Pagnidibsom',
    'active' => 'students',
    'pageTitle' => 'Modifier le dossier',
    'pageSubtitle' => $student->full_name . ' - ' . $student->matricule,
])

@section('content')
    <form method="POST" action="{{ route('students.update', $student) }}">
        @csrf
        @method('PUT')
        @include('students._form', ['submitLabel' => 'Mettre à jour'])
    </form>
@endsection
