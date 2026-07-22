@extends('layouts.app', [
    'title' => 'Nouvelle classe - Lycée Privé Pagnidibsom',
    'active' => 'classes',
    'pageTitle' => 'Nouvelle classe',
    'pageSubtitle' => 'Creation d\'une classe pour l\'année ' . ($academicYear?->name ?? 'active'),
])

@section('content')
    <form method="POST" action="{{ route('classes.store') }}">
        @csrf
        @include('classes._form', ['submitLabel' => 'Créer la classe'])
    </form>
@endsection
